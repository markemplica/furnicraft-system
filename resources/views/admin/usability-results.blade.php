@extends('layouts.app')
@section('title', 'SUS usability results')
@section('content')
<header class="page-heading"><div><p class="section-kicker">Usability evaluation · SUS</p><h1>System Usability Scale results</h1><p>SUS reports perceived usability. It does not establish recommendation quality, computer-vision accuracy, or furniture fit.</p></div></header>
<section class="metric-grid mb-4" aria-label="SUS results">
    <div class="metric"><span>Respondents</span><strong>{{ number_format($respondentCount) }}</strong></div>
    <div class="metric"><span>Mean SUS score</span><strong>{{ $meanScore === null ? '—' : number_format($meanScore, 1) }}<small> / 100</small></strong></div>
    <div class="metric"><span>Median SUS score</span><strong>{{ $medianScore === null ? '—' : number_format($medianScore, 1) }}<small> / 100</small></strong></div>
</section>
<section class="panel mb-4"><h2 class="h5">Scoring and interpretation</h2><p>For odd-numbered items, subtract 1 from the response. For even-numbered items, subtract the response from 5. Add the adjusted values and multiply by 2.5 to produce a score from 0 to 100. Report this as a descriptive usability score with the respondent count; it is not a percentage correct and no score is shown until responses are collected. Since participation is voluntary, describe the respondents and sampling method in any research report.</p></section>
<section class="panel"><h2 class="h5">Recent responses</h2><div class="table-responsive"><table class="table"><thead><tr><th>Respondent</th><th>Score</th><th>Submitted</th></tr></thead><tbody>@forelse($responses as $response)<tr><td>{{ $response->user?->name ?? 'Deleted account' }}</td><td>{{ number_format($response->sus_score, 1) }}</td><td>{{ $response->updated_at->format('M j, Y') }}</td></tr>@empty<tr><td colspan="3">No completed SUS responses yet.</td></tr>@endforelse</tbody></table></div>{{ $responses->links() }}</section>
@endsection
