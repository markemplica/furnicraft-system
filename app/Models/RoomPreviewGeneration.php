<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomPreviewGeneration extends Model
{
    protected $fillable = ['room_image_id', 'furniture_product_id', 'status', 'attempts', 'message'];
}
