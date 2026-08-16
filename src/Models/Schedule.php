<?php

namespace IlBronza\Schedules\Models;


use Carbon\CarbonInterface;
use IlBronza\MeasurementUnits\BaseMeasurementUnitHelpers\BaseMeasurementUnitHelper;
use IlBronza\MeasurementUnits\Models\MeasurementUnit;
use IlBronza\Schedules\Helpers\Applicators\ScheduleDeadlineCalculatorHelper;
use IlBronza\Schedules\Helpers\Applicators\ScheduleStartingCalculatorHelper;
use IlBronza\Schedules\Helpers\Calculators\ScheduleCurrentValueCalculatorHelper;
use IlBronza\Schedules\Helpers\Calculators\ScheduleValueComparator;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Services\ScheduleSoftDeleteLifecycle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RuntimeException;
use function is_array;

// use IlBronza\Schedules\Models\TypeNotification;

class Schedule extends SchedulePackageBaseModel
{
	public const STATUS_CURRENT = 'current';
	public const STATUS_EXPIRED = 'expired';
	public const STATUS_MANAGED = 'managed';

	static $deletingRelationships = ['scheduledNotifications'];

	protected $casts = [
		'expired_at' => 'datetime',
		'managed_at' => 'datetime',
	];

	protected static function booted(): void
	{
		static::deleting(function (Schedule $schedule): void {
			$lifecycle = app(ScheduleSoftDeleteLifecycle::class);

			if ($schedule->isForceDeleting()) {
				$lifecycle->cascadeForceDeleteScheduledNotifications($schedule);

				return;
			}

			$lifecycle->cascadeSoftDeleteScheduledNotifications($schedule);
		});

		static::restored(function (Schedule $schedule): void {
			app(ScheduleSoftDeleteLifecycle::class)
				->cascadeRestoreScheduledNotifications($schedule);
		});
	}

	public function schedulable()
	{
		return $this->morphTo();
	}

	public function getSchedulable() : Model
	{
		$schedulable = $this->schedulable;

		if (is_null($schedulable)) {
			throw new RuntimeException(
				'Schedule ['.$this->getKey().'] is missing required schedulable morph.'
			);
		}

		return $schedulable;
	}

	public function type()
	{
		return $this->belongsTo(Type::getProjectClassName());
	}

	public function getMeasurementUnit() : MeasurementUnit
	{
		return $this->getType()->getMeasurementUnit();
	}

	public function getName() : ? string
	{
		return $this->getType()->getName();
	}

	public function isExpired() : bool
	{
		return $this->getValueComparator()->hasReached(
			$this,
			$this->getCurrentValue(),
			$this->getDeadlineValue()
		);
	}

	protected function getValueComparator(): ScheduleValueComparator
	{
		return app(ScheduleValueComparator::class);
	}

	public function getPercentageValidityLimit(): float
	{
		return $this->getType()->getPercentageValidityLimit();
	}

	public function isExpiring() : bool
	{
		return $this->getPercentageValidityAttribute() >= $this->getPercentageValidityLimit();
	}

	// public function typeNotifications()
	// {
	// 	return $this->hasMany(TypeNotification::class);
	// }

	public function scheduledNotifications()
	{
		return $this->hasMany(ScheduledNotification::getProjectClassName());
	}

	public function typeNotifications()
	{
		// hasManyThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null, $secondLocalKey = null)
		return $this->hasMany(TypeNotification::getProjectClassName(), 'type_id', 'type_id');
	}

	public function getType() : Type
	{
		if (is_null($this->type_id)) {
			throw new RuntimeException(
				'Schedule ['.$this->getKey().'] is missing required type_id.'
			);
		}

		if($this->relationLoaded('type')) {
			if (is_null($this->type)) {
				throw new RuntimeException(
					'Schedule ['.$this->getKey().'] type relation is null for type_id ['.$this->type_id.'].'
				);
			}

			return $this->type;
		}

		$type = Type::getProjectClassName()::findCached($this->type_id);

		if (is_null($type)) {
			throw new RuntimeException(
				'Schedule ['.$this->getKey().'] references missing type_id ['.$this->type_id.'].'
			);
		}

		return $type;
	}

	public function setType(Type $type)
	{
		$this->setRelation('type', $type);

		$typeForeignKey = $this->type()->getForeignKeyName();

		$this->$typeForeignKey = $type->getKey();
	}

	public function getCurrentValueAttribute() : mixed
	{
		return cache()->remember(
			$this->cacheKey('current_value'),
			60,
			fn () => $this->calculateCurrentValue()
		);
	}

	public function calculateCurrentValue(): mixed
	{
		$currentValue = ScheduleCurrentValueCalculatorHelper::getCurrentValueFromSchedule($this);

		return $this->parseOutputValueByType($currentValue);
	}

	public function getCurrentValue() : mixed
	{
		return $this->current_value;
	}

	/**
	 * Progress through the schedule as a percentage of the starting→deadline span.
	 *
	 * percentage = (delta / span) * 100 where
	 *   span  = difference(starting, deadline)
	 *   delta = difference(starting, current)
	 *
	 * Semantics:
	 * - deadline reached (ScheduleValueComparator) → 100
	 * - current == starting → 0
	 * - current before starting → negative percentage
	 * - current past deadline is covered by the early 100 return
	 * - floats use raw IEEE-754 arithmetic (no epsilon)
	 * - dates/timezones are delegated to the measurement unit helper
	 *
	 * @throws RuntimeException when starting and deadline yield a zero span
	 */
	public function getPercentageValidityAttribute() : float
	{
		$deadlineValue = $this->getDeadlineValue();
		$currentValue = $this->getCurrentValue();

		if($this->getValueComparator()->hasReached($this, $currentValue, $deadlineValue))
			return 100;

		$rawStartingValue = $this->getAttributes()['starting_value'] ?? null;

		if (is_null($rawStartingValue)) {
			$startingValue = ScheduleStartingCalculatorHelper::calculateFromSchedule($this);
		} else {
			$startingValue = $this->getStartingValue();
		}

		$span = $this->getMeasurementUnitHelper()->calculateDifference($startingValue, $deadlineValue);

		if ($span == 0) {
			throw new RuntimeException(
				'Schedule ['.$this->getKey().'] has a zero span between starting and deadline; percentage validity cannot be computed.'
			);
		}

		$delta = $this->getMeasurementUnitHelper()->calculateDifference($startingValue, $currentValue);

		return $delta / ($span / 100);
	}

	public function getPercentageValidity(): float
	{
		return $this->percentage_validity;
	}

    public function setStarting(mixed $value, bool $save = false)
    {
    	$this->starting_value = $value;

    	if($save)
    		$this->save();
    }

	public function setDeadline(mixed $value = null, bool $save = false)
	{
		$this->deadline_value = $value;

		if($save)
			$this->save();
	}

	public function getMeasurementUnitHelper() : BaseMeasurementUnitHelper
	{
		return $this->getType()->getMeasurementUnit()->getHelper();
	}

	public function parseOutputValueByType(mixed $value) : mixed
	{
		$helper = $this->getMeasurementUnitHelper();

		return $helper->parseMeasurementUnitOutputValue($value);
	}

    public function getStartingValue() : mixed
    {
		return $this->parseOutputValueByType(
			$this->starting_value
		);
    }

    public function getDeadlineValue() : mixed
    {
    	return $this->parseOutputValueByType(
    		$this->deadline_value
    	);
    }

    public function calculateDeadline(bool $save = true) : mixed
    {
    	$result = ScheduleDeadlineCalculatorHelper::calculateFromSchedule(
			$this
		);

		$this->setDeadline($result, $save);

		return $result;
    }

    public function calculateStarting(bool $save = true) : mixed
    {
		$result = ScheduleStartingCalculatorHelper::calculateFromSchedule(
			$this
		);

		$this->setStarting($result, $save);

		return $result;
    }

	public function markExpired(): void
	{
		$this->expired_at = now();
		$this->save();
	}

	public function markManaged(CarbonInterface $managedAt): void
	{
		$this->managed_at = $managedAt;
		$this->save();
	}

	public function isManaged(): bool
	{
		return ! is_null($this->managed_at);
	}

	public function isMarkedExpired(): bool
	{
		return ! is_null($this->expired_at);
	}

	public function getStatusAttribute(): string
	{
		if($this->isManaged())
			return self::STATUS_MANAGED;

		if($this->isMarkedExpired())
			return self::STATUS_EXPIRED;

		return self::STATUS_CURRENT;
	}

	public function isCurrent(): bool
	{
		return is_null($this->expired_at) && is_null($this->managed_at);
	}

	public function scopeNotExpired($query)
	{
		$query->whereNull('expired_at');
	}

	public function scopeNotManaged($query)
	{
		$query->whereNull('managed_at');
	}

	public function scopeCurrent($query)
	{
		$query->notExpired()->notManaged();
	}

	public function scopeByTypes($query, array|Collection $types)
	{
		if(! is_array($types))
			$types = $types->pluck('id');

		$query->whereIn('type_id', $types);
	}

	public function scopeByType($query, Type $type)
	{
		$query->byTypeId(
			$type->getKey()
		);
	}

	public function scopeByTypeId($query, string $typeId)
	{
		$query->where('type_id', $typeId);
	}
}