@extends('layouts.app')

@section('title', 'Deployment readiness')

@section('content')
<header class="page-heading"><div><p class="section-kicker">Operations</p><h1>Deployment readiness</h1><p>This checks the running app’s configuration. Passing checks do not verify hosting, successful email delivery, backups, or a restore drill.</p></div></header>
<div class="alert {{ $ready ? 'alert-success' : 'alert-warning' }} fc-alert" role="status"><strong>{{ $ready ? 'Configuration checks passed' : 'Not production ready' }}</strong><span>{{ $ready ? 'Complete the deployment and recovery checks in the runbook before serving real customers.' : 'Resolve the blocked settings for the target host before deployment.' }}</span></div>
<section class="panel"><h2 class="h4">Configuration checks</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Check</th><th>Status</th><th>Details</th></tr></thead><tbody>@foreach($checks as $check)<tr><th scope="row">{{ $check['name'] }}</th><td><span class="status status-{{ $check['ok'] ? 'confirmed' : 'pending' }}">{{ $check['ok'] ? 'Pass' : 'Blocked' }}</span></td><td>{{ $check['detail'] }}</td></tr>@endforeach</tbody></table></div></section>
<p class="small text-muted mt-3">Never paste secrets into this page, logs, or support requests. Environment values are not displayed.</p>
@endsection
