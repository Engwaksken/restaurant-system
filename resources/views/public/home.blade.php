@php
    $brand = \App\Support\Brand::for($restaurant);
    $a11yDefaults = [
        'font' => (int) ($accessibility['default_text_size'] ?? 0),
        'toggles' => (object) array_filter([
            'high-contrast' => filter_var($accessibility['high_contrast'] ?? false, FILTER_VALIDATE_BOOL),
            'reduce-motion' => filter_var($accessibility['reduce_motion'] ?? false, FILTER_VALIDATE_BOOL),
        ]),
    ];
    $currency = $restaurant->currency ?: $brand->currency();
    $categoryCounts = $menuItems->countBy('menu_category_id');
    $showcase = $menuItems->sortByDesc(fn ($item) => !empty($item->image_path))->take(3);
    $fromPrice = fn ($item) => $item->variants->isNotEmpty() ? (float) $item->variants->min('price') : (float) $item->price;
    $nameWords = preg_split('/\s+/', trim($brand->name())) ?: [];
    $lastWord = count($nameWords) > 1 ? array_pop($nameWords) : null;
@endphp
<!doctype html>
<html lang="en">
<head>
    @include('public.partials.head')
    <title>{{ $brand->name() }}{{ $brand->tagline() ? ' · ' . $brand->tagline() : ' · Order online' }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($brand->about() ?? ($brand->tagline() ?? 'Order online from ' . $brand->name() . ' for pickup or delivery.'), 155) }}">
    <meta property="og:title" content="{{ $brand->name() }}">
    <meta property="og:description" content="{{ $brand->tagline() ?? 'Order online for pickup or delivery.' }}">
    @if($brand->heroUrl())<meta property="og:image" content="{{ url($brand->heroUrl()) }}">@endif
</head>

<body>

@if($brand->phone() || $brand->openingHours())
    <div class="topline d-none d-md-block">
        <div class="container">
            <span>@if($brand->openingHours())<i class="far fa-clock me-2"></i>{{ \Illuminate\Support\Str::of($brand->openingHours())->explode("\n")->first() }}@endif</span>
            <span class="d-flex gap-3">
                @if($brand->phone())<a href="tel:{{ preg_replace('/[^0-9+]/', '', $brand->phone()) }}"><i class="fas fa-phone me-2"></i>{{ $brand->phone() }}</a>@endif
                @foreach($brand->socialLinks() as $link)
                    <a href="{{ $link['url'] }}" target="_blank" rel="noopener" aria-label="{{ $link['label'] }}"><i class="{{ $link['icon'] }}"></i></a>
                @endforeach
            </span>
        </div>
    </div>
@endif

<header class="site-nav" id="siteNav">
    <div class="container">
        <a class="site-brand" href="{{ route('home') }}">
            @include('public.partials.logo')
            <span class="min-w-0">
                <span class="site-brand-name d-block">{{ $brand->name() }}</span>
                @if($brand->tagline())<span class="site-brand-tag">{{ $brand->tagline() }}</span>@endif
            </span>
        </a>

        <nav class="site-links" aria-label="Main">
            <a href="#menu">Menu</a>
            @if($brand->about())<a href="#about">About</a>@endif
            <a href="#contact">Contact</a>
        </nav>

        <button type="button" class="btn btn-primary cart-button" id="cartButton" data-bs-toggle="modal" data-bs-target="#cartModal" aria-label="Open cart">
            <i class="fas fa-bag-shopping"></i>
            <span class="d-none d-sm-inline">Cart</span>
            <span class="cart-count" id="cartCount">0</span>
            <span class="d-none d-lg-inline small opacity-75">{{ $currency }} <span id="headerSubtotal">0.00</span></span>
        </button>
    </div>
</header>

<main id="siteMain">

<section class="hero">
    @if($brand->heroUrl())
        <div class="hero-media" style="background-image: url('{{ $brand->heroUrl() }}')"></div>
    @else
        <div class="hero-pattern"></div>
    @endif

    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="hero-badge"><span>Open for orders</span> Pickup &amp; delivery available</span>
                <h1>
                    @if($lastWord){{ implode(' ', $nameWords) }} <em>{{ $lastWord }}</em>@else{{ $brand->name() }}@endif
                </h1>
                <p class="lead">{{ $brand->tagline() ?? 'Freshly prepared favourites, ordered online in a few taps. Pick it up or have it delivered to your door.' }}</p>

                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a href="#menu" class="btn btn-primary btn-lg"><i class="fas fa-utensils me-2"></i>Explore the menu</a>
                    <button type="button" class="btn btn-glass btn-lg" data-bs-toggle="modal" data-bs-target="#cartModal">
                        <i class="fas fa-bag-shopping me-2"></i>View cart
                    </button>
                </div>

                <div class="hero-facts">
                    @if($brand->address())<span><i class="fas fa-location-dot"></i>{{ \Illuminate\Support\Str::limit($brand->address(), 60) }}</span>@endif
                    @if($brand->phone())<span><i class="fas fa-phone"></i>{{ $brand->phone() }}</span>@endif
                    <span><i class="fas fa-money-bill-wave"></i>Pay on delivery</span>
                </div>
            </div>

            @if($showcase->isNotEmpty())
                <div class="col-lg-5 d-none d-lg-block">
                    <div class="hero-card">
                        <div class="d-flex justify-content-between align-items-center mb-3 px-1">
                            <strong class="text-white">Popular right now</strong>
                            <span class="small text-white-50">{{ $menuItems->count() }} dishes</span>
                        </div>
                        @foreach($showcase as $item)
                            <a class="hero-dish" href="#" data-bs-toggle="modal" data-bs-target="#itemModal-{{ $item->id }}">
                                @if(!empty($item->image_path))
                                    <img src="{{ $item->image_url }}" alt="" loading="lazy">
                                @else
                                    <span class="dish-ph" style="font-size:1.3rem"><i class="fas fa-bowl-food"></i></span>
                                @endif
                                <span class="min-w-0">
                                    <strong class="text-truncate">{{ $item->name }}</strong>
                                    <small>{{ $item->category?->name }}</small>
                                </span>
                                <span class="price-tag">{{ $currency }} {{ number_format($fromPrice($item)) }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
    <div class="hero-wave"></div>
</section>

<section class="features">
    <div class="container">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="feature reveal">
                    <span class="feature-icon"><i class="fas fa-leaf"></i></span>
                    <div><h3>Freshly prepared</h3><p>Every order is cooked when you place it, never sitting around.</p></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature reveal">
                    <span class="feature-icon"><i class="fas fa-motorcycle"></i></span>
                    <div><h3>Pickup or delivery</h3><p>Collect it yourself or have it brought to your door.</p></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature reveal">
                    <span class="feature-icon"><i class="fas fa-location-crosshairs"></i></span>
                    <div><h3>Live order tracking</h3><p>Follow your order from the kitchen to you in real time.</p></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="menu" class="menu-section">
    <div class="container">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-3">
            <div>
                <span class="eyebrow">Our menu</span>
                <h2 class="section-title mt-2">What would you like today?</h2>
                <p class="section-lead mb-0">Choose from our food, drinks and specials. Tap a dish to customise it.</p>
            </div>
        </div>

        <div class="menu-toolbar">
            <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
                @if($categories->count())
                    <div class="category-scroller flex-grow-1" role="toolbar" aria-label="Filter by category">
                        <button type="button" class="category-filter active" data-category="all" aria-pressed="true">All<span class="count">{{ $menuItems->count() }}</span></button>
                        @foreach($categories as $category)
                            @if($categoryCounts->get($category->id))
                                <button type="button" class="category-filter" data-category="{{ $category->id }}" aria-pressed="false">
                                    {{ $category->name }}<span class="count">{{ $categoryCounts->get($category->id) }}</span>
                                </button>
                            @endif
                        @endforeach
                    </div>
                @endif
                <div class="menu-search ms-md-auto">
                    <i class="fas fa-magnifying-glass"></i>
                    <label class="visually-hidden" for="menuSearch">Search the menu</label>
                    <input type="search" id="menuSearch" class="form-control" placeholder="Search dishes…">
                </div>
            </div>
        </div>

        <div class="row g-4" id="menuGrid">
            @forelse($menuItems as $item)
                @php($hasOptions = $item->variants->isNotEmpty() || $item->addons->isNotEmpty())
                <div class="col-sm-6 col-lg-4 col-xl-3 menu-item" data-category="{{ $item->menu_category_id }}" data-name="{{ strtolower($item->name . ' ' . $item->description) }}">
                    <article class="menu-card">
                        <a href="#" class="menu-card-media d-block" data-bs-toggle="modal" data-bs-target="#itemModal-{{ $item->id }}" aria-label="View {{ $item->name }}">
                            @if(!empty($item->image_path))
                                <img src="{{ $item->image_url }}" alt="{{ $item->name }}" loading="lazy">
                            @else
                                <span class="dish-ph"><i class="fas fa-bowl-food"></i></span>
                            @endif
                            @if($item->category)<span class="menu-card-badge">{{ $item->category->name }}</span>@endif
                        </a>
                        <div class="menu-card-body">
                            <h3 class="menu-card-title">{{ $item->name }}</h3>
                            @if(!empty($item->description))
                                <p class="menu-card-desc">{{ $item->description }}</p>
                            @else
                                <p class="menu-card-desc">&nbsp;</p>
                            @endif
                            <div class="menu-card-foot">
                                <div class="menu-price">
                                    @if($item->variants->isNotEmpty())<small>from</small>@endif
                                    <small>{{ $currency }}</small>{{ number_format($fromPrice($item), 2) }}
                                </div>
                                @if($hasOptions)
                                    <button type="button" class="add-btn" data-bs-toggle="modal" data-bs-target="#itemModal-{{ $item->id }}" aria-label="Choose options for {{ $item->name }}">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                @else
                                    <button type="button" class="add-btn add-to-cart" data-id="{{ $item->id }}" data-name="{{ $item->name }}" data-price="{{ $item->price }}" data-image="{{ $item->image_url ?? '' }}" aria-label="Add {{ $item->name }} to cart">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12">
                    <div class="menu-empty">
                        <i class="fas fa-bowl-food d-block"></i>
                        <h3 class="h5">Our menu is being updated</h3>
                        <p class="mb-0">Please check back shortly{{ $brand->phone() ? ' or call us on ' . $brand->phone() : '' }}.</p>
                    </div>
                </div>
            @endforelse

            <div class="col-12" id="menuNoResults" hidden>
                <div class="menu-empty">
                    <i class="fas fa-magnifying-glass d-block"></i>
                    <h3 class="h5">No dishes match your search</h3>
                    <p class="mb-0">Try a different word or browse all categories.</p>
                </div>
            </div>
        </div>

        @foreach($menuItems as $item)
            <div class="modal fade item-modal" id="itemModal-{{ $item->id }}" tabindex="-1" aria-labelledby="itemModalLabel-{{ $item->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="item-modal-media">
                            @if(!empty($item->image_path))
                                <img src="{{ $item->image_url }}" alt="{{ $item->name }}" loading="lazy">
                            @else
                                <span class="dish-ph"><i class="fas fa-bowl-food"></i></span>
                            @endif
                            <button type="button" class="item-modal-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-xmark"></i></button>
                        </div>
                        <div class="modal-body p-4">
                            @if($item->category)<span class="eyebrow mb-2">{{ $item->category->name }}</span>@endif
                            <h5 class="modal-title h4 fw-bold mt-1" id="itemModalLabel-{{ $item->id }}">{{ $item->name }}</h5>
                            @if(!empty($item->description))
                                <p class="text-muted mb-3">{{ $item->description }}</p>
                            @endif
                            <div class="item-options" data-item="{{ $item->id }}">
                                @if($item->variants->isNotEmpty())
                                    <div class="option-group-title mt-3">Choose a size or option</div>
                                    <label class="visually-hidden" for="variant-{{ $item->id }}">Choose</label>
                                    <select class="form-select mb-3 item-variant" id="variant-{{ $item->id }}">
                                        @foreach($item->variants as $variant)
                                            <option value="{{ $variant->id }}" data-name="{{ $variant->name }}" data-price="{{ $variant->price }}">{{ $variant->name }} — {{ $currency }} {{ number_format((float) $variant->price, 2) }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                @if($item->addons->isNotEmpty())
                                    <div class="option-group-title mt-3">Add extras</div>
                                    @foreach($item->addons as $addon)
                                        <label class="addon-option" for="addon-{{ $addon->id }}">
                                            <input class="form-check-input item-addon" type="checkbox" id="addon-{{ $addon->id }}" value="{{ $addon->id }}" data-name="{{ $addon->name }}" data-price="{{ $addon->price }}">
                                            <span>{{ $addon->name }}</span>
                                            <span class="addon-price">+{{ number_format((float) $addon->price, 2) }}</span>
                                        </label>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                        <div class="modal-footer border-0 px-4 pb-4 pt-0">
                            <button
                                type="button"
                                class="btn btn-primary btn-lg w-100 d-flex justify-content-between align-items-center add-to-cart"
                                data-id="{{ $item->id }}"
                                data-name="{{ $item->name }}"
                                data-price="{{ $item->price }}"
                                data-image="{{ $item->image_url ?? '' }}"
                            >
                                <span><i class="fas fa-bag-shopping me-2"></i>Add to order</span>
                                <span>{{ $currency }} <span class="item-modal-total" data-item-total="{{ $item->id }}">{{ number_format($fromPrice($item), 2) }}</span></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>

@if($brand->about())
    <section class="about-section" id="about">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6">
                    <div class="about-visual reveal" @if($brand->heroUrl()) style="background-image: url('{{ $brand->heroUrl() }}')" @endif>
                        <div class="about-stamp">
                            <strong>{{ $menuItems->count() }}+</strong>
                            <span class="small">dishes on the menu</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <span class="eyebrow">Our story</span>
                    <h2 class="section-title mt-2">Welcome to {{ $brand->name() }}</h2>
                    <p class="text-muted fs-6 mb-4" style="white-space: pre-line">{{ $brand->about() }}</p>
                    <div class="row g-3">
                        @if($brand->openingHours())
                            <div class="col-sm-6">
                                <div class="info-card">
                                    <i class="far fa-clock lead-icon"></i>
                                    <h3>Opening hours</h3>
                                    <p class="small">{{ $brand->openingHours() }}</p>
                                </div>
                            </div>
                        @endif
                        @if($brand->address() || $brand->phone())
                            <div class="col-sm-6">
                                <div class="info-card">
                                    <i class="fas fa-location-dot lead-icon"></i>
                                    <h3>Find us</h3>
                                    <p class="small">{{ collect([$brand->address(), $brand->phone()])->filter()->implode("\n") }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif

<section class="py-5">
    <div class="container py-lg-4">
        <div class="cta-band reveal">
            <div class="row align-items-center g-3">
                <div class="col-lg-8">
                    <h2 class="h2 mb-2">Hungry? Your next favourite meal is a few taps away.</h2>
                    <p class="mb-0 opacity-75">Order now and track it live until it reaches you.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="#menu" class="btn btn-light btn-lg fw-bold"><i class="fas fa-utensils me-2"></i>Start your order</a>
                </div>
            </div>
        </div>
    </div>
</section>

</main>

@include('public.partials.footer')

<button type="button" class="mobile-cart-bar" id="mobileCartBar" data-bs-toggle="modal" data-bs-target="#cartModal">
    <span><i class="fas fa-bag-shopping me-2"></i><span id="mobileCartCount">0</span> items · {{ $currency }} <span id="mobileCartTotal">0.00</span></span>
    <span class="go">View cart <i class="fas fa-arrow-right ms-1"></i></span>
</button>

<div class="toast-added" id="addedToast" role="status" aria-live="polite"><i class="fas fa-circle-check"></i><span id="addedToastText">Added to your order</span></div>


<div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <form id="checkoutForm" method="POST" action="{{ route('order.store') }}" class="modal-content order-modal border-0 shadow">
            @csrf

            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="cartModalLabel">
                    Your cart <span class="text-muted fw-normal" id="cartTitleCount"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0">

                @if($errors->any())
                    <div class="alert alert-danger rounded-0 mb-0 py-2 px-3 small">
                        <ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="row g-0">

                    <div class="col-lg-7 p-3 p-lg-4">
                        <div class="order-section-title mb-2">Items</div>

                        <div id="cartEmpty" class="text-center text-muted py-4">
                            <i class="fas fa-bag-shopping fs-1 mb-3 d-block text-brand opacity-50"></i>
                            Your cart is empty. Add items from the menu.
                        </div>

                        <ul id="cartItems" class="list-unstyled mb-0"></ul>
                    </div>

                    <div class="col-lg-5 p-3 p-lg-4" style="background: var(--cream)">
                        <div class="order-section-title mb-1">Delivery details</div>
                        <div class="small text-muted mb-2"><span class="text-danger" aria-hidden="true">*</span> Required</div>

                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="customerName">Full name <span class="text-danger" aria-hidden="true">*</span></label>
                                <input class="form-control form-control-sm" id="customerName" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="e.g. Amina Kato" autocomplete="name">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="customerPhone">Phone number <span class="text-danger" aria-hidden="true">*</span></label>
                                <input class="form-control form-control-sm" id="customerPhone" type="tel" name="phone" value="{{ old('phone') }}" required maxlength="40" placeholder="e.g. 0700 000 000" autocomplete="tel">
                                <div class="form-text">We call this number only about your order.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="customerEmail">Email <span class="text-muted fw-normal">(optional)</span></label>
                                <input class="form-control form-control-sm" id="customerEmail" type="email" name="email" value="{{ old('email') }}" maxlength="255" placeholder="e.g. name@example.com" autocomplete="email">
                                <div class="form-text">Get a receipt and enable online payment.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="customerAddress">Delivery address <span class="text-muted fw-normal">(optional)</span></label>
                                <input class="form-control form-control-sm" id="customerAddress" name="address" value="{{ old('address') }}" maxlength="1000" placeholder="e.g. Plot 12, Kampala Road, near the fuel station" autocomplete="street-address">
                            </div>
                            @if($qrTable)
                                <input type="hidden" name="table_token" value="{{ $qrTable->qr_token }}">
                                <input type="hidden" name="branch_id" value="{{ $qrTable->branch_id }}">
                                <div class="col-12"><div class="alert alert-info py-2 mb-0">Ordering for table <strong>{{ $qrTable->name }}</strong> at {{ $qrTable->branch->name }}.</div></div>
                            @elseif($branches->count() > 1)
                                <div class="col-12">
                                    <label class="form-label" for="orderBranch">Branch <span class="text-danger" aria-hidden="true">*</span></label>
                                    <select class="form-select form-select-sm" id="orderBranch" name="branch_id" required>
                                        <option value="" disabled selected>Choose a branch</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @elseif($branches->count() === 1)
                                <input type="hidden" name="branch_id" value="{{ $branches->first()->id }}">
                            @endif
                            <div class="col-12">
                                <fieldset>
                                    <legend class="form-label mb-2">Payment method <span class="text-danger" aria-hidden="true">*</span></legend>
                                    <label class="payment-option d-flex align-items-start gap-2 border rounded p-2 mb-2">
                                        <input class="form-check-input mt-1" type="radio" name="payment_option" value="cash_on_delivery" @checked(old('payment_option', 'cash_on_delivery') === 'cash_on_delivery') required>
                                        <span>
                                            <span class="d-block fw-semibold small"><i class="fas fa-money-bill-wave me-1 text-success"></i>Pay on delivery or pickup</span>
                                            <span class="d-block text-muted" style="font-size:.75rem">Pay cash when your order arrives or when you collect it.</span>
                                        </span>
                                    </label>
                                    @foreach($paymentGateways as $gateway => $label)
                                        @php($isMobileMoney = $gateway === 'iotec')
                                        <label class="payment-option d-flex align-items-start gap-2 border rounded p-2 mb-2">
                                            <input class="form-check-input mt-1" type="radio" name="payment_option" value="{{ $gateway }}" @checked(old('payment_option') === $gateway) data-needs-email="{{ $isMobileMoney ? '0' : '1' }}">
                                            <span>
                                                <span class="d-block fw-semibold small"><i class="fas {{ $isMobileMoney ? 'fa-mobile-screen-button' : ($gateway === 'flutterwave' ? 'fa-credit-card' : 'fa-globe') }} me-1 text-primary"></i>{{ $isMobileMoney ? 'Mobile money (MTN / Airtel)' : 'Pay online with ' . $label }}</span>
                                                <span class="d-block text-muted" style="font-size:.75rem">{{ $isMobileMoney ? "We'll send a payment prompt to your phone number after you place the order." : "You'll continue to the secure payment page after placing the order." }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                    @foreach($manualMethods as $method)
                                        <label class="payment-option d-flex align-items-start gap-2 border rounded p-2 mb-2">
                                            <input class="form-check-input mt-1" type="radio" name="payment_option" value="{{ $method->optionKey() }}" @checked(old('payment_option') === $method->optionKey()) data-needs-email="0">
                                            <span>
                                                <span class="d-block fw-semibold small"><i class="{{ $method->icon }} me-1 text-primary"></i>{{ $method->name }}</span>
                                                <span class="d-block text-muted" style="font-size:.75rem">Payment instructions are shown after you place the order.</span>
                                            </span>
                                        </label>
                                    @endforeach
                                    <div class="form-text" id="onlinePaymentEmailHint" hidden>A valid email is required for card payment.</div>
                                </fieldset>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="orderNotes">Order notes <span class="text-muted fw-normal">(optional)</span></label>
                                <textarea class="form-control form-control-sm" id="orderNotes" name="notes" rows="2" maxlength="1000" placeholder="e.g. Less spicy, no onions, ring the bell twice">{{ old('notes') }}</textarea>
                            </div>
                        </div>

                        <div class="order-section-title mb-2">Order summary</div>
                        <div class="bg-white rounded border p-3 small">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Subtotal</span>
                                <span>{{ $restaurant->currency }} <span id="cartSubtotal">0.00</span></span>
                            </div>
                            <div class="text-muted" style="font-size: .75rem;">Taxes and delivery fees are confirmed when you place the order.</div>
                        </div>
                    </div>

                </div>

                <div id="cartPayload"></div>
            </div>

            <div class="modal-footer border-top justify-content-between">
                <div>
                    <div class="small text-muted">Total</div>
                    <div class="fw-bold fs-5">{{ $restaurant->currency }} <span id="footerTotal">0.00</span></div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Continue shopping</button>
                    <button class="btn btn-primary px-4" id="submitOrder" type="submit" disabled><i class="fas fa-lock me-2"></i>Place order</button>
                </div>
            </div>

        </form>
    </div>
</div>


@if(filter_var($accessibility['show_on_website'] ?? true, FILTER_VALIDATE_BOOL))
<div class="floating-accessibility dropup">
    <button
        type="button"
        class="btn shadow"
        data-bs-toggle="dropdown"
        data-bs-auto-close="outside"
        aria-expanded="false"
        aria-label="Accessibility options"
        title="Accessibility"
    >
        <i class="fas fa-universal-access"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end a11y-menu shadow">
        <li class="a11y-label">Text size</li>
        <li class="px-2 pb-2">
            <div class="btn-group btn-group-sm w-100" role="group" aria-label="Text size">
                <button type="button" class="btn btn-outline-secondary" data-a11y-font="-1" aria-label="Decrease text size"><i class="fas fa-minus"></i></button>
                <button type="button" class="btn btn-outline-secondary" data-a11y-font="1" aria-label="Increase text size"><i class="fas fa-plus"></i></button>
            </div>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li><button type="button" class="dropdown-item" data-a11y-toggle="high-contrast" aria-pressed="false"><i class="fas fa-circle-half-stroke"></i>High contrast</button></li>
        <li><button type="button" class="dropdown-item" data-a11y-toggle="grayscale" aria-pressed="false"><i class="fas fa-droplet-slash"></i>Grayscale</button></li>
        <li><button type="button" class="dropdown-item" data-a11y-toggle="reduce-motion" aria-pressed="false"><i class="fas fa-pause"></i>Reduce motion</button></li>
        <li><hr class="dropdown-divider"></li>
        <li><button type="button" class="dropdown-item text-danger" data-a11y-reset><i class="fas fa-rotate-left"></i>Reset</button></li>
    </ul>
</div>
@endif

<div class="chat-widget" id="chatWidget">
    <section class="chat-panel card shadow-lg border-0" id="chatPanel" aria-labelledby="chatPanelLabel" hidden>
        <header class="card-header bg-primary text-white d-flex align-items-center justify-content-between py-2 border-0">
            <div>
                <div class="fw-semibold" id="chatPanelLabel">{{ filter_var($aiSettings['enabled'] ?? false, FILTER_VALIDATE_BOOL) && trim((string) ($aiSettings['assistant_name'] ?? '')) !== '' ? $aiSettings['assistant_name'] : $brand->name() . ' Assistant' }}</div>
                <small class="opacity-75">Ask about the menu, prices or orders</small>
            </div>
            <button type="button" class="btn btn-sm text-white" id="chatClose" aria-label="Close chat">
                <i class="fas fa-xmark"></i>
            </button>
        </header>
        <div class="chat-messages" id="chatMessages" aria-live="polite">
            <div class="chat-bubble bot">{{ trim((string) ($aiSettings['greeting'] ?? '')) ?: 'Hello! Ask me about our menu, prices, opening hours or how to order.' }}</div>
        </div>
        <div id="chatAttachmentPreview" class="chat-attachment-preview" hidden>
            <span class="chat-attachment-thumb" id="chatAttachmentThumb"><i class="fas fa-file-pdf"></i></span>
            <span class="chat-attachment-name" id="chatAttachmentName"></span>
            <button type="button" class="btn btn-sm btn-link text-muted p-0 ms-auto" id="chatAttachmentRemove" aria-label="Remove attachment"><i class="fas fa-xmark"></i></button>
        </div>
        <form id="chatForm" class="card-footer bg-white p-2 d-flex gap-2 border-top">
            <input type="file" id="chatFile" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" hidden>
            <button type="button" class="btn btn-sm btn-light border chat-attach" id="chatAttach" aria-label="Attach a photo or PDF" title="Attach a photo or PDF">
                <i class="fas fa-paperclip"></i>
            </button>
            <input id="chatInput" class="form-control form-control-sm" maxlength="1000" placeholder="Type a message or attach a photo" aria-label="Message">
            <button class="btn btn-primary btn-sm px-3" type="submit" aria-label="Send message">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
    </section>
    <button type="button" class="btn btn-primary shadow chat-launcher" id="chatLauncher" aria-expanded="false" aria-controls="chatPanel" aria-label="Chat with us">
        <i class="fas fa-comments"></i>
    </button>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const currency = @json($restaurant->currency);
    const money = value => Number(value).toFixed(2);

    const search = document.getElementById('menuSearch');
    const items = document.querySelectorAll('.menu-item');
    const categoryButtons = document.querySelectorAll('.category-filter');
    const cartItems = new Map();
    const cartList = document.getElementById('cartItems');
    const cartEmpty = document.getElementById('cartEmpty');
    const cartPayload = document.getElementById('cartPayload');
    const submitOrder = document.getElementById('submitOrder');
    const cartCount = document.getElementById('cartCount');
    const cartTitleCount = document.getElementById('cartTitleCount');
    const cartSubtotal = document.getElementById('cartSubtotal');
    const footerTotal = document.getElementById('footerTotal');
    const headerSubtotal = document.getElementById('headerSubtotal');
    const paymentRadios = [...document.querySelectorAll('[name="payment_option"]')];
    const customerEmail = document.getElementById('customerEmail');
    const onlinePaymentEmailHint = document.getElementById('onlinePaymentEmailHint');
    const noResults = document.getElementById('menuNoResults');
    const mobileBar = document.getElementById('mobileCartBar');
    const mobileCount = document.getElementById('mobileCartCount');
    const mobileTotal = document.getElementById('mobileCartTotal');
    const cartButton = document.getElementById('cartButton');
    const toast = document.getElementById('addedToast');
    const toastText = document.getElementById('addedToastText');
    let toastTimer = null;

    function announceAdded(name) {
        toastText.textContent = `${name} added to your order`;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), 2200);
        cartButton.classList.remove('bump');
        void cartButton.offsetWidth;
        cartButton.classList.add('bump');
    }

    function updatePaymentRequirement() {
        const onlineSelected = paymentRadios.some(radio => radio.checked && radio.dataset.needsEmail === '1');
        customerEmail.required = onlineSelected;
        onlinePaymentEmailHint.hidden = !onlineSelected;
    }

    paymentRadios.forEach(radio => radio.addEventListener('change', updatePaymentRequirement));
    updatePaymentRequirement();

    function renderCart() {
        cartList.replaceChildren();
        cartPayload.replaceChildren();
        let subtotal = 0;
        let count = 0;
        let index = 0;

        const addHidden = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            cartPayload.append(input);
        };

        cartItems.forEach((line, key) => {
            const lineTotal = line.price * line.quantity;
            subtotal += lineTotal;
            count += line.quantity;

            const row = document.createElement('li');
            row.className = 'cart-line d-flex align-items-center gap-3 py-3';

            let thumb;
            if (line.image) {
                thumb = document.createElement('img');
                thumb.src = line.image;
                thumb.alt = '';
                thumb.className = 'cart-thumb';
            } else {
                thumb = document.createElement('div');
                thumb.className = 'cart-thumb-placeholder';
                thumb.innerHTML = '<i class="fas fa-utensils"></i>';
            }

            const info = document.createElement('div');
            info.className = 'flex-grow-1 min-w-0';
            const name = document.createElement('div');
            name.className = 'fw-semibold small text-truncate';
            name.textContent = line.name;
            const unit = document.createElement('div');
            unit.className = 'text-muted small';
            unit.textContent = `${currency} ${money(line.price)} each`;
            info.append(name, unit);

            const controls = document.createElement('div');
            controls.className = 'd-flex flex-column align-items-end gap-2';
            const total = document.createElement('div');
            total.className = 'fw-semibold small';
            total.textContent = `${currency} ${money(lineTotal)}`;

            const group = document.createElement('div');
            group.className = 'btn-group btn-group-sm';
            group.setAttribute('role', 'group');
            group.setAttribute('aria-label', `Quantity for ${line.name}`);

            const minus = document.createElement('button');
            minus.type = 'button';
            minus.className = 'btn btn-outline-secondary';
            minus.setAttribute('aria-label', 'Decrease quantity');
            minus.innerHTML = '<i class="fas fa-minus"></i>';
            minus.addEventListener('click', () => {
                line.quantity--;
                if (line.quantity <= 0) { cartItems.delete(key); }
                renderCart();
            });

            const qty = document.createElement('span');
            qty.className = 'btn btn-outline-secondary disabled fw-semibold';
            qty.textContent = line.quantity;

            const plus = document.createElement('button');
            plus.type = 'button';
            plus.className = 'btn btn-outline-secondary';
            plus.setAttribute('aria-label', 'Increase quantity');
            plus.innerHTML = '<i class="fas fa-plus"></i>';
            plus.addEventListener('click', () => { line.quantity++; renderCart(); });

            group.append(minus, qty, plus);
            controls.append(total, group);
            row.append(thumb, info, controls);
            cartList.append(row);

            addHidden(`items[${index}][menu_item_id]`, line.id);
            addHidden(`items[${index}][quantity]`, line.quantity);
            if (line.variantId) { addHidden(`items[${index}][variant_id]`, line.variantId); }
            line.addonIds.forEach((addonId, i) => addHidden(`items[${index}][addon_ids][${i}]`, addonId));
            index++;
        });

        cartEmpty.hidden = cartItems.size > 0;
        cartSubtotal.textContent = money(subtotal);
        footerTotal.textContent = money(subtotal);
        headerSubtotal.textContent = money(subtotal);
        cartCount.textContent = count;
        cartTitleCount.textContent = count ? `(${count})` : '';
        submitOrder.disabled = cartItems.size === 0;
        mobileCount.textContent = count;
        mobileTotal.textContent = money(subtotal);
        mobileBar.classList.toggle('has-items', count > 0);
        document.body.classList.toggle('has-cart', count > 0);
    }

    let selectedCategory = 'all';

    function filterItems() {

        const term = (search?.value || '').toLowerCase().trim();
        let visible = 0;

        items.forEach(item => {

            const name = item.dataset.name || '';
            const category = item.dataset.category || '';

            const matchesSearch =
                !term || name.includes(term);

            const matchesCategory =
                selectedCategory === 'all' ||
                category === selectedCategory;

            item.style.display =
                matchesSearch && matchesCategory
                    ? ''
                    : 'none';
            if (matchesSearch && matchesCategory) { visible++; }
        });

        if (noResults) { noResults.hidden = visible > 0 || items.length === 0; }
    }

    search?.addEventListener('input', filterItems);

    categoryButtons.forEach(button => {

        button.addEventListener('click', function () {

            selectedCategory = this.dataset.category;

            categoryButtons.forEach(btn => {
                btn.classList.remove('active');
                btn.setAttribute('aria-pressed', 'false');
            });

            this.classList.add('active');
            this.setAttribute('aria-pressed', 'true');

            filterItems();
        });

    });

    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', () => {
            const id = button.dataset.id;
            const options = document.querySelector(`.item-options[data-item="${id}"]`);
            const variant = options?.querySelector('.item-variant')?.selectedOptions[0];
            const addons = [...(options?.querySelectorAll('.item-addon:checked') || [])];
            const addonIds = addons.map((el) => el.value).sort();
            const key = [id, variant ? variant.value : '', addonIds.join('.')].join('|');
            const name = button.dataset.name
                + (variant ? ` (${variant.dataset.name})` : '')
                + addons.map((el) => ` + ${el.dataset.name}`).join('');
            const price = (variant ? Number(variant.dataset.price) : Number(button.dataset.price))
                + addons.reduce((sum, el) => sum + Number(el.dataset.price), 0);
            const line = cartItems.get(key) || { id, variantId: variant ? variant.value : null, addonIds, name, price, image: button.dataset.image || '', quantity: 0 };
            line.quantity++;
            cartItems.set(key, line);
            renderCart();
            announceAdded(button.dataset.name);
            const modal = button.closest('.modal');
            if (modal) { bootstrap.Modal.getInstance(modal)?.hide(); }
        });
    });

    // Live price in each item modal as options change.
    document.querySelectorAll('.item-options').forEach(options => {
        const id = options.dataset.item;
        const base = Number(document.querySelector(`.modal .add-to-cart[data-id="${id}"]`)?.dataset.price || 0);
        const totalEl = document.querySelector(`[data-item-total="${id}"]`);
        const update = () => {
            const variant = options.querySelector('.item-variant')?.selectedOptions[0];
            const addons = [...options.querySelectorAll('.item-addon:checked')];
            const price = (variant ? Number(variant.dataset.price) : base) + addons.reduce((sum, el) => sum + Number(el.dataset.price), 0);
            if (totalEl) { totalEl.textContent = money(price); }
        };
        options.addEventListener('change', update);
        update();
    });

    // Sticky nav shadow and on-scroll reveal.
    const nav = document.getElementById('siteNav');
    const onScroll = () => nav.classList.toggle('scrolled', window.scrollY > 10);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => entries.forEach(entry => {
            if (entry.isIntersecting) { entry.target.classList.add('in'); observer.unobserve(entry.target); }
        }), { threshold: .15 });
        document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
    } else {
        document.querySelectorAll('.reveal').forEach(el => el.classList.add('in'));
    }

    renderCart();

    @if($errors->any())
        bootstrap.Modal.getOrCreateInstance(document.getElementById('cartModal')).show();
    @endif

});

document.addEventListener('DOMContentLoaded', function () {

    const root = document.documentElement;
    // Restaurant defaults apply until a visitor sets their own preferences.
    const a11yDefaults = @json($a11yDefaults);
    let stored = null;
    try { stored = JSON.parse(localStorage.getItem('a11y') || 'null'); } catch (e) { stored = null; }
    const prefs = stored || a11yDefaults;
    let fontScale = prefs.font || 0;
    const toggles = Object.assign({}, prefs.toggles || {});
    const main = document.getElementById('siteMain');

    function applyA11y() {
        root.style.fontSize = fontScale ? `${100 + fontScale * 10}%` : '';
        ['high-contrast', 'grayscale'].forEach(name => {
            main.classList.toggle(`a11y-${name}`, !!toggles[name]);
        });
        root.classList.toggle('a11y-reduce-motion', !!toggles['reduce-motion']);
        document.querySelectorAll('[data-a11y-toggle]').forEach(button => {
            button.setAttribute('aria-pressed', String(!!toggles[button.dataset.a11yToggle]));
        });
        localStorage.setItem('a11y', JSON.stringify({ font: fontScale, toggles }));
    }

    document.querySelectorAll('[data-a11y-font]').forEach(button => {
        button.addEventListener('click', () => {
            fontScale = Math.max(-2, Math.min(4, fontScale + Number(button.dataset.a11yFont)));
            applyA11y();
        });
    });

    document.querySelectorAll('[data-a11y-toggle]').forEach(button => {
        button.addEventListener('click', () => {
            const name = button.dataset.a11yToggle;
            toggles[name] = !toggles[name];
            applyA11y();
        });
    });

    document.querySelector('[data-a11y-reset]')?.addEventListener('click', () => {
        fontScale = 0;
        Object.keys(toggles).forEach(name => delete toggles[name]);
        applyA11y();
    });

    applyA11y();

    const launcher = document.getElementById('chatLauncher');
    const panel = document.getElementById('chatPanel');
    const closeButton = document.getElementById('chatClose');
    const chatForm = document.getElementById('chatForm');
    const chatInput = document.getElementById('chatInput');
    const chatMessages = document.getElementById('chatMessages');

    function setChatOpen(open) {
        panel.hidden = !open;
        launcher.setAttribute('aria-expanded', String(open));
        launcher.querySelector('i').className = open ? 'fas fa-xmark' : 'fas fa-comments';
        if (open) { chatInput.focus(); }
    }

    launcher.addEventListener('click', () => setChatOpen(panel.hidden));
    closeButton.addEventListener('click', () => setChatOpen(false));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !panel.hidden) { setChatOpen(false); }
    });

    function addBubble(text, list = [], side = 'bot') {
        const bubble = document.createElement('div');
        bubble.className = `chat-bubble ${side}`;
        bubble.textContent = text;

        if (list.length) {
            const ul = document.createElement('ul');
            list.forEach(item => {
                const li = document.createElement('li');
                li.textContent = `${item.name}, ${currency} ${Number(item.price).toLocaleString()}`;
                ul.append(li);
            });
            bubble.append(ul);
        }

        chatMessages.append(bubble);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Attachments: one photo or PDF per message. Photos are shrunk on the
    // device first so uploads stay small and fast on mobile data.
    const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;
    const fileInput = document.getElementById('chatFile');
    const preview = document.getElementById('chatAttachmentPreview');
    const previewThumb = document.getElementById('chatAttachmentThumb');
    const previewName = document.getElementById('chatAttachmentName');
    let attachment = null;

    function clearAttachment() {
        attachment = null;
        fileInput.value = '';
        preview.hidden = true;
        previewThumb.replaceChildren(Object.assign(document.createElement('i'), { className: 'fas fa-file-pdf' }));
    }

    function shrinkImage(file) {
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { return Promise.resolve(file); }
        return new Promise(resolve => {
            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = () => {
                const scale = Math.min(1, 1600 / Math.max(img.width, img.height));
                if (scale === 1 && file.size < 1.5 * 1024 * 1024) { URL.revokeObjectURL(url); resolve(file); return; }
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(img.width * scale);
                canvas.height = Math.round(img.height * scale);
                canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                URL.revokeObjectURL(url);
                canvas.toBlob(blob => resolve(blob ? new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file), 'image/jpeg', 0.85);
            };
            img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
            img.src = url;
        });
    }

    document.getElementById('chatAttach').addEventListener('click', () => fileInput.click());
    document.getElementById('chatAttachmentRemove').addEventListener('click', clearAttachment);
    fileInput.addEventListener('change', async () => {
        const chosen = fileInput.files && fileInput.files[0];
        if (!chosen) { return; }
        if (!/^image\/(jpeg|png|webp|gif)$|^application\/pdf$/.test(chosen.type)) {
            addBubble('Please attach a photo (JPG, PNG, WebP or GIF) or a PDF.');
            clearAttachment();
            return;
        }
        const ready = await shrinkImage(chosen);
        if (ready.size > MAX_UPLOAD_BYTES) {
            addBubble('That file is too large. Please send one under 5 MB.');
            clearAttachment();
            return;
        }
        attachment = ready;
        previewName.textContent = chosen.name;
        if (ready.type.startsWith('image/')) {
            const thumb = document.createElement('img');
            thumb.src = URL.createObjectURL(ready);
            thumb.alt = '';
            previewThumb.replaceChildren(thumb);
        }
        preview.hidden = false;
        chatInput.focus();
    });

    function addUserBubble(message, file) {
        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble user';
        if (file) {
            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.alt = 'Attached photo';
                img.className = 'chat-bubble-image';
                bubble.append(img);
            } else {
                const label = document.createElement('div');
                label.className = 'chat-bubble-file';
                label.append(Object.assign(document.createElement('i'), { className: 'fas fa-file-pdf me-1' }), document.createTextNode(file.name));
                bubble.append(label);
            }
        }
        if (message) {
            bubble.append(document.createTextNode(message));
        }
        chatMessages.append(bubble);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    chatForm.addEventListener('submit', async event => {
        event.preventDefault();
        const message = chatInput.value.trim();
        const file = attachment;
        if (!message && !file) return;

        addUserBubble(message, file);
        chatInput.value = '';
        clearAttachment();

        const body = new FormData();
        if (message) { body.append('message', message); }
        if (file) { body.append('file', file, file.name); }

        const typing = document.createElement('div');
        typing.className = 'chat-bubble bot chat-typing';
        typing.textContent = file ? 'Looking at your file...' : '...';
        chatMessages.append(typing);
        chatMessages.scrollTop = chatMessages.scrollHeight;

        try {
            const response = await fetch('/api/v1/chat/message', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body,
            });
            const data = await response.json();
            typing.remove();
            const errorText = data.errors ? Object.values(data.errors).flat()[0] : null;
            addBubble(errorText || data.reply || 'Sorry, something went wrong. Please try again.', data.items || []);
        } catch (error) {
            typing.remove();
            addBubble('Sorry, the assistant is unavailable right now. Please try again later.');
        }
    });

});
</script>


</body>
</html>
