<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        abort_unless($user && $user->restaurant_id, 403);
        abort_unless($user->can('chatbot.view') || $user->can('chatbot.manage') || $user->can('admin'), 403);
        $conversations = ChatConversation::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->with(['customer:id,name,phone', 'assignee:id,name'])
            ->latest('last_activity_at')->latest('id')->paginate(25)->withQueryString();

        return view('admin.chatbot.index', compact('conversations'));
    }

    public function show(int $conversation): View
    {
        $user = auth()->user();
        abort_unless($user && $user->restaurant_id, 403);
        abort_unless($user->can('chatbot.view') || $user->can('chatbot.manage') || $user->can('admin'), 403);
        $record = ChatConversation::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->with(['customer:id,name,phone', 'assignee:id,name'])
            ->findOrFail($conversation);
        $messages = $record->messages()->with('user:id,name')->orderBy('id')->paginate(50);

        return view('admin.chatbot.show', ['conversation' => $record, 'messages' => $messages]);
    }

    public function ask(Request $request): JsonResponse
    {
        abort(404, 'The admin chatbot is unavailable until conversations are tenant-scoped.');
    }
}
