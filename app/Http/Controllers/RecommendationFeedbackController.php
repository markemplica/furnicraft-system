<?php

namespace App\Http\Controllers;

use App\Models\Recommendation;
use App\Models\RecommendationItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecommendationFeedbackController extends Controller
{
    public function update(Request $request, Recommendation $recommendation, RecommendationItem $recommendationItem)
    {
        abort_unless($recommendation->user_id === $request->user()->id, 403);
        abort_unless($recommendationItem->recommendation_id === $recommendation->id, 404);

        $data = $request->validate([
            'relevance' => ['required', Rule::in(['1', '0'])],
            'rejection_reason' => ['nullable', Rule::requiredIf($request->input('relevance') === '0'), Rule::in(['style', 'price', 'material', 'dimensions', 'room_fit', 'other'])],
        ]);

        $relevant = $data['relevance'] === '1';
        $recommendationItem->update([
            'relevance' => $relevant,
            'rejection_reason' => $relevant ? null : $data['rejection_reason'],
            'evaluated_at' => now(),
        ]);

        return back()->with('success', 'Thanks. Your response will help evaluate recommendation relevance.');
    }
}
