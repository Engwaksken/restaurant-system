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
    if ($errors->getBag('method')->any() || $methods->contains(fn ($m) => $errors->getBag('method-' . $m->id)->any())) { $activeTab = 'other'; }
    $activeTab = $gateways->has($activeTab) || $activeTab === 'other' ? $activeTab : $gateways->keys()->first();
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
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'other' ? 'active' : '' }}" id="tab-other" data-bs-toggle="tab" data-bs-target="#pane-other" type="button" role="tab" aria-controls="pane-other" aria-selected="{{ $activeTab === 'other' ? 'true' : 'false' }}">
                <i class="fas fa-wallet me-2"></i>Other methods
                <span class="badge bg-light ms-2">{{ $methods->where('is_active', true)->count() }}</span>
            </button>
        </li>
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

        <div class="tab-pane fade {{ $activeTab === 'other' ? 'show active' : '' }}" id="pane-other" role="tabpanel" aria-labelledby="tab-other" tabindex="0">
            <div class="p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1">Other payment methods</h2>
                        <p class="text-muted mb-0">Add ways to pay that don't need an online integration, such as a mobile money merchant code or a bank transfer. Customers see your instructions after ordering, and staff record the payment when it arrives.</p>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#method-modal-new"><i class="fas fa-plus me-2"></i>Add payment method</button>
                </div>

                <div class="list-row">
                    <div class="d-flex align-items-center gap-3">
                        <span class="stat-icon tone-success"><i class="fas fa-money-bill-wave"></i></span>
                        <div><div class="fw-semibold">Pay on delivery or pickup</div><small class="text-muted">Built in and always available.</small></div>
                    </div>
                    <span class="badge bg-success">Always on</span>
                </div>
                @foreach($methods as $method)
                    <div class="list-row">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <span class="stat-icon tone-brand"><i class="{{ $method->icon }}"></i></span>
                            <div class="min-w-0">
                                <div class="fw-semibold">{{ $method->name }}</div>
                                <small class="text-muted d-block text-truncate" style="max-width: 520px">{{ \Illuminate\Support\Str::limit($method->instructions, 110) }}</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge {{ $method->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $method->is_active ? 'Shown at checkout' : 'Hidden' }}</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#method-modal-{{ $method->id }}"><i class="fas fa-pen-to-square me-1"></i>Edit</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#method-delete-{{ $method->id }}" aria-label="Delete {{ $method->name }}"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                @endforeach
                @if($methods->isEmpty())
                    <x-admin.empty icon="fas fa-wallet" title="No other methods yet" message="Add a mobile money merchant code, bank transfer or any other way customers can pay you." />
                @endif
            </div>
        </div>
    </div>
</div>

@foreach(collect([null])->concat($methods) as $method)
    @php($bagName = $method ? 'method-' . $method->id : 'method')
    @php($bag = $errors->getBag($bagName))
    @php($field = fn ($key, $default = null) => $bag->any() ? old($key, $default) : $default)
    <div class="modal fade" id="method-modal-{{ $method?->id ?? 'new' }}" tabindex="-1" aria-hidden="true" @if($bag->any()) data-open-on-load @endif>
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ $method ? route('admin.settings.payment-methods.update', $method->id) : route('admin.settings.payment-methods.store') }}">
                @csrf
                @if($method) @method('PUT') @endif
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-wallet me-2 text-primary"></i>{{ $method ? 'Edit ' . $method->name : 'Add payment method' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if($bag->any())<div class="alert alert-danger small">{{ $bag->first() }}</div>@endif
                    <div class="mb-3">
                        <label class="form-label" for="{{ $bagName }}-name">Name shown to customers <span class="text-danger">*</span></label>
                        <input class="form-control" id="{{ $bagName }}-name" name="name" maxlength="80" required value="{{ $field('name', $method?->name) }}" placeholder="e.g. MTN MoMo Pay">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="{{ $bagName }}-icon">Icon</label>
                        <select class="form-select" id="{{ $bagName }}-icon" name="icon">
                            @foreach($methodIcons as $icon => $label)
                                <option value="{{ $icon }}" @selected($field('icon', $method?->icon ?? 'fas fa-mobile-screen-button') === $icon)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="{{ $bagName }}-instructions">Payment instructions <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="{{ $bagName }}-instructions" name="instructions" rows="4" maxlength="1000" required placeholder="e.g. Dial *165*3#, enter merchant code 123456 and pay the order total.">{{ $field('instructions', $method?->instructions) }}</textarea>
                        <div class="form-text">Shown after the customer places the order. The order number is shown as the payment reference.</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label" for="{{ $bagName }}-order">Display order</label>
                            <input class="form-control" type="number" min="0" max="1000" id="{{ $bagName }}-order" name="display_order" value="{{ $field('display_order', $method?->display_order ?? 0) }}">
                        </div>
                        <div class="col-6 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="{{ $bagName }}-active" name="is_active" value="1" @checked($field('is_active', $method?->is_active ?? true))>
                                <label class="form-check-label" for="{{ $bagName }}-active">Show at checkout</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-1"></i>{{ $method ? 'Save changes' : 'Add method' }}</button>
                </div>
            </form>
        </div>
    </div>
    @if($method)
        <div class="modal fade" id="method-delete-{{ $method->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="POST" action="{{ route('admin.settings.payment-methods.destroy', $method->id) }}">
                    @csrf
                    @method('DELETE')
                    <div class="modal-body text-center p-4">
                        <div class="delete-icon-wrap bg-danger-subtle text-danger"><i class="fas fa-trash fs-4"></i></div>
                        <h5 class="mb-2">Remove {{ $method->name }}?</h5>
                        <p class="text-muted mb-0">Customers will no longer see it at checkout. Existing orders keep their payment choice.</p>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Remove</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endforeach
@endsection


@push('scripts')
<script>
document.querySelectorAll('[data-open-on-load]').forEach(function (modal) {
    if (window.bootstrap) { bootstrap.Modal.getOrCreateInstance(modal).show(); }
});
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
