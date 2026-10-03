@extends('layouts.app')
@section('title', 'Room placement accuracy review')
@section('content')
<header class="page-heading"><div><p class="section-kicker">Room-image evaluation · Reviewer rubric</p><h1>Assess room analysis and placement separately from usability.</h1><p>Review each analyzed room image and the available AI analysis/placement preview. Record whether floor detection, placement-zone choice, clearance, and displayed scale look correct. These are reviewer judgments, not survey SUS scores.</p></div></header>

<section class="metric-grid mb-4" aria-label="Room placement assessment results">
    @foreach(['floor_detection_correct' => 'Floor detection', 'zone_appropriate' => 'Placement zone', 'clearance_respected' => 'Clearance', 'scale_plausible' => 'Scale plausibility'] as $key => $label)
        <div class="metric"><span>{{ $label }} · reviewer pass</span><strong>{{ $metrics[$key]['rate'] === null ? '—' : number_format($metrics[$key]['rate'], 1).'%' }}</strong><small>{{ $metrics[$key]['correct'] }} pass / {{ $metrics[$key]['count'] }} ratings</small></div>
    @endforeach
</section>
<section class="panel mb-4"><h2 class="h5">Assessment coverage</h2><p class="mb-0">{{ array_sum($sourceCounts) }} total reviewer assessments: {{ $sourceCounts['gemini'] }} on Gemini-analyzed rooms and {{ $sourceCounts['fallback'] }} on fallback/local-analysis rooms. Interpret pass rates with these sample counts; they are not a controlled benchmark or a claim of guaranteed fit.</p></section>

<section class="vstack gap-3">
@forelse($rooms as $room)
    @php($review = $evaluations->get($room->id))
    <article class="panel">
        <div class="d-flex flex-column flex-lg-row gap-3">
            <img src="{{ route('room-images.original', $room) }}" alt="Room {{ $room->id }} uploaded image" style="width:min(100%,320px);max-height:220px;object-fit:contain;background:#f4f2ee">
            <div class="flex-grow-1">
                <h2 class="h5">Room {{ $room->id }} · {{ $room->analyzed_at->format('M j, Y g:i A') }}</h2>
                <p class="small text-muted">{{ data_get($room->analysis, 'gemini_used') ? 'Gemini analysis' : 'Fallback/local analysis' }} · {{ count(data_get($room->analysis, 'candidate_zones', [])) }} candidate zones · {{ data_get($room->analysis, 'scene_confidence', 'unknown') }} scene confidence</p>
                <p>{{ data_get($room->analysis, 'summary', 'No analysis summary available.') }}</p>
                <a class="btn btn-sm btn-outline-primary mb-3" href="{{ route('admin.room-placement-evaluation.preview', $room) }}" target="_blank" rel="noopener">Open placement preview</a>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.room-placement-evaluation.store', $room) }}" class="row g-3 border-top pt-3">
            @csrf
            @foreach([
                'floor_detection_correct' => 'Does the detected floor area match the visible floor?',
                'zone_appropriate' => 'Is the suggested furniture placement zone appropriate?',
                'clearance_respected' => 'Does the placement avoid visible obstacles and walking paths?',
                'scale_plausible' => 'Does the furniture scale look plausible in this image?',
            ] as $field => $label)
                <div class="col-md-6"><label class="form-label" for="{{ $field }}-{{ $room->id }}">{{ $label }}</label><select class="form-select" id="{{ $field }}-{{ $room->id }}" name="{{ $field }}" required>@unless($review)<option value="" selected disabled>Choose an assessment</option>@endunless @foreach(['yes' => 'Yes', 'no' => 'No', 'not_applicable' => 'Cannot assess'] as $value => $text)<option value="{{ $value }}" @selected($review && (($review->$field === true && $value === 'yes') || ($review->$field === false && $value === 'no') || ($review->$field === null && $value === 'not_applicable')))>{{ $text }}</option>@endforeach</select></div>
            @endforeach
            <div class="col-12"><label class="form-label" for="review-notes-{{ $room->id }}">Reviewer notes (optional)</label><textarea class="form-control" id="review-notes-{{ $room->id }}" name="notes" rows="2" maxlength="2000">{{ $review?->notes }}</textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">{{ $review ? 'Update my assessment' : 'Save my assessment' }}</button></div>
        </form>
    </article>
@empty
    <div class="panel">No analyzed room images are available to assess yet.</div>
@endforelse
</section>
<div class="mt-3">{{ $rooms->links() }}</div>
@endsection
