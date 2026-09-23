<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationTransaction extends Model
{
    protected $fillable = ['reservation_id', 'actor_id', 'action', 'notes', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
