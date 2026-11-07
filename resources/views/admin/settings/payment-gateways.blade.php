@extends('layouts.app')

@section('title', 'Payment Gateways')
@section('page-title', 'Payment Gateways')
@section('page-subtitle', 'Let customers pay online when they order from your website')
@section('own-validation-errors', '1')

@section('content')
@php
    $activeTab = request('tab');
    foreach ($gateways as $key => $gateway) {
        if ($errors->getBag($key)->any()) { $activeTab = $key; break; }
    }
    $activeTab = $gateways->has($activeTab) ? $activeTab : $gateways->keys()->first();
@endphp

<x-admin.stats :items="$pageStats" />

<div class="alert alert-info d-flex gap-2 mb-4">
    <i class="fas fa-shield-halved mt-1"></i>
    <div>
        Keys are stored encrypted and never shown again in full. Leave a secret field blank to keep the saved value.
        Start in <strong>test mode</strong>, place a test order on your website, then switch to <strong>live mode</strong>.
    </div>
</div>

<div class="panel p-0">
    <ul class="nav nav-tabs settings-tabs px-3 pt-2" role="tablist">
        @foreach($gateways as $key => $gateway)
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTab === $key ? 'active' : '' }}" id="tab-{{ $key }}" data-bs-toggle="tab" data-bs-target="#pane-{{ $key }}" type="button" role="tab" aria-controls="pane-{{ $key }}" aria-selected="{{ $activeTab === $key ? 'true' : 'false' }}">
                    <i class="{{ $gateway['icon'] }} me-2"></i>{{ $gateway['label'] }}
                    @if($gateway['ready'])
                        <span class="badge {{ $gateway['mode'] === 'live' ? 'bg-success' : 'bg-info' }} ms-2">{{ ucfirst($gateway['mode']) }}</span>
                    @elseif($gateway['usesEnv'])
                        <span class="badge bg-secondary ms-2">Server config</span>
                    @else
                        <span class="badge bg-light ms-2">Off</span>
                    @endif
                    @if($errors->getBag($key)->any())<span class="tab-error-dot" aria-hidden="true"></span>@endif
                </button>
            </li>
        @endforeach
    </ul>

    <div class="tab-content">
        @foreach($gateways as $key => $gateway)
            @php($bag = $errors->getBag($key))
            <div class="tab-pane fade {{ $activeTab === $key ? 'show active' : '' }}" id="pane-{{ $key }}" role="tabpanel" aria-labelledby="tab-{{ $key }}" tabindex="0">
                <form method="POST" action="{{ route('admin.settings.payment-gateways.update', $key) }}" class="p-4" autocomplete="off">
                    @csrf
                    @method('PUT')

                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                        <div>
                            <h2 class="h5 mb-1">{{ $gateway['label'] }}</h2>
                            <p class="text-muted mb-0">{{ $gateway['description'] }}</p>
                            @if($gateway['setting']?->updated_at)
                                <small class="text-muted"><i class="fas fa-clock-rotate-left me-1"></i>Last changed {{ $gateway['setting']->updated_at->diffForHumans() }}{{ $gateway['setting']->editor ? ' by ' . $gateway['setting']->editor->name : '' }}</small>
                            @endif
                        </div>
                        <a href="{{ $gateway['docs'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fas fa-book me-1"></i>Where do I find my keys?</a>
                    </div>

                    @if($gateway['usesEnv'])
                        <div class="alert alert-secondary small"><i class="fas fa-server me-1"></i>{{ $gateway['label'] }} is currently set up in the server configuration. Saving here will take over from it.</div>
                    @endif

                    @if($bag->any())
                        <div class="alert alert-danger small"><i class="fas fa-triangle-exclamation me-1"></i>{{ $bag->first() }}</div>
                    @endif

                    <div class="row g-4">
                        <div class="col-lg-5">
                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" role="switch" id="{{ $key }}-enabled" name="is_enabled" value="1" @checked($bag->any() ? old('is_enabled') : $gateway['enabled'])>
                                <label class="form-check-label fw-semibold" for="{{ $key }}-enabled">Accept payments with {{ $gateway['label'] }}</label>
                                <div class="form-text">When on, customers see this option at checkout.</div>
                            </div>

                            <div class="panel-section-title mb-2">Mode</div>
                            @foreach(['test' => ['Test mode', 'fas fa-flask', 'Sandbox only. No real money moves.'], 'live' => ['Live mode', 'fas fa-bolt', 'Customers are charged for real.']] as $mode => [$label, $icon, $help])
                                <label class="d-flex gap-3 align-items-start border rounded p-3 mb-2 mode-option" for="{{ $key }}-mode-{{ $mode }}">
                                    <input class="form-check-input mt-1" type="radio" name="mode" id="{{ $key }}-mode-{{ $mode }}" value="{{ $mode }}" @checked(($bag->any() ? old('mode', $gateway['mode']) : $gateway['mode']) === $mode)>
                                    <span>
                                        <span class="d-block fw-semibold"><i class="{{ $icon }} me-1"></i>{{ $label }}</span>
                                        <span class="d-block small text-muted">{{ $help }}</span>
                                        <code class="small">{{ $gateway['base_urls'][$mode] }}</code>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <div class="col-lg-7">
                            <div class="panel-section-title mb-2">Credentials</div>
                            @foreach($gateway['fields'] as $field => $meta)
                                <div class="mb-3">
                                    <label class="form-label" for="{{ $key }}-{{ $field }}">
                                        {{ $meta['label'] }}
                                        @if(in_array($field, $gateway['required'], true))<span class="text-danger">*</span>@endif
                                    </label>
                                    <div class="input-group has-validation">
                                        <span class="input-group-text"><i class="fas {{ $meta['secret'] ? 'fa-lock' : 'fa-key' }}"></i></span>
                                        @if($meta['secret'])
                                            <input class="form-control font-monospace @if($bag->has($field)) is-invalid @endif" type="password" id="{{ $key }}-{{ $field }}" name="{{ $field }}" value="" placeholder="{{ $gateway['values'][$field] ? 'Saved: ' . $gateway['values'][$field] : 'Not set' }}" autocomplete="new-password" spellcheck="false">
                                            <button class="btn btn-light border" type="button" data-toggle-secret="{{ $key }}-{{ $field }}" aria-label="Show {{ strtolower($meta['label']) }}"><i class="fas fa-eye"></i></button>
                                        @else
                                            <input class="form-control font-monospace @if($bag->has($field)) is-invalid @endif" type="text" id="{{ $key }}-{{ $field }}" name="{{ $field }}" value="{{ $bag->any() ? old($field, $gateway['values'][$field]) : $gateway['values'][$field] }}" autocomplete="off" spellcheck="false">
                                        @endif
                                        @if($bag->has($field))<div class="invalid-feedback">{{ $bag->first($field) }}</div>@endif
                                    </div>
                                    <div class="form-text">{{ $meta['help'] }}</div>
                                    @if($meta['secret'] && $gateway['values'][$field])
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="checkbox" value="1" id="{{ $key }}-clear-{{ $field }}" name="clear_{{ $field }}">
                                            <label class="form-check-label small" for="{{ $key }}-clear-{{ $field }}">Remove the saved {{ strtolower($meta['label']) }}</label>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3 mt-2">
                        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-floppy-disk me-2"></i>Save {{ $gateway['label'] }}</button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-toggle-secret]').forEach(function (button) {
    button.addEventListener('click', function () {
        const input = document.getElementById(button.dataset.toggleSecret);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
});
</script>
@endpush
