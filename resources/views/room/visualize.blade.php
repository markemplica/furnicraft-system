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
        <p class="room-compare-intro">Drag the furniture into place, adjust its size and angle, then compare the result with the room you uploaded.</p>

        <section class="room-source" aria-labelledby="image-used-heading">
            <h2 id="image-used-heading" class="room-label">Image used</h2>
            <div class="room-source-card">
                <img src="{{ asset('storage/'.$roomImage->image_path) }}" alt="Uploaded room thumbnail">
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
                <small><span data-preview-score @if(!$selectedItem) hidden @endif>{{ $selectedItem ? number_format($selectedItem->total_score, 1).' fit · ' : '' }}</span>PHP <span data-preview-price>{{ number_format($selectedProduct->price, 2) }}</span></small>
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

        <div class="comparison-picker">
            <div><label for="product-picker">Furniture to preview</label><small>Active Lance Furniture catalog items only</small></div>
            <select class="form-select" id="product-picker" data-product-picker>
                @foreach($products as $product)
                    @php($item = $recommendation?->items->firstWhere('furniture_product_id', $product->id))
                    <option value="{{ $product->id }}"
                        data-name="{{ $product->name }}"
                        data-image="{{ $product->image_url }}"
                        data-width="{{ $product->width_cm }}"
                        data-depth="{{ $product->depth_cm }}"
                        data-height="{{ $product->height_cm }}"
                        data-category="{{ $product->category }}"
                        data-price="{{ $product->price }}"
                        data-score="{{ $item?->total_score }}"
                        data-url="{{ route('catalog.show', $product) }}"
                        @selected($selectedProduct?->id === $product->id)>{{ $product->name }} · {{ $product->width_cm }} cm wide</option>
                @endforeach
            </select>
        </div>

        <div class="placement-status" aria-live="polite">
            <div><span class="room-label">Placement</span><strong data-placement-status>{{ $roomImage->room_width_m ? 'Dimension-based fit' : 'Approximate fit' }}</strong></div>
            <span data-placement-dimensions></span>
        </div>

        <div class="room-comparison" data-room-comparison data-room-width="{{ $roomImage->room_width_m }}" data-room-depth="{{ $roomImage->room_depth_m }}" tabindex="0" aria-label="Interactive furniture placement area. Drag to position the selected furniture, or use the arrow keys." style="--split:50%; --product-width:40%; --product-x:50%; --product-y:84%; --product-rotation:0deg; --product-flip:1;">
            <img class="comparison-room-base" src="{{ asset('storage/'.$roomImage->image_path) }}" alt="Original uploaded room">
            <div class="comparison-result" data-comparison-result aria-hidden="true">
                <img class="comparison-room-result" src="{{ asset('storage/'.$roomImage->image_path) }}" alt="">
                @if($selectedProduct)<img class="comparison-product" src="{{ $selectedProduct->image_url }}" alt="" draggable="false" data-comparison-product>@endif
                <div class="comparison-result-wash"></div>
            </div>
            <span class="comparison-tag comparison-tag-original">Original</span>
            <span class="comparison-tag comparison-tag-result">Result</span>
            <span class="comparison-divider" aria-hidden="true"></span>
        </div>

        <section class="placement-tools" aria-label="Furniture placement controls">
            <div class="placement-actions">
                <button class="btn btn-sm btn-dark" type="button" data-fit-room>Fit to room</button>
                <button class="btn btn-sm btn-outline-dark" type="button" data-rotate-product="-5" aria-label="Rotate furniture left">Rotate left</button>
                <button class="btn btn-sm btn-outline-dark" type="button" data-rotate-product="5" aria-label="Rotate furniture right">Rotate right</button>
                <button class="btn btn-sm btn-outline-dark" type="button" data-flip-product aria-pressed="false">Flip</button>
                <button class="btn btn-sm btn-outline-dark active" type="button" data-blend-product aria-pressed="true" aria-label="Blend furniture colors with the room">Blend</button>
            </div>
            <div class="placement-sliders">
                <label><span>Size <output data-scale-output>100%</output></span><input class="form-range" type="range" min="50" max="180" step="1" value="100" data-product-scale></label>
                <label><span>Angle <output data-rotation-output>0°</output></span><input class="form-range" type="range" min="-45" max="45" step="1" value="0" data-product-rotation></label>
            </div>
            <p>Drag anywhere in the room to position the furniture. Use the arrow keys for precise movement.</p>
        </section>

        <div class="comparison-controls" role="group" aria-label="Room comparison view">
            <button class="btn btn-sm btn-outline-dark" type="button" data-compare-mode="original">Show original</button>
            <button class="btn btn-sm btn-dark" type="button" data-compare-mode="split" aria-pressed="true">Split view</button>
            <button class="btn btn-sm btn-outline-dark" type="button" data-compare-mode="result">Show result</button>
            <label class="comparison-scrubber"><span>Before / after</span><input class="form-range" type="range" min="0" max="100" value="50" aria-label="Drag to compare the original room and FurniCraft result" data-compare-range></label>
        </div>

        <footer class="comparison-note">
            <strong>Preview, not a measurement guarantee.</strong>
            <span>The result is a 2D planning aid. Verify actual room and product dimensions with Lance Furniture Shop before deciding.</span>
        </footer>

        <details class="room-analysis-details">
            <summary>View room analysis</summary>
            <dl><dt>Summary</dt><dd>{{ data_get($roomImage->analysis,'summary') }}</dd><dt>Layout</dt><dd>{{ data_get($roomImage->analysis,'layout') }}</dd><dt>Lighting</dt><dd>{{ data_get($roomImage->analysis,'lighting') }}</dd><dt>Limits</dt><dd>{{ data_get($roomImage->analysis,'constraints') }}</dd></dl>
        </details>

        <div class="d-flex justify-content-end mt-3">
            <a class="btn btn-outline-primary" href="{{ $recommendation ? route('recommendations.show', $recommendation) : route('preferences.edit', ['room' => $roomImage->id]).'#recommendation-form' }}">{{ $recommendation ? 'Back to recommendations' : 'Use room for recommendations' }}</a>
        </div>
    </section>
</div>
@endsection
