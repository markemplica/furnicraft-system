<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiServiceEvent extends Model
{
    protected $fillable = ['feature', 'operation', 'status', 'model', 'http_status', 'latency_ms', 'fallback_used', 'failure_code'];

    protected function casts(): array
    {
        return ['fallback_used' => 'boolean', 'latency_ms' => 'integer', 'http_status' => 'integer'];
    }
}
