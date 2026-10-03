<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemUsabilityResponse;

class UsabilityResultsController extends Controller
{
    public function index()
    {
        $responses = SystemUsabilityResponse::with('user')->latest()->paginate(20);
        $scores = SystemUsabilityResponse::pluck('sus_score')->map(fn ($score) => (float) $score);
        $respondentCount = $scores->count();
        $meanScore = $scores->isEmpty() ? null : $scores->avg();
        $medianScore = $scores->isEmpty() ? null : $scores->median();

        return view('admin.usability-results', compact('responses', 'respondentCount', 'meanScore', 'medianScore'));
    }
}
