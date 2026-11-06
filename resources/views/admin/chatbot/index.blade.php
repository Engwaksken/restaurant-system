@extends('layouts.app')

@section('title', 'Restaurant Chatbot')
@section('page-title', 'Restaurant Chatbot')

@section('content')
<div class="container-fluid px-0">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div>
            <div class="text-muted small mb-1">
                <i class="fas fa-robot me-1"></i>
                AI Assistant
            </div>

            <h2 class="h5 fw-bold mb-1">
                <i class="fas fa-comments text-primary me-2"></i>
                Restaurant Chatbot
            </h2>

            <p class="text-muted mb-0">
                Ask about menu items, prices, availability, orders, reservations, stock, suppliers and restaurant information.
            </p>
        </div>

        <button type="button"
                class="btn btn-outline-secondary"
                id="clearChatButton">
            <i class="fas fa-broom me-1"></i>
            Clear Screen
        </button>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <div class="d-flex align-items-center justify-content-between gap-3">
                <div>
                    <strong>
                        <i class="fas fa-robot text-primary me-2"></i>
                        Restaurant Assistant
                    </strong>
                    <div class="text-muted small">
                        Answers are grounded in your restaurant database.
                    </div>
                </div>

                <span class="badge text-bg-success">
                    <i class="fas fa-database me-1"></i>
                    Database Connected
                </span>
            </div>
        </div>

        <div class="card-body p-0">
            <div id="chatMessages"
                 class="p-3 p-md-4"
                 style="height:58vh; min-height:420px; overflow-y:auto; background:#f8f9fa;">

                @forelse($messages as $message)
                    @php
                        $role = $message->role
                            ?? $message->sender
                            ?? $message->sender_type
                            ?? 'assistant';

                        $content = $message->content
                            ?? $message->message
                            ?? '';
                    @endphp

                    <div class="d-flex mb-3 {{ $role === 'user' ? 'justify-content-end' : 'justify-content-start' }}">
                        <div class="rounded-4 px-3 py-2 shadow-sm
                                    {{ $role === 'user' ? 'bg-primary text-white' : 'bg-white border' }}"
                             style="max-width:78%;">
                            <div class="small fw-semibold mb-1">
                                <i class="fas {{ $role === 'user' ? 'fa-user' : 'fa-robot' }} me-1"></i>
                                {{ $role === 'user' ? 'You' : 'Assistant' }}
                            </div>
                            <div style="white-space:pre-wrap;">{{ $content }}</div>
                        </div>
                    </div>
                @empty
                    <div id="emptyChatState"
                         class="h-100 d-flex align-items-center justify-content-center text-center">
                        <div>
                            <div class="mx-auto mb-3 rounded-circle bg-primary-subtle text-primary
                                        d-flex align-items-center justify-content-center"
                                 style="width:72px;height:72px;">
                                <i class="fas fa-robot fa-2x"></i>
                            </div>

                            <h3 class="h5 fw-bold">Ask about your restaurant</h3>

                            <p class="text-muted mb-3">
                                The assistant scans relevant restaurant records before replying.
                            </p>

                            <div class="d-flex flex-wrap justify-content-center gap-2">
                                <button class="btn btn-sm btn-outline-primary quick-question"
                                        data-question="Which menu items are available?">
                                    Available menu
                                </button>

                                <button class="btn btn-sm btn-outline-primary quick-question"
                                        data-question="What orders are currently pending?">
                                    Pending orders
                                </button>

                                <button class="btn btn-sm btn-outline-primary quick-question"
                                        data-question="Which inventory items are low in stock?">
                                    Low stock
                                </button>

                                <button class="btn btn-sm btn-outline-primary quick-question"
                                        data-question="What reservations do we have?">
                                    Reservations
                                </button>
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>

            <div class="border-top bg-white p-3">
                <form id="chatForm">
                    @csrf

                    <div class="input-group">
                        <textarea id="chatInput"
                                  class="form-control"
                                  rows="2"
                                  maxlength="3000"
                                  placeholder="Ask a question about the restaurant..."
                                  required></textarea>

                        <button type="submit"
                                class="btn btn-primary px-4"
                                id="chatSendButton">
                            <i class="fas fa-paper-plane me-1"></i>
                            Send
                        </button>
                    </div>

                    <div class="form-text">
                        The chatbot reads matching live records from the restaurant system before answering.
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('chatForm');
    const input = document.getElementById('chatInput');
    const messages = document.getElementById('chatMessages');
    const sendButton = document.getElementById('chatSendButton');
    const clearButton = document.getElementById('clearChatButton');

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }

    function scrollBottom() {
        messages.scrollTop = messages.scrollHeight;
    }

    function removeEmptyState() {
        document.getElementById('emptyChatState')?.remove();
    }

    function appendMessage(role, content) {
        removeEmptyState();

        const row = document.createElement('div');
        row.className = 'd-flex mb-3 ' + (role === 'user' ? 'justify-content-end' : 'justify-content-start');

        row.innerHTML = `
            <div class="rounded-4 px-3 py-2 shadow-sm ${role === 'user' ? 'bg-primary text-white' : 'bg-white border'}"
                 style="max-width:78%;">
                <div class="small fw-semibold mb-1">
                    <i class="fas ${role === 'user' ? 'fa-user' : 'fa-robot'} me-1"></i>
                    ${role === 'user' ? 'You' : 'Assistant'}
                </div>
                <div style="white-space:pre-wrap;">${escapeHtml(content)}</div>
            </div>
        `;

        messages.appendChild(row);
        scrollBottom();
    }

    async function ask(question) {
        question = question.trim();

        if (!question) {
            return;
        }

        appendMessage('user', question);
        input.value = '';
        input.disabled = true;
        sendButton.disabled = true;

        const loadingId = 'chat-loading-' + Date.now();
        const loading = document.createElement('div');
        loading.id = loadingId;
        loading.className = 'd-flex mb-3 justify-content-start';
        loading.innerHTML = `
            <div class="rounded-4 px-3 py-2 shadow-sm bg-white border">
                <i class="fas fa-spinner fa-spin me-2"></i>
                Scanning restaurant information...
            </div>
        `;
        messages.appendChild(loading);
        scrollBottom();

        try {
            const response = await fetch(@json(route('admin.chatbot.ask')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                },
                body: JSON.stringify({
                    message: question
                })
            });

            const data = await response.json();

            document.getElementById(loadingId)?.remove();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Unable to get a response.');
            }

            appendMessage('assistant', data.reply);

        } catch (error) {
            document.getElementById(loadingId)?.remove();

            appendMessage(
                'assistant',
                error.message || 'The chatbot could not process the question.'
            );
        } finally {
            input.disabled = false;
            sendButton.disabled = false;
            input.focus();
        }
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        ask(input.value);
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    document.querySelectorAll('.quick-question').forEach(function (button) {
        button.addEventListener('click', function () {
            ask(button.dataset.question || '');
        });
    });

    clearButton.addEventListener('click', function () {
        messages.innerHTML = `
            <div id="emptyChatState"
                 class="h-100 d-flex align-items-center justify-content-center text-center">
                <div>
                    <div class="mx-auto mb-3 rounded-circle bg-primary-subtle text-primary
                                d-flex align-items-center justify-content-center"
                         style="width:72px;height:72px;">
                        <i class="fas fa-robot fa-2x"></i>
                    </div>
                    <h3 class="h5 fw-bold">Chat screen cleared</h3>
                    <p class="text-muted mb-0">Ask another question about the restaurant.</p>
                </div>
            </div>
        `;
    });

    scrollBottom();
});
</script>
@endpush
