# Room Preview v2 backend

The v2 flow is additive and independent of the existing room-preview UI and routes. It is disabled by default.

## Enable for a staged rollout

Set these values in the local or deployment environment, then clear cached config:

```dotenv
ROOM_PREVIEW_V2_ENABLED=true
ROOM_PREVIEW_V2_ROLLOUT_PERCENT=5
ROOM_PREVIEW_PIPELINE_VERSION=v2
ROOM_IMAGE_RETENTION_DAYS=30
```

Run `php artisan migrate`, clear cached configuration with `php artisan config:clear`, and start a queue worker (`php artisan queue:work --tries=2 --timeout=540`). Start at 0-5%, inspect queue and AI telemetry, then increase rollout gradually. To stop immediately set `ROOM_PREVIEW_V2_ENABLED=false` and clear config cache.

The authenticated POST endpoint accepts only `furniture_id`: `/room/{roomImage}/preview-v2`. It returns HTTP 202 with a status URL. Poll that URL; completed results include a short-lived signed artifact URL. The request never accepts prompts, client image bytes, product measurements, or placement coordinates. Owner checks, active catalog state, complete product dimensions, and the 10-per-hour route limit are enforced server-side.

## Runtime requirements and behavior

GD is required for image downscaling, image-quality sampling, deterministic PNG compositing, and pixel comparison. EXIF rotation is applied to JPEG uploads when GD and EXIF support are available. Generated image metadata is stripped. Without GD, v2 reports a placement-only rough estimate and does not pretend that a rendered preview is ready. Keep v2 disabled until GD is enabled and verified in the PHP runtime used by both web and queue workers.

AI processing requires explicit stored photo consent. Analysis cache is keyed by normalized image SHA-256 and pipeline version; per-preview cache keys also include product ID and product update time. AI image output must pass the five-score verifier and the outside-placement pixel guard; rejected output falls back to a deterministic catalog image composite when GD and a usable catalog image are available.

Room images and generated artifacts are private. The normal retention cleanup removes room artifacts and stale shared analysis cache rows; the default is 30 days. API keys are sent in the Gemini authentication header, not in query strings. Never put customer photos or API keys in benchmark fixtures or logs.

## Benchmark

`php artisan roompreview:benchmark` expects 8 to 10 non-sensitive room fixtures matching `tests/Fixtures/room-*.png`. You can specify a different fixture directory with `--path=`. The current command is intentionally offline: it measures preprocessing time, output size, duplicate hashes, and failures. It does not make provider requests, estimate live Gemini cost, or claim scale, fidelity, or verification scores without annotations. Those figures must be collected from a properly labeled set before publishing performance claims.

## Rollback

Set `ROOM_PREVIEW_V2_ENABLED=false`, clear config cache, and keep the old routes in use. The migrations are additive; roll them back with `php artisan migrate:rollback --step=2` only if no v2 data must be retained. That rollback drops only the new analysis table and v2 generation columns.
