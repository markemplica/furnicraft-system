@extends('layouts.app')
@section('title', 'Recommendation evaluation')
@section('content')
<header class="page-heading">
    <div>
        <p class="section-kicker">Model evaluation</p>
        <h1>Recommendation relevance, measured from customer judgments.</h1>
        <p>These are observational prototype metrics. They describe submitted feedback and reservations; they do not prove that one scoring method is universally better.</p>
    </div>
</header>

<section class="metric-grid mb-4" aria-label="Recommendation evaluation counts">
    <div class="metric"><span>Recommendation lists</span><strong>{{ number_format($metrics['total_recommendations']) }}</strong></div>
    <div class="metric"><span>Lists with feedback</span><strong>{{ number_format($metrics['judged_recommendations']) }}</strong><small>{{ $metrics['feedback_coverage'] === null ? '—' : number_format($metrics['feedback_coverage'], 1).'% of lists' }}</small></div>
    <div class="metric"><span>Item judgments</span><strong>{{ number_format($metrics['judged_items']) }}</strong><small>{{ $metrics['relevant_items'] }} relevant · {{ $metrics['not_relevant_items'] }} not relevant</small></div>
    <div class="metric"><span>Observed acceptance</span><strong>{{ $metrics['acceptance_rate'] === null ? '—' : number_format($metrics['acceptance_rate'], 1).'%' }}</strong><small>Relevant judgments / all judgments</small></div>
    <div class="metric"><span>Lists with reservation</span><strong>{{ number_format($metrics['reservation_recommendations']) }}</strong><small>{{ $metrics['reservation_conversion'] === null ? '—' : number_format($metrics['reservation_conversion'], 1).'% of lists' }}</small></div>
</section>

<section class="panel mb-4">
    <h2 class="h4">Ranking comparison on customer-judged items</h2>
    <p>Hybrid ranking uses the recorded total score (82% content-based + 18% collaborative). The baseline ranks the same recommendation list by its content score only. For each method, the observed Precision@K is relevant judgments divided by judged items inside that method’s top K. Unrated items are excluded from the denominator, so judgment coverage should be considered alongside the score.</p>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Metric</th><th>Hybrid 82/18</th><th>Content-only baseline</th></tr></thead>
            <tbody>
                <tr><th>Observed Precision@3</th><td>{{ $metrics['hybrid_at_3'] === null ? 'Not enough feedback' : number_format($metrics['hybrid_at_3'], 1).'%' }}</td><td>{{ $metrics['content_at_3'] === null ? 'Not enough feedback' : number_format($metrics['content_at_3'], 1).'%' }}</td></tr>
                <tr><th>Judgment coverage in top 3</th><td>{{ $metrics['hybrid_coverage_at_3'] === null ? '—' : number_format($metrics['hybrid_coverage_at_3'], 1).'%' }}</td><td>{{ $metrics['content_coverage_at_3'] === null ? '—' : number_format($metrics['content_coverage_at_3'], 1).'%' }}</td></tr>
                <tr><th>Observed Precision@5</th><td>{{ $metrics['hybrid_at_5'] === null ? 'Not enough feedback' : number_format($metrics['hybrid_at_5'], 1).'%' }}</td><td>{{ $metrics['content_at_5'] === null ? 'Not enough feedback' : number_format($metrics['content_at_5'], 1).'%' }}</td></tr>
                <tr><th>Judgment coverage in top 5</th><td>{{ $metrics['hybrid_coverage_at_5'] === null ? '—' : number_format($metrics['hybrid_coverage_at_5'], 1).'%' }}</td><td>{{ $metrics['content_coverage_at_5'] === null ? '—' : number_format($metrics['content_coverage_at_5'], 1).'%' }}</td></tr>
            </tbody>
        </table>
    </div>
    <p class="small text-muted mb-0">New recommendation records snapshot the 82/18 weights and whether the customer had prior favorites or reservations when the list was generated. Cold-start acceptance uses the same explicit relevant/not-relevant judgments.</p>
</section>

<section class="row g-4">
    <div class="col-lg-6"><div class="panel h-100"><h2 class="h5">Cold-start comparison</h2>
        <p>Cold-start means no favorites or reservations existed for the customer at generation time.</p>
        <dl class="row mb-0"><dt class="col-7">Cold-start judgments</dt><dd class="col-5">{{ $metrics['cold_start']['count'] }}</dd><dt class="col-7">Cold-start acceptance</dt><dd class="col-5">{{ $metrics['cold_start']['rate'] === null ? '—' : number_format($metrics['cold_start']['rate'], 1).'%' }}</dd><dt class="col-7">Established-user judgments</dt><dd class="col-5">{{ $metrics['established']['count'] }}</dd><dt class="col-7">Established-user acceptance</dt><dd class="col-5">{{ $metrics['established']['rate'] === null ? '—' : number_format($metrics['established']['rate'], 1).'%' }}</dd></dl>
    </div></div>
    <div class="col-lg-6"><div class="panel h-100"><h2 class="h5">Reasons marked not relevant</h2>
        @if($rejectionReasons->isEmpty())<p class="text-muted mb-0">No rejection reasons recorded yet.</p>@else<ul class="mb-0">@foreach($rejectionReasons as $reason => $count)<li>{{ str($reason ?: 'unspecified')->replace('_', ' ')->title() }}: {{ $count }}</li>@endforeach</ul>@endif
    </div></div>
</section>
@endsection
