<?php

namespace IlBronza\Schedules\Integrations;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Explicit legacy bridge for FileCabinet Dossierrow expiration dates.
 * Kept outside the Schedulable contract on purpose.
 */
class LegacyDossierrowScheduleValueGetter
{
    public function supports(Model $model): bool
    {
        $dossierrowClass = $this->dossierrowClass();

        if (is_null($dossierrowClass)) {
            return false;
        }

        if (! $model instanceof $dossierrowClass) {
            return false;
        }

        return $model->getFormrow()->isExpirationDate();
    }

    public function getCurrentValue(Model $model): mixed
    {
        if (! $this->supports($model)) {
            throw new RuntimeException(
                'Model ' . get_class($model)
                . ' is not supported by the legacy Dossierrow schedule value getter'
            );
        }

        return $model->getCurrentDate();
    }

    protected function dossierrowClass(): ?string
    {
        $dossierrowClass = config(
            'schedules.integrations.fileCabinet.dossierrowModel',
            'IlBronza\\FileCabinet\\Models\\Dossierrow'
        );

        if (is_null($dossierrowClass)) {
            return null;
        }

        if (! is_string($dossierrowClass)) {
            throw new RuntimeException(
                'The FileCabinet dossier row model must be a class name'
            );
        }

        if (! class_exists($dossierrowClass)) {
            return null;
        }

        return $dossierrowClass;
    }
}
