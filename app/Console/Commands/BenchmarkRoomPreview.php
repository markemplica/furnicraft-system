<?php

namespace App\Console\Commands;

use App\Services\RoomPreviewRasterService;
use Illuminate\Console\Command;

class BenchmarkRoomPreview extends Command
{
    protected $signature = 'roompreview:benchmark {--path= : Directory with 8 to 10 non-sensitive sample room images}';
    protected $description = 'Measure offline room-preview image preprocessing against a fixture set';

    public function handle(RoomPreviewRasterService $raster): int
    {
        $pattern = $this->option('path')
            ? rtrim((string) $this->option('path'), '\\/') . DIRECTORY_SEPARATOR . '*'
            : base_path((string) config('roompreview.benchmark_fixture_glob'));
        $files = collect(glob($pattern) ?: [])->filter(fn ($file) => is_file($file))->values();
        if ($files->count() < 8 || $files->count() > 10) {
            $this->error('Benchmark needs 8 to 10 sample images. Current count: '.$files->count().'. Add non-customer fixtures matching tests/Fixtures/room-*.png (or specify another directory with --path).');
            return self::FAILURE;
        }

        $times = []; $hashes = []; $failures = 0; $sizes = [];
        foreach ($files as $file) {
            $started = hrtime(true);
            try {
                $bytes = file_get_contents($file);
                if (! is_string($bytes)) throw new \RuntimeException('read_failed');
                $hashes[] = hash('sha256', $bytes);
                $result = $raster->normalize($bytes);
                $sizes[] = strlen($result['bytes']);
            } catch (\Throwable $exception) {
                $failures++;
            } finally {
                $times[] = (hrtime(true) - $started) / 1_000_000;
            }
        }
        sort($times);
        $count = count($times);
        $p95 = $times[max(0, (int) ceil($count * 0.95) - 1)] ?? 0;
        $unique = count(array_unique($hashes));
        $report = [
            'mode' => 'offline_preprocessing_only',
            'fixture_count' => $files->count(),
            'successful_images' => $count - $failures,
            'average_preprocessing_ms' => round(array_sum($times) / max(1, $count), 2),
            'p95_preprocessing_ms' => round($p95, 2),
            'api_calls' => 0,
            'estimated_api_cost' => 'not measured; offline mode made no provider requests',
            'duplicate_image_cache_hit_rate' => count($hashes) ? round((count($hashes) - $unique) / count($hashes), 4) : null,
            'failure_rate' => round($failures / max(1, $count), 4),
            'average_output_bytes' => $sizes ? (int) round(array_sum($sizes) / count($sizes)) : null,
            'known_object_scale_error' => 'not measured; fixture annotations are not configured',
            'verification_pass_rate' => 'not measured; offline mode does not call Gemini',
            'catalog_fidelity' => 'not measured; offline mode does not call Gemini',
            'gd_available' => $raster->available(),
        ];
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
