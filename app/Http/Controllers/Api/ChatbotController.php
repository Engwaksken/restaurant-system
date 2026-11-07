<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Services\Ai\ChatAttachment;
use App\Services\Ai\RestaurantAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Website chat: AI when the restaurant has switched it on, simple menu answers otherwise. */
class ChatbotController extends Controller
{
    public function message(Request $request, RestaurantAssistant $assistant): JsonResponse
    {
        $data = $request->validate([
            'message' => ['nullable', 'required_without:file', 'string', 'max:1000'],
            'file' => ChatAttachment::rules(),
        ], [
            'file.max' => 'That file is too large. Please send one under 5 MB.',
            'file.mimetypes' => 'Please attach a photo (JPG, PNG, WebP or GIF) or a PDF.',
            'message.required_without' => 'Type a message or attach a file.',
        ]);
        $restaurant = Restaurant::query()
            ->where('code', config('restaurant.public_code'))
            ->where('is_active', true)
            ->firstOrFail();

        $answer = $assistant->reply($restaurant, (string) ($data['message'] ?? ''), attachment: ChatAttachment::fromUpload($request->file('file')));

        return response()->json(['reply' => $answer['reply'], 'items' => $answer['items']]);
    }
}
