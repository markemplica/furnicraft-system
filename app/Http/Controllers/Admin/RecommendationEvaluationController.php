<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recommendation;
use App\Models\RecommendationItem;
use App\Models\Reservation;

class RecommendationEvaluationController extends Controller
{
    public function index()
    {
        $recommendations = Recommendation::with(['items' => fn ($query) => $query->orderBy('position')])
            ->whereHas('items', fn ($query) => $query->whereNotNull('relevance'))
            ->get();
        $judgments = RecommendationItem::whereNotNull('relevance')->get();
        $judgedRecommendationIds = $judgments->pluck('recommendation_id')->unique();
        $reservations = Reservation::whereNotNull('recommendation_id')->distinct('recommendation_id')->count('recommendation_id');
        $totalRecommendations = Recommendation::count();

        $metrics = [
            'total_recommendations' => $totalRecommendations,
            'judged_recommendations' => $judgedRecommendationIds->count(),
            'judged_items' => $judgments->count(),
            'relevant_items' => $judgments->where('relevance', true)->count(),
            'not_relevant_items' => $judgments->where('relevance', false)->count(),
            'acceptance_rate' => $judgments->isEmpty() ? null : $judgments->where('relevance', true)->count() / $judgments->count() * 100,
            'feedback_coverage' => $totalRecommendations === 0 ? null : $judgedRecommendationIds->count() / $totalRecommendations * 100,
            'reservation_recommendations' => $reservations,
            'reservation_conversion' => $totalRecommendations === 0 ? null : $reservations / $totalRecommendations * 100,
            'hybrid_at_3' => $this->precisionAt($recommendations, 'total_score', 3),
            'content_at_3' => $this->precisionAt($recommendations, 'content_score', 3),
            'hybrid_at_5' => $this->precisionAt($recommendations, 'total_score', 5),
            'content_at_5' => $this->precisionAt($recommendations, 'content_score', 5),
            'hybrid_coverage_at_3' => $this->judgmentCoverageAt($recommendations, 'total_score', 3),
            'content_coverage_at_3' => $this->judgmentCoverageAt($recommendations, 'content_score', 3),
            'hybrid_coverage_at_5' => $this->judgmentCoverageAt($recommendations, 'total_score', 5),
            'content_coverage_at_5' => $this->judgmentCoverageAt($recommendations, 'content_score', 5),
            'cold_start' => $this->groupAcceptance($judgments, $recommendations, true),
            'established' => $this->groupAcceptance($judgments, $recommendations, false),
        ];

        $rejectionReasons = $judgments->where('relevance', false)->groupBy('rejection_reason')->map->count()->sortDesc();

        return view('admin.recommendation-evaluation', compact('metrics', 'rejectionReasons'));
    }

    private function precisionAt($recommendations, string $scoreColumn, int $k): ?float
    {
        $relevant = 0;
        $judged = 0;

        foreach ($recommendations as $recommendation) {
            $top = $recommendation->items->sortByDesc($scoreColumn)->take($k)->filter(fn ($item) => $item->relevance !== null);
            $relevant += $top->where('relevance', true)->count();
            $judged += $top->count();
        }

        return $judged === 0 ? null : $relevant / $judged * 100;
    }

    private function judgmentCoverageAt($recommendations, string $scoreColumn, int $k): ?float
    {
        $slots = 0;
        $judged = 0;

        foreach ($recommendations as $recommendation) {
            $top = $recommendation->items->sortByDesc($scoreColumn)->take($k);
            $slots += $top->count();
            $judged += $top->filter(fn ($item) => $item->relevance !== null)->count();
        }

        return $slots === 0 ? null : $judged / $slots * 100;
    }

    private function groupAcceptance($judgments, $recommendations, bool $coldStart): array
    {
        $ids = $recommendations->filter(fn ($recommendation) => $recommendation->is_cold_start === $coldStart)->pluck('id');
        $group = $judgments->whereIn('recommendation_id', $ids);

        return [
            'count' => $group->count(),
            'rate' => $group->isEmpty() ? null : $group->where('relevance', true)->count() / $group->count() * 100,
        ];
    }
}
