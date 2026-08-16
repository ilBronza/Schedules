<?php

namespace IlBronza\Schedules\Tests\Unit;

use IlBronza\Schedules\Models\ScheduledNotification;
use PHPUnit\Framework\TestCase;

/**
 * Status precedence (highest first): managed > expired > dispatched > pending.
 *
 * Each declared status is reachable:
 * - pending: no notification_id, expired_at, or managed_at
 * - dispatched: notification_id set (and not expired/managed)
 * - expired: expired_at set after schedule deadline, before dispatch
 * - managed: managed_at set when the schedule is managed
 */
class ScheduledNotificationTest extends TestCase
{
    public function testPendingStatus(): void
    {
        $this->assertSame(
            ScheduledNotification::STATUS_PENDING,
            $this->notification()->status
        );
    }

    public function testDispatchedStatus(): void
    {
        $this->assertSame(
            ScheduledNotification::STATUS_DISPATCHED,
            $this->notification(dispatched: true)->status
        );
    }

    public function testExpiredStatus(): void
    {
        $this->assertSame(
            ScheduledNotification::STATUS_EXPIRED,
            $this->notification(expired: true)->status
        );
    }

    public function testManagedStatus(): void
    {
        $this->assertSame(
            ScheduledNotification::STATUS_MANAGED,
            $this->notification(managed: true)->status
        );
    }

    public function testExpiredStatusHasPriorityOverDispatchedStatus(): void
    {
        $this->assertSame(
            ScheduledNotification::STATUS_EXPIRED,
            $this->notification(expired: true, dispatched: true)->status
        );
    }

    public function testManagedStatusHasPriorityOverExpiredStatus(): void
    {
        $this->assertSame(
            ScheduledNotification::STATUS_MANAGED,
            $this->notification(managed: true, expired: true)->status
        );
    }

    public function testManagedStatusHasPriorityOverDispatchedStatus(): void
    {
        $this->assertSame(
            ScheduledNotification::STATUS_MANAGED,
            $this->notification(managed: true, dispatched: true)->status
        );
    }

    public function testManagedStatusHasHighestPriority(): void
    {
        $this->assertSame(
            ScheduledNotification::STATUS_MANAGED,
            $this->notification(
                managed: true,
                expired: true,
                dispatched: true
            )->status
        );
    }

    public function testIsPendingMatchesUnresolvedThreshold(): void
    {
        $this->assertTrue($this->notification()->isPending());
        $this->assertFalse($this->notification(dispatched: true)->isPending());
        $this->assertFalse($this->notification(expired: true)->isPending());
        $this->assertFalse($this->notification(managed: true)->isPending());
    }

    protected function notification(
        bool $managed = false,
        bool $expired = false,
        bool $dispatched = false
    ): ScheduledNotification {
        return new class($managed, $expired, $dispatched) extends ScheduledNotification {
            public function __construct(
                protected bool $managed = false,
                protected bool $expired = false,
                protected bool $dispatched = false
            ) {
                parent::__construct();
            }

            public function isManaged(): bool
            {
                return $this->managed;
            }

            public function isExpired(): bool
            {
                return $this->expired;
            }

            public function isDispatched(): bool
            {
                return $this->dispatched;
            }
        };
    }
}
