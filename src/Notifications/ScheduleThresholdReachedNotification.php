<?php

namespace IlBronza\Schedules\Notifications;

use IlBronza\Notifications\Notification;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;

class ScheduleThresholdReachedNotification extends Notification
{
    public function __construct(
        protected Schedule $schedule,
        protected ScheduledNotification $scheduledNotification
    ) {
        parent::__construct();
    }

    public function toArray($notifiable): array
    {
        $payload = [
            'message' => $this->getMessage(),
            'schedule_id' => $this->schedule->getKey(),
            'scheduled_notification_id' => $this->scheduledNotification->getKey(),
            'schedulable_type' => $this->schedule->schedulable_type,
            'schedulable_id' => $this->schedule->schedulable_id,
        ];

        $urgency = $this->getUrgency();

        if (! is_null($urgency)) {
            $payload['urgency'] = $urgency;
            $payload['priority'] = $urgency;
        }

        return $payload;
    }

    public function getPriority(): ?int
    {
        return $this->getUrgency();
    }

    protected function getUrgency(): ?int
    {
        if (is_null($this->scheduledNotification->type_notification_id)) {
            return null;
        }

        return $this->scheduledNotification
            ->getTypeNotification()
            ->getUrgency();
    }

    protected function getMessage(): string
    {
        return __('schedules::notifications.thresholdReached', [
            'schedule' => $this->schedule->getName(),
            'schedulable' => $this->getSchedulableName(),
        ]);
    }

    protected function getSchedulableName(): string
    {
        $schedulable = $this->schedule->getSchedulable();

        if (method_exists($schedulable, 'getName')) {
            $name = $schedulable->getName();

            if (! is_null($name)) {
                return $name;
            }
        }

        return class_basename($schedulable).' '.$schedulable->getKey();
    }
}
