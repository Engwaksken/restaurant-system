<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    public function index(): View
    {
        abort(404, 'The admin chatbot is unavailable until conversations are tenant-scoped.');
    }

    public function ask(Request $request): JsonResponse
    {
        abort(404, 'The admin chatbot is unavailable until conversations are tenant-scoped.');
    }
}
