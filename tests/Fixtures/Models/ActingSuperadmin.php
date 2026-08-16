<?php

namespace IlBronza\Schedules\Tests\Fixtures\Models;

use IlBronza\AccountManager\Models\User;

class ActingSuperadmin extends User
{
    public function isSuperAdmin()
    {
        return true;
    }

    public function hasRole($roles, ?string $guard = null): bool
    {
        return true;
    }

    public function getRolesString(): string
    {
        return 'superadmin';
    }
}
