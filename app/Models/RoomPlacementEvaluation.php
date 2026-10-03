<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomPlacementEvaluation extends Model
{
    protected $fillable = ['room_image_id', 'evaluator_id', 'floor_detection_correct', 'zone_appropriate', 'clearance_respected', 'scale_plausible', 'notes'];

    protected function casts(): array
    {
        return ['floor_detection_correct' => 'boolean', 'zone_appropriate' => 'boolean', 'clearance_respected' => 'boolean', 'scale_plausible' => 'boolean'];
    }

    public function roomImage()
    {
        return $this->belongsTo(RoomImage::class);
    }
}
