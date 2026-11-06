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
    </style>
</head>

<body>

<nav class="navbar navbar-expand-lg bg-white shadow-sm sticky-top">
    <div class="container">

        <a class="navbar-brand fw-bold" href="{{ route('home') }}">
            {{ config('app.name', 'Restaurant') }}
        </a>

        <div class="ms-auto">
            <a
                href="{{ route('login') }}"
                class="btn btn-outline-dark"
            >
                Staff Login
            </a>
        </div>

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
                                {{ $restaurant->currency }} {{ number_format((float) $item->price, 2) }}
                            </div>

                            <button
                                type="button"
                                class="btn btn-primary w-100 add-to-cart"
                                data-id="{{ $item->id }}"
                                data-name="{{ $item->name }}"
                                data-price="{{ $item->price }}"
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
>
    ♿
</button>

<button
    type="button"
    class="btn btn-primary floating-chat"
    title="Chat with us"
>
    💬
</button>


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

        cartItems.forEach((line, id) => {
            subtotal += line.price * line.quantity;
            const row = document.createElement('li');
            row.className = 'list-group-item d-flex justify-content-between align-items-center';
            const text = document.createElement('span');
            text.textContent = `${line.name} × ${line.quantity}`;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-sm btn-outline-danger';
            remove.textContent = 'Remove';
            remove.addEventListener('click', () => { cartItems.delete(id); renderCart(); });
            row.append(text, remove);
            cartList.append(row);

            [['menu_item_id', id], ['quantity', line.quantity]].forEach(([key, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `items[${index}][${key}]`;
                input.value = value;
                cartPayload.append(input);
            });
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
            const line = cartItems.get(id) || { name: button.dataset.name, price: Number(button.dataset.price), quantity: 0 };
            line.quantity++;
            cartItems.set(id, line);
            renderCart();
            document.getElementById('checkout').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

});
</script>

</body>
</html>
