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

        if ($measurement['status'] === 'does-not-fit') {
            return $this->blockedPlacement(
                $preferredSurface,
                'does-not-fit',
                'The catalog dimensions exceed the usable room measurements. Choose a smaller product or verify the room measurements.',
                $measurement,
            );
        }

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
            $typeScore = $suitableTypes->isEmpty() || $suitableTypes->contains($type) ? 25 : 0;
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
            $quad = $this->quad($zone['quad_percent'] ?? $zone['_quad_percent'] ?? null);
            $renderWidth = $this->renderWidth($product, $roomWidth, $zone);
            $needsConfirmation = $score < 70 || $measurement['status'] !== 'measured-fit';

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
                'validation_status' => $needsConfirmation ? 'advisory' : 'supported',
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
        return match (true) {
            str_contains($type, 'door') => 'opening',
            str_contains($type, 'cabinet'), str_contains($type, 'bed') => 'wall-floor',
            default => 'floor',
        };
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
        $typeAllowed = $suitableTypes->isEmpty() || $suitableTypes->contains(fn ($candidate) => $candidate === $type || str_contains($candidate, $type) || str_contains($type, $candidate));

        if (! $typeAllowed) {
            return false;
        }

        if (str_contains($type, 'door')) {
            return $surface === 'opening' && $this->quad($zone['quad_percent'] ?? $zone['_quad_percent'] ?? null) !== null;
        }

        if ($surface === 'opening') {
            return false;
        }

        return $surface === $preferredSurface || ($preferredSurface === 'floor' && $surface === 'wall-floor');
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

        if (! str_contains($type, 'door')) {
            $regions = $regions->concat($analysis['openings'] ?? []);
        }

        return $regions->contains(function ($region) use ($footprint) {
            $bounds = $this->polygonBounds(is_array($region) ? ($region['polygon'] ?? []) : []);

            return $bounds && $this->boxesOverlap($footprint, $bounds);
        });
    }

    private function renderWidth(FurnitureProduct $product, ?float $roomWidth, array $zone): float
    {
        $capacity = $this->clamp($zone['max_width_percent'] ?? 65, 15, 90);
        if (str_contains(strtolower($product->recommendationType()), 'door')) {
            return $capacity;
        }
        if ($roomWidth && $product->width_cm) {
            $measured = ((float) $product->width_cm / ($roomWidth * 100)) * 100;

            return $this->clamp(min($capacity * .96, $measured * 1.08), 10, 90);
        }

        return $this->clamp($capacity * .78, 12, 90);
    }

    private function openingZones(array $analysis): array
    {
        return collect($analysis['openings'] ?? [])->map(function ($opening) {
            $points = collect(is_array($opening) ? ($opening['polygon'] ?? []) : [])
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
                'max_width_percent' => max(15, $maximumX - $minimumX),
                'rotation_deg' => 0,
                'perspective_deg' => 0,
                'flip' => false,
                'suitable_types' => ['door'],
                'clearance_score' => 92,
                'occlusion_score' => 5,
                'confidence' => $analysis['scene_confidence'] ?? 'medium',
                'reason' => 'Aligned to the detected '.trim((string) ($opening['label'] ?? 'door opening')).' frame.',
                '_source' => 'scene-opening',
                '_quad_percent' => $this->orderedQuad($points->values()->all()),
            ];
        })->filter()->values()->all();
    }

    private function fallbackPlacement(array $analysis, string $type, string $surface, array $measurement): array
    {
        $defaults = match (true) {
            str_contains($type, 'door') => [88, 76, 24],
            str_contains($type, 'cabinet') => [70, 88, 36],
            str_contains($type, 'bed') => [36, 88, 58],
            str_contains($type, 'sala') => [64, 88, 68],
            str_contains($type, 'chair') => [42, 87, 28],
            default => [56, 86, 48],
        };

        $hasLegacyAnalysis = filled($analysis['placement_x_percent'] ?? null)
            && filled($analysis['placement_y_percent'] ?? null);

        return [
            'x_percent' => $this->clamp($analysis['placement_x_percent'] ?? $defaults[0], 5, 95),
            'y_percent' => $this->clamp($analysis['placement_y_percent'] ?? $defaults[1], 30, 96),
            'max_width_percent' => $this->clamp($analysis['placement_width_percent'] ?? $defaults[2], 15, 90),
            'rotation_deg' => 0,
            'perspective_deg' => 0,
            'flip' => 1,
            'quad_percent' => null,
            'surface' => $surface,
            'confidence' => $hasLegacyAnalysis ? (string) ($analysis['placement_confidence'] ?? 'low') : 'low',
            'score' => $hasLegacyAnalysis ? 45 : 30,
            'reason' => $hasLegacyAnalysis
                ? (string) ($analysis['placement_reason'] ?? 'Legacy room analysis; confirm the suggested position.')
                : (str_contains($type, 'door')
                    ? 'AI doorway detection is temporarily unavailable. A doorway-proportioned fallback was applied; confirm it visually.'
                    : 'No room zones were detected. Confirm a suitable placement point in the image.'),
            'needs_confirmation' => true,
            'source' => $hasLegacyAnalysis ? 'legacy-analysis' : 'type-fallback',
            'renderable' => true,
            'validation_status' => 'advisory',
            'measurement_status' => $measurement['status'],
            'fit_basis' => $hasLegacyAnalysis ? 'legacy-image-estimate' : 'furniture-type-fallback',
            'validation_messages' => $measurement['messages'],
        ];
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
