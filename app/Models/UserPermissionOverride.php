<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPermissionOverride extends Model
{
    protected $fillable = ['permission', 'granted'];

    protected function casts(): array
    {
        return ['granted' => 'boolean'];
    }
}
