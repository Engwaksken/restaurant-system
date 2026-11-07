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

        $scoped = ChatConversation::query()->where('restaurant_id', $user->restaurant_id);
        $averageRating = (clone $scoped)->whereNotNull('rating')->avg('rating');
        $pageStats = [
            ['label' => 'Conversations', 'value' => number_format((clone $scoped)->count()), 'icon' => 'fas fa-comments', 'tone' => 'brand'],
            ['label' => 'Active today', 'value' => number_format((clone $scoped)->where('last_activity_at', '>=', now()->startOfDay())->count()), 'icon' => 'fas fa-signal', 'tone' => 'success'],
            ['label' => 'Waiting for staff', 'value' => number_format((clone $scoped)->where('status', 'waiting')->count()), 'icon' => 'fas fa-hourglass-half', 'tone' => 'warning'],
            ['label' => 'Average rating', 'value' => $averageRating ? number_format((float) $averageRating, 1) . ' / 5' : '—', 'icon' => 'fas fa-star', 'tone' => 'info'],
        ];

        return view('admin.chatbot.index', compact('conversations', 'pageStats'));
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

    /**
     * Staff chat widget. Uses the same assistant as the website, scoped to the
     * user's own restaurant and limited to public menu and contact details.
     */
    public function ask(Request $request, \App\Services\Ai\RestaurantAssistant $assistant): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403);
        $data = $request->validate([
            'message' => ['nullable', 'required_without:file', 'string', 'max:1000'],
            'file' => \App\Services\Ai\ChatAttachment::rules(),
        ]);
        $answer = $assistant->reply(
            \App\Models\Restaurant::query()->findOrFail($user->restaurant_id),
            (string) ($data['message'] ?? ''),
            attachment: \App\Services\Ai\ChatAttachment::fromUpload($request->file('file')),
        );

        return response()->json(['reply' => $answer['reply'], 'items' => $answer['items']]);
    }
}
