# Schedules

Scadenze e soglie di notifica per Laravel, basate su un valore che avanza nel tempo (chilometri, date, ore, pezzi, …).

Il package non calcola “quanto manca” da solo: legge il valore corrente dal modello host, lo confronta con una scadenza, e quando una soglia è raggiunta notifica i ruoli configurati.

Se stai arrivando da v2.5.1, leggi anche [UPGRADING.md](UPGRADING.md).

---

## Indice

1. [A cosa serve](#a-cosa-serve)
2. [I pezzi del dominio](#i-pezzi-del-dominio)
3. [Installazione](#installazione)
4. [Configurazione dell’applicazione host](#configurazione-dellapplicazione-host)
5. [Rendere un modello schedulabile](#rendere-un-modello-schedulabile)
6. [Creare un tipo di scadenza](#creare-un-tipo-di-scadenza)
7. [Soglie di notifica](#soglie-di-notifica)
8. [Applicare le scadenze](#applicare-le-scadenze)
9. [Valutare le scadenze (cron)](#valutare-le-scadenze-cron)
10. [Segnare una scadenza come gestita](#segnare-una-scadenza-come-gestita)
11. [Leggere le scadenze da un modello](#leggere-le-scadenze-da-un-modello)
12. [Stati](#stati)
13. [Interfaccia HTTP](#interfaccia-http)
14. [Soft delete](#soft-delete)
15. [Personalizzazioni](#personalizzazioni)
16. [Comportamento fail-fast](#comportamento-fail-fast)

---

## A cosa serve

Esempio classico: un veicolo ha un odometro. Vuoi:

- una **revisione ogni 20.000 km**;
- un avviso **2.000 km prima**;
- poi un reminder ogni **500 km** fino a 500 km dalla scadenza;
- quando l’operatore ha fatto la revisione, **chiudere** la scadenza.

Lo stesso schema vale per date (`assicurazione tra 365 giorni, avviso a 30`), ore di lavoro, pezzi prodotti, e qualunque unità gestita da `ilbronza/measurementunits`.

Il flusso è sempre questo:

```
Tipo (template)  →  applicazione su un modello  →  Schedule (istanza)
                 →  evaluator ogni minuto
                 →  notifiche alle soglie
                 →  scadenza marcata expired
                 →  operatore la marca managed
```

---

## I pezzi del dominio

| Concetto | Classe | Ruolo |
|---|---|---|
| **Tipo** | `Type` | Template: unità di misura, durata (`validity`), modelli a cui si applica, ruoli da notificare |
| **Soglia tipo** | `TypeNotification` | “Avvisa N unità prima della deadline”, con eventuale ripetizione |
| **Scadenza** | `Schedule` | Istanza su un modello concreto (`Vehicle #12`), con `starting_value` e `deadline_value` |
| **Soglia istanza** | `ScheduledNotification` | Copia operativa di una `TypeNotification` su quella schedule |
| **Invio** | `ScheduledNotificationDispatch` | Storico di ogni invio (ripetizioni incluse) |

Una schedule vive su un morph (`schedulable_type` / `schedulable_id`). Il valore corrente **non è salvato** sulla schedule: viene riletto dal modello a ogni valutazione.

```
Type 1──* TypeNotification
  │
  └──* Schedule  ── morph ──  Vehicle / altro modello Schedulable
           │
           └──* ScheduledNotification ──* ScheduledNotificationDispatch
```

---

## Installazione

```bash
composer require ilbronza/schedules
php artisan vendor:publish --tag=schedules.config
php artisan migrate
```

Dipendenze runtime dichiarate in `composer.json`: Laravel 11, Carbon 3, Spatie Activitylog e Permission, e i pacchetti IlBronza `crud`, `buttons`, `datatables`, `form`, `formfield`, `measurementunits`, `notifications`.

Prima di lanciare le migration di Schedules deve già esistere la tabella Laravel `notifications` (di solito creata da `ilbronza/notifications`). Le scheduled notification tengono una foreign key su `notifications.id`.

---

## Configurazione dell’applicazione host

Il package non conosce i modelli del progetto. Pubblica `config/schedules.php` e dichiara:

```php
'applicableTo' => [
    \App\Models\Vehicle::class => 'vehicles',
],

'notifications' => [
    // Modello Eloquent che usa il trait Notifiable (tipicamente Role).
    'roleModel' => \App\Models\Role::class,
],

'defaultRoles' => [
    'superadmin',
    'administrator',
    'schedules',
],

'routeRoles' => [
    // Override opzionale per singola route:
    // 'ibSchedules.types.index' => ['schedules'],
],

'evaluator' => [
    'enabled' => true,
    'cron' => '* * * * *',
    'chunkSize' => 100,
    'overlapExpirationMinutes' => 10,
],
```

Punti fermi:

- Ogni classe in `applicableTo` **deve** implementare `IlBronza\Schedules\Contracts\Schedulable`.
- L’alias (`vehicles`) è lo slug usato nelle URL. Non mettere mai un FQCN PHP in una route.
- Un FQCN può comparire una sola volta: alias duplicati o ambigui lanciano `RuntimeException`.
- Se AccountManager è installato e `notifications.roleModel` è `null`, si usa il role model di AccountManager.
- All’avvio l’applicazione valida la config. Classi inesistenti o non `Schedulable` fanno fallire il boot. Per disattivare (sconsigliato): `'validateConfiguration' => false`.

---

## Rendere un modello schedulabile

```php
use IlBronza\Schedules\Contracts\Schedulable;
use IlBronza\Schedules\Traits\InteractsWithSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model implements Schedulable
{
    use InteractsWithSchedule;

    /**
     * Metodi che il package può chiamare per leggere il valore corrente.
     * Qualunque altro nome viene rifiutato.
     */
    protected array $allowedScheduleValueMethods = [
        'getOdometer',
    ];

    public function getOdometer(): float
    {
        return (float) $this->odometer;
    }

    /**
     * Scope usato dalla UI di applicazione massiva.
     * Il nome è scope + Studly(nome del Type).
     * Tipo "Revisione" → scopeRevisione.
     */
    public function scopeRevisione(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function getName(): string
    {
        return $this->plate;
    }
}
```

### Getter allowlistati

Il tipo di scadenza indica *come* leggere il valore, in uno di questi due modi:

| Campo sul Type | Effetto |
|---|---|
| `source` | `odometer` → chiama `getOdometer()` |
| `method` | chiama esattamente quel metodo, es. `getOdometer` |

Se sono presenti entrambi, **vince `method`**. In ogni caso il metodo deve stare in `$allowedScheduleValueMethods`. Un getter non allowlistato lancia `RuntimeException` (e in HTTP create/update del tipo dà 422).

Non è un accessor Eloquent: `source = current_km` risolve `getCurrentKm()`, non `getCurrentKmAttribute()`.

### Metodi opzionali del trait

| Metodo | Default | A cosa serve |
|---|---|---|
| `getSchedulableModelNameAttribute()` | `getMorphClass()` | Etichetta del modello nella lista “applica a…” |
| `getSchedulableModelTableFieldsArray()` | colonne vuote extra | Campi aggiuntivi nella datatable degli elementi |
| `getName()` | — | Nome mostrato nel payload della notifica |

### Scope per l’applicazione da UI

`getSchedulableElementsQuery()` chiama `scope{Studly(nome del Type)}`. Se manca, l’indice di applicazione lancia un’eccezione. Se vuoi tutti i record:

```php
public function scopeRevisione(Builder $query): Builder
{
    return $query;
}
```

Applicando le scadenze da codice (`ScheduleApplicatorHelper`) lo scope **non** è necessario.

---

## Creare un tipo di scadenza

Un `Type` è il template. Campi rilevanti:

| Campo | Significato |
|---|---|
| `name` | Nome visibile. Dalla UI di applicazione deriva anche lo scope (`Revisione` → `scopeRevisione`) |
| `measurement_unit_id` | Unità di `ilbronza/measurementunits` (km, giorni, ore, …) |
| `validity` | Durata della scadenza in quell’unità. `starting + validity = deadline` |
| `percentage_validity` | Soglia di “in scadenza”: `isExpiring()` è true quando il progresso ≥ questo valore (default 99) |
| `allow_multiple` | Se `false`, un modello può avere una sola schedule *corrente* di questo tipo |
| `models` | Elenco dei FQCN a cui si applica, con `source` e/o `method` |
| `roles` | Ruoli Spatie che ricevono le notifiche (id del role model) |

`models` deve:

- essere un array non vuoto;
- usare FQCN presenti in `schedules.applicableTo`;
- avere `source` e/o `method`;
- risolvere a getter allowlistati.

Esempio in codice:

```php
use IlBronza\Schedules\Models\Type;

$type = Type::getProjectClassName()::create([
    'name' => 'Revisione',
    'validity' => 20000,
    'percentage_validity' => 90,
    'measurement_unit_id' => $kilometers->getKey(),
    'allow_multiple' => false,
    'models' => [
        [
            'model' => \App\Models\Vehicle::class,
            'source' => 'odometer',
        ],
    ],
    'roles' => [
        ['roles' => $maintenanceRoleId],
    ],
]);
```

`roles` accetta anche `role_id` o `id` come chiave dell’entry. Entry malformate o vuote lanciano `RuntimeException` in valutazione.

Dalla UI: **Impostazioni → Scadenze → Tipologie** (`/schedules-management/types`).

---

## Soglie di notifica

Una `TypeNotification` appartiene a un tipo e definisce *quando* avvisare rispetto alla deadline della schedule.

| Campo | Significato |
|---|---|
| `before` | Offset della **prima** ripetizione, prima della deadline. Obbligatorio |
| `repeat_every` | Intervallo tra un invio e il successivo (dopo il primo) |
| `repeat_every_measurement_unit_id` | Unità dell’intervallo (stessa base dell’unità del tipo) |
| `last_repetition` | Offset dell’**ultima** ripetizione ammessa, prima della deadline. Se omesso, si ripete fino alla deadline |
| `urgency` | Intero ≥ 0 scritto nel payload (`urgency` / `priority`) |

`type_id` è immutabile dopo la creazione.

### Vincoli (save + form HTTP 422)

- `repeat_every` e l’unità di ripetizione stanno insieme: uno senza l’altro è errore.
- `last_repetition` richiede la ripetizione.
- `repeat_every` deve essere numerico e `> 0`.
- `last_repetition` deve essere numerico, `≥ 0`, e **più vicino alla deadline** di `before` (`last_repetition ≤ before`).

### Esempio numerico

Tipo con `validity = 20000` km, veicolo a 90.000 km:

- schedule: starting `90000`, deadline `110000`
- soglia `before = 2000` → prima notifica a `108000`
- `repeat_every = 500`, `last_repetition = 500` → invii a 108000, 108500, 109000, 109500

Senza ripetizione la soglia parte **una sola volta**, anche se l’evaluator gira di nuovo.

Ogni invio viene scritto in `schedules__scheduled_notification_dispatches`. Sulla scheduled notification restano solo `notification_id` e `last_dispatched_value` dell’ultimo invio (idempotenza e calcolo del prossimo intervallo).

Il messaggio di default:

> La scadenza "Revisione" per "AB123CD" ha raggiunto una soglia di notifica.

---

## Applicare le scadenze

`ScheduleApplicatorHelper` crea (o aggiorna) la schedule e tutte le soglie in **una transazione**. O tutto o niente.

### Da codice

```php
use IlBronza\Schedules\Helpers\Applicators\ScheduleApplicatorHelper;
use IlBronza\Schedules\Helpers\Applicators\BulkScheduleApplicatorHelper;

// Partenza = valore corrente del modello (getOdometer()), deadline = starting + validity
$schedule = ScheduleApplicatorHelper::applicateScheduleToModel($type, $vehicle);

// Partenza esplicita
$schedule = ScheduleApplicatorHelper::applicateStartingScheduleToModel($type, $vehicle, 90000);

// Deadline esplicita (starting = deadline − validity)
$schedule = ScheduleApplicatorHelper::applicateEndingScheduleToModel($type, $vehicle, 110000);

// Come i precedenti, ma riusano la schedule corrente dello stesso tipo.
// Non creano duplicati. Se i limiti cambiano, le soglie si riallineano;
// una soglia il cui threshold si sposta torna pending e può essere reinviata.
$schedule = ScheduleApplicatorHelper::findOrApplicateStartingScheduleToModel($type, $vehicle, 95000);
$schedule = ScheduleApplicatorHelper::findOrApplicateEndingScheduleToModel($type, $vehicle, 115000);

// Stesso tipo su tanti modelli, una transazione, lock ordinati
BulkScheduleApplicatorHelper::applicateScheduleToModels($type, $vehicles);
```

`applicate*` (senza `findOr`) crea sempre una schedule nuova. Se `allow_multiple` è `false` e ne esiste già una corrente, lancia `RuntimeException`.

### Dalla UI

1. Apri il tipo → **Applica**.
2. Scegli il modello (`vehicles`).
3. Seleziona gli elementi e conferma.

La route usa l’alias, non la classe PHP:

`/schedules-management/types/applicate/{type}/models/vehicles`

---

## Valutare le scadenze (cron)

Il package registra `schedules:evaluate` e, di default, lo mette nello scheduler Laravel ogni minuto (`withoutOverlapping`). L’host deve comunque far girare lo scheduler:

```bash
* * * * * php /path/to/artisan schedule:run
```

A mano:

```bash
php artisan schedules:evaluate
php artisan schedules:evaluate --chunk=250
```

Per invalidare manualmente le schedule:

```bash
# Solo quelle appartenenti a un tipo
php artisan schedules:invalidate <type-id>

# Quelle di tutti i tipi; verifica prima il conteggio senza modifiche
php artisan schedules:invalidate --all --dry-run
php artisan schedules:invalidate --all
```

`type` e `--all` sono alternativi. L'invalidazione soft-delete di ogni schedule
e delle relative scheduled notification; non modifica `expired_at` e non fa
force-delete, quindi resta possibile il ripristino.

Per ogni schedule **corrente** (non expired, non managed) l’evaluator:

1. rilegge il valore corrente dal modello (getter allowlistato);
2. per ogni scheduled notification ancora eligible, se la soglia è raggiunta invia la notifica ai ruoli del tipo;
3. se la deadline della schedule è raggiunta, marca `expired_at` e fa scadere le soglie ancora `pending`.

Le soglie già `dispatched` o `managed` non vengono toccate quando la schedule scade.

L’esecuzione è fail-fast: valore illeggibile, ruoli mancanti, unità di ripetizione incompatibile, errore di dispatch → il comando si ferma.

Il valore corrente della schedule è anche esposto come attributo `current_value`, cachato 60 secondi.

Per disattivare lo schedule automatico: `'evaluator.enabled' => false`.

---

## Segnare una scadenza come gestita

Quando l’intervento è stato fatto (revisione eseguita, rinnovo, …) si marca la schedule come **managed**. La transizione è transazionale: lo stesso `managed_at` va sulla schedule e su tutte le scheduled notification ancora senza timestamp. Ripetere l’azione non cambia il timestamp originale.

```php
use IlBronza\Schedules\Services\ScheduleManager;

app(ScheduleManager::class)->manage($schedule);
app(ScheduleManager::class)->manageMany([$id1, $id2]);
```

Dalla tabella scadenze: azione singola o “Segna come gestite” sulla selezione.

Una schedule managed non viene più valutata.

---

## Leggere le scadenze da un modello

Metodi del trait `InteractsWithSchedule`:

```php
$vehicle->schedules;                          // morphMany
$vehicle->getSchedules();

$vehicle->getCurrentScheduleByType($type);    // una, la più lontana in deadline
$vehicle->getCurrentSchedulesByType($type);   // tutte le correnti di quel tipo
$vehicle->getLatestByType($type);             // anche expired/managed

$vehicle->getApplicatedScheduleTypes();       // Type distinti già applicati
$vehicle->scheduledNotifications();           // hasManyThrough, filtrato sul morph
```

Sulla schedule:

```php
$schedule->getStartingValue();
$schedule->getDeadlineValue();
$schedule->getCurrentValue();          // rilegge il modello (cache 60s)
$schedule->getPercentageValidity();    // 0 al starting, 100 alla deadline
$schedule->isCurrent();
$schedule->isExpiring();               // progresso ≥ percentage_validity del tipo
$schedule->isManaged();
$schedule->isMarkedExpired();
```

`getPercentageValidity()` lancia se starting e deadline coincidono (span zero).

---

## Stati

### Schedule

Precedenza: **managed > expired > current**.

| Stato | Condizione |
|---|---|
| `current` | `expired_at` e `managed_at` nulli — viene valutata |
| `expired` | `expired_at` valorizzato — deadline raggiunta |
| `managed` | `managed_at` valorizzato — chiusa dall’operatore |

Scope: `Schedule::current()`, `notExpired()`, `notManaged()`, `byType($type)`.

### Scheduled notification

Precedenza: **managed > expired > dispatched > pending**.

| Stato | Condizione |
|---|---|
| `pending` | mai inviata, non expired, non managed |
| `dispatched` | almeno un invio (`notification_id` e/o riga in `dispatches`) |
| `expired` | la schedule è scaduta prima che questa soglia potesse partire |
| `managed` | la schedule è stata gestita |

Una soglia `dispatched` **non** viene sovrascritta a `expired`. `markExpired()` su una notifica non pending lancia `RuntimeException`.

Le soglie repeating restano eligible dopo il primo invio: lo scope `pending()` include anche quelle già dispatchate se il tipo ha `repeat_every` + unità.

---

## Interfaccia HTTP

Prefisso URL: `/schedules-management`. Prefisso nomi: `ibSchedules.` (config `routePrefix`).

Middleware: `web`, `auth`, `schedules.roles`. I ruoli ammessi sono `defaultRoles`, con override per route in `routeRoles`. Senza autenticazione o senza ruolo: accesso negato.

| Area | Route principali |
|---|---|
| Tipologie | `ibSchedules.types.index/create/show/edit/destroy` |
| Applica tipo | `ibSchedules.types.applicate.index`, `types.applicate.classname.index/store` |
| Soglie tipo | `ibSchedules.types.typeNotifications.create/store`, `typeNotifications.*` |
| Scadenze | `ibSchedules.schedules.index/show/manage/manageMany` |

Il menu (se il package Menu è registrato) aggiunge sotto Impostazioni: elenco tipologie e elenco scadenze.

Non c’è create/edit di una `Schedule` da form: si crea solo applicando un tipo.

---

## Soft delete

- Cancellare una `Schedule` soft-delete delle sue scheduled notification; il restore le ripristina.
- Force-delete di una schedule force-delete anche le notification già soft-deleted.
- Un `Type` **non** si cancella se esistono schedule non cancellate che lo referenziano. Se la cancellazione è permessa, le type notification cascano.
- Una `TypeNotification` **non** si cancella se esistono scheduled notification che la referenziano.

Tentativi non validi: `RuntimeException`, non silenzio.

---

## Personalizzazioni

### Dispatcher di notifiche

Il default è `IlBronzaScheduleNotificationDispatcher`: invia `ScheduleThresholdReachedNotification` al primo ruolo del tipo, poi `ChildDatabaseNotification` agli altri, tramite `ilbronza/notifications`.

Per sostituirlo:

```php
use IlBronza\Schedules\Contracts\ScheduleNotificationDispatcher;

$this->app->bind(
    ScheduleNotificationDispatcher::class,
    \App\Schedules\MyDispatcher::class
);
```

`dispatch()` deve restituire l’id della riga in `notifications`.

### FileCabinet (legacy)

Se è installato `ilbronza/filecabinet`, le `Dossierrow` di tipo expiration-date possono fornire il valore corrente senza implementare `Schedulable`. È un ponte esplicito, non il percorso consigliato per i modelli nuovi.

### Modelli del package

In `config/schedules.php` → `models.*.class` puoi estendere `Schedule`, `Type`, ecc. La classe deve discendere da quella del package. La validazione di boot lo verifica.

---

## Comportamento fail-fast

Il package non ignora gli errori di dominio. In particolare:

| Situazione | Cosa succede |
|---|---|
| Classe in `applicableTo` inesistente o non `Schedulable` | eccezione al boot |
| `Type.models` invalido | eccezione al save / 422 in HTTP |
| Getter non allowlistato | `RuntimeException` |
| Configurazione ripetizione incompleta | eccezione al save / 422 |
| Tipo senza ruoli al momento del dispatch | l’evaluator si ferma |
| Role id inesistente | l’evaluator si ferma |
| Unità di ripetizione con base diversa da quella del tipo | l’evaluator si ferma |
| Cancellazione di Type/TypeNotification ancora referenziati | `RuntimeException` |
| Span starting–deadline a zero in `percentage_validity` | `RuntimeException` |

Questo è intenzionale: una scadenza silenziosa è peggio di un job che fallisce in evidenza.
