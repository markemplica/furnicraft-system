<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomPreviewGeneration extends Model
{
    protected $fillable = [
        'room_image_id', 'furniture_product_id', 'status', 'attempts', 'message', 'placement_json',
        'cache_key', 'confidence', 'confidence_label', 'failure_code', 'metrics_json', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['placement_json' => 'array', 'metrics_json' => 'array', 'confidence' => 'float', 'completed_at' => 'datetime'];
    }
}
