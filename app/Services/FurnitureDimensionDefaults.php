<?php

namespace App\Services;

class FurnitureDimensionDefaults
{
    /**
     * Return width × depth × height in centimeters, filling only missing values
     * with a standard planning size for the product type.
     */
    public static function resolve(string $name, string $category, array $dimensions): array
    {
        $type = strtolower($name.' '.$category);
        $standard = match (true) {
            str_contains($type, 'double deck'), str_contains($type, 'bunk') =>
                (str_contains($type, 'taiwan') ? [135, 200, 170] : [150, 200, 170]),
            str_contains($type, 'door') =>
                (str_contains($type, 'double door') || str_contains($type, 'channel') ? [160, 4, 210] : [90, 4, 210]),
            str_contains($type, 'bed') =>
                (str_contains($type, 'single') ? [105, 200, 100] : (str_contains($type, 'double') ? [135, 200, 105] : [150, 200, 110])),
            str_contains($type, 'sofa'), str_contains($type, 'couch'), str_contains($type, 'sala'), str_contains($type, 'sectional') => [225, 90, 92],
            str_contains($type, 'wardrobe'), str_contains($type, 'cabinet'), str_contains($type, 'dresser'), str_contains($type, 'shelf'), str_contains($type, 'storage') => [100, 45, 180],
            str_contains($type, 'dining table') => [180, 90, 76],
            str_contains($type, 'dining chair') => [45, 50, 90],
            str_contains($type, 'desk') => [120, 60, 75],
            str_contains($type, 'table') => [105, 60, 75],
            str_contains($type, 'bench') => [120, 45, 45],
            str_contains($type, 'chair'), str_contains($type, 'stool') => [50, 55, 90],
            default => [100, 60, 90],
        };

        $resolved = [];
        $estimated = false;
        foreach (['width_cm', 'depth_cm', 'height_cm'] as $index => $key) {
            $value = $dimensions[$key] ?? null;
            if (! is_numeric($value) || (float) $value <= 0) {
                $value = $standard[$index];
                $estimated = true;
            }
            $resolved[$key] = $value;
        }

        $resolved['dimensions_estimated'] = $estimated;

        return $resolved;
    }
}