@extends('layouts.app')

@section('title', 'Restaurant & Branding')
@section('page-title', 'Restaurant & Branding')
@section('page-subtitle', 'Identity, logo, favicon, colours and customer-facing details')

@section('content')
@php
    $field = fn (string $name, $default = null) => old($name, $restaurant->{$name} ?? $default);
    $primary = old('primary_color', $preview->primary());
    $accent = old('accent_color', $preview->accent());
    $images = [
        'logo' => ['label' => 'Logo', 'url' => $preview->logoUrl(), 'help' => 'PNG, JPG or WebP up to 2 MB. A square or wide logo on a transparent background works best.', 'icon' => 'fas fa-image', 'wide' => false, 'accept' => 'image/png,image/jpeg,image/webp'],
        'favicon' => ['label' => 'Favicon', 'url' => $restaurant->favicon_path ? $preview->faviconUrl() : null, 'help' => 'Browser tab icon. Square PNG or ICO, 64×64 px or larger, up to 512 KB. Falls back to the logo.', 'icon' => 'fas fa-star', 'wide' => false, 'accept' => 'image/png,image/x-icon,image/vnd.microsoft.icon,image/jpeg,image/webp,.ico'],
        'hero_image' => ['label' => 'Website cover image', 'url' => $preview->heroUrl(), 'help' => 'Large photo behind the homepage headline. JPG or WebP, at least 1600 px wide, up to 4 MB.', 'icon' => 'fas fa-panorama', 'wide' => true, 'accept' => 'image/png,image/jpeg,image/webp'],
    ];
@endphp

<x-admin.page-header title="Brand your restaurant" subtitle="These details appear on your website, order pages, receipts, PDFs and every customer email.">
    <a href="{{ url('/') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="fas fa-arrow-up-right-from-square me-2"></i>Preview website</a>
</x-admin.page-header>

<form method="POST" action="{{ route('admin.settings.restaurant.update') }}" enctype="multipart/form-data" id="brandingForm">
    @csrf
    @method('PUT')

    <div class="row g-4">
        <div class="col-xl-8">

            <div class="panel p-0 mb-4">
                <ul class="nav nav-tabs settings-tabs px-3 pt-2" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-identity" data-bs-toggle="tab" data-bs-target="#pane-identity" type="button" role="tab" aria-controls="pane-identity" aria-selected="true" data-fields="name,tagline,about">
                            <i class="fas fa-store me-2"></i>Identity<span class="tab-error-dot d-none" aria-hidden="true"></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-imagery" data-bs-toggle="tab" data-bs-target="#pane-imagery" type="button" role="tab" aria-controls="pane-imagery" aria-selected="false" data-fields="logo,favicon,hero_image">
                            <i class="fas fa-images me-2"></i>Logo & images<span class="tab-error-dot d-none" aria-hidden="true"></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-colours" data-bs-toggle="tab" data-bs-target="#pane-colours" type="button" role="tab" aria-controls="pane-colours" aria-selected="false" data-fields="primary_color,accent_color">
                            <i class="fas fa-palette me-2"></i>Colours<span class="tab-error-dot d-none" aria-hidden="true"></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-contact" data-bs-toggle="tab" data-bs-target="#pane-contact" type="button" role="tab" aria-controls="pane-contact" aria-selected="false" data-fields="phone,email,address,opening_hours">
                            <i class="fas fa-address-book me-2"></i>Contact & hours<span class="tab-error-dot d-none" aria-hidden="true"></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-social" data-bs-toggle="tab" data-bs-target="#pane-social" type="button" role="tab" aria-controls="pane-social" aria-selected="false" data-fields="website_url,facebook_url,instagram_url,whatsapp_number">
                            <i class="fas fa-share-nodes me-2"></i>Website & social<span class="tab-error-dot d-none" aria-hidden="true"></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-email" data-bs-toggle="tab" data-bs-target="#pane-email" type="button" role="tab" aria-controls="pane-email" aria-selected="false" data-fields="email_footer">
                            <i class="fas fa-envelope-open-text me-2"></i>Emails<span class="tab-error-dot d-none" aria-hidden="true"></span>
                        </button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="pane-identity" role="tabpanel" aria-labelledby="tab-identity" tabindex="0">
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="name">Restaurant name <span class="text-danger">*</span></label>
                        <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ $field('name') }}" required maxlength="255" data-preview="name">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="tagline">Tagline</label>
                        <input class="form-control @error('tagline') is-invalid @enderror" id="tagline" name="tagline" value="{{ $field('tagline') }}" maxlength="160" placeholder="e.g. Fresh flavours, served fast" data-preview="tagline">
                        @error('tagline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="about">About your restaurant</label>
                        <textarea class="form-control @error('about') is-invalid @enderror" id="about" name="about" rows="3" maxlength="2000" placeholder="A short story shown on your website's About section.">{{ $field('about') }}</textarea>
                        @error('about')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
                    </div>
                    <div class="tab-pane fade" id="pane-imagery" role="tabpanel" aria-labelledby="tab-imagery" tabindex="0">
            <div class="p-4">
                <div class="d-grid gap-3">
                    @foreach($images as $key => $image)
                        <div class="brand-upload">
                            <div class="brand-upload-preview {{ $image['wide'] ? 'wide' : '' }}" id="{{ $key }}Preview">
                                @if($image['url'])
                                    <img src="{{ $image['url'] }}" alt="Current {{ strtolower($image['label']) }}">
                                @else
                                    <i class="{{ $image['icon'] }}"></i>
                                @endif
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <label class="form-label mb-1" for="{{ $key }}">{{ $image['label'] }}</label>
                                <input class="form-control form-control-sm @error($key) is-invalid @enderror" type="file" id="{{ $key }}" name="{{ $key }}" accept="{{ $image['accept'] }}" data-image-preview="{{ $key }}Preview">
                                @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">{{ $image['help'] }}</div>
                                @if($image['url'])
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" value="1" id="remove_{{ $key }}" name="remove_{{ $key }}">
                                        <label class="form-check-label small" for="remove_{{ $key }}">Remove current {{ strtolower($image['label']) }}</label>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
                    </div>
                    <div class="tab-pane fade" id="pane-colours" role="tabpanel" aria-labelledby="tab-colours" tabindex="0">
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="primary_color">Primary colour <span class="text-danger">*</span></label>
                        <div class="color-field">
                            <input type="color" class="form-control form-control-color" id="primary_color_picker" value="{{ $primary }}" aria-label="Pick primary colour">
                            <input class="form-control @error('primary_color') is-invalid @enderror" id="primary_color" name="primary_color" value="{{ $primary }}" required pattern="#[0-9a-fA-F]{6}" maxlength="7">
                        </div>
                        @error('primary_color')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        <div class="form-text">Buttons, links, highlights and email headers.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="accent_color">Accent colour <span class="text-danger">*</span></label>
                        <div class="color-field">
                            <input type="color" class="form-control form-control-color" id="accent_color_picker" value="{{ $accent }}" aria-label="Pick accent colour">
                            <input class="form-control @error('accent_color') is-invalid @enderror" id="accent_color" name="accent_color" value="{{ $accent }}" required pattern="#[0-9a-fA-F]{6}" maxlength="7">
                        </div>
                        @error('accent_color')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        <div class="form-text">Admin sidebar, website hero and footer. Darker colours work best.</div>
                    </div>
                    <div class="col-12">
                        <span class="small text-muted me-2">Presets:</span>
                        @foreach([['#e8590c', '#1c1917'], ['#dc2626', '#18181b'], ['#16a34a', '#14532d'], ['#2563eb', '#0f172a'], ['#9333ea', '#1e1b4b'], ['#ca8a04', '#292524'], ['#0d9488', '#042f2e']] as [$p, $a])
                            <button type="button" class="btn btn-sm btn-light me-1 mb-1" data-preset-primary="{{ $p }}" data-preset-accent="{{ $a }}" aria-label="Use preset {{ $p }} and {{ $a }}">
                                <span class="brand-swatch" style="background: {{ $p }}"></span><span class="brand-swatch" style="background: {{ $a }}"></span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
                    </div>
                    <div class="tab-pane fade" id="pane-contact" role="tabpanel" aria-labelledby="tab-contact" tabindex="0">
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Phone</label>
                        <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ $field('phone') }}" maxlength="40" placeholder="e.g. +256 700 000 000">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control @error('email') is-invalid @enderror" type="email" id="email" name="email" value="{{ $field('email') }}" maxlength="255" placeholder="hello@yourrestaurant.com">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Shown to customers and used as the reply-to address on emails.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="address">Address</label>
                        <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2" maxlength="2000">{{ $field('address') }}</textarea>
                        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="opening_hours">Opening hours</label>
                        <textarea class="form-control @error('opening_hours') is-invalid @enderror" id="opening_hours" name="opening_hours" rows="2" maxlength="500" placeholder="Mon–Fri 8am–10pm&#10;Sat–Sun 10am–11pm">{{ $field('opening_hours') }}</textarea>
                        @error('opening_hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
                    </div>
                    <div class="tab-pane fade" id="pane-social" role="tabpanel" aria-labelledby="tab-social" tabindex="0">
            <div class="p-4">
                <div class="row g-3">
                    @foreach([
                        'website_url' => ['Website', 'fas fa-globe', 'https://yourrestaurant.com'],
                        'facebook_url' => ['Facebook', 'fab fa-facebook-f', 'https://facebook.com/yourpage'],
                        'instagram_url' => ['Instagram', 'fab fa-instagram', 'https://instagram.com/yourhandle'],
                        'whatsapp_number' => ['WhatsApp number', 'fab fa-whatsapp', '+256 700 000 000'],
                    ] as $name => [$label, $icon, $placeholder])
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $name }}">{{ $label }}</label>
                            <div class="input-group has-validation">
                                <span class="input-group-text"><i class="{{ $icon }}"></i></span>
                                <input class="form-control @error($name) is-invalid @enderror" id="{{ $name }}" name="{{ $name }}" value="{{ $field($name) }}" maxlength="255" placeholder="{{ $placeholder }}" @if($name !== 'whatsapp_number') type="url" @endif>
                                @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
                    </div>
                    <div class="tab-pane fade" id="pane-email" role="tabpanel" aria-labelledby="tab-email" tabindex="0">
            <div class="p-4">
                <label class="form-label" for="email_footer">Email footer message</label>
                <textarea class="form-control @error('email_footer') is-invalid @enderror" id="email_footer" name="email_footer" rows="3" maxlength="1000" placeholder="e.g. Thank you for dining with us! Follow us on Instagram for weekly specials.">{{ $field('email_footer') }}</textarea>
                @error('email_footer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Every customer email uses your logo, colours and contact details, and ends with this message.</div>
            </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.settings.restaurant') }}" class="btn btn-light">Discard changes</a>
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-floppy-disk me-2"></i>Save branding</button>
            </div>
        </div>

        {{-- Live preview --}}
        <div class="col-xl-4">
            <div class="brand-preview" id="brandPreview" style="--pv-primary: {{ $primary }}; --pv-accent: {{ $accent }};">
                <div class="brand-preview-hero" id="previewHero" @if($preview->heroUrl()) style="background-image: url('{{ $preview->heroUrl() }}')" @endif>
                    <div class="d-flex align-items-center gap-2 mb-4">
                        <span class="brand-preview-logo" id="previewLogo">
                            @if($preview->logoUrl())
                                <img src="{{ $preview->logoUrl() }}" alt="">
                            @else
                                {{ $preview->initials() }}
                            @endif
                        </span>
                        <strong data-preview-target="name">{{ $preview->name() }}</strong>
                    </div>
                    <div class="h5 text-white mb-1" data-preview-target="name">{{ $preview->name() }}</div>
                    <p class="small mb-3 opacity-75" data-preview-target="tagline">{{ $preview->tagline() ?? 'Your tagline appears here' }}</p>
                    <span class="brand-preview-btn">Order now</span>
                </div>
                <div class="p-3">
                    <div class="panel-section-title mb-2">Live preview</div>
                    <p class="small text-muted mb-3">See how your homepage header and buttons will look. Save to apply everywhere.</p>
                    <div class="d-flex flex-wrap gap-2 small">
                        <span><span class="brand-swatch" id="swatchPrimary" style="background: {{ $primary }}"></span> Primary</span>
                        <span><span class="brand-swatch" id="swatchAccent" style="background: {{ $accent }}"></span> Accent</span>
                    </div>
                    <hr>
                    <ul class="list-unstyled small mb-0 d-grid gap-2 text-muted">
                        <li><i class="fas fa-check text-success me-2"></i>Website, menu and checkout</li>
                        <li><i class="fas fa-check text-success me-2"></i>Order confirmation &amp; tracking pages</li>
                        <li><i class="fas fa-check text-success me-2"></i>Customer emails</li>
                        <li><i class="fas fa-check text-success me-2"></i>Admin sidebar, favicon and PDFs</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Remember the open tab, and jump to the first tab that has a validation error.
    const tabs = [...document.querySelectorAll('.settings-tabs [data-fields]')];
    const errors = @json(array_keys($errors->getMessages()));
    let target = null;
    tabs.forEach(function (tab) {
        const hasError = tab.dataset.fields.split(',').some(field => errors.includes(field));
        tab.querySelector('.tab-error-dot').classList.toggle('d-none', !hasError);
        if (hasError && !target) { target = tab; }
        tab.addEventListener('shown.bs.tab', function () {
            try { sessionStorage.setItem('brandingTab', tab.id); } catch (e) {}
        });
    });
    if (!target) {
        try { target = document.getElementById(sessionStorage.getItem('brandingTab') || ''); } catch (e) {}
    }
    if (target) { bootstrap.Tab.getOrCreateInstance(target).show(); }

    const preview = document.getElementById('brandPreview');
    const hexPattern = /^#[0-9a-fA-F]{6}$/;

    function bindColor(name, cssVar, swatchId) {
        const text = document.getElementById(name);
        const picker = document.getElementById(name + '_picker');
        const swatch = document.getElementById(swatchId);
        const apply = function (value) {
            if (!hexPattern.test(value)) return;
            preview.style.setProperty(cssVar, value);
            swatch.style.background = value;
        };
        picker.addEventListener('input', function () { text.value = picker.value; apply(picker.value); });
        text.addEventListener('input', function () { if (hexPattern.test(text.value)) { picker.value = text.value; } apply(text.value); });
        return function (value) { text.value = value; picker.value = value; apply(value); };
    }

    const setPrimary = bindColor('primary_color', '--pv-primary', 'swatchPrimary');
    const setAccent = bindColor('accent_color', '--pv-accent', 'swatchAccent');

    document.querySelectorAll('[data-preset-primary]').forEach(function (button) {
        button.addEventListener('click', function () {
            setPrimary(button.dataset.presetPrimary);
            setAccent(button.dataset.presetAccent);
        });
    });

    document.querySelectorAll('[data-preview]').forEach(function (input) {
        input.addEventListener('input', function () {
            document.querySelectorAll('[data-preview-target="' + input.dataset.preview + '"]').forEach(function (target) {
                target.textContent = input.value || (input.dataset.preview === 'tagline' ? 'Your tagline appears here' : 'Restaurant');
            });
        });
    });

    document.querySelectorAll('[data-image-preview]').forEach(function (input) {
        input.addEventListener('change', function () {
            const file = input.files && input.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            const url = URL.createObjectURL(file);
            const box = document.getElementById(input.dataset.imagePreview);
            const img = document.createElement('img');
            img.src = url;
            img.alt = '';
            box.replaceChildren(img);
            if (input.id === 'logo') {
                const logo = document.getElementById('previewLogo');
                logo.replaceChildren(img.cloneNode());
            }
            if (input.id === 'hero_image') {
                document.getElementById('previewHero').style.backgroundImage = 'url(' + url + ')';
            }
        });
    });
});
</script>
@endpush
