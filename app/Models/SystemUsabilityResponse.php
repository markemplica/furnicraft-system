<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemUsabilityResponse extends Model
{
    protected $fillable = ['user_id', 'answers', 'sus_score'];

    protected function casts(): array
    {
        return ['answers' => 'array', 'sus_score' => 'decimal:2'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
