<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomImage extends Model
{
    protected $fillable = ['user_id', 'image_path', 'room_width_m', 'room_depth_m', 'analysis', 'analysis_status', 'analysis_attempts', 'analysis_error', 'analysis_requested_at', 'gemini_consent_at', 'gemini_consent_version', 'analyzed_at'];

    protected function casts(): array
    {
        return ['analysis' => 'array', 'analysis_attempts' => 'integer', 'analysis_requested_at' => 'datetime', 'gemini_consent_at' => 'datetime', 'analyzed_at' => 'datetime', 'room_width_m' => 'decimal:2', 'room_depth_m' => 'decimal:2'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
