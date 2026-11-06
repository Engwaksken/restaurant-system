@extends('layouts.app')

@section('title', 'Conversation')
@section('page-title', 'Conversation History')

@section('content')
<div class="panel p-4">
    <a class="btn btn-outline-secondary btn-sm mb-3" href="{{ route('admin.chatbot.index') }}">Back to conversations</a>
    <h2 class="h5">{{ $conversation->customer?->name ?? 'Guest customer' }}</h2>
    <p class="text-muted">{{ ucfirst($conversation->status) }} · Assigned to {{ $conversation->assignee?->name ?? 'no staff member' }}</p>
    <p class="alert alert-info">Conversation history is read-only. Staff replies and AI prompts are not enabled.</p>
    <div class="list-group mb-3">
        @forelse($messages as $message)
            <div class="list-group-item">
                <div class="d-flex justify-content-between gap-3"><strong>{{ ucfirst($message->sender_type) }}{{ $message->user?->name ? ' · ' . $message->user->name : '' }}</strong><small class="text-muted">{{ $message->created_at->format('Y-m-d H:i') }}</small></div>
                <p class="mb-0 mt-2" style="white-space:pre-wrap">{{ $message->message }}</p>
            </div>
        @empty<p class="text-muted">No messages in this conversation.</p>@endforelse
    </div>
    {{ $messages->links() }}
</div>
@endsection
