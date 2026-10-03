<?php

namespace App\Http\Controllers;

use App\Models\SystemUsabilityResponse;
use Illuminate\Http\Request;

class SystemUsabilityController extends Controller
{
    public function edit(Request $request)
    {
        $response = SystemUsabilityResponse::where('user_id', $request->user()->id)->first();

        return view('usability.edit', compact('response'));
    }

    public function store(Request $request)
    {
        $rules = [];
        for ($question = 1; $question <= 10; $question++) {
            $rules['q'.$question] = ['required', 'integer', 'between:1,5'];
        }
        $data = $request->validate($rules);
        $sum = 0;
        $answers = [];
        for ($questionNumber = 1; $questionNumber <= 10; $questionNumber++) {
            $answer = (int) $data['q'.$questionNumber];
            $sum += $questionNumber % 2 === 1 ? $answer - 1 : 5 - $answer;
            $answers[] = $answer;
        }
        SystemUsabilityResponse::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['answers' => $answers, 'sus_score' => $sum * 2.5],
        );

        return redirect()->route('usability.edit')->with('success', 'Your usability survey response was saved.');
    }
}
