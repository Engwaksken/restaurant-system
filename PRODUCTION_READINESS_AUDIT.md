# Production Readiness Audit — Remediation Status

## Scope and status

Application-level remediation and automated verification have been added for the original audit findings and related authorization/tenant-isolation gaps discovered during implementation. **Production deployment is still gated on deployment-specific tenant mapping, secret/configuration setup, production-database migration/backup verification, and infrastructure checks.**

## Findings

| Priority | Finding | Remediation | Remaining release gate |
|---|---|---|---|
| High | Admin module authorization | Added per-user persistent permissions. Generic reads are tenant-scoped through restaurant, branch, order, customer, or assigned-user ownership. Generic create/update/delete is disabled. Sales exports, dashboard metrics, orders, payments, POS, and menu operations require explicit abilities and tenant assignment. | Assign existing users/customers/menu data from an owner-reviewed mapping and grant least privilege. Generic modules without a provable tenant relationship are unavailable until replaced with domain-specific implementations. |
| High | POS checkout scope and availability | Requires assigned restaurant and permission; validates branch/customer/menu-item ownership and rechecks/locks availability in a transaction. Client tax is ignored; discounts require a separate ability and cannot exceed subtotal; line count and quantity are bounded. | Inventory consumption and trusted tax calculation are not implemented. Configure those business rules before enabling them. Test concurrency using production database technology. |
| Medium | Order status/cancellation | Status allowlist and explicit transitions; tenant-scoped lists, metrics, details, status and cancellation actions; payment recording rechecks balance under lock. | Confirm transition policy with operations and map production permissions. |
| Medium | Menu ownership | Menu CRUD requires a restaurant assignment and ability; categories/items are tenant-scoped, category ownership is validated, and the hard-coded restaurant fallback is removed. | Map existing records/users and validate image storage and operational role grants. |
| Medium | Public order success/track | Public website now provides a cart/checkout form. Order creation validates active menu items and branch within the configured restaurant, calculates prices server-side, and issues a 256-bit random tracking token stored only as a hash. Tracking is rate-limited; bearer-token pages set no-store and no-referrer protections. | Set `PUBLIC_RESTAURANT_CODE`; verify business requirements and production-like ordering. Possession of the random token grants access to limited order details. |
| Medium | Admin fallback endpoint coverage | Generic pages are read-only, write endpoints return 404, and reads fail closed when tenant ownership cannot be established. Admin chatbot pages are disabled until conversations are tenant-scoped. | Build concrete controllers for required write workflows and add tenant ownership to currently global tables before exposing them. |
| Runtime | PHP version mismatch | Verified local CLI PHP 8.4.27; project requirement remains PHP `^8.3`. | Verify PHP 8.3+ and required extensions/drivers in production and CI. |
| Tests | Regression coverage | Added tenant isolation, permission, generic-write denial, transition, export/payment, POS pricing, and public tracking tests. `composer test` passes: **14 tests, 42 assertions** under PHP 8.4.27. | Automated coverage is a baseline, not full business/UAT coverage. Test production database drivers, gateway integrations, inventory, failure/retry behavior, and operational workflows. |
| Low | Historical route parse error log | Current route registration succeeds under PHP 8.4.27; historical log retained. | Review production logs and preserve historical records according to retention policy. |

## Provisioning and deployment checklist

1. Back up the production database and rehearse migrations on a copy.
2. Deploy the code and run `php artisan migrate --force`.
3. Assign existing records only from a reviewed tenant mapping, for example `php artisan tenants:assign user <user-id> <restaurant-id> --branch=<branch-id>` or `php artisan tenants:assign customer <customer-id> <restaurant-id>`.
4. Configure secrets for `ADMIN_EMAIL` and a unique `ADMIN_PASSWORD` (at least 16 characters). Use `ADMIN_GRANT_SUPER_ADMIN=true` only for a deliberately provisioned trusted account; use `php artisan users:permission` for least-privilege grants/revocations.
5. Set `PUBLIC_RESTAURANT_CODE` to the intended active storefront restaurant.
6. Set `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, secure session/cookie settings, and verify storage, mail, queue, backups, monitoring, and incident logging.
7. Run `composer test`, route checks, and business UAT against a production-like database/runtime before opening traffic.

This document records code-level remediation, not a production deployment certification. Generic writes and admin chatbot access remain intentionally disabled until their domain workflows and tenant ownership are implemented.
