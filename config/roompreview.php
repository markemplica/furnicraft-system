<?php

return [
    'use_v2_pipeline' => (bool) env('ROOM_PREVIEW_V2_ENABLED', false),
    'rollout_percent' => max(0, min(100, (int) env('ROOM_PREVIEW_V2_ROLLOUT_PERCENT', 0))),
    'pipeline_version' => (string) env('ROOM_PREVIEW_PIPELINE_VERSION', 'v2'),
    'max_dimension' => 1600,
    'min_dimension' => 600,
    'max_upload_bytes' => 8 * 1024 * 1024,
    'max_pixels' => 40000000,
    'jpeg_quality' => 86,
    'blur_variance_min' => 18.0,
    'brightness_min' => 0.08,
    'brightness_max' => 0.96,
    'pixel_difference_max' => 0.05,
    'verification_min_score' => 0.75,
    'max_generation_attempts' => 3,
    'max_provider_calls' => 8,
    'job_timeout_seconds' => 540,
    'unique_for_seconds' => 600,
    'retention_days' => max(1, (int) env('ROOM_IMAGE_RETENTION_DAYS', 30)),
    'signed_url_minutes' => 5,
    'benchmark_fixture_glob' => env('ROOM_PREVIEW_BENCHMARK_GLOB', 'tests/Fixtures/room-*.png'),
];
