<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecommendationItem extends Model
{
    protected $fillable = ['recommendation_id', 'furniture_product_id', 'content_score', 'collaborative_score', 'total_score', 'position', 'explanation', 'relevance', 'rejection_reason', 'evaluated_at'];

    protected function casts(): array
    {
        return ['relevance' => 'boolean', 'evaluated_at' => 'datetime'];
    }

    public function recommendation()
    {
        return $this->belongsTo(Recommendation::class);
    }

    public function product()
    {
        return $this->belongsTo(FurnitureProduct::class, 'furniture_product_id');
    }
}
