<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Recommendation;
use App\Services\GeminiService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function store(Request $request, Recommendation $recommendation, GeminiService $gemini)
    {
        abort_unless($recommendation->user_id === $request->user()->id, 403);
        $data = $request->validate(['message' => ['required', 'string', 'max:1000']]);
        ChatMessage::create(['user_id' => $request->user()->id, 'recommendation_id' => $recommendation->id, 'role' => 'user', 'message' => $data['message']]);
        $answer = $gemini->chat($recommendation->load('items.product'), $data['message']);
        $source = $answer['source'] ?? ($answer['used'] ? 'gemini' : 'local-fallback');
        ChatMessage::create(['user_id' => $request->user()->id, 'recommendation_id' => $recommendation->id, 'role' => 'assistant', 'message' => $answer['text'], 'gemini_used' => $answer['used'], 'ai_source' => $source]);

        $notice = match ($source) {
            'local-fallback' => 'Gemini is temporarily unavailable. FurniCraft replied using local catalog guidance.',
            'catalog-rule' => 'FurniCraft replied using current catalog listings. Physical branch stock still needs confirmation.',
            default => 'FurniCraft replied to your question.',
        };

        return back()->with('success', $notice);
    }
}
