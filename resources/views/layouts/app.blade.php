<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <meta name="csrf-token"
          content="{{ csrf_token() }}">

    <title>
        @yield('title', 'Dashboard') -
        {{ config('restaurant.name', 'Kemmy Restaurant') }}
    </title>

    {{-- Font --}}
    <link rel="preconnect"
          href="https://fonts.googleapis.com">

    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    {{-- Bootstrap --}}
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet">

    {{-- Font Awesome --}}
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        rel="stylesheet">

    {{-- Admin CSS --}}
    <link
        href="{{ asset('css/restaurant-admin.css') }}?v={{ @filemtime(public_path('css/restaurant-admin.css')) }}"
        rel="stylesheet">

    @stack('styles')
</head>

<body>

<div class="restaurant-admin-wrapper">

    {{-- =========================================================
         SIDEBAR OVERLAY
    ========================================================== --}}
    <div class="admin-sidebar-overlay"
         id="sidebarOverlay"></div>


    {{-- =========================================================
         SIDEBAR
    ========================================================== --}}
    <aside class="admin-sidebar"
           id="adminSidebar">

        <div class="sidebar-header">

            <a href="{{ url('/admin/dashboard') }}"
               class="sidebar-brand">

                <span class="sidebar-brand-icon">
                    <i class="fas fa-utensils"></i>
                </span>

                <span class="sidebar-brand-content">

                    <strong>
                        {{ config('restaurant.name', 'Kemmy Restaurant') }}
                    </strong>

                    <small>
                        Restaurant Management
                    </small>

                </span>

            </a>


            <button type="button"
                    class="sidebar-close d-lg-none"
                    id="sidebarClose">

                <i class="fas fa-times"></i>

            </button>

        </div>


        <div class="sidebar-navigation">

            {{-- Dashboard --}}
            <a href="{{ url('/admin/dashboard') }}"
               class="sidebar-item {{ request()->is('admin/dashboard') ? 'active' : '' }}">

                <span class="sidebar-item-icon">
                    <i class="fas fa-chart-line"></i>
                </span>

                <span>Dashboard</span>

            </a>


            {{-- =====================================================
                 POS
            ====================================================== --}}
            <button type="button"
                    class="sidebar-group-toggle"
                    data-sidebar-target="sidebarPos">

                <span class="sidebar-group-label">

                    <span class="sidebar-item-icon">
                        <i class="fas fa-cash-register"></i>
                    </span>

                    <span>POS</span>

                </span>

                <i class="fas fa-chevron-down sidebar-chevron"></i>

            </button>

            <div class="sidebar-submenu
                        {{ request()->is('admin/pos*') ? 'show' : '' }}"
                 id="sidebarPos">

                <a href="{{ url('/admin/pos') }}">
                    <i class="fas fa-plus"></i>
                    New Sale
                </a>

                <a href="{{ url('/admin/pos/held') }}">
                    <i class="fas fa-pause"></i>
                    Held Orders
                </a>

                <a href="{{ url('/admin/pos/current') }}">
                    <i class="fas fa-clock"></i>
                    Current Orders
                </a>

                <a href="{{ url('/admin/pos/history') }}">
                    <i class="fas fa-clock-rotate-left"></i>
                    Order History
                </a>

                <a href="{{ url('/admin/pos/returns') }}">
                    <i class="fas fa-rotate-left"></i>
                    Returns / Refunds
                </a>

            </div>


            {{-- =====================================================
                 ORDERS
            ====================================================== --}}
            <button type="button"
                    class="sidebar-group-toggle"
                    data-sidebar-target="sidebarOrders">

                <span class="sidebar-group-label">

                    <span class="sidebar-item-icon">
                        <i class="fas fa-receipt"></i>
                    </span>

                    <span>Orders</span>

                </span>

                <i class="fas fa-chevron-down sidebar-chevron"></i>

            </button>

            <div class="sidebar-submenu
                        {{ request()->is('admin/orders*') ? 'show' : '' }}"
                 id="sidebarOrders">

                <a href="{{ url('/admin/orders/dine-in') }}">
                    <i class="fas fa-utensils"></i>
                    Dine-In
                </a>

                <a href="{{ url('/admin/orders/takeaway') }}">
                    <i class="fas fa-bag-shopping"></i>
                    Takeaway
                </a>

                <a href="{{ url('/admin/orders/delivery') }}">
                    <i class="fas fa-motorcycle"></i>
                    Delivery
                </a>

                <a href="{{ url('/admin/orders/online') }}">
                    <i class="fas fa-globe"></i>
                    Online Orders
                </a>

                <a href="{{ url('/admin/orders/cancelled') }}">
                    <i class="fas fa-ban"></i>
                    Cancelled Orders
                </a>

            </div>


            {{-- =====================================================
                 KITCHEN
            ====================================================== --}}
            <button type="button"
                    class="sidebar-group-toggle"
                    data-sidebar-target="sidebarKitchen">

                <span class="sidebar-group-label">

                    <span class="sidebar-item-icon">
                        <i class="fas fa-fire-burner"></i>
                    </span>

                    <span>Kitchen</span>

                </span>

                <i class="fas fa-chevron-down sidebar-chevron"></i>

            </button>

            <div class="sidebar-submenu
                        {{ request()->is('admin/kitchen*') ? 'show' : '' }}"
                 id="sidebarKitchen">

                <a href="{{ url('/admin/kitchen') }}">
                    <i class="fas fa-display"></i>
                    Kitchen Display
                </a>

                <a href="{{ url('/admin/kitchen/pending') }}">
                    <i class="fas fa-hourglass-start"></i>
                    Pending
                </a>

                <a href="{{ url('/admin/kitchen/preparing') }}">
                    <i class="fas fa-fire"></i>
                    Preparing
                </a>

                <a href="{{ url('/admin/kitchen/ready') }}">
                    <i class="fas fa-bell-concierge"></i>
                    Ready
                </a>

                <a href="{{ url('/admin/kitchen/completed') }}">
                    <i class="fas fa-circle-check"></i>
                    Completed
                </a>

            </div>


            {{-- =====================================================
                 MENU
            ====================================================== --}}
            <button type="button"
                    class="sidebar-group-toggle"
                    data-sidebar-target="sidebarMenu">

                <span class="sidebar-group-label">

                    <span class="sidebar-item-icon">
                        <i class="fas fa-book-open"></i>
                    </span>

                    <span>Menu</span>

                </span>

                <i class="fas fa-chevron-down sidebar-chevron"></i>

            </button>

            <div class="sidebar-submenu
                        {{ request()->is('admin/menu*') || request()->is('admin/recipes*') ? 'show' : '' }}"
                 id="sidebarMenu">

                <a href="{{ url('/admin/menu-items') }}">
                    <i class="fas fa-burger"></i>
                    Menu Items
                </a>

                <a href="{{ url('/admin/menu-categories') }}">
                    <i class="fas fa-layer-group"></i>
                    Categories
                </a>

                <a href="{{ url('/admin/menu-addons') }}">
                    <i class="fas fa-circle-plus"></i>
                    Add-ons
                </a>

                <a href="{{ url('/admin/menu-variants') }}">
                    <i class="fas fa-code-branch"></i>
                    Variants
                </a>

                <a href="{{ url('/admin/recipes') }}">
                    <i class="fas fa-book"></i>
                    Recipes
                </a>

                <a href="{{ url('/admin/menu-availability') }}">
                    <i class="fas fa-toggle-on"></i>
                    Availability
                </a>

            </div>


            {{-- =====================================================
                 TABLES
            ====================================================== --}}
            <button type="button"
                    class="sidebar-group-toggle"
                    data-sidebar-target="sidebarTables">

                <span class="sidebar-group-label">

                    <span class="sidebar-item-icon">
                        <i class="fas fa-chair"></i>
                    </span>

                    <span>Tables</span>

                </span>

                <i class="fas fa-chevron-down sidebar-chevron"></i>

            </button>

            <div class="sidebar-submenu"
                 id="sidebarTables">

                <a href="{{ url('/admin/floors') }}">
                    <i class="fas fa-building"></i>
                    Floors / Sections
                </a>

                <a href="{{ url('/admin/tables') }}">
                    <i class="fas fa-chair"></i>
                    Tables
                </a>

                <a href="{{ url('/admin/reservations') }}">
                    <i class="fas fa-calendar-check"></i>
                    Reservations
                </a>

                <a href="{{ url('/admin/qr-ordering') }}">
                    <i class="fas fa-qrcode"></i>
                    QR Ordering
                </a>

            </div>


            {{-- =====================================================
                 CUSTOMERS
            ====================================================== --}}
            <button type="button"
                    class="sidebar-group-toggle"
                    data-sidebar-target="sidebarCustomers">

                <span class="sidebar-group-label">

                    <span class="sidebar-item-icon">
                        <i class="fas fa-users"></i>
                    </span>

                    <span>Customers</span>

                </span>

                <i class="fas fa-chevron-down sidebar-chevron"></i>

            </button>

            <div class="sidebar-submenu"
                 id="sidebarCustomers">

                <a href="{{ url('/admin/customers') }}">
                    <i class="fas fa-user-group"></i>
                    Customers
                </a>

                <a href="{{ url('/admin/loyalty') }}">
                    <i class="fas fa-star"></i>
                    Loyalty
                </a>

                <a href="{{ url('/admin/feedback') }}">
                    <i class="fas fa-comments"></i>
                    Feedback
                </a>

                <a href="{{ url('/admin/customer-accounts') }}">
                    <i class="fas fa-address-card"></i>
                    Customer Accounts
                </a>

            </div>


            {{-- =====================================================
                 INVENTORY
            ====================================================== --}}
            <button type="button"
                    class="sidebar-group-toggle"
                    data-sidebar-target="sidebarInventory">

                <span class="sidebar-group-label">

                    <span class="sidebar-item-icon">
                        <i class="fas fa-boxes-stacked"></i>
                    </span>

                    <span>Inventory</span>

                </span>

                <i class="fas fa-chevron-down sidebar-chevron"></i>

            </button>

            <div class="sidebar-submenu"
                 id="sidebarInventory">

                <a href="{{ url('/admin/inventory/products') }}">
                    <i class="fas fa-box"></i>
                    Products
                </a>

                <a href="{{ url('/admin/inventory/categories') }}">
                    <i class="fas fa-tags"></i>
                    Categories
                </a>

                <a href="{{ url('/admin/inventory/stock') }}">
                    <i class="fas fa-warehouse"></i>
                    Stock
                </a>

                <a href="{{ url('/admin/inventory/adjustments') }}">
                    <i class="fas fa-sliders"></i>
                    Stock Adjustments
                </a>

                <a href="{{ url('/admin/inventory/transfers') }}">
                    <i class="fas fa-right-left"></i>
                    Transfers
                </a>

                <a href="{{ url('/admin/inventory/wastage') }}">
                    <i class="fas fa-trash-can"></i>
                    Wastage
                </a>

                <a href="{{ url('/admin/inventory/low-stock') }}">
                    <i class="fas fa-triangle-exclamation"></i>
                    Low Stock
                </a>

            </div>


            {{-- =====================================================
                 PURCHASING
            ====================================================== --}}
            <button type="button"
                    class="sidebar-group-toggle"
                    data-sidebar-target="sidebarPurchasing">

                <span class="sidebar-group-label">

                    <span class="sidebar-item-icon">
                        <i class="fas fa-cart-flatbed"></i>
                    </span>

                    <span>Purchasing</span>

                </span>

                <i class="fas fa-chevron-down sidebar-chevron"></i>

            </button>

            <div class="sidebar-submenu"
                 id="sidebarPurchasing">

                <a href="{{ url('/admin/suppliers') }}">
                    <i class="fas fa-truck"></i>
                    Suppliers
                </a>

                <a href="{{ url('/admin/purchase-orders') }}">
                    <i class="fas fa-file-circle-plus"></i>
                    Purchase Orders
                </a>

                <a href="{{ url('/admin/purchases') }}">
                    <i class="fas fa-cart-shopping"></i>
                    Purchases
                </a>

                <a href="{{ url('/admin/supplier-payments') }}">
                    <i class="fas fa-money-check-dollar"></i>
                    Supplier Payments
                </a>

                <a href="{{ url('/admin/payables') }}">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Payables
                </a>

            </div>


            {{-- =====================================================
                 FINANCE
            ====================================================== --}}
            <button type="button"
                    class="sidebar-group-toggle"
                    data-sidebar-target="sidebarFinance">

                <span class="sidebar-group-label">

                    <span class="sidebar-item-icon">
                        <i class="fas fa-coins"></i>
                    </span>

                    <span>Finance</span>

                </span>

                <i class="fas fa-chevron-down sidebar-chevron"></i>

            </button>

            <div class="sidebar-submenu"
                 id="sidebarFinance">

                <a href="{{ url('/admin/payments') }}">
                    <i class="fas fa-money-bill-transfer"></i>
                    Payments
                </a>

                <a href="{{ url('/admin/expenses') }}">
                    <i class="fas fa-wallet"></i>
                    Expenses
                </a>

                <a href="{{ url('/admin/receivables') }}">
                    <i class="fas fa-hand-holding-dollar"></i>
                    Receivables
                </a>

                <a href="{{ url('/admin/cash-register') }}">
                    <i class="fas fa-cash-register"></i>
                    Cash Register
                </a>

                <a href="{{ url('/admin/accounts') }}">
                    <i class="fas fa-building-columns"></i>
                    Accounts
                </a>

                <a href="{{ url('/admin/general-ledger') }}">
                    <i class="fas fa-book"></i>
                    General Ledger
                </a>

                <a href="{{ url('/admin/reconciliation') }}">
                    <i class="fas fa-scale-balanced"></i>
                    Reconciliation
                </a>

            </div>


            {{-- =====================================================
                 EMPLOYEES
            ====================================================== --}}
            <button type="button"
                    class="sidebar-group-toggle"
                    data-sidebar-target="sidebarEmployees">

                <span class="sidebar-group-label">

                    <span class="sidebar-item-icon">
                        <i class="fas fa-user-tie"></i>
                    </span>

                    <span>Employees</span>

                </span>

                <i class="fas fa-chevron-down sidebar-chevron"></i>

            </button>

            <div class="sidebar-submenu"
                 id="sidebarEmployees">

                <a href="{{ url('/admin/staff') }}">
                    <i class="fas fa-users-gear"></i>
                    Staff
                </a>

                <a href="{{ url('/admin/roles') }}">
                    <i class="fas fa-user-shield"></i>
                    Roles
                </a>

                <a href="{{ url('/admin/attendance') }}">
                    <i class="fas fa-user-clock"></i>
                    Attendance
                </a>

                <a href="{{ url('/admin/shifts') }}">
                    <i class="fas fa-calendar-days"></i>
                    Shifts
                </a>

                <a href="{{ url('/admin/payroll') }}">
                    <i class="fas fa-money-check"></i>
                    Payroll
                </a>

            </div>


            {{-- =====================================================
                 DIRECT MODULES
            ====================================================== --}}
            <a href="{{ url('/admin/reports') }}"
               class="sidebar-item">

                <span class="sidebar-item-icon">
                    <i class="fas fa-chart-column"></i>
                </span>

                <span>Reports</span>

            </a>


            <a href="{{ url('/admin/notifications') }}"
               class="sidebar-item">

                <span class="sidebar-item-icon">
                    <i class="fas fa-bell"></i>
                </span>

                <span>Notifications</span>

            </a>


            <a href="{{ url('/admin/chatbot') }}"
               class="sidebar-item">

                <span class="sidebar-item-icon">
                    <i class="fas fa-robot"></i>
                </span>

                <span>Chatbot</span>

            </a>


            {{-- =====================================================
                 SETTINGS
            ====================================================== --}}
            <button type="button"
                    class="sidebar-group-toggle"
                    data-sidebar-target="sidebarSettings">

                <span class="sidebar-group-label">

                    <span class="sidebar-item-icon">
                        <i class="fas fa-gears"></i>
                    </span>

                    <span>Settings</span>

                </span>

                <i class="fas fa-chevron-down sidebar-chevron"></i>

            </button>

            <div class="sidebar-submenu"
                 id="sidebarSettings">

                <a href="{{ url('/admin/settings/restaurant') }}">
                    <i class="fas fa-store"></i>
                    Restaurant
                </a>

                <a href="{{ url('/admin/settings/branches') }}">
                    <i class="fas fa-code-branch"></i>
                    Branches
                </a>

                <a href="{{ url('/admin/settings/taxes') }}">
                    <i class="fas fa-percent"></i>
                    Taxes
                </a>

                <a href="{{ url('/admin/settings/payment-gateways') }}">
                    <i class="fas fa-credit-card"></i>
                    Payment Gateways
                </a>

                <a href="{{ url('/admin/settings/receipt') }}">
                    <i class="fas fa-receipt"></i>
                    Receipt
                </a>

                <a href="{{ url('/admin/settings/invoice') }}">
                    <i class="fas fa-file-invoice"></i>
                    Invoice
                </a>

                <a href="{{ url('/admin/settings/email') }}">
                    <i class="fas fa-envelope"></i>
                    Email
                </a>

                <a href="{{ url('/admin/settings/sms') }}">
                    <i class="fas fa-message"></i>
                    SMS
                </a>

                <a href="{{ url('/admin/settings/ai') }}">
                    <i class="fas fa-brain"></i>
                    AI
                </a>

                <a href="{{ url('/admin/settings/accessibility') }}">
                    <i class="fas fa-universal-access"></i>
                    Accessibility
                </a>

                <a href="{{ url('/admin/settings/backups') }}">
                    <i class="fas fa-database"></i>
                    Backups
                </a>

                <a href="{{ url('/admin/settings/roles-permissions') }}">
                    <i class="fas fa-user-lock"></i>
                    Roles & Permissions
                </a>

                <a href="{{ url('/admin/settings/activity-logs') }}">
                    <i class="fas fa-clock-rotate-left"></i>
                    Activity Logs
                </a>

            </div>

        </div>

    </aside>


    {{-- =========================================================
         MAIN CONTENT
    ========================================================== --}}
    <main class="admin-main">

        <header class="admin-topbar">

            <div class="topbar-left">

                <button type="button"
                        class="mobile-sidebar-button d-lg-none"
                        id="sidebarOpen">

                    <i class="fas fa-bars"></i>

                </button>


                <div>
                    <h1>
                        @yield('page-title', 'Dashboard')
                    </h1>
                </div>

            </div>


            <div class="topbar-right">

                <a href="{{ url('/') }}"
                   target="_blank"
                   class="topbar-button"
                   title="View restaurant">

                    <i class="fas fa-globe"></i>

                </a>


                <button type="button"
                        class="topbar-button"
                        title="Notifications">

                    <i class="fas fa-bell"></i>

                </button>


                <div class="user-summary">

                    <span class="user-avatar">
                        {{ strtoupper(substr(auth()->user()?->name ?? 'A', 0, 1)) }}
                    </span>

                    <span class="user-name d-none d-md-inline">
                        {{ auth()->user()?->name ?? 'Administrator' }}
                    </span>

                </div>

            </div>

        </header>


        <div class="admin-content">

            {{-- =====================================================
                 SINGLE GLOBAL FLASH MESSAGE AREA
                 DO NOT repeat this inside individual pages
            ====================================================== --}}

            @if(session('success'))

                <div class="alert alert-success alert-dismissible fade show global-flash-message">

                    <i class="fas fa-circle-check me-2"></i>

                    {{ session('success') }}

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"></button>

                </div>

            @endif


            @if(session('error'))

                <div class="alert alert-danger alert-dismissible fade show global-flash-message">

                    <i class="fas fa-circle-exclamation me-2"></i>

                    {{ session('error') }}

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"></button>

                </div>

            @endif


            @yield('content')

        </div>

    </main>

</div>


{{-- =============================================================
     ACCESSIBILITY
============================================================= --}}
<div class="accessibility-widget">

    <button type="button"
            class="floating-action accessibility-trigger"
            id="accessibilityTrigger">

        <i class="fas fa-universal-access"></i>

    </button>


    <div class="accessibility-panel"
         id="accessibilityPanel">

        <div class="widget-panel-header">

            <strong>
                Accessibility
            </strong>

            <button type="button"
                    id="accessibilityClose">

                <i class="fas fa-times"></i>

            </button>

        </div>


        <button data-accessibility="increase">
            <i class="fas fa-plus"></i>
            Increase Text
        </button>

        <button data-accessibility="decrease">
            <i class="fas fa-minus"></i>
            Decrease Text
        </button>

        <button data-accessibility="contrast">
            <i class="fas fa-circle-half-stroke"></i>
            High Contrast
        </button>

        <button data-accessibility="grayscale">
            <i class="fas fa-droplet-slash"></i>
            Greyscale
        </button>

        <button data-accessibility="reset">
            <i class="fas fa-rotate-left"></i>
            Reset
        </button>

    </div>

</div>


{{-- =============================================================
     CHATBOT
============================================================= --}}
<div class="chatbot-widget">

    <button type="button"
            class="floating-action chatbot-trigger"
            id="chatbotTrigger">

        <i class="fas fa-comments"></i>

    </button>


    <div class="chatbot-panel"
         id="chatbotPanel">

        <div class="chatbot-header">

            <div>

                <strong>
                    Restaurant Assistant
                </strong>

                <small>
                    Online
                </small>

            </div>

            <button type="button"
                    id="chatbotClose">

                <i class="fas fa-times"></i>

            </button>

        </div>


        <div class="chatbot-messages"
             id="chatMessages">

            <div class="chat-message bot">
                Hello! How can I help?
            </div>

        </div>


        <form class="chatbot-form"
              id="chatbotForm">

            <input type="text"
                   id="chatbotInput"
                   placeholder="Type your message...">

            <button type="submit">

                <i class="fas fa-paper-plane"></i>

            </button>

        </form>

    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

<script src="{{ asset('js/restaurant-admin.js') }}?v={{ @filemtime(public_path('js/restaurant-admin.js')) }}"></script>

@stack('scripts')

</body>
</html>