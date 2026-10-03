<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiServiceEvent;
use Illuminate\Support\Carbon;

class AiServiceHealthController extends Controller
{
    public function index()
    {
        $since = now()->subDays(7);
        $events = AiServiceEvent::query()->where('created_at', '>=', $since);
        $totals = (clone $events)->selectRaw("count(*) as attempts, sum(case when status = 'success' then 1 else 0 end) as successes, sum(case when status = 'failure' then 1 else 0 end) as failures, sum(case when fallback_used = 1 then 1 else 0 end) as fallbacks")->first();
        $operations = (clone $events)->selectRaw("feature, operation, count(*) as attempts, sum(case when status = 'success' then 1 else 0 end) as successes, sum(case when status = 'failure' then 1 else 0 end) as failures, sum(case when fallback_used = 1 then 1 else 0 end) as fallbacks, max(created_at) as last_seen")
            ->groupBy('feature', 'operation')->orderByDesc('last_seen')->get();
        $recent = AiServiceEvent::query()->latest()->limit(50)->get();

        return view('admin.ai-service-health', compact('totals', 'operations', 'recent', 'since'));
    }
}
