<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomPreviewAnalysis extends Model
{
    protected $fillable = ['image_sha256', 'pipeline_version', 'status', 'attempts', 'analysis', 'failure_code', 'analyzed_at'];

    protected function casts(): array
    {
        return ['analysis' => 'array', 'attempts' => 'integer', 'analyzed_at' => 'datetime'];
    }
}
