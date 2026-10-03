@extends('layouts.app')
@section('title', 'Privacy audit trail')
@section('content')
<header class="page-heading"><div><p class="section-kicker">Privacy controls</p><h1>Room-photo data activity</h1><p>Recorded consent, Gemini image transfers, user deletions, and scheduled retention cleanup.</p></div></header>
<div class="table-responsive panel"><table class="table align-middle"><thead><tr><th>When</th><th>Action</th><th>Account</th><th>Record</th><th>Details</th></tr></thead><tbody>@forelse($events as $event)<tr><td>{{ $event->created_at->format('M j, Y g:i A') }}</td><td>{{ str($event->action)->replace('_', ' ')->title() }}</td><td>{{ $event->user?->name ?? 'Deleted or system account' }}</td><td>{{ $event->subject_type ? $event->subject_type.' #'.$event->subject_id : '—' }}</td><td><code>{{ $event->metadata ? json_encode($event->metadata, JSON_UNESCAPED_SLASHES) : '—' }}</code></td></tr>@empty<tr><td colspan="5">No privacy events recorded.</td></tr>@endforelse</tbody></table></div>
<div class="mt-3">{{ $events->links() }}</div>
@endsection
