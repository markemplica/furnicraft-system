<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FurnitureProduct extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'category', 'description', 'wood_type', 'varnish_finish', 'style', 'price', 'width_cm', 'depth_cm', 'height_cm', 'image_path', 'tags', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'tags' => 'array', 'width_cm' => 'decimal:2', 'depth_cm' => 'decimal:2', 'height_cm' => 'decimal:2'];
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function views()
    {
        return $this->hasMany(FurnitureViewEvent::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function getImageUrlAttribute(): string
    {
        if (! $this->image_path) {
            return asset('images/furniture/default.svg');
        }
        if (str_starts_with($this->image_path, 'demo:')) {
            return asset('images/furniture/'.substr($this->image_path, 5));
        }
        if (str_starts_with($this->image_path, 'catalog:')) {
            return asset('images/catalog/'.substr($this->image_path, 8));
        }

        return asset('storage/'.$this->image_path);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
