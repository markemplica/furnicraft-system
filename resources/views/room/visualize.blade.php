@extends('layouts.app')
@section('title','Room comparison preview')
@section('content')
@php
    $selectedItem = $recommendation?->items->firstWhere('furniture_product_id', $selectedProduct?->id);
@endphp
<div class="room-compare-layout">
    <aside class="room-compare-story">
        <p class="section-kicker">Your room, reimagined</p>
        <h1>See <span data-preview-name>{{ $selectedProduct?->name ?? 'furniture' }}</span> in your space.</h1>
        <p class="room-compare-intro">FurniCraft automatically positions and scales the selected furniture using the clear area detected in the room photo and its catalog dimensions.</p>

        <section class="room-source" aria-labelledby="image-used-heading">
            <h2 id="image-used-heading" class="room-label">Image used</h2>
            <div class="room-source-card">
                <img src="{{ route('room-images.original', $roomImage) }}" alt="Uploaded room thumbnail">
                <div><span>Original room</span><strong>Room {{ $roomImage->id }}</strong><small>{{ $roomImage->room_width_m ?: '?' }} × {{ $roomImage->room_depth_m ?: '?' }} m</small></div>
            </div>
        </section>

        <ol class="comparison-steps">
            <li><span>1</span><div><strong>Before</strong><small>Your uploaded room remains the base scene.</small></div></li>
            <li><span>2</span><div><strong>After</strong><small><span data-preview-name>{{ $selectedProduct?->name ?? 'The furniture' }}</span> is placed using its catalog image and dimensions.</small></div></li>
        </ol>

        @if($selectedProduct)
        <article class="preview-product-card" data-preview-product-card>
            <img src="{{ $selectedProduct->image_url }}" alt="{{ $selectedProduct->name }}" data-preview-card-image>
            <div>
                <span class="room-label">{{ $recommendation ? 'Recommended product' : 'Selected product' }}</span>
                <strong data-preview-name>{{ $selectedProduct->name }}</strong>
                <small><span data-preview-score @if(!$selectedItem) hidden @endif>{{ $selectedItem ? number_format($selectedItem->total_score, 1).'% match · ' : '' }}</span>PHP <span data-preview-price>{{ number_format($selectedProduct->price, 2) }}</span></small>
                <a href="{{ route('catalog.show', $selectedProduct) }}" data-preview-product-link>View product details</a>
            </div>
        </article>
        @endif
    </aside>

    <section class="comparison-panel" aria-labelledby="comparison-heading">
        <header class="comparison-panel-head">
            <div><span class="room-label">2D placement preview</span><h2 id="comparison-heading">Original room / FurniCraft result</h2></div>
            <span class="preview-status"><i aria-hidden="true"></i> Planning preview</span>
        </header>

        @php($analysisRunning = in_array($roomImage->analysis_status, ['queued', 'processing', 'retrying'], true))
        @php($analysisLabel = ['completed' => 'AI room analysis complete', 'queued' => 'Room analysis queued', 'processing' => 'Analyzing room image', 'retrying' => 'AI temporarily unavailable · retrying', 'failed' => 'AI room analysis needs attention'][$roomImage->analysis_status] ?? 'Safe local room guidance')
        <div class="alert {{ $roomImage->analysis_status === 'failed' ? 'alert-warning' : 'alert-light' }} fc-alert" role="status" aria-live="polite" data-room-analysis-watch="{{ route('room.analysis-status', $roomImage) }}" data-room-analysis-current="{{ $roomImage->analysis_status }}">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div><strong data-room-analysis-label>{{ $analysisLabel }}</strong><div class="small text-muted" data-room-analysis-message>{{ $roomImage->analysis_error ?: ($analysisRunning ? 'You may keep this page open; the final analysis will appear automatically.' : 'Placement confidence and detected room zones are shown below.') }}</div></div>
                @unless($analysisRunning || auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('room.reanalyze', $roomImage) }}">@csrf<button class="btn btn-sm btn-outline-primary" type="submit">Re-analyze room</button></form>
                @endunless
            </div>
        </div>

        @if($recommendation && $selectedProduct && $roomImage->gemini_consent_at && $geminiPreviewUnavailable)
            @php($previewRunning = $previewGeneration && in_array($previewGeneration->status, ['queued', 'processing', 'retrying'], true))
            <div class="alert {{ $previewGeneration && in_array($previewGeneration->status, ['failed', 'fallback'], true) ? 'alert-warning' : 'alert-info' }} fc-alert" role="status" aria-live="polite" data-preview-watch="{{ route('room.preview-status', [$roomImage, $selectedProduct->id]) }}" data-preview-status="{{ $previewGeneration?->status ?? 'queued' }}">
                <strong data-preview-label>{{ $previewRunning ? 'Preparing your furnished-room image in the background' : (($previewGeneration?->status === 'failed' || $previewGeneration?->status === 'fallback') ? 'Showing local 2D placement preview' : 'Preparing your furnished-room image in the background') }}</strong>
                <span data-preview-message>{{ $previewGeneration?->message ?: 'You can keep using this page. The image will appear automatically when ready.' }}</span>
            </div>
        @endif

        <div class="placement-status" aria-live="polite">
            <div><span class="room-label">Selected furniture · planning preview</span><strong data-placement-status>{{ data_get($selectedPlacement, 'renderable', true) ? (data_get($selectedPlacement, 'validation_status') === 'supported' ? 'AI placement supported' : 'Placement requires verification') : 'No validated placement available' }}</strong></div>
            <span data-placement-dimensions></span>
        </div>

        <script type="application/json" data-initial-placement-quad>@json(data_get($selectedPlacement, 'quad_percent'))</script>

        <div class="room-comparison" data-room-comparison data-room-width="{{ $roomImage->room_width_m }}" data-room-depth="{{ $roomImage->room_depth_m }}" data-product-width="{{ $selectedProduct?->width_cm }}" data-product-depth="{{ $selectedProduct?->depth_cm }}" data-product-height="{{ $selectedProduct?->height_cm }}" data-product-category="{{ $selectedProduct?->category }}" data-product-type="{{ $selectedProduct?->recommendationType() }}" data-placement-ai="{{ in_array(data_get($selectedPlacement,'source'), ['candidate-zone','scene-opening'], true) ? 'true' : 'false' }}" data-generated-result="{{ $generatedWithGemini ? 'true' : 'false' }}" data-gemini-unavailable="{{ $geminiPreviewUnavailable ? 'true' : 'false' }}" data-placement-type="{{ data_get($roomImage->analysis,'analyzed_furniture_type') }}" data-placement-x="{{ data_get($selectedPlacement,'x_percent',50) }}" data-placement-y="{{ data_get($selectedPlacement,'y_percent',86) }}" data-placement-width="{{ data_get($selectedPlacement,'max_width_percent',72) }}" data-placement-rotation="{{ data_get($selectedPlacement,'rotation_deg',0) }}" data-placement-perspective="{{ data_get($selectedPlacement,'perspective_deg',0) }}" data-placement-flip="{{ data_get($selectedPlacement,'flip',1) }}" data-placement-confidence="{{ data_get($selectedPlacement,'confidence','low') }}" data-placement-source="{{ data_get($selectedPlacement,'source','type-fallback') }}" data-placement-reason="{{ data_get($selectedPlacement,'reason') }}" data-placement-needs-confirmation="{{ data_get($selectedPlacement,'needs_confirmation',true) ? 'true' : 'false' }}" data-placement-renderable="{{ data_get($selectedPlacement,'renderable',true) ? 'true' : 'false' }}" data-placement-validation="{{ data_get($selectedPlacement,'validation_status','advisory') }}" data-placement-measurement="{{ data_get($selectedPlacement,'measurement_status','measurements-missing') }}" data-placement-basis="{{ data_get($selectedPlacement,'fit_basis','image-estimate') }}" role="slider" tabindex="0" aria-label="Drag left or right to compare the original room and FurniCraft planning preview" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50" style="--split:50%; --product-width:40%; --product-x:50%; --product-y:84%; --product-rotation:0deg; --product-perspective:0deg; --product-flip:1;">
            <img class="comparison-room-base" src="{{ route('room-images.original', $roomImage) }}" alt="Original uploaded room">
            <div class="comparison-result" data-comparison-result aria-hidden="true">
                <img class="comparison-room-result" src="{{ $generatedPreview && $selectedProduct ? route('room-images.preview', [$roomImage, $selectedProduct->id]) : route('room-images.original', $roomImage) }}" alt="">
                @if(!$generatedPreview && $selectedProduct)<img class="comparison-product" src="{{ $selectedProduct->image_url }}" alt="" draggable="false" data-comparison-product>@endif
                @if(!$generatedPreview && $selectedProduct)<canvas class="comparison-product-warp" data-comparison-product-warp hidden></canvas>@endif
                @if(!$generatedPreview)<div class="comparison-result-wash"></div>@endif
            </div>
            <span class="comparison-tag comparison-tag-original">Original</span>
            <span class="comparison-tag comparison-tag-result">{{ $generatedWithGemini ? 'Gemini result' : 'Result' }}</span>
            <span class="comparison-divider" aria-hidden="true"></span>
        </div>

        <p class="automatic-placement-note"><strong>Placement validation.</strong> <span data-placement-guidance>{{ data_get($selectedPlacement,'reason','This is a 2D planning estimate.') }}</span> <button class="btn btn-sm btn-outline-primary ms-2" type="button" data-placement-confirm hidden>Choose suggested position</button></p>

        <footer class="comparison-note">
            <strong>Preview, not a measurement guarantee.</strong>
            <span>The result is a 2D planning aid. Verify actual room and product dimensions with Lance Furniture Shop before deciding.</span>
        </footer>

        <details class="room-analysis-details">
            <summary>View room analysis</summary>
            <dl><dt>Detected room</dt><dd>{{ data_get($roomImage->analysis,'room_type','Unspecified room') }}</dd><dt>Summary</dt><dd>{{ data_get($roomImage->analysis,'summary') }}</dd><dt>Layout</dt><dd>{{ data_get($roomImage->analysis,'layout') }}</dd><dt>Suggested placement</dt><dd>{{ data_get($roomImage->analysis,'placement_reason','Centered on the visible floor area.') }}</dd><dt>Lighting</dt><dd>{{ data_get($roomImage->analysis,'lighting') }}</dd><dt>Limits</dt><dd>{{ data_get($roomImage->analysis,'constraints') }}</dd></dl>
        </details>

        <div class="d-flex justify-content-end mt-3">
            @if(auth()->user()->isAdmin())
                <a class="btn btn-outline-primary" href="{{ route('admin.room-placement-evaluation') }}">Back to accuracy review</a>
            @else
                <a class="btn btn-outline-primary" href="{{ $recommendation ? route('recommendations.show', $recommendation) : route('preferences.edit', ['room' => $roomImage->id]).'#recommendation-form' }}">{{ $recommendation ? 'Back to recommendations' : 'Use room for recommendations' }}</a>
            @endif
        </div>
        <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
            @if($roomImage->gemini_consent_at && !auth()->user()->isAdmin())<form method="POST" action="{{ route('room.ai-consent.revoke', $roomImage) }}" onsubmit="return confirm('Stop future AI processing and delete generated previews for this photo?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-secondary" type="submit">Turn off AI for this photo</button></form>@endif
            @unless(auth()->user()->isAdmin())<form method="POST" action="{{ route('room.destroy', $roomImage) }}" onsubmit="return confirm('Delete this room photo and its generated previews?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Delete room photo</button></form>@endunless
        </div>
    </section>
</div>
@endsection
