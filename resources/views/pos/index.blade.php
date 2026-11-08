@extends('layouts.app')

@section('title', 'POS')
@section('page-title', 'Point of Sale')

@push('styles')
<style>
    .pos-grid { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 1rem; }
    @media (max-width: 991px) { .pos-grid { grid-template-columns: 1fr; } }
    .pos-menu { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .5rem; }
    .pos-item { display: flex; flex-direction: column; align-items: flex-start; gap: .15rem; text-align: left; padding: .6rem .7rem; border: 1px solid var(--bs-border-color); border-radius: .5rem; background: var(--bs-body-bg); }
    .pos-item:hover, .pos-item:focus-visible { border-color: var(--bs-primary); }
    .pos-line { display: grid; grid-template-columns: 1fr 70px auto; gap: .4rem; align-items: start; padding: .4rem 0; border-bottom: 1px solid var(--bs-border-color); }
    dialog.pos-options { border: 1px solid var(--bs-border-color); border-radius: .75rem; max-width: 420px; width: calc(100% - 2rem); }
    dialog.pos-options::backdrop { background: rgba(0, 0, 0, .4); }
</style>
@endpush

@section('content')
@php $currency = config('restaurant.currency'); @endphp
<form method="POST" action="{{ route('admin.pos.checkout') }}" id="posForm">
    @csrf
    @if($resume)<input type="hidden" name="held_order_id" value="{{ $resume->id }}">@endif
    <div class="pos-grid">
        <section class="panel p-3" aria-label="Menu">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <input id="menuSearch" class="form-control" style="max-width: 260px" placeholder="Search menu…" aria-label="Search menu">
                <select id="categoryFilter" class="form-select" style="max-width: 200px" aria-label="Category">
                    <option value="">All categories</option>
                    @foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                </select>
                <a class="btn btn-outline-secondary ms-auto" href="{{ route('admin.pos.held') }}">Held orders <span class="badge bg-secondary">{{ $heldCount }}</span></a>
            </div>
            <div class="pos-menu" id="menuGrid">
                @foreach($categories as $category)
                    @foreach($category->items as $item)
                        <button type="button" class="pos-item" data-id="{{ $item->id }}" data-category="{{ $category->id }}" data-name="{{ strtolower($item->name) }}">
                            <strong>{{ $item->name }}</strong>
                            <small class="text-muted">{{ $category->name }}</small>
                            <span>{{ $item->variants->isNotEmpty() ? 'from ' : '' }}{{ number_format((float) ($item->variants->min('price') ?? $item->price), 2) }}</span>
                            @if($item->variants->isNotEmpty() || $item->addons->isNotEmpty())<small class="text-primary">Options</small>@endif
                        </button>
                    @endforeach
                @endforeach
            </div>
            @if($categories->flatMap->items->isEmpty())<p class="text-muted mb-0">No available menu items.</p>@endif
        </section>

        <section class="panel p-3" aria-label="Current order">
            <h2 class="h5">{{ $resume ? 'Resumed: ' . $resume->label : 'Current order' }}</h2>
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="form-label small mb-0" for="posBranch">Branch</label>
                    <select class="form-select form-select-sm" id="posBranch" name="branch_id" required>
                        @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id', $resume?->branch_id) == $branch->id)>{{ $branch->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label small mb-0" for="posType">Type</label>
                    <select class="form-select form-select-sm" id="posType" name="order_type">
                        @foreach(['dine_in' => 'Dine-in', 'takeaway' => 'Takeaway', 'delivery' => 'Delivery', 'online' => 'Online'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('order_type', $resume?->order_type ?? 'dine_in') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small mb-0" for="posCustomer">Customer</label>
                    <select class="form-select form-select-sm" id="posCustomer" name="customer_id">
                        <option value="" data-points="0">Walk-in</option>
                        @foreach($customers as $customer)<option value="{{ $customer->id }}" data-points="{{ (int) $customer->loyalty_points }}" @selected(old('customer_id', $resume?->customer_id) == $customer->id)>{{ $customer->name }} · {{ $customer->phone }}</option>@endforeach
                    </select>
                </div>
            </div>

            <div id="cartLines" aria-live="polite"></div>
            <p id="cartEmpty" class="text-muted small my-3">Tap a menu item to add it.</p>
            <div id="cartPayload"></div>

            <div class="row g-2 mt-2">
                @if($canDiscount)
                    <div class="col-6"><label class="form-label small mb-0" for="posDiscount">Discount</label><input class="form-control form-control-sm" id="posDiscount" type="number" name="discount" min="0" step="0.01" value="{{ old('discount', 0) }}"></div>
                @endif
                @if($loyalty['enabled'])
                    <div class="col-6"><label class="form-label small mb-0" for="posPoints">Redeem points <span id="pointsAvailable" class="text-muted"></span></label><input class="form-control form-control-sm" id="posPoints" type="number" name="loyalty_points" min="0" step="1" value="{{ old('loyalty_points', 0) }}"></div>
                @endif
                <div class="col-12"><label class="form-label small mb-0" for="posNotes">Order notes</label><input class="form-control form-control-sm" id="posNotes" name="notes" maxlength="1000" value="{{ old('notes', $resume?->notes) }}"></div>
            </div>

            <dl class="row small mt-3 mb-2">
                <dt class="col-7 fw-normal">Subtotal</dt><dd class="col-5 text-end" id="sumSubtotal">0.00</dd>
                <dt class="col-7 fw-normal">Discounts</dt><dd class="col-5 text-end" id="sumDiscount">0.00</dd>
                <dt class="col-7 fw-normal">{{ $taxConfig['name'] }} ({{ rtrim(rtrim(number_format($taxRate, 2), '0'), '.') }}%{{ $taxConfig['inclusive'] ? ', included' : '' }})</dt><dd class="col-5 text-end" id="sumTax">0.00</dd>
                <dt class="col-7 fw-normal">Delivery</dt><dd class="col-5 text-end" id="sumDelivery">0.00</dd>
                <dt class="col-7 fs-6">Estimated total</dt><dd class="col-5 text-end fs-6 fw-bold">{{ $currency }} <span id="sumTotal">0.00</span></dd>
            </dl>
            <p class="small text-muted">Final prices, tax and point values are confirmed when the order is created.</p>

            <details class="mb-2">
                <summary class="small">Hold this order instead</summary>
                <div class="d-flex gap-2 mt-2">
                    <input class="form-control form-control-sm" name="hold_label" maxlength="100" placeholder="Label, e.g. Table 4" value="{{ old('hold_label', $resume?->label) }}" aria-label="Hold label">
                    <button class="btn btn-sm btn-outline-secondary" type="submit" formaction="{{ route('admin.pos.hold') }}" formnovalidate data-needs-items>Hold</button>
                </div>
            </details>
            <button class="btn btn-primary w-100 btn-lg" type="submit" data-needs-items>Create order</button>
        </section>
    </div>
</form>

<dialog class="pos-options" id="optionsDialog" aria-labelledby="optionsTitle">
    <form method="dialog" id="optionsForm">
        <h2 class="h5" id="optionsTitle"></h2>
        <fieldset id="variantField" class="mb-3"><legend class="form-label fs-6">Choose an option</legend><div id="variantChoices"></div></fieldset>
        <fieldset id="addonField" class="mb-3"><legend class="form-label fs-6">Extras</legend><div id="addonChoices"></div></fieldset>
        <label class="form-label" for="optionNotes">Kitchen note (optional)</label>
        <input class="form-control mb-3" id="optionNotes" maxlength="255">
        <div class="d-flex gap-2 justify-content-end">
            <button class="btn btn-light" value="cancel" type="submit" formnovalidate>Cancel</button>
            <button class="btn btn-primary" value="add" type="submit">Add to order</button>
        </div>
    </form>
</dialog>
@endsection

@php
    $menuData = [];
    foreach ($categories as $category) {
        foreach ($category->items as $item) {
            $menuData[$item->id] = [
                'name' => $item->name,
                'price' => (float) $item->price,
                'variants' => $item->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'price' => (float) $v->price])->values()->all(),
                'addons' => $item->addons->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'price' => (float) $a->price])->values()->all(),
            ];
        }
    }
    $resumeItems = $resume ? $resume->items : [];
@endphp
@push('scripts')
<script>
(function () {
    'use strict';
    const MENU = @json((object) $menuData);
    const RESUME = @json($resumeItems);
    const TAX_RATE = {{ $taxRate }};
    const TAX_INCLUSIVE = {{ $taxConfig['inclusive'] ? 'true' : 'false' }};
    const DELIVERY_FEE = {{ $deliveryFee }};
    const POINT_VALUE = {{ $loyalty['enabled'] ? $loyalty['pointValue'] : 0 }};

    const form = document.getElementById('posForm');
    const linesEl = document.getElementById('cartLines');
    const payload = document.getElementById('cartPayload');
    const emptyEl = document.getElementById('cartEmpty');
    const dialog = document.getElementById('optionsDialog');
    const money = (n) => (Math.round(n * 100) / 100).toFixed(2);
    let cart = [];
    let pending = null;

    function unitPrice(line) {
        const item = MENU[line.menu_item_id];
        const variant = item.variants.find((v) => v.id === line.variant_id);
        const extras = item.addons.filter((a) => line.addon_ids.includes(a.id)).reduce((sum, a) => sum + a.price, 0);
        return (variant ? variant.price : item.price) + extras;
    }

    function describe(line) {
        const item = MENU[line.menu_item_id];
        const variant = item.variants.find((v) => v.id === line.variant_id);
        const extras = item.addons.filter((a) => line.addon_ids.includes(a.id)).map((a) => '+ ' + a.name);
        return { title: item.name + (variant ? ' (' + variant.name + ')' : ''), extras: extras, notes: line.notes || '' };
    }

    function addLine(line) {
        const key = JSON.stringify([line.menu_item_id, line.variant_id, [...line.addon_ids].sort(), line.notes || '']);
        const existing = cart.find((l) => l.key === key);
        if (existing) { existing.quantity += line.quantity; } else { cart.push({ ...line, key }); }
        render();
    }

    function hidden(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = name; input.value = value;
        payload.append(input);
    }

    function render() {
        linesEl.replaceChildren(); payload.replaceChildren();
        let subtotal = 0;
        cart.forEach((line, index) => {
            const info = describe(line);
            const total = unitPrice(line) * line.quantity;
            subtotal += total;

            const row = document.createElement('div'); row.className = 'pos-line';
            const label = document.createElement('div');
            const strong = document.createElement('strong'); strong.textContent = info.title; label.append(strong);
            info.extras.concat(info.notes ? ['Note: ' + info.notes] : []).forEach((text) => {
                const small = document.createElement('small'); small.className = 'd-block text-muted'; small.textContent = text; label.append(small);
            });
            const qty = document.createElement('input');
            qty.type = 'number'; qty.min = '0'; qty.step = '1'; qty.value = line.quantity; qty.className = 'form-control form-control-sm';
            qty.setAttribute('aria-label', 'Quantity of ' + info.title);
            qty.addEventListener('change', () => {
                const value = Number(qty.value);
                if (!(value > 0)) { cart.splice(index, 1); } else { line.quantity = Math.min(99, value); }
                render();
            });
            const price = document.createElement('span'); price.className = 'text-end'; price.textContent = money(total);
            row.append(label, qty, price);
            linesEl.append(row);

            hidden(`items[${index}][menu_item_id]`, line.menu_item_id);
            hidden(`items[${index}][quantity]`, line.quantity);
            if (line.variant_id) { hidden(`items[${index}][variant_id]`, line.variant_id); }
            line.addon_ids.forEach((id, i) => hidden(`items[${index}][addon_ids][${i}]`, id));
            if (line.notes) { hidden(`items[${index}][notes]`, line.notes); }
        });

        const discount = Number(document.getElementById('posDiscount')?.value || 0);
        const points = Number(document.getElementById('posPoints')?.value || 0);
        const discounts = Math.min(subtotal, discount + points * POINT_VALUE);
        const taxable = Math.max(0, subtotal - discounts);
        const tax = TAX_INCLUSIVE ? taxable - taxable / (1 + TAX_RATE / 100) : taxable * TAX_RATE / 100;
        const delivery = document.getElementById('posType').value === 'delivery' ? DELIVERY_FEE : 0;
        document.getElementById('sumSubtotal').textContent = money(subtotal);
        document.getElementById('sumDiscount').textContent = discounts > 0 ? '- ' + money(discounts) : '0.00';
        document.getElementById('sumTax').textContent = money(tax);
        document.getElementById('sumDelivery').textContent = money(delivery);
        document.getElementById('sumTotal').textContent = money(taxable + (TAX_INCLUSIVE ? 0 : tax) + delivery);
        emptyEl.hidden = cart.length > 0;
        form.querySelectorAll('[data-needs-items]').forEach((button) => { button.disabled = cart.length === 0; });
    }

    function choice(type, name, value, text, checked) {
        const wrap = document.createElement('div'); wrap.className = 'form-check';
        const input = document.createElement('input');
        input.className = 'form-check-input'; input.type = type; input.name = name; input.value = value; input.id = name + value; input.checked = checked;
        if (type === 'radio') { input.required = true; }
        const label = document.createElement('label'); label.className = 'form-check-label'; label.htmlFor = input.id; label.textContent = text;
        wrap.append(input, label);
        return wrap;
    }

    function openOptions(id) {
        const item = MENU[id];
        if (!item.variants.length && !item.addons.length) {
            addLine({ menu_item_id: Number(id), quantity: 1, variant_id: null, addon_ids: [], notes: '' });
            return;
        }
        pending = Number(id);
        document.getElementById('optionsTitle').textContent = item.name;
        const variants = document.getElementById('variantChoices'); variants.replaceChildren();
        item.variants.forEach((v, i) => variants.append(choice('radio', 'variant', v.id, v.name + ' - ' + money(v.price), i === 0)));
        document.getElementById('variantField').hidden = !item.variants.length;
        const addons = document.getElementById('addonChoices'); addons.replaceChildren();
        item.addons.forEach((a) => addons.append(choice('checkbox', 'addon', a.id, a.name + ' (+' + money(a.price) + ')', false)));
        document.getElementById('addonField').hidden = !item.addons.length;
        document.getElementById('optionNotes').value = '';
        dialog.showModal();
    }

    dialog.addEventListener('close', () => {
        if (dialog.returnValue !== 'add' || pending === null) { pending = null; return; }
        const optionsForm = document.getElementById('optionsForm');
        const variant = optionsForm.querySelector('input[name="variant"]:checked');
        addLine({
            menu_item_id: pending,
            quantity: 1,
            variant_id: variant ? Number(variant.value) : null,
            addon_ids: [...optionsForm.querySelectorAll('input[name="addon"]:checked')].map((el) => Number(el.value)),
            notes: document.getElementById('optionNotes').value.trim(),
        });
        pending = null;
    });

    document.getElementById('menuGrid').addEventListener('click', (event) => {
        const button = event.target.closest('.pos-item');
        if (button) { openOptions(button.dataset.id); }
    });

    function filterMenu() {
        const term = document.getElementById('menuSearch').value.toLowerCase().trim();
        const category = document.getElementById('categoryFilter').value;
        document.querySelectorAll('.pos-item').forEach((button) => {
            button.hidden = (term && !button.dataset.name.includes(term)) || (category && button.dataset.category !== category);
        });
    }
    document.getElementById('menuSearch').addEventListener('input', filterMenu);
    document.getElementById('categoryFilter').addEventListener('change', filterMenu);
    ['posDiscount', 'posPoints', 'posType'].forEach((id) => document.getElementById(id)?.addEventListener('input', render));
    document.getElementById('posType').addEventListener('change', render);

    const customer = document.getElementById('posCustomer');
    function showPoints() {
        const label = document.getElementById('pointsAvailable');
        if (label) { label.textContent = customer.value ? '(' + customer.selectedOptions[0].dataset.points + ' available)' : ''; }
    }
    customer.addEventListener('change', showPoints);
    showPoints();

    RESUME.forEach((line) => {
        if (MENU[line.menu_item_id]) {
            addLine({
                menu_item_id: Number(line.menu_item_id), quantity: Number(line.quantity) || 1,
                variant_id: line.variant_id ? Number(line.variant_id) : null,
                addon_ids: (line.addon_ids || []).map(Number), notes: line.notes || '',
            });
        }
    });
    form.addEventListener('submit', () => form.querySelectorAll('[type="submit"]').forEach((button) => { button.disabled = true; }));
    render();
})();
</script>
@endpush
