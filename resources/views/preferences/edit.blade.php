@extends('layouts.app')
@section('title','Plan your room')
@section('content')
@php($activeRoomId = (int) old('room_image_id', $selectedRoomId))
<header class="page-heading">
    <div>
        <p class="section-kicker">Guided customer workflow</p>
        <h1>Plan your room in two required steps.</h1>
        <p>Choose exactly what furniture you want, then upload or select a room and generate matching recommendations.</p>
    </div>
    <a class="btn btn-outline-dark" href="{{ route('recommendations.index') }}">Past recommendations</a>
</header>

<x-workflow-progress :step="$preferenceComplete ? 2 : 1" />

<section id="preference-form" class="workflow-section" aria-labelledby="preference-heading">
    <div class="workflow-section-heading">
        <div>
            <p class="section-kicker mb-1">Step 1 · Required</p>
            <h2 id="preference-heading">Browse and set your preferences.</h2>
            <p>Choose the furniture you need, then save the budget, materials, and style the recommendation engine should use.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="workflow-state is-done" data-preference-save-state @if(!$preferenceComplete) hidden @endif>Saved</span>
            <a class="btn btn-outline-dark" href="{{ route('catalog.index') }}">Browse catalog</a>
        </div>
    </div>

    <form method="POST" action="{{ route('preferences.update') }}" class="panel" data-preferences-form>
        @csrf
        <div class="alert fc-alert mb-4" role="status" aria-live="polite" data-preferences-status hidden></div>
        <div class="row g-4">
            <div class="col-md-4"><label class="form-label" for="furniture_type">Furniture type</label><select class="form-select" id="furniture_type" name="furniture_type" required><option value="">Choose furniture</option>@foreach($furnitureTypes as $type)<option value="{{ $type }}" @selected(old('furniture_type',$preference?->furniture_type)===$type)>{{ $type }}</option>@endforeach</select><div class="form-text">Recommendations will only include this type.</div></div>
            <div class="col-md-4"><label class="form-label" for="budget_min">Minimum budget PHP</label><input class="form-control" id="budget_min" type="number" min="0" step="100" name="budget_min" value="{{ old('budget_min',$preference?->budget_min ?? 5000) }}" required></div>
            <div class="col-md-4"><label class="form-label" for="budget_max">Maximum budget PHP</label><input class="form-control" id="budget_max" type="number" min="1" step="100" name="budget_max" value="{{ old('budget_max',$preference?->budget_max ?? 50000) }}" required></div>
            <div class="col-md-4"><label class="form-label" for="style">Furniture style</label><select class="form-select" id="style" name="style"><option value="">Open to any style</option>@foreach($styles as $style)<option @selected(old('style',$preference?->style)===$style)>{{ $style }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label" for="wood_type">Wood type</label><select class="form-select" id="wood_type" name="wood_type"><option value="">Either wood</option>@foreach(['Gmelina','Mahogany'] as $value)<option @selected(old('wood_type',$preference?->wood_type)===$value)>{{ $value }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label" for="varnish_finish">Varnish finish</label><select class="form-select" id="varnish_finish" name="varnish_finish"><option value="">Any finish</option>@foreach(['Natural','Narra Look','Mahogany','Chocolate Brown'] as $value)<option @selected(old('varnish_finish',$preference?->varnish_finish)===$value)>{{ $value }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label" for="notes">What else matters?</label><textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Example: Easy-clean dining furniture for a family of six.">{{ old('notes',$preference?->notes) }}</textarea></div>
            <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary btn-lg" type="submit" data-preferences-submit>{{ $preference ? 'Update preferences' : 'Save preferences and continue' }}</button></div>
        </div>
    </form>
</section>

<section id="room-preview" class="workflow-section" aria-labelledby="room-preview-heading">
    <div class="workflow-section-heading">
        <div>
            <p class="section-kicker mb-1">Step 2 · Required</p>
            <h2 id="room-preview-heading">Choose a room and generate recommendations.</h2>
            <p>Upload or select a room. FurniCraft analyzes the space, then ranks only active <span data-saved-furniture-type-lower>{{ strtolower($preference?->furniture_type ?: 'furniture') }}</span> matches closest to your saved preferences.</p>
        </div>
        <span class="workflow-state is-done" data-room-step-state @if(!$activeRoomId) hidden @endif>Room selected</span>
    </div>

    @unless($preferenceComplete)
        <div class="alert alert-warning fc-alert" data-step-one-warning>Complete Step 1 before uploading a room image.</div>
    @endunless

    <div class="row g-4">
        <div class="col-lg-5">
            <form method="POST" action="{{ route('room.store') }}" enctype="multipart/form-data" class="panel vstack gap-3">
                @csrf
                <input type="hidden" name="return_to" value="recommendations">
                <fieldset class="vstack gap-3" data-room-upload-fieldset @disabled(!$preferenceComplete)>
                    <div>
                        <label class="form-label" for="room_image">Room photo</label>
                        <input class="form-control" id="room_image" type="file" name="room_image" accept="image/jpeg,image/png,image/webp" required>
                        <div class="form-text">JPG, PNG, or WebP up to 5 MB. Avoid people and personal documents.</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label" for="upload_room_width_m">Known width m <span class="text-muted">(optional)</span></label><input class="form-control" id="upload_room_width_m" type="number" name="room_width_m" min="1" max="50" step=".1"></div>
                        <div class="col-6"><label class="form-label" for="upload_room_depth_m">Known depth m <span class="text-muted">(optional)</span></label><input class="form-control" id="upload_room_depth_m" type="number" name="room_depth_m" min="1" max="50" step=".1"></div>
                    </div>
                    <button class="btn btn-primary btn-lg" type="submit">Analyze and select this room</button>
                </fieldset>
                <label class="form-check panel mb-0"><input class="form-check-input" type="checkbox" name="gemini_consent" value="1"><span class="form-check-label">I consent to sending this room photo to Google Gemini for AI room analysis and generated previews. <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener">Read how images are handled</a>. Leave unchecked for local guidance only.</span></label>
                <p class="small text-muted mb-0">Known measurements override visual estimates. Room photos are stored privately, stripped of EXIF metadata, and retained for {{ (int) config('privacy.room_image_retention_days', 90) }} days unless deleted sooner.</p>
            </form>
        </div>

        <div class="col-lg-7">
            <h3 class="h4 mb-3">Your analyzed rooms</h3>
            <p class="small text-muted" data-room-selection-status aria-live="polite">Click a room to use it for your recommendation.</p>
            @forelse($rooms as $room)
                @php($isSelectedRoom = $activeRoomId === $room->id)
                @php($roomReady = in_array($room->analysis_status, ['completed', 'fallback'], true))
                @php($statusLabel = ['completed' => 'AI analyzed', 'queued' => 'Queued', 'processing' => 'Analyzing', 'retrying' => 'Retrying', 'failed' => 'Needs retry'][$room->analysis_status] ?? 'Local guidance')
                <article class="panel room-option mb-3 d-flex flex-column flex-sm-row gap-3 align-items-sm-center {{ $isSelectedRoom ? 'is-selected' : '' }}" data-room-option data-room-id="{{ $room->id }}" data-room-ready="{{ $roomReady ? 'true' : 'false' }}" data-room-analysis-watch="{{ route('room.analysis-status', $room) }}" role="button" tabindex="0" aria-pressed="{{ $isSelectedRoom ? 'true' : 'false' }}" aria-label="Use Room {{ $room->id }} for recommendations">
                    <img src="{{ route('room-images.original', $room) }}" alt="Uploaded room from {{ $room->created_at->format('M j, Y') }}" width="140" height="100" style="object-fit:cover">
                    <div class="flex-grow-1">
                        <div><strong>Room {{ $room->id }} · {{ $room->created_at->format('F j, Y · g:i A') }}</strong><span class="badge text-bg-primary ms-1" data-room-selected-badge @if(!$isSelectedRoom) hidden @endif>Selected</span><span class="badge text-bg-light ms-1" data-room-analysis-label>{{ $statusLabel }}</span></div>
                        <p class="small text-muted mb-2">{{ $room->room_width_m ?: '?' }} × {{ $room->room_depth_m ?: '?' }} m · {{ data_get($room->analysis,'dimension_confidence','unavailable') }} dimensions<span data-room-analysis-message>@if($room->analysis_error) · {{ $room->analysis_error }}@endif</span></p>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('room.visualize',$room) }}">Open 2D preview</a>
                            <form method="POST" action="{{ route('room.destroy', $room) }}" onsubmit="return confirm('Delete this room photo and its generated previews?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Delete photo</button></form>
                            @unless(in_array($room->analysis_status, ['queued', 'processing', 'retrying'], true))
                                <form method="POST" action="{{ route('room.reanalyze', $room) }}">@csrf<button class="btn btn-sm btn-outline-dark" type="submit">Re-analyze room</button></form>
                            @endunless
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty-state"><h3>No analyzed room yet.</h3><p>A room image is required before FurniCraft can generate recommendations.</p></div>
            @endforelse
        </div>
    </div>

    <form method="POST" action="{{ route('recommendations.store') }}" class="panel mt-4" id="recommendation-form">
        @csrf
        <input type="hidden" id="room_image_id" name="room_image_id" value="{{ $activeRoomId ?: '' }}" required>
        <div class="row g-3 align-items-center">
            <div class="col-lg">
                <p class="section-kicker mb-1">AI-powered matching</p>
                <h3 class="h4 mb-1">Generate <span data-saved-furniture-type>{{ $preference?->furniture_type ?: 'furniture' }}</span> recommendations</h3>
                <p class="text-muted mb-0" data-selected-room-label>{{ $activeRoomId ? 'Room '.$activeRoomId.' selected.' : 'Click one of your analyzed rooms first.' }} Only active <span data-saved-furniture-type-lower>{{ strtolower($preference?->furniture_type ?: 'furniture') }}</span> products will be ranked by your budget, style, wood, finish, and room fit.</p>
            </div>
            <div class="col-lg-auto d-grid">
                <button class="btn btn-primary btn-lg" type="submit" data-generate-recommendations @disabled(!$preferenceComplete || $rooms->isEmpty() || !$activeRoomId || !optional($rooms->firstWhere('id', $activeRoomId))->analysis_status || !in_array(optional($rooms->firstWhere('id', $activeRoomId))->analysis_status, ['completed','fallback'], true))>Generate recommendations</button>
            </div>
        </div>
    </form>
</section>
@endsection
