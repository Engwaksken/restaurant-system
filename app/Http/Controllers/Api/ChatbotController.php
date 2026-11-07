<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Services\Ai\RestaurantAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Website chat: AI when the restaurant has switched it on, simple menu answers otherwise. */
class ChatbotController extends Controller
{
    public function message(Request $request, RestaurantAssistant $assistant): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:1000']]);
        $restaurant = Restaurant::query()
            ->where('code', config('restaurant.public_code'))
            ->where('is_active', true)
            ->firstOrFail();

        $answer = $assistant->reply($restaurant, $data['message']);

        return response()->json(['reply' => $answer['reply'], 'items' => $answer['items']]);
    }
}
