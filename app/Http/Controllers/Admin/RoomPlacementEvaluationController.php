<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomImage;
use App\Models\RoomPlacementEvaluation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomPlacementEvaluationController extends Controller
{
    private const DIMENSIONS = ['floor_detection_correct', 'zone_appropriate', 'clearance_respected', 'scale_plausible'];

    public function index(Request $request)
    {
        $rooms = RoomImage::whereNotNull('analyzed_at')->latest('analyzed_at')->paginate(12);
        $evaluations = RoomPlacementEvaluation::where('evaluator_id', $request->user()->id)
            ->whereIn('room_image_id', $rooms->pluck('id'))->get()->keyBy('room_image_id');
        $allEvaluations = RoomPlacementEvaluation::with('roomImage')->get();
        $sourceCounts = [
            'gemini' => $allEvaluations->filter(fn ($evaluation) => data_get($evaluation->roomImage?->analysis, 'gemini_used') === true)->count(),
            'fallback' => $allEvaluations->filter(fn ($evaluation) => data_get($evaluation->roomImage?->analysis, 'gemini_used') !== true)->count(),
        ];
        $metrics = [];
        foreach (self::DIMENSIONS as $dimension) {
            $judged = $allEvaluations->whereNotNull($dimension);
            $metrics[$dimension] = [
                'count' => $judged->count(),
                'correct' => $judged->where($dimension, true)->count(),
                'rate' => $judged->isEmpty() ? null : $judged->where($dimension, true)->count() / $judged->count() * 100,
            ];
        }

        return view('admin.room-placement-evaluation', compact('rooms', 'evaluations', 'metrics', 'sourceCounts'));
    }

    public function store(Request $request, RoomImage $roomImage)
    {
        abort_unless($roomImage->analyzed_at, 404);
        $rules = ['notes' => ['nullable', 'string', 'max:2000']];
        foreach (self::DIMENSIONS as $dimension) {
            $rules[$dimension] = ['required', Rule::in(['yes', 'no', 'not_applicable'])];
        }
        $data = $request->validate($rules);
        $assessment = ['room_image_id' => $roomImage->id, 'evaluator_id' => $request->user()->id, 'notes' => $data['notes'] ?? null];
        foreach (self::DIMENSIONS as $dimension) {
            $assessment[$dimension] = $data[$dimension] === 'not_applicable' ? null : $data[$dimension] === 'yes';
        }
        RoomPlacementEvaluation::updateOrCreate(
            ['room_image_id' => $roomImage->id, 'evaluator_id' => $request->user()->id],
            $assessment,
        );

        return back()->with('success', 'Room placement assessment saved.');
    }
}
