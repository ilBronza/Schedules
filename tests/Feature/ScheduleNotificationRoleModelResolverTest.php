<?php

namespace IlBronza\Schedules\Tests\Feature;

use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\TypeCreateStoreFieldsetsParameters;
use IlBronza\Schedules\Services\IlBronzaScheduleNotificationDispatcher;
use IlBronza\Schedules\Services\ScheduleNotificationRoleModelResolver;
use IlBronza\Schedules\Services\SchedulesConfigurationValidator;
use IlBronza\Schedules\Tests\Fixtures\Models\Role;
use IlBronza\Schedules\Tests\Fixtures\Models\Schedulable;
use IlBronza\Schedules\Tests\TestCase;
use ReflectionMethod;
use RuntimeException;
use Spatie\Permission\Models\Role as SpatieRole;

class ScheduleNotificationRoleModelResolverTest extends TestCase
{
    public function testFormValidatorAndDispatcherResolveTheSameRoleModel(): void
    {
        config([
            'schedules.notifications.roleModel' => null,
            'accountmanager.models.role.class' => Role::class,
        ]);

        $expected = $this->resolver()->resolve();

        $this->assertSame(Role::class, $expected);
        $this->assertSame(
            $expected,
            $this->roleModelClassFrom(new TypeCreateStoreFieldsetsParameters())
        );
        $this->assertSame(
            $expected,
            $this->roleModelClassFrom($this->dispatcher())
        );

        $this->validator()->validate();
    }

    public function testSchedulesConfigTakesPrecedenceOverAccountManager(): void
    {
        config([
            'schedules.notifications.roleModel' => Role::class,
            'accountmanager.models.role.class' => SpatieRole::class,
        ]);

        $expected = $this->resolver()->resolve();

        $this->assertSame(Role::class, $expected);
        $this->assertSame(
            $expected,
            $this->roleModelClassFrom(new TypeCreateStoreFieldsetsParameters())
        );
        $this->assertSame(
            $expected,
            $this->roleModelClassFrom($this->dispatcher())
        );
    }

    public function testMissingRoleModelFailsForTypeFormInsteadOfFallingBackToSpatie(): void
    {
        config([
            'schedules.notifications.roleModel' => null,
            'accountmanager.models.role.class' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not configured');

        $this->roleModelClassFrom(new TypeCreateStoreFieldsetsParameters());
    }

    public function testMissingRoleModelFailsForDispatcherWithTheSameMessage(): void
    {
        config([
            'schedules.notifications.roleModel' => null,
            'accountmanager.models.role.class' => null,
        ]);

        $fieldsetMessage = null;

        try {
            $this->roleModelClassFrom(new TypeCreateStoreFieldsetsParameters());
        } catch (RuntimeException $exception) {
            $fieldsetMessage = $exception->getMessage();
        }

        $this->assertNotNull($fieldsetMessage);

        try {
            $this->roleModelClassFrom($this->dispatcher());
            $this->fail('Dispatcher accepted a missing role model');
        } catch (RuntimeException $exception) {
            $this->assertSame($fieldsetMessage, $exception->getMessage());
        }
    }

    public function testDispatcherRejectsNonNotifiableRoleModelLikeValidator(): void
    {
        config([
            'schedules.notifications.roleModel' => Schedulable::class,
        ]);

        $validatorMessage = null;

        try {
            $this->validator()->validate();
            $this->fail('Validator accepted a non-notifiable role model');
        } catch (RuntimeException $exception) {
            $validatorMessage = $exception->getMessage();
        }

        $this->assertStringContainsString(
            'must use Laravel Notifiable',
            $validatorMessage
        );

        try {
            $this->roleModelClassFrom($this->dispatcher());
            $this->fail('Dispatcher accepted a non-notifiable role model');
        } catch (RuntimeException $exception) {
            $this->assertSame($validatorMessage, $exception->getMessage());
        }
    }

    protected function roleModelClassFrom(object $subject): string
    {
        $method = new ReflectionMethod($subject, 'getRoleModelClass');
        $method->setAccessible(true);

        return $method->invoke($subject);
    }

    protected function resolver(): ScheduleNotificationRoleModelResolver
    {
        return $this->app->make(ScheduleNotificationRoleModelResolver::class);
    }

    protected function dispatcher(): IlBronzaScheduleNotificationDispatcher
    {
        return $this->app->make(IlBronzaScheduleNotificationDispatcher::class);
    }

    protected function validator(): SchedulesConfigurationValidator
    {
        return $this->app->make(SchedulesConfigurationValidator::class);
    }
}
