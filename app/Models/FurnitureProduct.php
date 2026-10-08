<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class FurnitureProduct extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'category', 'description', 'wood_type', 'varnish_finish', 'style', 'price', 'width_cm', 'depth_cm', 'height_cm', 'dimensions_estimated', 'image_path', 'tags', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'tags' => 'array', 'width_cm' => 'decimal:2', 'depth_cm' => 'decimal:2', 'height_cm' => 'decimal:2', 'dimensions_estimated' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $product): void {
            $preserveEstimate = $product->exists
                && (bool) $product->getOriginal('dimensions_estimated')
                && ! $product->isDirty(['width_cm', 'depth_cm', 'height_cm']);

            $dimensions = \App\Services\FurnitureDimensionDefaults::resolve(
                (string) $product->name,
                (string) $product->category,
                [
                    'width_cm' => $product->width_cm,
                    'depth_cm' => $product->depth_cm,
                    'height_cm' => $product->height_cm,
                ],
            );

            $product->forceFill($dimensions);
            if ($preserveEstimate) {
                $product->dimensions_estimated = true;
            }
        });
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

    public function recommendationType(): string
    {
        $category = strtolower($this->category);

        foreach (['Door', 'Table', 'Chair', 'Bed', 'Cabinet'] as $type) {
            if (str_contains($category, strtolower($type))) {
                return $type;
            }
        }

        return $this->category;
    }

    public static function recommendationTypes(): Collection
    {
        return static::where('status', 'active')->orderBy('category')->get()
            ->map(fn (self $product) => $product->recommendationType())
            ->unique()->sort()->values();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
