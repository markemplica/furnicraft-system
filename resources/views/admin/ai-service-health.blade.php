@extends('layouts.app')

@section('title', 'AI service health')

@section('content')
<section class="page-heading"><p class="eyebrow">Operations</p><h1>AI service health</h1><p>Gemini call outcomes and local fallback usage. Metrics cover the last seven days; this is call history, not a live provider availability check.</p></section>

<div class="row g-3 mb-4">
    @foreach([['AI attempts', $totals->attempts ?? 0], ['Successful model calls', $totals->successes ?? 0], ['Failed model calls', $totals->failures ?? 0], ['Local fallbacks used', $totals->fallbacks ?? 0]] as [$label, $value])
        <div class="col-6 col-xl-3"><div class="panel h-100"><p class="small text-muted mb-1">{{ $label }}</p><strong class="display-6">{{ number_format((int) $value) }}</strong></div></div>
    @endforeach
</div>

<section class="panel mb-4"><h2 class="h4">By AI operation</h2><p class="small text-muted">Since {{ $since->format('M j, Y g:i A') }}</p>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Feature</th><th>Operation</th><th>Attempts</th><th>Successes</th><th>Failures</th><th>Fallbacks</th><th>Last event</th></tr></thead><tbody>
    @forelse($operations as $operation)<tr><td>{{ str($operation->feature)->headline() }}</td><td>{{ str($operation->operation)->headline() }}</td><td>{{ $operation->attempts }}</td><td>{{ $operation->successes }}</td><td>{{ $operation->failures }}</td><td>{{ $operation->fallbacks }}</td><td>{{ \Illuminate\Support\Carbon::parse($operation->last_seen)->diffForHumans() }}</td></tr>
    @empty<tr><td colspan="7" class="text-muted">No AI service calls recorded in this period.</td></tr>@endforelse
    </tbody></table></div>
</section>

<section class="panel"><h2 class="h4">Recent model events</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Time</th><th>Feature / operation</th><th>Status</th><th>Model</th><th>HTTP</th><th>Latency</th><th>Fallback</th><th>Failure class</th></tr></thead><tbody>
@forelse($recent as $event)<tr><td>{{ $event->created_at->format('M j, g:i:s A') }}</td><td>{{ str($event->feature)->headline() }} · {{ str($event->operation)->headline() }}</td><td>{{ str($event->status)->headline() }}</td><td>{{ $event->model ?: '—' }}</td><td>{{ $event->http_status ?: '—' }}</td><td>{{ $event->latency_ms !== null ? $event->latency_ms.' ms' : '—' }}</td><td>{{ $event->fallback_used ? 'Yes' : 'No' }}</td><td>{{ $event->failure_code ?: '—' }}</td></tr>
@empty<tr><td colspan="8" class="text-muted">No AI service events have been recorded.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
