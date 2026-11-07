<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $restaurant->name ?? config('app.name', 'Restaurant') }}</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: #f8f9fa;
        }

        .site-header {
            z-index: 1030;
        }

        .hero {
            background: linear-gradient(135deg, #212529 0%, #495057 100%);
            color: #fff;
            padding: 72px 0;
        }

        .hero .text-muted {
            color: #dee2e6 !important;
        }

        .hero-visual {
            font-size: 9rem;
            color: rgba(255, 255, 255, .12);
            text-align: center;
        }

        .menu-card {
            height: 100%;
            border: 0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,.06);
        }

        .menu-card img {
            width: 100%;
            height: 210px;
            object-fit: cover;
        }

        .price {
            font-weight: 700;
            font-size: 1.1rem;
        }

        .order-modal .modal-header,
        .order-modal .modal-footer {
            padding: .75rem 1rem;
        }

        .order-modal .form-label {
            font-size: .8rem;
            font-weight: 600;
            margin-bottom: .25rem;
        }

        .order-modal .form-control,
        .order-modal .form-select {
            font-size: .875rem;
        }

        .order-section-title {
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #6c757d;
        }

        .cart-line + .cart-line {
            border-top: 1px solid #e9ecef;
        }

        .cart-thumb,
        .cart-thumb-placeholder {
            width: 56px;
            height: 56px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .cart-thumb-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f3f5;
            color: #adb5bd;
        }

        .order-summary {
            position: sticky;
            top: 0;
        }

        .floating-accessibility {
            position: fixed;
            right: 18px;
            bottom: 84px;
            z-index: 1055;
        }

        .floating-accessibility .btn {
            width: 46px;
            height: 46px;
            border-radius: 50%;
        }

        .a11y-menu {
            min-width: 220px;
            padding: .35rem;
            font-size: .875rem;
        }

        .a11y-menu .dropdown-item {
            padding: .35rem .6rem;
            border-radius: 6px;
        }

        .a11y-menu .dropdown-item i {
            width: 1.25rem;
        }

        .a11y-menu .dropdown-divider {
            margin: .35rem 0;
        }

        .a11y-menu .a11y-label {
            font-size: .75rem;
            font-weight: 600;
            color: #6c757d;
            padding: .25rem .6rem;
        }

        .chat-widget {
            position: fixed;
            right: 18px;
            bottom: 18px;
            z-index: 1056;
        }

        .chat-launcher {
            width: 54px;
            height: 54px;
            border-radius: 50%;
        }

        .chat-panel {
            position: absolute;
            right: 0;
            bottom: 66px;
            width: 360px;
            height: 480px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border-radius: 12px;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: .5rem;
            padding: .75rem;
            background: #f8f9fa;
        }

        .chat-bubble {
            max-width: 85%;
            padding: .5rem .75rem;
            border-radius: 12px;
            font-size: .875rem;
            word-wrap: break-word;
        }

        .chat-bubble.bot {
            background: #fff;
            border: 1px solid #e9ecef;
            align-self: flex-start;
        }

        .chat-bubble.user {
            background: #0d6efd;
            color: #fff;
            align-self: flex-end;
        }

        .chat-bubble ul {
            padding-left: 1.1rem;
            margin: .4rem 0 0;
        }

        @media (max-width: 575.98px) {
            .chat-widget {
                right: 12px;
                bottom: 12px;
            }

            .chat-panel {
                position: fixed;
                left: 8px;
                right: 8px;
                bottom: 74px;
                width: auto;
                height: min(70vh, 520px);
            }

            .floating-accessibility {
                right: 12px;
                bottom: 76px;
            }
        }

        .a11y-high-contrast {
            filter: contrast(1.6);
        }

        .a11y-grayscale {
            filter: grayscale(1);
        }

        html.a11y-reduce-motion *,
        html.a11y-reduce-motion *::before,
        html.a11y-reduce-motion *::after {
            transition: none !important;
            animation: none !important;
            scroll-behavior: auto !important;
        }
    </style>
</head>

<body>

<header class="site-header sticky-top bg-white shadow-sm">
    <div class="container d-flex align-items-center gap-3 py-2">

        <a class="d-flex align-items-center gap-2 text-dark text-decoration-none fw-bold fs-5" href="{{ route('home') }}">
            @if(!empty($restaurant->logo_path))
                <img src="{{ asset('storage/' . $restaurant->logo_path) }}" alt="" height="36" class="rounded">
            @else
                <i class="fas fa-utensils text-primary"></i>
            @endif
            <span>{{ $restaurant->name }}</span>
        </a>

        <button
            type="button"
            class="btn btn-dark position-relative ms-auto d-flex align-items-center gap-2"
            data-bs-toggle="modal"
            data-bs-target="#cartModal"
            aria-label="Open cart"
        >
            <i class="fas fa-cart-shopping"></i>
            <span class="d-none d-sm-inline">Cart</span>
            <span class="badge bg-warning text-dark" id="cartCount">0</span>
            <span class="small d-none d-md-inline">{{ $restaurant->currency }} <span id="headerSubtotal">0.00</span></span>
        </button>

    </div>
</header>

<main id="siteMain">

<section class="hero">
    <div class="container">
        <div class="row align-items-center g-4">

            <div class="col-lg-7">
                <h1 class="display-5 fw-bold">{{ $restaurant->name }}</h1>

                <p class="lead text-muted mb-3">
                    Order online and pick up or get it delivered.
                </p>

                <ul class="list-unstyled text-muted mb-4">
                    @if(!empty($restaurant->address))
                        <li class="mb-1"><i class="fas fa-location-dot me-2"></i>{{ $restaurant->address }}</li>
                    @endif
                    @if(!empty($restaurant->phone))
                        <li><i class="fas fa-phone me-2"></i>{{ $restaurant->phone }}</li>
                    @endif
                </ul>

                <div class="d-flex flex-wrap gap-2">
                    <a href="#menu" class="btn btn-primary btn-lg">
                        <i class="fas fa-bowl-food me-2"></i>Browse menu
                    </a>
                    <button
                        type="button"
                        class="btn btn-outline-light btn-lg"
                        data-bs-toggle="modal"
                        data-bs-target="#cartModal"
                    >
                        <i class="fas fa-cart-shopping me-2"></i>View cart
                    </button>
                </div>
            </div>

            <div class="col-lg-5 d-none d-lg-block">
                <div class="hero-visual">
                    <i class="fas fa-utensils"></i>
                </div>
            </div>

        </div>
    </div>
</section>


<section id="menu" class="py-5">

    <div class="container">

        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">

            <div>
                <h2 class="fw-bold mb-1">
                    Our Menu
                </h2>

                <p class="text-muted mb-0">
                    Choose from our available food, drinks and services.
                </p>
            </div>

            <div style="max-width: 320px; width: 100%;">
                <input
                    type="search"
                    id="menuSearch"
                    class="form-control"
                    placeholder="Search menu..."
                >
            </div>

        </div>


        @if($categories->count())

            <div class="d-flex flex-wrap gap-2 mb-4">

                <button
                    class="btn btn-dark category-filter"
                    data-category="all"
                >
                    All
                </button>

                @foreach($categories as $category)

                    <button
                        class="btn btn-outline-dark category-filter"
                        data-category="{{ $category->id }}"
                    >
                        {{ $category->name }}
                    </button>

                @endforeach

            </div>

        @endif


        <div class="row g-4" id="menuGrid">

            @forelse($menuItems as $item)

                <div
                    class="col-sm-6 col-lg-4 col-xl-3 menu-item"
                    data-category="{{ $item->menu_category_id }}"
                    data-name="{{ strtolower($item->name) }}"
                >

                    <div class="card menu-card">

                        @if(!empty($item->image_path))

                            <img
                                src="{{ asset('storage/' . $item->image_path) }}"
                                alt="{{ $item->name }}"
                            >

                        @else

                            <div
                                class="d-flex align-items-center justify-content-center bg-light"
                                style="height: 210px;"
                            >
                                <span class="text-muted">
                                    No image
                                </span>
                            </div>

                        @endif


                        <div class="card-body d-flex flex-column">

                            <h5 class="fw-bold">
                                {{ $item->name }}
                            </h5>

                            @if(!empty($item->description))
                                <p class="text-muted small flex-grow-1">
                                    {{ $item->description }}
                                </p>
                            @endif

                            <div class="price mb-3">
                                {{ $restaurant->currency }}
                                @if($item->variants->isNotEmpty())
                                    from {{ number_format((float) $item->variants->min('price'), 2) }}
                                @else
                                    {{ number_format((float) $item->price, 2) }}
                                @endif
                            </div>

                            <button
                                type="button"
                                class="btn btn-primary w-100 mt-auto"
                                data-bs-toggle="modal"
                                data-bs-target="#itemModal-{{ $item->id }}"
                            >
                                Add to Order
                            </button>

                        </div>

                    </div>

                </div>

            @empty

                <div class="col-12">
                    <div class="alert alert-light border text-center">
                        No menu items are currently available.
                    </div>
                </div>

            @endforelse

        </div>

        @foreach($menuItems as $item)
            <div class="modal fade" id="itemModal-{{ $item->id }}" tabindex="-1" aria-labelledby="itemModalLabel-{{ $item->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="itemModalLabel-{{ $item->id }}">{{ $item->name }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="item-options" data-item="{{ $item->id }}">
                                @if($item->variants->isNotEmpty())
                                    <label class="form-label small mb-1" for="variant-{{ $item->id }}">Choose</label>
                                    <select class="form-select mb-3 item-variant" id="variant-{{ $item->id }}">
                                        @foreach($item->variants as $variant)
                                            <option value="{{ $variant->id }}" data-name="{{ $variant->name }}" data-price="{{ $variant->price }}">{{ $variant->name }} — {{ number_format((float) $variant->price, 2) }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                @foreach($item->addons as $addon)
                                    <div class="form-check mb-2">
                                        <input class="form-check-input item-addon" type="checkbox" id="addon-{{ $addon->id }}" value="{{ $addon->id }}" data-name="{{ $addon->name }}" data-price="{{ $addon->price }}">
                                        <label class="form-check-label" for="addon-{{ $addon->id }}">{{ $addon->name }} (+{{ number_format((float) $addon->price, 2) }})</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button
                                type="button"
                                class="btn btn-primary add-to-cart"
                                data-id="{{ $item->id }}"
                                data-name="{{ $item->name }}"
                                data-price="{{ $item->price }}"
                                data-image="{{ !empty($item->image_path) ? asset('storage/' . $item->image_path) : '' }}"
                            >
                                Add to Order
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

    </div>

</section>

</main>


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

                    <div class="col-lg-7 p-3">
                        <div class="order-section-title mb-2">Items</div>

                        <div id="cartEmpty" class="text-center text-muted py-4">
                            <i class="fas fa-basket-shopping fs-2 mb-2 d-block"></i>
                            Your cart is empty. Add items from the menu.
                        </div>

                        <ul id="cartItems" class="list-unstyled mb-0"></ul>
                    </div>

                    <div class="col-lg-5 p-3 bg-light">
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
                            @if($branches->count() > 1)
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
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Continue shopping</button>
                    <button class="btn btn-success px-4" id="submitOrder" type="submit" disabled>Place order</button>
                </div>
            </div>

        </form>
    </div>
</div>


<div class="floating-accessibility dropup">
    <button
        type="button"
        class="btn btn-dark shadow"
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

<div class="chat-widget" id="chatWidget">
    <section class="chat-panel card shadow-lg border-0" id="chatPanel" aria-labelledby="chatPanelLabel" hidden>
        <header class="card-header bg-primary text-white d-flex align-items-center justify-content-between py-2 border-0">
            <div>
                <div class="fw-semibold" id="chatPanelLabel">Restaurant Assistant</div>
                <small class="opacity-75">Ask about the menu, prices or orders</small>
            </div>
            <button type="button" class="btn btn-sm text-white" id="chatClose" aria-label="Close chat">
                <i class="fas fa-xmark"></i>
            </button>
        </header>
        <div class="chat-messages" id="chatMessages" aria-live="polite">
            <div class="chat-bubble bot">Hello. Ask me about the menu, prices, orders or reservations.</div>
        </div>
        <form id="chatForm" class="card-footer bg-white p-2 d-flex gap-2 border-top">
            <input id="chatInput" class="form-control form-control-sm" maxlength="1000" placeholder="Type your message" aria-label="Message" required>
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
    }

    let selectedCategory = 'all';

    function filterItems() {

        const term = (search?.value || '').toLowerCase().trim();

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
        });
    }

    search?.addEventListener('input', filterItems);

    categoryButtons.forEach(button => {

        button.addEventListener('click', function () {

            selectedCategory = this.dataset.category;

            categoryButtons.forEach(btn => {
                btn.classList.remove('btn-dark');
                btn.classList.add('btn-outline-dark');
            });

            this.classList.remove('btn-outline-dark');
            this.classList.add('btn-dark');

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
            const modal = button.closest('.modal');
            if (modal) { bootstrap.Modal.getInstance(modal)?.hide(); }
        });
    });

    renderCart();

    @if($errors->any())
        bootstrap.Modal.getOrCreateInstance(document.getElementById('cartModal')).show();
    @endif

});

document.addEventListener('DOMContentLoaded', function () {

    const root = document.documentElement;
    const prefs = JSON.parse(localStorage.getItem('a11y') || '{}');
    let fontScale = prefs.font || 0;
    const toggles = prefs.toggles || {};
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
                li.textContent = `${item.name} — ${currency} ${Number(item.price).toFixed(2)}`;
                ul.append(li);
            });
            bubble.append(ul);
        }

        chatMessages.append(bubble);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    chatForm.addEventListener('submit', async event => {
        event.preventDefault();
        const message = chatInput.value.trim();
        if (!message) return;

        addBubble(message, [], 'user');
        chatInput.value = '';

        try {
            const response = await fetch('/api/v1/chat/message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ message }),
            });
            const data = await response.json();
            addBubble(data.reply || 'Sorry, something went wrong. Please try again.', data.items || []);
        } catch (error) {
            addBubble('Sorry, the assistant is unavailable right now. Please try again later.');
        }
    });

});
</script>

</body>
</html>
