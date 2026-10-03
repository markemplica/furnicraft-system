<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPreference extends Model
{
    protected $fillable = ['user_id', 'furniture_type', 'budget_min', 'budget_max', 'style', 'wood_type', 'varnish_finish', 'room_width_m', 'room_depth_m', 'notes'];

    protected function casts(): array
    {
        return ['budget_min' => 'decimal:2', 'budget_max' => 'decimal:2', 'room_width_m' => 'decimal:2', 'room_depth_m' => 'decimal:2'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
