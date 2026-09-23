<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = ['reference', 'user_id', 'furniture_product_id', 'recommendation_id', 'branch', 'notes', 'status', 'confirmed_at', 'confirmed_by'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime'];
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
