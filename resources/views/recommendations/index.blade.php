@extends('layouts.app')
@section('title','Past recommendations')
@section('content')
<header class="page-heading"><div><p class="section-kicker">Saved results</p><h1>Your recommendations</h1><p>Open any result to revisit its ranked furniture and conversation.</p></div><a class="btn btn-primary" href="{{ route('preferences.edit') }}">Create a new set</a></header>
@forelse($recommendations as $recommendation)<a class="panel d-flex justify-content-between align-items-center text-reset text-decoration-none mb-3" href="{{ route('recommendations.show',$recommendation) }}"><div><strong>{{ $recommendation->created_at->format('F j, Y · g:i A') }}{{ $recommendation->furniture_type ? ' · '.$recommendation->furniture_type : '' }}</strong><p class="text-muted mb-0">{{ Str::limit($recommendation->summary,140) }}</p></div><span>{{ $recommendation->items_count }} matches</span></a>@empty<div class="empty-state"><h2>No recommendations yet.</h2><p>Save your budget, room, and finish preferences to create the first set.</p></div>@endforelse
{{ $recommendations->links() }}
@endsection
