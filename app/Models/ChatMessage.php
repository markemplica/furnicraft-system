<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $fillable = ['user_id', 'recommendation_id', 'role', 'message', 'gemini_used', 'ai_source'];

    protected function casts(): array
    {
        return ['gemini_used' => 'boolean'];
    }
}
