@extends('layouts.app')

@section('title', $group['label'] . ' Settings')
@section('page-title', $group['label'] . ' Settings')
@section('page-subtitle', $group['description'])

@section('content')
@php
    $sections = $group['sections'];
    $firstErrorSection = null;
    foreach ($sections as $sectionLabel => $keys) {
        foreach ($keys as $key) {
            if ($errors->has($key)) { $firstErrorSection = $sectionLabel; break 2; }
        }
    }
    $activeSection = $firstErrorSection ?? array_key_first($sections);
    $value = fn (string $key) => old($key, $values[$key] ?? null);
    $isOn = fn (string $key) => filter_var(old($key, $values[$key] ?? false), FILTER_VALIDATE_BOOL);
    $tests = [
        'email' => ['label' => 'Send a test email', 'field' => 'test_to', 'type' => 'email', 'placeholder' => 'you@example.com', 'icon' => 'fas fa-paper-plane', 'button' => 'Send test email', 'help' => 'Save your settings first. The test uses the saved settings.'],
        'sms' => ['label' => 'Send a test SMS', 'field' => 'test_to', 'type' => 'tel', 'placeholder' => '0772 123 456', 'icon' => 'fas fa-comment-sms', 'button' => 'Send test SMS', 'help' => 'Save your settings first. Uses the saved provider even if notifications are off.'],
        'ai' => ['label' => 'Ask the assistant', 'field' => 'test_question', 'type' => 'text', 'placeholder' => 'What do you recommend for lunch?', 'icon' => 'fas fa-wand-magic-sparkles', 'button' => 'Ask', 'help' => 'Save your settings first. Uses your real menu; each test is a billed API call.'],
    ];
    $test = $tests[$groupKey] ?? null;
@endphp

<div class="row g-4">
    <div class="{{ $test ? 'col-xl-8' : 'col-12' }}">
        <form method="POST" action="{{ route('admin.settings.system.update', $groupKey) }}" autocomplete="off" id="settingsForm">
            @csrf
            @method('PUT')

            <div class="panel p-0 mb-3">
                <ul class="nav nav-tabs settings-tabs px-3 pt-2" role="tablist">
                    @foreach($sections as $sectionLabel => $keys)
                        @php($sectionId = \Illuminate\Support\Str::slug($sectionLabel))
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $sectionLabel === $activeSection ? 'active' : '' }}" id="tab-{{ $sectionId }}" data-bs-toggle="tab" data-bs-target="#pane-{{ $sectionId }}" type="button" role="tab" aria-controls="pane-{{ $sectionId }}" aria-selected="{{ $sectionLabel === $activeSection ? 'true' : 'false' }}">
                                {{ $sectionLabel }}
                                @if(collect($keys)->contains(fn ($key) => $errors->has($key)))<span class="tab-error-dot" aria-hidden="true"></span>@endif
                            </button>
                        </li>
                    @endforeach
                </ul>

                <div class="tab-content">
                    @foreach($sections as $sectionLabel => $keys)
                        @php($sectionId = \Illuminate\Support\Str::slug($sectionLabel))
                        <div class="tab-pane fade {{ $sectionLabel === $activeSection ? 'show active' : '' }} p-4" id="pane-{{ $sectionId }}" role="tabpanel" aria-labelledby="tab-{{ $sectionId }}" tabindex="0">
                            <div class="row g-3">
                                @foreach($keys as $key)
                                    @php($field = $group['fields'][$key])
                                    @php($showIf = $field['show_if'] ?? null)
                                    <div class="{{ in_array($field['type'], ['textarea', 'toggle'], true) ? 'col-12' : 'col-md-6' }}"
                                         @if($showIf) data-show-if="{{ array_key_first($showIf) }}" data-show-value="{{ reset($showIf) }}" @endif>
                                        @if($field['type'] === 'toggle')
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="{{ $key }}" value="0">
                                                <input class="form-check-input" type="checkbox" role="switch" id="{{ $key }}" name="{{ $key }}" value="1" @checked($isOn($key))>
                                                <label class="form-check-label fw-semibold" for="{{ $key }}">{{ $field['label'] }}</label>
                                                @if(!empty($field['help']))<div class="form-text">{{ $field['help'] }}</div>@endif
                                            </div>
                                        @else
                                            <label class="form-label" for="{{ $key }}">{{ $field['label'] }}</label>
                                            @if($field['type'] === 'select')
                                                <select class="form-select @error($key) is-invalid @enderror" id="{{ $key }}" name="{{ $key }}">
                                                    @foreach($field['options'] as $optionValue => $optionLabel)
                                                        <option value="{{ $optionValue }}" @selected((string) $value($key) === (string) $optionValue)>{{ $optionLabel }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif($field['type'] === 'model')
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-microchip"></i></span>
                                                    <input class="form-control font-monospace @error($key) is-invalid @enderror" id="{{ $key }}" name="{{ $key }}" value="{{ $value($key) }}" list="modelOptions" autocomplete="off" spellcheck="false">
                                                    <button class="btn btn-light border" type="submit" form="loadModelsForm" title="Load the provider's current model list"><i class="fas fa-rotate me-1"></i>Load models</button>
                                                </div>
                                                <datalist id="modelOptions"></datalist>
                                                <div class="small text-muted mt-2">
                                                    <i class="fas fa-link me-1"></i>API address: <code id="providerBaseUrl">{{ $aiProviders[$values['provider'] ?? 'anthropic']['base_url'] ?? '' }}</code>
                                                    <span class="ms-2" id="providerModelCount"></span>
                                                </div>
                                            @elseif($field['type'] === 'textarea')
                                                <textarea class="form-control @error($key) is-invalid @enderror" id="{{ $key }}" name="{{ $key }}" rows="3">{{ $value($key) }}</textarea>
                                            @elseif($field['type'] === 'secret')
                                                <div class="input-group has-validation">
                                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                    <input class="form-control font-monospace @error($key) is-invalid @enderror" type="password" id="{{ $key }}" name="{{ $key }}" value="" placeholder="{{ $secrets[$key] ? 'Saved: ' . $secrets[$key] : 'Not set' }}" autocomplete="new-password" spellcheck="false">
                                                    <button class="btn btn-light border" type="button" data-toggle-secret="{{ $key }}" aria-label="Show {{ strtolower($field['label']) }}"><i class="fas fa-eye"></i></button>
                                                    @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                </div>
                                                @if($secrets[$key])
                                                    <div class="form-check mt-1">
                                                        <input class="form-check-input" type="checkbox" value="1" id="clear_{{ $key }}" name="clear_{{ $key }}">
                                                        <label class="form-check-label small" for="clear_{{ $key }}">Remove the saved {{ strtolower($field['label']) }}</label>
                                                    </div>
                                                @endif
                                            @else
                                                <input class="form-control @error($key) is-invalid @enderror" type="{{ $field['type'] === 'number' ? 'number' : ($field['type'] === 'email' ? 'email' : 'text') }}" @if(!empty($field['step'])) step="{{ $field['step'] }}" @endif id="{{ $key }}" name="{{ $key }}" value="{{ $value($key) }}">
                                            @endif
                                            @if($field['type'] !== 'secret')
                                                @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            @endif
                                            @if(!empty($field['help']))<div class="form-text">{{ $field['help'] }}</div>@endif
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.settings.system', $groupKey) }}" class="btn btn-light">Discard changes</a>
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-floppy-disk me-2"></i>Save settings</button>
            </div>
        </form>
    </div>

    @if($groupKey === 'ai')
        <form method="POST" action="{{ route('admin.settings.ai.models') }}" id="loadModelsForm" class="d-none">@csrf</form>
    @endif

    @if($test)
        <div class="col-xl-4">
            <div class="panel p-4">
                <div class="panel-section-title mb-2"><i class="{{ $test['icon'] }} me-2"></i>{{ $test['label'] }}</div>
                <form method="POST" action="{{ route('admin.settings.system.test', $groupKey) }}">
                    @csrf
                    <label class="visually-hidden" for="{{ $test['field'] }}">{{ $test['label'] }}</label>
                    <input class="form-control mb-2 @error($test['field']) is-invalid @enderror" type="{{ $test['type'] }}" id="{{ $test['field'] }}" name="{{ $test['field'] }}" value="{{ old($test['field']) }}" placeholder="{{ $test['placeholder'] }}" required>
                    @error($test['field'])<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                    <button class="btn btn-outline-primary w-100" type="submit"><i class="{{ $test['icon'] }} me-2"></i>{{ $test['button'] }}</button>
                    <div class="form-text">{{ $test['help'] }}</div>
                </form>
            </div>
            @if($groupKey === 'ai')
                <div class="panel p-4 mt-3 small text-muted">
                    <div class="panel-section-title mb-2"><i class="fas fa-shield-halved me-2"></i>Privacy</div>
                    The assistant is only given your public menu, prices, opening hours and contact details. It never sees orders, customers, staff or finances.
                </div>
            @endif
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-toggle-secret]').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.toggleSecret);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
        });
    });

    // AI: fill in the API address, model suggestions and default model for the chosen provider.
    const providers = @json($aiProviders ?? []);
    const providerSelect = document.getElementById('provider');
    const modelInput = document.getElementById('model');
    if (providerSelect && modelInput && Object.keys(providers).length) {
        const datalist = document.getElementById('modelOptions');
        const baseUrl = document.getElementById('providerBaseUrl');
        const count = document.getElementById('providerModelCount');
        const syncProvider = function (switched) {
            const provider = providers[providerSelect.value];
            if (!provider) { return; }
            baseUrl.textContent = provider.base_url;
            datalist.replaceChildren(...provider.models.map(function (id) {
                const option = document.createElement('option');
                option.value = id;
                return option;
            }));
            count.textContent = provider.loaded ? provider.models.length + ' models available' : 'Showing suggested models. Save your key to load the full list.';
            const belongsElsewhere = Object.keys(providers).some(function (key) {
                return key !== providerSelect.value && providers[key].models.includes(modelInput.value);
            });
            if (switched && (modelInput.value === '' || belongsElsewhere)) {
                modelInput.value = provider.default_model;
            }
        };
        providerSelect.addEventListener('change', function () { syncProvider(true); });
        syncProvider(modelInput.value === '');
    }

    // Show provider-specific fields only for the selected provider.
    const dependants = document.querySelectorAll('[data-show-if]');
    function refresh() {
        dependants.forEach(function (wrapper) {
            const control = document.getElementById(wrapper.dataset.showIf);
            wrapper.hidden = control && control.value !== wrapper.dataset.showValue;
        });
    }
    dependants.forEach(function (wrapper) {
        document.getElementById(wrapper.dataset.showIf)?.addEventListener('change', refresh);
    });
    refresh();
});
</script>
@endpush
