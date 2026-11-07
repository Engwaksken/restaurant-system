# Restaurant System

Laravel application for restaurant ordering and branch operations.

## Requirements

- PHP 8.3 or newer (PHP 8.4 is tested)
- Composer and a supported Laravel database driver
- Node.js/npm only when rebuilding frontend assets

## Local setup

```sh
composer install
cp .env.example .env
php artisan key:generate
```

Configure the database and set `APP_URL`. Set `PUBLIC_RESTAURANT_CODE` to an active restaurant code. Before seeding an administrator, configure `ADMIN_EMAIL` and a unique `ADMIN_PASSWORD` of at least 16 characters. `ADMIN_GRANT_SUPER_ADMIN=true` grants the full `admin` wildcard and must only be set deliberately for a trusted administrator.

Then run:

```sh
php artisan migrate
php artisan db:seed
npm install
npm run build
composer test
```

The seeders create the default restaurant, branch, and menu categories. They create an administrator only when explicit admin credentials are provided.

## Tenant and permission operations

Tenant ownership is fail-closed. Existing records are **not** automatically assigned to a restaurant because ownership cannot safely be inferred. After reviewing the mapping, use:

```sh
php artisan tenants:assign user <user-id> <restaurant-id> --branch=<branch-id>
php artisan tenants:assign customer <customer-id> <restaurant-id>
php artisan tenants:assign inventory_item <item-id> <restaurant-id>
php artisan tenants:assign inventory_category <category-id> <restaurant-id>
php artisan tenants:assign expense_category <category-id> <restaurant-id>
php artisan tenants:assign supplier <supplier-id> <restaurant-id>
php artisan tenants:assign chat_conversation <conversation-id> <restaurant-id>
```

Reassignment across tenants requires the explicit `--force` option and a reviewed mapping. Grant least-privilege abilities from the tenant-scoped **Settings → Roles & Permissions** screen or with `php artisan users:permission <user-id> <ability>`. The screen exposes an explicit allowlist and cannot manage wildcard administrators. Granting `admin` requires `--super-admin` on the CLI.

## Checkout configuration

- `RESTAURANT_TAX_RATE` is a server-side percentage; a client-submitted tax is ignored.
- `RESTAURANT_DELIVERY_FEE` is the configured delivery fee in the restaurant currency.
- Inventory-tracked menu items require a recipe. Recipe quantities and branch stock quantities use the inventory item's `usage_unit`. Checkout locks stock, rejects shortages, and records signed stock movements in the order transaction.
- Staff with purchasing permissions can maintain tenant-owned suppliers, create/cancel unreceived purchase orders, receive ordered stock fully or partially, record standalone stock receipts, and record partial/manual supplier payments against a receipt. Receipt updates are atomic and purchase-order quantities cannot be received beyond ordered amounts. Manual payment records lock receipt balances and reject overpayment; supplier-payment settlement is recorded manually.
- Staff with inventory transfer/wastage permissions can move stock between same-restaurant branches or record damaged/expired stock. Both actions lock and update branch stock atomically and create matching ledger/audit records; below-zero stock is rejected.
- The kitchen display (`/admin/kitchen`) shows the user's tenant/branch queue oldest-first and refreshes every 30 seconds. `kitchen.view` grants read access; `kitchen.manage` (or `orders.manage`) allows starting preparation and marking orders ready. Kitchen actions lock the order, reject stale screens that would re-apply a change, keep item kitchen status in sync, and are audit-logged. Completion and cancellation remain front-of-house order actions. `KITCHEN_LATE_MINUTES` controls when waiting orders are highlighted.
- Floors/sections, tables and reservations (`/admin/floors`, `/admin/tables`, `/admin/reservations`) are branch-scoped. `tables.view`/`tables.manage` and `reservations.view`/`reservations.manage` control access. Tables are deactivated rather than deleted, and staff can only set manual statuses (available, occupied, cleaning, inactive). A reservation holds its table for `RESERVATION_DURATION_MINUTES` (default 120) either side of the booking time; the booking check locks the table and rejects overlaps, over-capacity parties and tables from other branches. Seating requires an available table and marks it occupied; completing the reservation marks the table for cleaning. Reservation deposits are not collected by this workflow.
- Customers (`/admin/customers`) are restaurant-owned and keyed by phone, matching how public orders and reservations find or create them. `customers.view`/`customers.manage` control access; branch-assigned staff see order history and spend only for their branch. Customers cannot be deleted until a data-retention/erasure policy is approved. Loyalty points are displayed but not yet earned or redeemed.
- After an order is served/completed, the customer can rate it once (1-5 with an optional comment) from the private tracking page. Submissions are rate-limited, and one rating per order is enforced by a unique key. `customers.feedback.view` grants the branch-scoped feedback report (`/admin/feedback`).
- Menu availability (`/admin/menu-availability`, `menu.availability.manage`) marks items available/unavailable with an explicit state, not a toggle; unavailable items are rejected by POS and public checkout.
- POS current orders and order history (`/admin/pos/current`, `/admin/pos/history`) are status-filtered views of the branch-scoped order list; history supports a date range.
- The activity log (`/admin/settings/activity-logs`, `settings.activity-logs.view`) is read-only and restaurant-wide. New entries record the acting user's restaurant; earlier entries are attributed through their user, so pre-existing entries without a user are not shown.
- Public order tracking tokens are random bearer secrets; only their hashes are stored. Tracking pages are rate-limited and sent with no-store/no-referrer protections.
- Email confirmation is sent only when the customer provided an email. Configure a production mail transport to deliver it.
- Online payment providers are disabled by default. Configure provider credentials, production base URLs, and Pesapal IPN registration before enabling either gateway. Payment completion is based on server-to-server provider verification, not browser callback parameters.

Generic module writes remain disabled until implemented with domain-specific validation. Admin chat history is read-only and tenant-scoped; AI prompts and staff replies remain disabled pending an approved workflow. Some generic read pages also fail closed if the data has no safe tenant relationship.

## Release

Back up and rehearse migrations on a production-like copy before applying them to production. Set `APP_ENV=production`, `APP_DEBUG=false`, HTTPS and secure session settings; configure queue, mail, storage, monitoring, backups, and payment provider credentials. Run the feature suite and business UAT before opening traffic. See [PRODUCTION_READINESS_AUDIT.md](PRODUCTION_READINESS_AUDIT.md) for remaining module and deployment gates.
