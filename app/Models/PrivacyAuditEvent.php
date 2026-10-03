<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivacyAuditEvent extends Model
{
    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
