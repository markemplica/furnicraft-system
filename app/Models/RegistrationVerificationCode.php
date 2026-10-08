<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationVerificationCode extends Model
{
    protected $fillable = ['user_id', 'code_hash', 'expires_at', 'used_at', 'attempts', 'requested_ip'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }
}