<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'Restaurant Management System') }}</title>

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

        .hero {
            padding: 80px 0;
            background: #ffffff;
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

        .floating-accessibility,
        .floating-chat {
            position: fixed;
            right: 18px;
            width: 54px;
            height: 54px;
            border-radius: 50%;
            border: 0;
            z-index: 1055;
        }

        .floating-chat {
            bottom: 18px;
        }

        .floating-accessibility {
            bottom: 84px;
        }

        .a11y-high-contrast {
            filter: contrast(1.6);
        }

        .a11y-grayscale {
            filter: grayscale(1);
        }

        .a11y-reduce-motion *,
        .a11y-reduce-motion *::before,
        .a11y-reduce-motion *::after {
            transition: none !important;
            animation: none !important;
            scroll-behavior: auto !important;
        }
    </style>
</head>

<body>

<nav class="navbar navbar-expand-lg bg-white shadow-sm sticky-top">
    <div class="container">

        <a class="navbar-brand fw-bold" href="{{ route('home') }}">
            {{ config('app.name', 'Restaurant') }}
        </a>

    </div>
</nav>


<section class="hero">
    <div class="container text-center">

        <h1 class="display-5 fw-bold">
            Welcome to Our Restaurant
        </h1>

        <p class="lead text-muted">
            Browse our menu and place your order online.
        </p>

        <a href="#menu" class="btn btn-primary btn-lg">
            Order Now
        </a>

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
                            >
                                Add to Order
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="card border-0 shadow-sm mt-5" id="checkout">
            <div class="card-body p-4">
                <h2 class="h4">Your order</h2>
                <div id="cartEmpty" class="text-muted">Add menu items to start an order.</div>
                <ul id="cartItems" class="list-group list-group-flush mb-3"></ul>
                <div class="fw-bold mb-4">Estimated subtotal: <span id="cartSubtotal">0.00</span></div>

                @if($errors->any())
                    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif

                <form id="checkoutForm" method="POST" action="{{ route('order.store') }}">
                    @csrf
                    <div id="cartPayload"></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="customerName">Name</label><input class="form-control" id="customerName" name="name" value="{{ old('name') }}" required maxlength="255"></div>
                        <div class="col-md-6"><label class="form-label" for="customerPhone">Phone</label><input class="form-control" id="customerPhone" name="phone" value="{{ old('phone') }}" required maxlength="40"></div>
                        <div class="col-md-6"><label class="form-label" for="customerEmail">Email (optional)</label><input class="form-control" id="customerEmail" type="email" name="email" value="{{ old('email') }}" maxlength="255"></div>
                        <div class="col-md-6"><label class="form-label" for="customerAddress">Delivery address (optional)</label><input class="form-control" id="customerAddress" name="address" value="{{ old('address') }}" maxlength="1000"></div>
                        @if($branches->count() > 1)
                            <div class="col-md-6"><label class="form-label" for="orderBranch">Branch</label><select class="form-select" id="orderBranch" name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
                        @elseif($branches->count() === 1)
                            <input type="hidden" name="branch_id" value="{{ $branches->first()->id }}">
                        @endif
                        <div class="col-12"><label class="form-label" for="orderNotes">Notes (optional)</label><textarea class="form-control" id="orderNotes" name="notes" maxlength="1000">{{ old('notes') }}</textarea></div>
                        <div class="col-12"><button class="btn btn-success btn-lg" id="submitOrder" type="submit" disabled>Place order</button></div>
                    </div>
                </form>
            </div>
        </div>

    </div>

</section>


<button
    type="button"
    class="btn btn-dark floating-accessibility"
    title="Accessibility"
    aria-label="Accessibility"
    data-bs-toggle="offcanvas"
    data-bs-target="#accessibilityPanel"
>
    <i class="fas fa-universal-access"></i>
</button>

<button
    type="button"
    class="btn btn-primary floating-chat"
    title="Chat with us"
    aria-label="Chat with us"
    data-bs-toggle="offcanvas"
    data-bs-target="#chatPanel"
>
    <i class="fas fa-comments"></i>
</button>

<div class="offcanvas offcanvas-end" id="accessibilityPanel" tabindex="-1" aria-labelledby="accessibilityPanelLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="accessibilityPanelLabel">Accessibility</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-grid gap-2">
                <button type="button" class="btn btn-outline-secondary" data-a11y-font="1">Increase text</button>
                <button type="button" class="btn btn-outline-secondary" data-a11y-font="-1">Decrease text</button>
                <button type="button" class="btn btn-outline-secondary" data-a11y-toggle="high-contrast">High contrast</button>
                <button type="button" class="btn btn-outline-secondary" data-a11y-toggle="grayscale">Grayscale</button>
                <button type="button" class="btn btn-outline-secondary" data-a11y-toggle="reduce-motion">Reduce motion</button>
                <button type="button" class="btn btn-outline-danger" data-a11y-reset>Reset</button>
    </div>
</div>

<div class="offcanvas offcanvas-end" id="chatPanel" tabindex="-1" aria-labelledby="chatPanelLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="chatPanelLabel">Restaurant Assistant</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
        <div id="chatMessages" class="d-flex flex-column gap-2 flex-grow-1">
            <div class="alert alert-light border mb-0">Hello. Ask me about the menu, prices, orders or reservations.</div>
        </div>
        <form id="chatForm" class="d-flex gap-2 mt-3">
            <input id="chatInput" class="form-control" maxlength="1000" placeholder="Type your message" required>
            <button class="btn btn-primary" type="submit">Send</button>
        </form>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const search = document.getElementById('menuSearch');
    const items = document.querySelectorAll('.menu-item');
    const categoryButtons = document.querySelectorAll('.category-filter');
    const cartItems = new Map();
    const cartList = document.getElementById('cartItems');
    const cartEmpty = document.getElementById('cartEmpty');
    const cartPayload = document.getElementById('cartPayload');
    const submitOrder = document.getElementById('submitOrder');

    function renderCart() {
        cartList.replaceChildren();
        cartPayload.replaceChildren();
        let subtotal = 0;
        let index = 0;

        const addHidden = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            cartPayload.append(input);
        };

        cartItems.forEach((line, key) => {
            subtotal += line.price * line.quantity;
            const row = document.createElement('li');
            row.className = 'list-group-item d-flex justify-content-between align-items-center';
            const text = document.createElement('span');
            text.textContent = `${line.name} × ${line.quantity}`;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-sm btn-outline-danger';
            remove.textContent = 'Remove';
            remove.addEventListener('click', () => { cartItems.delete(key); renderCart(); });
            row.append(text, remove);
            cartList.append(row);

            addHidden(`items[${index}][menu_item_id]`, line.id);
            addHidden(`items[${index}][quantity]`, line.quantity);
            if (line.variantId) { addHidden(`items[${index}][variant_id]`, line.variantId); }
            line.addonIds.forEach((addonId, i) => addHidden(`items[${index}][addon_ids][${i}]`, addonId));
            index++;
        });

        cartEmpty.hidden = cartItems.size > 0;
        document.getElementById('cartSubtotal').textContent = subtotal.toFixed(2);
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
            const line = cartItems.get(key) || { id, variantId: variant ? variant.value : null, addonIds, name, price, quantity: 0 };
            line.quantity++;
            cartItems.set(key, line);
            renderCart();
            const modal = button.closest('.modal');
            if (modal) { bootstrap.Modal.getInstance(modal)?.hide(); }
            document.getElementById('checkout').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const root = document.documentElement;
    const prefs = JSON.parse(localStorage.getItem('a11y') || '{}');
    let fontScale = prefs.font || 0;
    const toggles = prefs.toggles || {};

    function applyA11y() {
        root.style.fontSize = fontScale ? `${100 + fontScale * 10}%` : '';
        ['high-contrast', 'grayscale', 'reduce-motion'].forEach(name => {
            document.body.classList.toggle(`a11y-${name}`, !!toggles[name]);
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

    const chatForm = document.getElementById('chatForm');
    const chatInput = document.getElementById('chatInput');
    const chatMessages = document.getElementById('chatMessages');

    function addBubble(text, items = []) {
        const bubble = document.createElement('div');
        bubble.className = 'alert alert-light border mb-0';
        bubble.textContent = text;

        if (items.length) {
            const list = document.createElement('ul');
            list.className = 'mb-0 mt-2';
            items.forEach(item => {
                const li = document.createElement('li');
                li.textContent = `${item.name} — ${Number(item.price).toFixed(2)}`;
                list.append(li);
            });
            bubble.append(list);
        }

        chatMessages.append(bubble);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    chatForm?.addEventListener('submit', async event => {
        event.preventDefault();
        const message = chatInput.value.trim();
        if (!message) return;

        const user = document.createElement('div');
        user.className = 'alert alert-primary mb-0 ms-auto';
        user.textContent = message;
        chatMessages.append(user);
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
