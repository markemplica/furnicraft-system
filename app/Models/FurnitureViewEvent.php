<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FurnitureViewEvent extends Model
{
    protected $fillable = ['user_id', 'furniture_product_id', 'session_key'];

    public function product()
    {
        return $this->belongsTo(FurnitureProduct::class, 'furniture_product_id');
    }
}
