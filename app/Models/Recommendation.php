<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recommendation extends Model
{
    protected $fillable = ['user_id', 'customer_preference_id', 'room_image_id', 'furniture_type', 'summary', 'algorithm_version', 'gemini_used', 'is_cold_start', 'content_weight', 'collaborative_weight'];

    protected function casts(): array
    {
        return ['gemini_used' => 'boolean', 'is_cold_start' => 'boolean', 'content_weight' => 'float', 'collaborative_weight' => 'float'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function preference()
    {
        return $this->belongsTo(CustomerPreference::class, 'customer_preference_id');
    }

    public function roomImage()
    {
        return $this->belongsTo(RoomImage::class);
    }

    public function items()
    {
        return $this->hasMany(RecommendationItem::class)->orderBy('position');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class);
    }
}
