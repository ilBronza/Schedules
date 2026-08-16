<?php

namespace IlBronza\Schedules\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Role extends Model
{
    use Notifiable;

    protected $table = 'roles';

    protected $guarded = [];
}
