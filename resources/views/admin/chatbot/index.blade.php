@extends('layouts.app')

@section('title', 'Chat Conversations')
@section('page-title', 'Customer Conversations')

@section('content')
<x-admin.stats :items="$pageStats ?? []" />
<div class="panel p-4">
    <p class="text-muted">Read-only tenant conversation history. Replies and AI prompts are disabled pending a reviewed workflow.</p>
    <div class="table-responsive"><table class="table table-striped">
        <thead><tr><th>Customer</th><th>Status</th><th>Assigned staff</th><th>Rating</th><th>Last activity</th><th></th></tr></thead>
        <tbody>@forelse($conversations as $conversation)<tr>
            <td>{{ $conversation->customer?->name ?? 'Guest' }} @if($conversation->customer?->phone)<small class="d-block text-muted">{{ $conversation->customer->phone }}</small>@endif</td>
            <td>{{ ucfirst($conversation->status) }}</td><td>{{ $conversation->assignee?->name ?? 'Unassigned' }}</td>
            <td>{{ $conversation->rating ?? '—' }}</td><td>{{ $conversation->last_activity_at?->format('Y-m-d H:i') ?? $conversation->created_at->format('Y-m-d H:i') }}</td>
            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.chatbot.show', $conversation->id) }}">View</a></td>
        </tr>@empty<tr><td colspan="6" class="text-muted">No tenant-owned conversations found.</td></tr>@endforelse</tbody>
    </table></div>
    {{ $conversations->links() }}
</div>
@endsection
