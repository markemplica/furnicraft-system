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
        <p class="ai-match-summary">We compared your room, style, preferred materials, finish, and budget to rank active catalog choices. Catalog listings do not confirm physical branch stock.</p>
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
        </aside>

        <div class="inline-preview-visual">
            <header class="comparison-panel-head">
                <div>
                    <span class="room-label">2D placement preview</span>
                    <h3>Original room / FurniCraft result</h3>
                </div>
                <span class="preview-status"><i aria-hidden="true"></i> Planning preview</span>
            </header>

            <div class="placement-status" aria-live="polite">
                <div><span class="room-label">Selected furniture · fixed preview</span><strong data-inline-preview-name>{{ $previewProduct->name }}</strong></div>
                <span><span data-placement-status>{{ data_get($previewPlacement,'renderable',true) ? (data_get($previewPlacement,'validation_status') === 'supported' ? 'AI placement supported' : 'Placement requires verification') : 'No validated placement available' }}</span><br><span data-placement-dimensions></span></span>
            </div>

            <script type="application/json" data-initial-placement-quad>@json(data_get($previewPlacement, 'quad_percent'))</script>

            <div class="room-comparison" data-room-comparison data-room-width="{{ $room->room_width_m }}" data-room-depth="{{ $room->room_depth_m }}" data-product-width="{{ $previewProduct->width_cm }}" data-product-depth="{{ $previewProduct->depth_cm }}" data-product-height="{{ $previewProduct->height_cm }}" data-product-category="{{ $previewProduct->category }}" data-product-type="{{ $previewProduct->recommendationType() }}" data-placement-ai="{{ in_array(data_get($previewPlacement,'source'), ['candidate-zone','scene-opening'], true) ? 'true' : 'false' }}" data-generated-result="false" data-gemini-unavailable="false" data-placement-type="{{ data_get($room->analysis,'analyzed_furniture_type') }}" data-placement-x="{{ data_get($previewPlacement,'x_percent',50) }}" data-placement-y="{{ data_get($previewPlacement,'y_percent',86) }}" data-placement-width="{{ data_get($previewPlacement,'max_width_percent',72) }}" data-placement-rotation="{{ data_get($previewPlacement,'rotation_deg',0) }}" data-placement-perspective="{{ data_get($previewPlacement,'perspective_deg',0) }}" data-placement-flip="{{ data_get($previewPlacement,'flip',1) }}" data-placement-confidence="{{ data_get($previewPlacement,'confidence','low') }}" data-placement-source="{{ data_get($previewPlacement,'source','type-fallback') }}" data-placement-reason="{{ data_get($previewPlacement,'reason') }}" data-placement-needs-confirmation="{{ data_get($previewPlacement,'needs_confirmation',true) ? 'true' : 'false' }}" data-placement-renderable="{{ data_get($previewPlacement,'renderable',true) ? 'true' : 'false' }}" data-placement-validation="{{ data_get($previewPlacement,'validation_status','advisory') }}" data-placement-measurement="{{ data_get($previewPlacement,'measurement_status','measurements-missing') }}" data-placement-basis="{{ data_get($previewPlacement,'fit_basis','image-estimate') }}" role="slider" tabindex="0" aria-label="Drag left or right to compare the original room and selected furniture planning preview" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50" style="--split:50%; --product-width:40%; --product-x:50%; --product-y:84%; --product-rotation:0deg; --product-perspective:0deg; --product-flip:1;">
                <img class="comparison-room-base" src="{{ route('room-images.original', $room) }}" alt="Original uploaded room">
                <div class="comparison-result" data-comparison-result aria-hidden="true">
                    <img class="comparison-room-result" src="{{ route('room-images.original', $room) }}" alt="">
                    <img class="comparison-product" src="{{ $previewProduct->image_url }}" alt="" draggable="false" data-comparison-product>
                    <canvas class="comparison-product-warp" data-comparison-product-warp hidden></canvas>
                    <div class="comparison-result-wash"></div>
                </div>
                <span class="comparison-tag comparison-tag-original">Original</span>
                <span class="comparison-tag comparison-tag-result">Result</span>
                <span class="comparison-divider" aria-hidden="true"></span>
            </div>

            <p class="automatic-placement-note"><strong>Placement validation.</strong> <span data-placement-guidance>{{ data_get($previewPlacement,'reason','Choose another recommended product to update this planning preview.') }}</span> <button class="btn btn-sm btn-outline-primary ms-2" type="button" data-placement-confirm hidden>Choose suggested position</button></p>
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
                        <p class="product-meta">{{ $item->product->wood_type }} · {{ $item->product->varnish_finish }} · {{ $item->product->width_cm }} × {{ $item->product->depth_cm }} cm</p>
                        <strong>PHP {{ number_format($item->product->price,2) }}</strong>
                        <form method="POST" action="{{ route('recommendations.items.feedback', [$recommendation, $item]) }}" class="mt-3 p-2 border rounded">
                            @csrf
                            <label class="form-label small mb-1" for="relevance-{{ $item->id }}">Is this recommendation relevant to you?</label>
                            <select class="form-select form-select-sm mb-2" id="relevance-{{ $item->id }}" name="rejection_reason" aria-label="Reason this recommendation is not relevant">
                                <option value="">If not relevant, choose a reason</option>
                                @foreach(['style' => 'Style', 'price' => 'Price', 'material' => 'Material or finish', 'dimensions' => 'Dimensions', 'room_fit' => 'Room fit', 'other' => 'Other'] as $reason => $label)
                                    <option value="{{ $reason }}" @selected($item->rejection_reason === $reason)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="d-flex gap-2 align-items-center">
                                <button class="btn btn-sm {{ $item->relevance === true ? 'btn-success' : 'btn-outline-success' }}" name="relevance" value="1">Relevant</button>
                                <button class="btn btn-sm {{ $item->relevance === false ? 'btn-danger' : 'btn-outline-secondary' }}" name="relevance" value="0">Not relevant</button>
                                @if($item->evaluated_at)<span class="small text-muted">Recorded {{ $item->evaluated_at->diffForHumans() }}</span>@endif
                            </div>
                        </form>
                        @if($room)
                            <div class="mt-2">
                                @php($itemPlacement = $placements->get($item->product->id) ?? [])
                                <script type="application/json" data-recommendation-placement-quad>@json(data_get($itemPlacement, 'quad_percent'))</script>
                                <button class="btn btn-sm {{ $loop->first ? 'btn-primary is-active' : 'btn-outline-primary' }}" type="button" data-recommendation-preview data-name="{{ $item->product->name }}" data-image="{{ $item->product->image_url }}" data-width="{{ $item->product->width_cm }}" data-depth="{{ $item->product->depth_cm }}" data-height="{{ $item->product->height_cm }}" data-category="{{ $item->product->category }}" data-type="{{ $item->product->recommendationType() }}" data-price="{{ number_format($item->product->price,2) }}" data-score="{{ number_format($item->total_score,0) }}% match" data-url="{{ route('catalog.show',$item->product) }}" data-placement-x="{{ data_get($itemPlacement,'x_percent',50) }}" data-placement-y="{{ data_get($itemPlacement,'y_percent',86) }}" data-placement-width="{{ data_get($itemPlacement,'max_width_percent',72) }}" data-placement-rotation="{{ data_get($itemPlacement,'rotation_deg',0) }}" data-placement-perspective="{{ data_get($itemPlacement,'perspective_deg',0) }}" data-placement-flip="{{ data_get($itemPlacement,'flip',1) }}" data-placement-confidence="{{ data_get($itemPlacement,'confidence','low') }}" data-placement-source="{{ data_get($itemPlacement,'source','type-fallback') }}" data-placement-reason="{{ data_get($itemPlacement,'reason') }}" data-placement-needs-confirmation="{{ data_get($itemPlacement,'needs_confirmation',true) ? 'true' : 'false' }}" data-placement-renderable="{{ data_get($itemPlacement,'renderable',true) ? 'true' : 'false' }}" data-placement-validation="{{ data_get($itemPlacement,'validation_status','advisory') }}" data-placement-measurement="{{ data_get($itemPlacement,'measurement_status','measurements-missing') }}" data-placement-basis="{{ data_get($itemPlacement,'fit_basis','image-estimate') }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $loop->first ? 'Showing in room' : 'Preview here' }}</button>
                            </div>
                        @endif
                    </div>
                    <div>
                        <div class="score">{{ number_format($item->total_score,0) }}%</div>
                        <form method="POST" action="{{ route('reservations.store') }}" class="mt-3 vstack gap-2">
                            @csrf
                            <input type="hidden" name="furniture_product_id" value="{{ $item->product->id }}">
                            <input type="hidden" name="recommendation_id" value="{{ $recommendation->id }}">
                            <select class="form-select form-select-sm" name="branch" required><option value="">Branch</option>@foreach(['Lingayen','Calasiao','San Jacinto','Sta. Barbara','Pozorrubio','Tarlac'] as $branch)<option>{{ $branch }}</option>@endforeach</select>
                            <button class="btn btn-sm btn-primary">Reserve</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

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
</div>
@endsection
