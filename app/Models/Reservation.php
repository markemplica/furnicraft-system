<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = ['reference', 'user_id', 'furniture_product_id', 'recommendation_id', 'branch', 'preferred_visit_at', 'expires_at', 'notes', 'status', 'decline_reason', 'cancellation_reason', 'confirmed_at', 'confirmed_by', 'item_ready_confirmed_at', 'reminder_sent_at', 'completed_at', 'no_show_at'];

    protected function casts(): array
    {
        return ['preferred_visit_at' => 'datetime', 'expires_at' => 'datetime', 'confirmed_at' => 'datetime', 'item_ready_confirmed_at' => 'datetime', 'reminder_sent_at' => 'datetime', 'completed_at' => 'datetime', 'no_show_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(FurnitureProduct::class, 'furniture_product_id');
    }

    public function recommendation()
    {
        return $this->belongsTo(Recommendation::class);
    }

    public function transactions()
    {
        return $this->hasMany(ReservationTransaction::class);
    }
}

