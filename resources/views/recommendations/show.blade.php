@extends('layouts.app')
@section('title','Recommended furniture')
@section('body_class','ai-matches-page')
@section('content')
@php
    $previewItem = $recommendation->items->first();
    $previewProduct = $previewItem?->product;
    $room = $recommendation->roomImage;
    $previewPlacement = $previewProduct ? ($placements->get($previewProduct->id) ?? []) : [];
@endphp

<header class="page-heading ai-matches-heading">
    <div>
        <p class="section-kicker">Step 3 · Your AI matches</p>
        <h1>{{ ucfirst(strtolower($recommendation->furniture_type ?: 'Furniture')) }} options selected for your room.</h1>
        <p class="ai-match-summary">We compared your room, style, preferred materials, finish, and budget to rank active catalog choices. Catalog listings do not confirm physical availability at Lance Furniture Shop.</p>
        <span class="small text-muted">{{ $recommendation->furniture_type ? $recommendation->furniture_type.' only' : 'Active catalog furniture' }} · Ranked by room fit and preferences · {{ $recommendation->gemini_used ? 'AI-assisted explanation' : 'Local recommendation fallback' }}</span>
    </div>
    <a class="btn btn-outline-dark" href="{{ route('preferences.edit') }}">Adjust room &amp; preferences</a>
</header>

<x-workflow-progress :step="3" />

<div class="ai-matches-dashboard">
@if($room && $previewProduct)
    <section class="comparison-panel recommendation-inline-preview" id="ai-room-preview" aria-labelledby="inline-preview-heading">
        <aside class="inline-preview-story">
            <p class="section-kicker">Your room, reimagined</p>
            <h2 id="inline-preview-heading">See <span data-inline-preview-name>{{ $previewProduct->name }}</span> in your space.</h2>
            <p class="inline-preview-intro">The selected furniture is automatically positioned and scaled using the room photo and its catalog dimensions.</p>

            <div class="room-source-card inline-room-source">
                <img src="{{ route('room-images.original', $room) }}" alt="Uploaded room thumbnail">
                <div><span>Original room</span><strong>Room {{ $room->id }}</strong><small>{{ $room->room_width_m ?: '?' }} × {{ $room->room_depth_m ?: '?' }} m</small></div>
            </div>

            <ol class="comparison-steps inline-preview-steps">
                <li><span>1</span><div><strong>Before</strong><small>Your uploaded room remains the base scene.</small></div></li>
                <li><span>2</span><div><strong>After</strong><small>The selected product is placed only when a usable room zone is available.</small></div></li>
            </ol>

            <article class="preview-product-card inline-selected-product">
                <img src="{{ $previewProduct->image_url }}" alt="{{ $previewProduct->name }}" data-inline-preview-card-image>
                <div>
                    <span class="room-label">Recommended product</span>
                    <strong data-inline-preview-name>{{ $previewProduct->name }}</strong>
                    <small><span data-inline-preview-score>{{ number_format($previewItem->total_score,0) }}% match</span> · PHP <span data-inline-preview-price>{{ number_format($previewProduct->price,2) }}</span></small>
                    <a href="{{ route('catalog.show',$previewProduct) }}" data-inline-preview-link>View product details</a>
                </div>
            </article>

            <aside class="ai-matches-chat">
        <div class="chat-panel">
            <div class="chat-head"><h2 class="h4 mb-1">Ask about these matches</h2><p class="small mb-0">Answers stay within this recommendation and Lance catalog data.</p></div>
            <div class="chat-log">
                @forelse($recommendation->messages as $message)
                    <div class="chat-message {{ $message->role }}"><strong>{{ $message->role === 'user' ? 'You' : 'FurniCraft' }}</strong>@if($message->role === 'assistant')<small class="d-block text-muted">{{ match($message->ai_source) { 'gemini' => 'Gemini response', 'catalog-rule' => 'Current catalog answer', 'local-fallback' => 'Local catalog fallback', default => $message->gemini_used ? 'AI-assisted response' : 'Local response' } }}</small>@endif<div>{{ $message->message }}</div></div>
                @empty
                    <p class="text-light-emphasis">Ask about material, finish, dimensions, price, or room suitability.</p>
                @endforelse
            </div>
            <form class="chat-compose" method="POST" action="{{ route('recommendations.chat',$recommendation) }}">
                @csrf
                <label class="visually-hidden" for="message">Question</label>
                <textarea class="form-control" id="message" name="message" rows="1" maxlength="1000" placeholder="Ask if a product or preference is available" required></textarea>
                <button class="btn btn-light w-100 mt-2">Ask FurniCraft</button>
            </form>
        </div>
            </aside>
        </aside>

        <div class="inline-preview-visual">
            <header class="comparison-panel-head">
                <div>
                    <span class="room-label">2D placement preview</span>
                    <h3>Automatic 2D room preview</h3>
                </div>
                <span class="preview-status"><i aria-hidden="true"></i> Planning preview</span>
            </header>

            <div class="placement-status" aria-live="polite">
                <div><span class="room-label">Furniture selected for this preview</span><strong data-inline-preview-name>{{ $previewProduct->name }}</strong></div>
                <span><span data-placement-status>{{ data_get($previewPlacement,'renderable',true) ? 'Automatic image fit' : 'No validated placement available' }}</span><br><span data-placement-dimensions></span></span>
                <p data-placement-guidance class="small mb-0"></p>
            </div>



            <script type="application/json" data-preview-analysis>@json($room->analysis ?? [])</script>
            <div class="room-preview-frame room-comparison" data-room-preview role="slider" tabindex="0" aria-label="Before and after preview. Use the arrow keys to move the divider." aria-valuemin="0" aria-valuemax="100" aria-valuenow="50" data-split="50"
                data-room-width="{{ $room->room_width_m }}" data-room-depth="{{ $room->room_depth_m }}"
                data-product-id="{{ $previewProduct->id }}" data-product-name="{{ $previewProduct->name }}"
                data-product-width="{{ $previewProduct->width_cm }}" data-product-depth="{{ $previewProduct->depth_cm }}" data-product-height="{{ $previewProduct->height_cm }}" data-product-dimensions-estimated="{{ $previewProduct->dimensions_estimated ? 'true' : 'false' }}"
                data-product-type="{{ $previewProduct->recommendationType() }}" data-product-category="{{ $previewProduct->category }}"
                data-initial-x="{{ data_get($previewPlacement,'x_percent',50) }}" data-initial-y="{{ data_get($previewPlacement,'y_percent',86) }}"
                data-initial-width="{{ data_get($previewPlacement,'max_width_percent',65) }}" data-initial-quad='@json(data_get($previewPlacement,"quad_percent"))'
                data-confidence="{{ data_get($previewPlacement,'confidence','low') }}" data-placement-source="{{ data_get($previewPlacement,'source','safe-fallback') }}"
                data-placement-status="{{ $room->analysis_status }}" data-consented="{{ $room->gemini_consent_at ? 'true' : 'false' }}"
                data-preview-v2-url="{{ route('room.preview-v2.store', $room) }}"
                data-verify-url="{{ route('room.preview-verify', [$room,$previewProduct]) }}" data-csrf="{{ csrf_token() }}">
                <img class="comparison-room-base" src="{{ route('room-images.original', $room) }}" alt="Before: original room">
                <img class="room-preview-source" src="{{ route('room-images.original', $room) }}" alt="" data-preview-room>
                <img class="room-preview-source" src="{{ $previewProduct->image_url }}" alt="" data-preview-product>
                <div class="comparison-result" data-comparison-result>
                    <canvas class="room-preview-canvas" data-room-preview-canvas role="img" aria-label="After: room with automatically fitted furniture"></canvas>
                </div>
                <div class="comparison-divider" data-comparison-divider aria-hidden="true"></div>
                <span class="comparison-tag comparison-tag-original" aria-hidden="true">Before</span>
                <span class="comparison-tag comparison-tag-result" aria-hidden="true">After</span>
                <div class="room-preview-loading" data-preview-loading role="status" aria-live="polite">Uploading · Analyzing room · Fitting furniture · Checking the result</div>
            </div>
            <div class="preview-detection-row" data-preview-detections aria-live="polite"></div>
            <div class="room-preview-actions">
                <button type="button" class="btn btn-primary" data-preview-download>Download preview (PNG)</button>
                <form method="POST" action="{{ route('room.reanalyze',$room) }}">@csrf<button class="btn btn-outline-secondary" type="submit">Try again</button></form>
                <a class="btn btn-outline-secondary" href="{{ route('preferences.edit',['room'=>$room->id]) }}#room-preview">Upload another photo</a>
            </div>
            <p class="automatic-placement-note">Preview is an estimate, not a measurement. Verify actual dimensions with Lance Furniture Shop before reserving.</p>
        </div>
    </section>
@endif

    <section class="ai-matches-results" aria-labelledby="matches-heading">
        <h2 id="matches-heading" class="h4 mb-3">Ranked matches</h2>
        <div class="fc-card overflow-hidden">
            @foreach($recommendation->items as $item)
                <article class="recommendation-item">
                    <a href="{{ route('catalog.show',$item->product) }}"><img src="{{ $item->product->image_url }}" alt="{{ $item->product->name }}"></a>
                    <div>
                        <p class="product-meta mb-1">#{{ $item->position }} · {{ $item->product->category }}</p>
                        <h3 class="h4"><a class="text-reset" href="{{ route('catalog.show',$item->product) }}">{{ $item->product->name }}</a></h3>
                        <p class="recommendation-reason">{{ $item->explanation }}</p>
                        <p class="product-meta">{{ $item->product->wood_type }} · {{ $item->product->varnish_finish }} · {{ number_format((float) $item->product->width_cm, 0) }} × {{ number_format((float) $item->product->depth_cm, 0) }} cm @if($item->product->dimensions_estimated)· standard estimate @endif</p>
                        <strong>PHP {{ number_format($item->product->price,2) }}</strong>
                        @if($room)
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-recommendation-preview
                                data-product-id="{{ $item->product->id }}" data-product-name="{{ $item->product->name }}"
                                data-product-image="{{ $item->product->image_url }}" data-product-width="{{ $item->product->width_cm }}"
                                data-product-depth="{{ $item->product->depth_cm }}" data-product-height="{{ $item->product->height_cm }}" data-product-dimensions-estimated="{{ $item->product->dimensions_estimated ? 'true' : 'false' }}"
                                data-product-type="{{ $item->product->recommendationType() }}" data-product-category="{{ $item->product->category }}"
                                data-product-score="{{ number_format($item->total_score,0) }}" data-product-price="{{ number_format($item->product->price,2) }}"
                                data-product-url="{{ route('catalog.show',$item->product) }}"
                                data-verify-url="{{ route('room.preview-verify', [$room,$item->product]) }}"
                                aria-pressed="{{ $previewProduct->id === $item->product->id ? 'true' : 'false' }}">
                                {{ $previewProduct->id === $item->product->id ? 'Selected' : 'Preview in room' }}
                            </button>
                        @endif
                    </div>
                    <div>
                        <div class="score">{{ number_format($item->total_score,0) }}%</div>
                        <form method="POST" action="{{ route('reservations.store') }}" class="mt-3 vstack gap-2">
                            @csrf
                            <input type="hidden" name="furniture_product_id" value="{{ $item->product->id }}">
                            <input type="hidden" name="recommendation_id" value="{{ $recommendation->id }}">
                            <input class="form-control form-control-sm" type="datetime-local" name="preferred_visit_at" min="{{ now()->format('Y-m-d\\TH:i') }}" aria-label="Preferred visit date and time"><select class="form-select form-select-sm" name="branch" required>@foreach(config('furnicraft.branches') as $branch)<option value="{{ $branch }}" @selected($branch === config('furnicraft.branch'))>{{ $branch }}</option>@endforeach</select>
                            @include('reservations._customization-fields', ['formSuffix' => 'recommendation-'.$item->product->id])<button class="btn btn-sm btn-primary">Reserve</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    </section>


</div>
@endsection
