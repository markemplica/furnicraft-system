<?php

namespace App\Services;

use App\Models\FurnitureProduct;

class FurniturePlacementService
{
    public function forProduct(array $analysis, FurnitureProduct $product, ?float $roomWidth = null, ?float $roomDepth = null): array
    {
        $type = strtolower($product->recommendationType());
        $preferredSurface = $this->preferredSurface($type);
        $measurement = $this->measurementAssessment($product, $roomWidth, $roomDepth, $type);


        $zones = collect($analysis['candidate_zones'] ?? [])->filter(fn ($zone) => is_array($zone));

        if (str_contains($type, 'door')) {
            $zones = $zones->concat($this->openingZones($analysis));
        }

        $ranked = $zones->filter(fn (array $zone) => $this->zoneIsCompatible($zone, $type, $preferredSurface))
            ->map(function (array $zone) use ($analysis, $type, $preferredSurface, $product, $roomWidth, $roomDepth) {
            $surface = strtolower((string) ($zone['surface'] ?? 'floor'));
            $suitableTypes = collect($zone['suitable_types'] ?? [])->map(fn ($value) => strtolower((string) $value));
            $confidence = $this->confidenceValue($zone['confidence'] ?? 0.45);
            $clearance = $this->percentageValue($zone['clearance_score'] ?? 60);
            $occlusion = $this->percentageValue($zone['occlusion_score'] ?? 20);
            $surfaceScore = $surface === $preferredSurface ? 35 : ($preferredSurface === 'floor' && $surface === 'wall-floor' ? 30 : 0);
            $typeScore = $this->zoneSupportsType($suitableTypes->all(), $type) ? 25 : 0;
            $dimensionScore = $this->dimensionScore($product, $roomWidth, $roomDepth, $zone);
            $validFloorAnchor = $this->validFloorAnchor($analysis, $zone, $surface);
            $collision = $this->collidesWithExcludedRegion($analysis, $zone, $product, $roomWidth, $roomDepth, $type);
            $score = ($confidence * 20) + $surfaceScore + $typeScore + ($clearance * .12) + ((100 - $occlusion) * .05) + $dimensionScore;

            return [
                'zone' => $zone,
                'score' => round(min(100, $score), 1),
                'valid' => $validFloorAnchor && ! $collision && $clearance >= 35 && $occlusion <= 65,
            ];
        })->filter(fn (array $candidate) => $candidate['valid'])->sortByDesc('score')->values();

        if ($ranked->isNotEmpty()) {
            $best = $ranked->first();
            $zone = $best['zone'];
            $score = (float) $best['score'];
            $quad = str_contains($type, 'door')
                ? $this->quad($zone['door_fit_quad_percent'] ?? $zone['quad_percent'] ?? $zone['_quad_percent'] ?? null)
                : null;
            $renderWidth = $this->renderWidth($product, $roomWidth, $zone);
            $needsConfirmation = false;

            return [
                'x_percent' => $this->clamp($zone['anchor_x_percent'] ?? 50, 5, 95),
                'y_percent' => $this->clamp($zone['anchor_y_percent'] ?? 86, 30, 96),
                'max_width_percent' => $renderWidth,
                'rotation_deg' => $this->clamp($zone['rotation_deg'] ?? 0, -18, 18),
                'perspective_deg' => $this->clamp($zone['perspective_deg'] ?? 0, -24, 24),
                'flip' => filter_var($zone['flip'] ?? false, FILTER_VALIDATE_BOOLEAN) ? -1 : 1,
                'quad_percent' => $quad,
                'surface' => $zone['surface'] ?? $preferredSurface,
                'confidence' => $score >= 78 ? 'high' : ($score >= 58 ? 'medium' : 'low'),
                'score' => $score,
                'reason' => trim((string) ($zone['reason'] ?? 'Best clear zone for the selected furniture type.')),
                'needs_confirmation' => $needsConfirmation,
                'source' => $zone['_source'] ?? 'candidate-zone',
                'renderable' => true,
                'validation_status' => ($score < 70 || $measurement['status'] !== 'measured-fit') ? 'advisory' : 'supported',
                'measurement_status' => $measurement['status'],
                'fit_basis' => $measurement['status'] === 'measured-fit' ? 'detected-zone-and-room-measurements' : 'detected-zone-only',
                'validation_messages' => $measurement['messages'],
            ];
        }

        if (str_contains($type, 'door')) {
            return $this->blockedPlacement(
                $preferredSurface,
                'no-opening',
                'No reliable doorway opening was detected. FurniCraft will not place a door at an invented position; re-analyze a clearer photo that shows the full frame.',
                $measurement,
            );
        }

        return $this->fallbackPlacement($analysis, $type, $preferredSurface, $measurement);
    }

    private function preferredSurface(string $type): string
    {
        return match ($this->furnitureFamily($type)) {
            'door' => 'opening',
            'bed', 'storage' => 'wall-floor',
            default => 'floor',
        };
    }

    private function furnitureFamily(string $type): string
    {
        $type = strtolower(trim($type));

        return match (true) {
            preg_match('/door|doorway|entry|entrance/', $type) === 1 => 'door',
            preg_match('/dining/', $type) === 1 => 'dining',
            preg_match('/bed|bunk|double.deck/', $type) === 1 => 'bed',
            preg_match('/cabinet|wardrobe|dresser|shelf|storage/', $type) === 1 => 'storage',
            preg_match('/sala|sofa|couch|sectional|living.room/', $type) === 1 => 'living-set',
            preg_match('/chair|stool|bench|seating/', $type) === 1 => 'seating',
            preg_match('/table|desk/', $type) === 1 => 'table',
            default => 'floor',
        };
    }

    private function zoneSupportsType(array $suitableTypes, string $type): bool
    {
        if ($suitableTypes === []) {
            return true;
        }

        $family = $this->furnitureFamily($type);

        foreach ($suitableTypes as $candidate) {
            $candidate = strtolower(trim((string) $candidate));
            if ($candidate === '' || $this->furnitureFamily($candidate) === $family) {
                return true;
            }

            if ($family === 'living-set' && preg_match('/living|lounge|receiving/', $candidate)) {
                return true;
            }
            if ($family === 'dining' && preg_match('/table|chair|seating/', $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function dimensionScore(FurnitureProduct $product, ?float $roomWidth, ?float $roomDepth, array $zone): float
    {
        if (! $roomWidth || ! $roomDepth || ! $product->width_cm || ! $product->depth_cm) {
            return 4;
        }

        $fitsRoom = ((float) $product->width_cm / 100) <= $roomWidth * .85
            && ((float) $product->depth_cm / 100) <= $roomDepth * .85;
        $requiredWidth = ((float) $product->width_cm / ($roomWidth * 100)) * 100;
        $fitsZone = $requiredWidth <= (float) ($zone['max_width_percent'] ?? 90);

        return $fitsRoom && $fitsZone ? 8 : 0;
    }

    private function measurementAssessment(FurnitureProduct $product, ?float $roomWidth, ?float $roomDepth, string $type): array
    {
        if (! $roomWidth || ! $roomDepth || ! $product->width_cm || ! $product->depth_cm) {
            return ['status' => 'measurements-missing', 'messages' => ['Add actual room measurements to validate physical scale.']];
        }

        if (str_contains($type, 'door')) {
            return ['status' => 'opening-measurements-required', 'messages' => ['Room dimensions are known, but doorway width and height must still be measured before purchase or installation.']];
        }

        $fits = ((float) $product->width_cm / 100) <= $roomWidth * .85
            && ((float) $product->depth_cm / 100) <= $roomDepth * .85;

        return $fits
            ? ['status' => 'measured-fit', 'messages' => ['Catalog dimensions fit within 85% of the supplied room width and depth.']]
            : ['status' => 'does-not-fit', 'messages' => ['Catalog dimensions exceed the usable supplied room measurements.']];
    }

    private function zoneIsCompatible(array $zone, string $type, string $preferredSurface): bool
    {
        $surface = strtolower(trim((string) ($zone['surface'] ?? 'floor')));
        $suitableTypes = collect($zone['suitable_types'] ?? [])->map(fn ($value) => strtolower(trim((string) $value)));
        $typeAllowed = $this->zoneSupportsType($suitableTypes->all(), $type);

        if (! $typeAllowed) {
            return false;
        }

        if (str_contains($type, 'door')) {
            return ! $this->isWindowZone($zone)
                && $surface === 'opening'
                && $this->quad($zone['door_fit_quad_percent'] ?? $zone['quad_percent'] ?? $zone['_quad_percent'] ?? null) !== null;
        }

        if ($surface === 'opening') {
            return false;
        }

        if ($this->furnitureFamily($type) === 'living-set') {
            return in_array($surface, ['floor', 'wall-floor'], true);
        }

        return $surface === $preferredSurface || ($preferredSurface === 'floor' && $surface === 'wall-floor');
    }

    private function isWindowZone(array $zone): bool
    {
        $description = strtolower(implode(' ', array_filter([
            $zone['label'] ?? null,
            $zone['opening_label'] ?? null,
            $zone['region_label'] ?? null,
            $zone['reason'] ?? null,
        ], fn ($value) => is_string($value) && trim($value) !== '')));

        return (bool) preg_match('/window|skylight|clerestory|transom|fenestration/', $description);
    }
    private function validFloorAnchor(array $analysis, array $zone, string $surface): bool
    {
        if (! in_array($surface, ['floor', 'wall-floor'], true) || count($analysis['floor_polygon'] ?? []) < 3) {
            return true;
        }

        return $this->pointInPolygon(
            (float) ($zone['anchor_x_percent'] ?? 50),
            (float) ($zone['anchor_y_percent'] ?? 86),
            $analysis['floor_polygon'],
        );
    }

    private function collidesWithExcludedRegion(array $analysis, array $zone, FurnitureProduct $product, ?float $roomWidth, ?float $roomDepth, string $type): bool
    {
        $width = $this->renderWidth($product, $roomWidth, $zone);
        $depth = ($roomDepth && $product->depth_cm)
            ? $this->clamp(((float) $product->depth_cm / ($roomDepth * 100)) * 100, 6, 35)
            : max(8, $width * .3);
        $x = (float) ($zone['anchor_x_percent'] ?? 50);
        $y = (float) ($zone['anchor_y_percent'] ?? 86);
        $footprint = [$x - ($width / 2), $y - $depth, $x + ($width / 2), $y];
        $regions = collect($analysis['obstacles'] ?? []);

        if (str_contains($type, 'door')) {
            // A door leaf detected inside the doorway is the existing door being
            // replaced, not a separate obstacle that should force the preview
            // onto a window or block the doorway entirely.
            $regions = $regions->reject(fn ($region) => is_array($region)
                && preg_match('/door[_\s-]*leaf/i', (string) ($region['label'] ?? '')));
        } else {
            $regions = $regions->concat($analysis['openings'] ?? []);
        }

        return $regions->contains(function ($region) use ($footprint) {
            $bounds = $this->polygonBounds(is_array($region) ? ($region['polygon'] ?? []) : []);

            return $bounds && $this->boxesOverlap($footprint, $bounds);
        });
    }

    private function renderWidth(FurnitureProduct $product, ?float $roomWidth, array $zone): float
    {
        $capacity = $this->clamp($zone['max_width_percent'] ?? 65, 1, 90);
        if (str_contains(strtolower($product->recommendationType()), 'door')) {
            return $capacity;
        }

        $catalogWidth = is_numeric($product->width_cm) ? (float) $product->width_cm : null;
        $roomRelativeWidth = $catalogWidth && $roomWidth
            ? $catalogWidth / $roomWidth
            : ($catalogWidth ? $catalogWidth / 3.5 : null);

        if ($roomRelativeWidth !== null) {
            return $this->clamp(min($capacity * .96, $roomRelativeWidth), 1, 90);
        }

        return $this->clamp($capacity * .72, 1, 90);
    }

    private function openingZones(array $analysis): array
    {
        return collect($analysis['openings'] ?? [])->map(function ($opening) {
            $label = trim((string) ($opening['label'] ?? ''));
            if ($this->isWindowZone(['label' => $label]) || ! preg_match('/door|doorway|entrance|entry|passage|archway|closet/', strtolower($label))) {
                return null;
            }

            $points = collect(is_array($opening) ? ($opening['door_fit_quad_percent'] ?? $opening['polygon'] ?? []) : [])
                ->filter(fn ($point) => is_array($point) && isset($point[0], $point[1]) && is_numeric($point[0]) && is_numeric($point[1]));

            if ($points->count() < 3) {
                return null;
            }

            $minimumX = (float) $points->min(fn ($point) => $point[0]);
            $maximumX = (float) $points->max(fn ($point) => $point[0]);
            $maximumY = (float) $points->max(fn ($point) => $point[1]);

            return [
                'surface' => 'opening',
                'anchor_x_percent' => ($minimumX + $maximumX) / 2,
                'anchor_y_percent' => $maximumY,
                'max_width_percent' => max(1, $maximumX - $minimumX),
                'rotation_deg' => 0,
                'perspective_deg' => 0,
                'flip' => false,
                'suitable_types' => ['door'],
                'clearance_score' => 92,
                'occlusion_score' => 5,
                'confidence' => $analysis['scene_confidence'] ?? 'medium',
                'reason' => 'Aligned to the detected ' . $label . ' frame.',
                '_source' => 'scene-opening',
                '_quad_percent' => $this->orderedQuad($points->values()->all()),
            ];
        })->filter()->values()->all();
    }

    private function fallbackPlacement(array $analysis, string $type, string $surface, array $measurement): array
    {
        $defaults = match ($this->furnitureFamily($type)) {
            'door' => [88, 76, 24],
            'storage' => [70, 88, 36],
            'bed' => [36, 88, 58],
            'living-set' => [64, 88, 68],
            'seating' => [42, 87, 28],
            'dining' => [52, 87, 58],
            'table' => [56, 86, 42],
            default => [56, 86, 48],
        };

        $floorFit = $this->fallbackFloorFit($analysis['floor_polygon'] ?? []);

        return [
            'x_percent' => $floorFit['x'] ?? $defaults[0],
            'y_percent' => $floorFit['y'] ?? $defaults[1],
            'max_width_percent' => $floorFit
                ? $this->clamp(min($defaults[2], $floorFit['width'] * 0.78), 15, 90)
                : $this->clamp($defaults[2], 15, 90),            'rotation_deg' => 0,
            'perspective_deg' => 0,
            'flip' => 1,
            'quad_percent' => null,
            'surface' => $surface,
            'confidence' => 'low',
            'score' => $floorFit ? 38 : 30,
            'reason' => $floorFit
                ? 'Automatically fitted inside the visible floor area detected in this room image. This is a 2D planning estimate; verify actual dimensions.'
                : 'Automatically placed using a furniture-type layout estimate because no clear floor area was detected. Verify actual dimensions.',
            'needs_confirmation' => false,
            'source' => $floorFit ? 'image-floor-fallback' : 'type-fallback',
            'renderable' => true,
            'validation_status' => 'advisory',
            'measurement_status' => $measurement['status'],
            'fit_basis' => $floorFit ? 'detected-floor-area' : 'furniture-type-fallback',
            'validation_messages' => $measurement['messages'],
        ];
    }

    private function fallbackFloorFit(array $polygon): ?array
    {
        $points = collect($polygon)->filter(fn ($point) => is_array($point)
            && isset($point[0], $point[1])
            && is_numeric($point[0])
            && is_numeric($point[1]))->values()->all();

        if (count($points) < 3) {
            return null;
        }

        $minimumX = max(0, min(array_column($points, 0)));
        $maximumX = min(100, max(array_column($points, 0)));
        $minimumY = max(0, min(array_column($points, 1)));
        $maximumY = min(100, max(array_column($points, 1)));
        $centerX = ($minimumX + $maximumX) / 2;
        $targetY = $minimumY + (($maximumY - $minimumY) * 0.78);

        foreach ([0, -0.08, 0.08, -0.16, 0.16, -0.24, 0.24] as $yOffset) {
            $y = $targetY + (($maximumY - $minimumY) * $yOffset);
            foreach ([0, -0.1, 0.1, -0.2, 0.2, -0.3, 0.3] as $xOffset) {
                $x = $centerX + (($maximumX - $minimumX) * $xOffset);
                if ($this->pointInPolygon($x, $y, $points)) {
                    return [
                        'x' => $this->clamp($x, 5, 95),
                        'y' => $this->clamp($y, 30, 96),
                        'width' => max(15, $maximumX - $minimumX),
                    ];
                }
            }
        }

        return null;
    }
    private function blockedPlacement(string $surface, string $status, string $reason, array $measurement): array
    {
        return [
            'x_percent' => 50.0, 'y_percent' => 86.0, 'max_width_percent' => 30.0,
            'rotation_deg' => 0.0, 'perspective_deg' => 0.0, 'flip' => 1,
            'quad_percent' => null, 'surface' => $surface, 'confidence' => 'low', 'score' => 0.0,
            'reason' => $reason, 'needs_confirmation' => true, 'source' => 'placement-blocked',
            'renderable' => false, 'validation_status' => $status,
            'measurement_status' => $measurement['status'], 'fit_basis' => 'insufficient-validated-space',
            'validation_messages' => array_values(array_unique(array_merge([$reason], $measurement['messages']))),
        ];
    }

    private function polygonBounds(array $polygon): ?array
    {
        $points = collect($polygon)->filter(fn ($point) => is_array($point) && isset($point[0], $point[1]) && is_numeric($point[0]) && is_numeric($point[1]));
        if ($points->count() < 3) {
            return null;
        }

        return [(float) $points->min(fn ($point) => $point[0]), (float) $points->min(fn ($point) => $point[1]), (float) $points->max(fn ($point) => $point[0]), (float) $points->max(fn ($point) => $point[1])];
    }

    private function boxesOverlap(array $a, array $b): bool
    {
        return $a[0] < $b[2] && $a[2] > $b[0] && $a[1] < $b[3] && $a[3] > $b[1];
    }

    private function pointInPolygon(float $x, float $y, array $polygon): bool
    {
        $inside = false;
        $points = array_values(array_filter($polygon, fn ($point) => is_array($point) && isset($point[0], $point[1]) && is_numeric($point[0]) && is_numeric($point[1])));
        $count = count($points);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = [(float) $points[$i][0], (float) $points[$i][1]];
            [$xj, $yj] = [(float) $points[$j][0], (float) $points[$j][1]];
            $crosses = (($yi > $y) !== ($yj > $y)) && ($x < (($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 0.00001)) + $xi);
            if ($crosses) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private function confidenceValue(mixed $value): float
    {
        if (is_numeric($value)) {
            return min(1, max(0, (float) $value > 1 ? (float) $value / 100 : (float) $value));
        }

        return match (strtolower((string) $value)) {
            'high' => .9,
            'medium' => .68,
            default => .42,
        };
    }

    private function percentageValue(mixed $value): float
    {
        $number = is_numeric($value) ? (float) $value : 0;

        return min(100, max(0, $number <= 1 ? $number * 100 : $number));
    }

    private function clamp(mixed $value, float $minimum, float $maximum): float
    {
        $number = is_numeric($value) ? (float) $value : $minimum;

        return round(min($maximum, max($minimum, $number)), 1);
    }

    private function quad(mixed $quad): ?array
    {
        if (! is_array($quad) || count($quad) < 4) {
            return null;
        }

        return $this->orderedQuad($quad);
    }

    private function orderedQuad(array $points): ?array
    {
        $valid = collect($points)->filter(fn ($point) => is_array($point) && isset($point[0], $point[1]) && is_numeric($point[0]) && is_numeric($point[1]))
            ->map(fn ($point) => [$this->clamp($point[0], 0, 100), $this->clamp($point[1], 0, 100)])
            ->values();

        if ($valid->count() < 4) {
            return null;
        }

        $topLeft = $valid->sortBy(fn ($point) => $point[0] + $point[1])->first();
        $bottomRight = $valid->sortByDesc(fn ($point) => $point[0] + $point[1])->first();
        $topRight = $valid->sortByDesc(fn ($point) => $point[0] - $point[1])->first();
        $bottomLeft = $valid->sortBy(fn ($point) => $point[0] - $point[1])->first();

        return [$topLeft, $topRight, $bottomRight, $bottomLeft];
    }
}
