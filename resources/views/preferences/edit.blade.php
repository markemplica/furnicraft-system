@extends('layouts.app')
@section('title','Plan your room')
@section('content')
<header class="page-heading">
    <div>
        <p class="section-kicker">Guided customer workflow</p>
        <h1>Plan your room in three required steps.</h1>
        <p>Browse Lance furniture, save what you need, upload your room for space analysis, then generate recommendations and ask the AI assistant.</p>
    </div>
    <a class="btn btn-outline-dark" href="{{ route('recommendations.index') }}">Past recommendations</a>
</header>

<x-workflow-progress :step="$selectedRoomId ? 4 : ($preference ? 3 : 2)" />

<section id="preference-form" class="workflow-section" aria-labelledby="preference-heading">
    <div class="workflow-section-heading">
        <div>
            <p class="section-kicker mb-1">Step 1 · Required</p>
            <h2 id="preference-heading">Browse and set your preferences.</h2>
            <p>Review the Lance catalog, then save the budget, materials, and style the recommendation engine should use.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            @if($preference)<span class="workflow-state is-done">Saved</span>@endif
            <a class="btn btn-outline-dark" href="{{ route('catalog.index') }}">Browse catalog</a>
        </div>
    </div>

    <form method="POST" action="{{ route('preferences.update') }}" class="panel">
        @csrf
        <div class="row g-4">
            <div class="col-md-3"><label class="form-label" for="budget_min">Minimum budget PHP</label><input class="form-control" id="budget_min" type="number" min="0" step="100" name="budget_min" value="{{ old('budget_min',$preference?->budget_min ?? 5000) }}" required></div>
            <div class="col-md-3"><label class="form-label" for="budget_max">Maximum budget PHP</label><input class="form-control" id="budget_max" type="number" min="1" step="100" name="budget_max" value="{{ old('budget_max',$preference?->budget_max ?? 50000) }}" required></div>
            <div class="col-md-3"><label class="form-label" for="style">Furniture style</label><select class="form-select" id="style" name="style"><option value="">Open to any style</option>@foreach($styles as $style)<option @selected(old('style',$preference?->style)===$style)>{{ $style }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label" for="wood_type">Wood type</label><select class="form-select" id="wood_type" name="wood_type"><option value="">Either wood</option>@foreach(['Gmelina','Mahogany'] as $value)<option @selected(old('wood_type',$preference?->wood_type)===$value)>{{ $value }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label" for="varnish_finish">Varnish finish</label><select class="form-select" id="varnish_finish" name="varnish_finish"><option value="">Any finish</option>@foreach(['Natural','Narra Look','Mahogany','Chocolate Brown'] as $value)<option @selected(old('varnish_finish',$preference?->varnish_finish)===$value)>{{ $value }}</option>@endforeach</select></div>
            <div class="col-md-8"><label class="form-label" for="notes">What else matters?</label><textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Example: Easy-clean dining furniture for a family of six.">{{ old('notes',$preference?->notes) }}</textarea></div>
            <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary btn-lg" type="submit">{{ $preference ? 'Update preferences' : 'Save preferences and continue' }}</button></div>
        </div>
    </form>
</section>

<section id="room-preview" class="workflow-section" aria-labelledby="room-preview-heading">
    <div class="workflow-section-heading">
        <div>
            <p class="section-kicker mb-1">Step 2 · Required</p>
            <h2 id="room-preview-heading">Upload a room for space analysis.</h2>
            <p>Computer vision reviews the layout and estimates dimensions when you do not provide them. Estimates remain planning guidance, not construction measurements.</p>
        </div>
        @if($selectedRoomId)<span class="workflow-state is-done">Room selected</span>@endif
    </div>

    @unless($preference)
        <div class="alert alert-warning fc-alert">Complete Step 1 before uploading a room image.</div>
    @endunless

    <div class="row g-4">
        <div class="col-lg-5">
            <form method="POST" action="{{ route('room.store') }}" enctype="multipart/form-data" class="panel vstack gap-3">
                @csrf
                <input type="hidden" name="return_to" value="recommendations">
                <fieldset class="vstack gap-3" @disabled(!$preference)>
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
                <p class="small text-muted mb-0">Known measurements override visual estimates and produce more reliable furniture scaling.</p>
            </form>
        </div>

        <div class="col-lg-7">
            <h3 class="h4 mb-3">Your analyzed rooms</h3>
            @forelse($rooms as $room)
                <article class="panel mb-3 d-flex flex-column flex-sm-row gap-3 align-items-sm-center {{ $selectedRoomId === $room->id ? 'border border-primary' : '' }}">
                    <img src="{{ asset('storage/'.$room->image_path) }}" alt="Uploaded room from {{ $room->created_at->format('M j, Y') }}" width="140" height="100" style="object-fit:cover">
                    <div class="flex-grow-1">
                        <div><strong>{{ $room->created_at->format('F j, Y · g:i A') }}</strong>@if($selectedRoomId === $room->id)<span class="badge text-bg-primary ms-1">Selected</span>@endif</div>
                        <p class="small text-muted mb-2">{{ $room->room_width_m ?: '?' }} × {{ $room->room_depth_m ?: '?' }} m · {{ data_get($room->analysis,'dimension_confidence','unavailable') }} dimensions · {{ data_get($room->analysis,'gemini_used') ? 'Gemini-assisted' : 'local guidance' }}</p>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-sm btn-primary" href="{{ route('preferences.edit', ['room' => $room->id]).'#recommendation-form' }}">Select this room</a>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('room.visualize',$room) }}">Open 2D preview</a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty-state"><h3>No analyzed room yet.</h3><p>A room image is required before FurniCraft can generate recommendations.</p></div>
            @endforelse
        </div>
    </div>
</section>

<section id="recommendation-form" class="workflow-section" aria-labelledby="recommendation-heading">
    <div class="workflow-section-heading">
        <div>
            <p class="section-kicker mb-1">Step 3 · AI-powered</p>
            <h2 id="recommendation-heading">Generate ranked furniture matches.</h2>
            <p>FurniCraft combines content matching, collaborative signals, room fit, and a Gemini explanation using Lance Furniture products only.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('recommendations.store') }}" class="panel">
        @csrf
        <div class="row g-4 align-items-end">
            <div class="col-lg-8">
                <label class="form-label" for="room_image_id">Analyzed room image</label>
                <select class="form-select form-select-lg" id="room_image_id" name="room_image_id" required @disabled(!$preference || $rooms->isEmpty())>
                    <option value="">Select an analyzed room</option>
                    @foreach($rooms as $room)<option value="{{ $room->id }}" @selected((int) old('room_image_id', $selectedRoomId) === $room->id)>Room {{ $room->id }} · {{ $room->room_width_m ?: '?' }} × {{ $room->room_depth_m ?: '?' }} m · {{ $room->created_at->format('M j, Y g:i A') }}</option>@endforeach
                </select>
                <div class="form-text">Your saved preferences and the selected room analysis will be used together.</div>
            </div>
            <div class="col-lg-4 d-grid">
                <button class="btn btn-primary btn-lg" type="submit" @disabled(!$preference || $rooms->isEmpty())>Generate recommendations</button>
            </div>
        </div>
    </form>
</section>
@endsection
