<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AdminModuleController extends Controller
{
    public function show(Request $request): View
    {
        $module = (string) $request->route('module');
        $modules = $this->modules();

        abort_unless(isset($modules[$module]), 404);
        $this->authorizeModule($module, 'view');

        $definition = $modules[$module];
        $tables = $definition['tables'] ?? [];
        $stats = $this->stats($tables);
        $createTable = $this->createTable($definition);
        $fields = [];
        $records = null;
        $columns = [];

        if ($createTable && Schema::hasTable($createTable)) {
            [$records, $columns] = $this->records(
                $request,
                $createTable,
                $definition
            );
        }

        return view('admin.modules.index', [
            'module' => $module,
            'definition' => $definition,
            'stats' => $stats,
            'createTable' => $createTable,
            // The generic schema-derived editor is intentionally read-only.
            // State-changing workflows must use concrete, validated controllers.
            'allowCreate' => false,
            'allowEdit' => false,
            'allowDelete' => false,
            'fields' => $fields,
            'records' => $records,
            'columns' => $columns,
        ]);
    }

    public function store(Request $request, string $module): RedirectResponse
    {
        abort(404, 'Generic module writes are disabled.');
    }

    public function update(Request $request, string $module, int $id): RedirectResponse
    {
        abort(404, 'Generic module writes are disabled.');
    }

    public function destroy(string $module, int $id): RedirectResponse
    {
        abort(404, 'Generic module writes are disabled.');
    }

    private function authorizeModule(string $module, string $operation): void
    {
        $user = auth()->user();
        abort_unless($user, 403);
        $ability = 'admin.modules.' . $module . '.' . $operation;
        abort_unless($user->can($ability) || $user->can('admin.modules.manage') || $user->can('admin'), 403);
    }

    private function records(Request $request, string $table, array $definition): array
    {
        $query = $this->scopedQuery($table);

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $displayColumns = $this->displayColumns($table, $definition);

        if ($search !== '') {
            $searchable = array_values(array_filter(
                $displayColumns,
                fn (string $column): bool => $this->isSearchableColumn($table, $column)
            ));

            if ($searchable !== []) {
                $query->where(function ($sub) use ($searchable, $search): void {
                    foreach ($searchable as $index => $column) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $sub->{$method}($column, 'like', '%' . $search . '%');
                    }
                });
            }
        }

        if ($status !== '') {
            if (Schema::hasColumn($table, 'status')) {
                $query->where('status', $status);
            } elseif (Schema::hasColumn($table, 'is_active')) {
                $query->where('is_active', $status === 'active' ? 1 : 0);
            } elseif (Schema::hasColumn($table, 'active')) {
                $query->where('active', $status === 'active' ? 1 : 0);
            }
        }

        $orderColumn = Schema::hasColumn($table, 'created_at') ? 'created_at' : 'id';
        $query->orderByDesc($orderColumn);

        /** @var LengthAwarePaginator $records */
        $records = $query->paginate(15)->withQueryString();

        return [$records, $displayColumns];
    }

    private function displayColumns(string $table, array $definition): array
    {
        if (!empty($definition['display_columns'])) {
            return array_values(array_filter(
                $definition['display_columns'],
                fn (string $column): bool => Schema::hasColumn($table, $column)
            ));
        }

        $preferred = [
            'name', 'title', 'label', 'code', 'reference', 'order_number',
            'category', 'phone', 'email', 'amount', 'price', 'capacity',
            'status', 'is_active', 'created_at',
        ];

        $columns = array_values(array_filter(
            $preferred,
            fn (string $column): bool => Schema::hasColumn($table, $column)
        ));

        if ($columns === []) {
            $columns = array_values(array_filter(
                Schema::getColumnListing($table),
                fn (string $column): bool => !in_array($column, [
                    'password', 'remember_token', 'api_key', 'secret', 'token',
                    'deleted_at', 'updated_at'
                ], true)
            ));
            $columns = array_slice($columns, 0, 7);
        }

        return $columns;
    }

    private function formFields(string $table, array $definition): array
    {
        $overrides = $definition['fields'] ?? [];
        $overrideMap = [];
        foreach ($overrides as $field) {
            if (!empty($field['name'])) {
                $overrideMap[$field['name']] = $field;
            }
        }

        $columns = DB::select('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
        $fields = [];

        foreach ($columns as $column) {
            $name = (string) $column->Field;

            if ($this->skipFormColumn($name, (string) ($column->Extra ?? ''))) {
                continue;
            }

            if ($this->isSystemManagedColumn($name)) {
                continue;
            }

            $override = $overrideMap[$name] ?? [];
            if (($override['hidden'] ?? false) === true) {
                continue;
            }

            $field = array_merge(
                $this->fieldFromColumn($table, $column),
                $override
            );

            $fields[] = $field;
        }

        return $fields;
    }

    private function fieldFromColumn(string $table, object $column): array
    {
        $name = (string) $column->Field;
        $sqlType = strtolower((string) $column->Type);
        $nullable = strtoupper((string) $column->Null) === 'YES';
        $hasDefault = $column->Default !== null;
        $required = !$nullable && !$hasDefault;

        $field = [
            'name' => $name,
            'label' => Str::headline($name),
            'type' => 'text',
            'required' => $required,
            'col' => 'col-md-6',
            'placeholder' => 'Enter ' . strtolower(Str::headline($name)),
            'options' => [],
        ];

        if ($name === 'description' || $name === 'notes' || $name === 'address' || str_contains($name, 'remarks')) {
            $field['type'] = 'textarea';
            $field['col'] = 'col-12';
            return $field;
        }

        if (str_ends_with($name, '_id')) {
            $options = $this->foreignOptions($name);
            if ($options !== []) {
                $field['type'] = 'select';
                $field['options'] = $options;
                $field['placeholder'] = 'Select ' . strtolower(Str::headline(Str::beforeLast($name, '_id')));
                return $field;
            }
            $field['type'] = 'number';
            return $field;
        }

        if ($name === 'status') {
            $field['type'] = 'select';
            $field['options'] = [
                'active' => 'Active',
                'inactive' => 'Inactive',
                'pending' => 'Pending',
                'completed' => 'Completed',
            ];
            return $field;
        }

        if (in_array($name, ['is_active', 'active', 'enabled'], true)) {
            $field['type'] = 'select';
            $field['options'] = ['1' => 'Active', '0' => 'Inactive'];
            return $field;
        }

        if (preg_match('/tinyint\(1\)|boolean|bool/', $sqlType)) {
            $field['type'] = 'select';
            $field['options'] = ['1' => 'Yes', '0' => 'No'];
            return $field;
        }

        if (str_contains($sqlType, 'text')) {
            $field['type'] = 'textarea';
            $field['col'] = 'col-12';
            return $field;
        }

        if (str_contains($sqlType, 'date') && !str_contains($sqlType, 'datetime') && !str_contains($sqlType, 'timestamp')) {
            $field['type'] = 'date';
            return $field;
        }

        if (str_contains($sqlType, 'datetime') || str_contains($sqlType, 'timestamp')) {
            $field['type'] = 'datetime-local';
            return $field;
        }

        if (preg_match('/int|decimal|double|float/', $sqlType)) {
            $field['type'] = 'number';
            if (preg_match('/decimal|double|float/', $sqlType)) {
                $field['step'] = '0.01';
            }
            return $field;
        }

        if (str_contains($name, 'email')) {
            $field['type'] = 'email';
            return $field;
        }

        if (str_contains($name, 'phone') || str_contains($name, 'mobile')) {
            $field['type'] = 'tel';
            return $field;
        }

        if (str_contains($name, 'password') || str_contains($name, 'secret') || str_contains($name, 'api_key')) {
            $field['type'] = 'password';
            return $field;
        }

        if (str_contains($name, 'url') || str_contains($name, 'website')) {
            $field['type'] = 'url';
            return $field;
        }

        if (preg_match('/^enum\((.+)\)$/', $sqlType, $matches)) {
            $values = str_getcsv($matches[1], ',', "'");
            $field['type'] = 'select';
            $field['options'] = collect($values)->mapWithKeys(
                fn ($value) => [$value => Str::headline((string) $value)]
            )->all();
            return $field;
        }

        return $field;
    }

    private function validationRules(array $fields): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $name = $field['name'];
            $type = $field['type'] ?? 'text';
            $fieldRules = [($field['required'] ?? false) ? 'required' : 'nullable'];

            switch ($type) {
                case 'email':
                    $fieldRules[] = 'email';
                    $fieldRules[] = 'max:255';
                    break;
                case 'url':
                    $fieldRules[] = 'url';
                    $fieldRules[] = 'max:2000';
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'date':
                case 'datetime-local':
                    $fieldRules[] = 'date';
                    break;
                case 'select':
                    $options = array_keys($field['options'] ?? []);
                    if ($options !== []) {
                        $fieldRules[] = 'in:' . implode(',', array_map('strval', $options));
                    }
                    break;
                case 'textarea':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:5000';
                    break;
                default:
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:255';
            }

            $rules[$name] = $fieldRules;
        }

        return $rules;
    }

    private function payload(string $table, array $fields, array $validated, bool $updating = false): array
    {
        $data = [];
        $allowed = collect($fields)->pluck('name')->all();

        foreach ($validated as $column => $value) {
            if (in_array($column, $allowed, true) && Schema::hasColumn($table, $column)) {
                $data[$column] = $value === '' ? null : $value;
            }
        }

        if (!$updating) {
            $this->applySystemDefaults($table, $data);
            if (Schema::hasColumn($table, 'created_at')) {
                $data['created_at'] = now();
            }
        }

        if (Schema::hasColumn($table, 'updated_at')) {
            $data['updated_at'] = now();
        }

        return $data;
    }

    private function applySystemDefaults(string $table, array &$data): void
    {
        $user = auth()->user();

        if (Schema::hasColumn($table, 'restaurant_id') && !array_key_exists('restaurant_id', $data)) {
            $data['restaurant_id'] = $user?->restaurant_id
                ?? (Schema::hasTable('restaurants') ? DB::table('restaurants')->orderBy('id')->value('id') : null)
                ?? 1;
        }

        if (Schema::hasColumn($table, 'branch_id') && !array_key_exists('branch_id', $data)) {
            $branchId = $user?->branch_id;
            if ($branchId !== null) {
                $data['branch_id'] = $branchId;
            }
        }

        $userId = auth()->id();
        if ($userId !== null) {
            foreach (['created_by', 'created_by_user_id'] as $column) {
                if (Schema::hasColumn($table, $column) && !array_key_exists($column, $data)) {
                    $data[$column] = $userId;
                }
            }
        }

        if (Schema::hasColumn($table, 'slug') && !array_key_exists('slug', $data)) {
            $source = $data['name'] ?? $data['title'] ?? $data['label'] ?? null;
            if ($source) {
                $data['slug'] = $this->uniqueSlug($table, (string) $source);
            }
        }

        if (Schema::hasColumn($table, 'code') && !array_key_exists('code', $data)) {
            $source = $data['name'] ?? $data['title'] ?? $data['label'] ?? 'REC';
            $data['code'] = $this->generateCode($table, (string) $source);
        }
    }

    private function foreignOptions(string $column): array
    {
        $map = [
            'restaurant_id' => 'restaurants',
            'branch_id' => 'branches',
            'category_id' => 'categories',
            'menu_category_id' => 'menu_categories',
            'inventory_category_id' => 'inventory_categories',
            'inventory_item_id' => 'inventory_items',
            'menu_item_id' => 'menu_items',
            'supplier_id' => 'suppliers',
            'customer_id' => 'customers',
            'user_id' => 'users',
            'staff_id' => 'users',
            'employee_id' => 'users',
            'role_id' => 'roles',
            'floor_id' => 'floors',
            'table_id' => 'restaurant_tables',
            'restaurant_table_id' => 'restaurant_tables',
            'account_id' => 'accounts',
            'purchase_id' => 'purchases',
            'purchase_order_id' => 'purchase_orders',
            'order_id' => 'orders',
        ];

        $base = Str::beforeLast($column, '_id');
        $table = $map[$column] ?? Str::plural($base);

        if (!Schema::hasTable($table)) {
            return [];
        }

        $labelColumn = collect(['name', 'title', 'full_name', 'label', 'code', 'order_number', 'email'])
            ->first(fn (string $candidate): bool => Schema::hasColumn($table, $candidate));

        if (!$labelColumn || !Schema::hasColumn($table, 'id')) {
            return [];
        }

        try {
        return $this->scopedQuery($table)
                ->when(Schema::hasColumn($table, 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                ->orderBy($labelColumn)
                ->limit(500)
                ->pluck($labelColumn, 'id')
                ->mapWithKeys(fn ($value, $key) => [(string) $key => (string) $value])
                ->all();
        } catch (Throwable $e) {
            report($e);
            return [];
        }
    }

    private function skipFormColumn(string $name, string $extra): bool
    {
        if (str_contains(strtolower($extra), 'auto_increment')) {
            return true;
        }

        return in_array($name, [
            'id', 'created_at', 'updated_at', 'deleted_at', 'remember_token',
            'email_verified_at', 'slug', 'code'
        ], true);
    }

    private function isSystemManagedColumn(string $name): bool
    {
        return in_array($name, [
            'restaurant_id', 'branch_id', 'created_by', 'created_by_user_id'
        ], true);
    }

    private function isSearchableColumn(string $table, string $column): bool
    {
        try {
            $type = strtolower((string) DB::selectOne(
                'SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` WHERE Field = ?',
                [$column]
            )->Type);

            return str_contains($type, 'char') || str_contains($type, 'text') || str_contains($type, 'enum');
        } catch (Throwable) {
            return false;
        }
    }

    private function createTable(array $definition): ?string
    {
        if (!empty($definition['create_table'])) {
            return $definition['create_table'];
        }

        $tables = $definition['tables'] ?? [];
        return $tables !== [] ? array_key_first($tables) : null;
    }

    private function missingRequiredColumns(string $table, array $suppliedColumns): array
    {
        try {
            $columns = DB::select('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
            $missing = [];

            foreach ($columns as $column) {
                $field = (string) $column->Field;
                $extra = strtolower((string) ($column->Extra ?? ''));

                if (in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at'], true)) {
                    continue;
                }
                if (str_contains($extra, 'auto_increment')) {
                    continue;
                }
                if (strtoupper((string) $column->Null) === 'YES') {
                    continue;
                }
                if ($column->Default !== null) {
                    continue;
                }
                if (in_array($field, $suppliedColumns, true)) {
                    continue;
                }

                $missing[] = $field;
            }

            return $missing;
        } catch (Throwable $e) {
            report($e);
            return [];
        }
    }

    private function friendlyDatabaseError(QueryException $e, string $title): string
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'duplicate entry')) {
            return 'A similar ' . strtolower($title) . ' record already exists.';
        }
        if (str_contains($message, 'cannot be null') || str_contains($message, "doesn't have a default value")) {
            return $title . ' requires additional information before it can be saved.';
        }
        if (str_contains($message, 'foreign key constraint')) {
            return $title . ' could not be saved because a related record is missing or in use.';
        }

        return $title . ' could not be saved. Please check the entered information.';
    }

    private function uniqueSlug(string $table, string $name): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $counter = 1;

        while (DB::table($table)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }

    private function generateCode(string $table, string $name): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3)) ?: 'REC';

        do {
            $code = $prefix . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        } while (DB::table($table)->where('code', $code)->exists());

        return $code;
    }

    private function stats(array $tables): array
    {
        $stats = [];

        foreach ($tables as $table => $label) {
            if (!Schema::hasTable($table)) {
                $stats[] = ['label' => $label, 'value' => '—', 'available' => false];
                continue;
            }

            try {
                $stats[] = [
                    'label' => $label,
                'value' => $this->scopedQuery($table)->count(),
                    'available' => true,
                ];
            } catch (Throwable $e) {
                report($e);
                $stats[] = ['label' => $label, 'value' => '—', 'available' => false];
            }
        }

        return $stats;
    }

    /** Apply tenant scope or deny tables that cannot be safely scoped. */
    private function scopedQuery(string $table): \Illuminate\Database\Query\Builder
    {
        $user = auth()->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        $query = DB::table($table);
        $hasScope = false;
        if (Schema::hasColumn($table, 'restaurant_id')) {
            $query->where($table . '.restaurant_id', $user->restaurant_id);
            $hasScope = true;
        }
        if (Schema::hasColumn($table, 'branch_id')) {
            $branchIds = DB::table('branches')
                ->where('restaurant_id', $user->restaurant_id)
                ->when($user->branch_id, fn ($branches) => $branches->where('id', $user->branch_id))
                ->select('id');
            $query->whereIn($table . '.branch_id', $branchIds);
            $hasScope = true;
        }
        if (!$hasScope && Schema::hasColumn($table, 'order_id') && Schema::hasTable('orders')) {
            $query->whereIn($table . '.order_id', $this->scopedQuery('orders')->select('id'));
            $hasScope = true;
        }
        if (!$hasScope && Schema::hasColumn($table, 'customer_id') && Schema::hasTable('customers') && Schema::hasColumn('customers', 'restaurant_id')) {
            $query->whereIn($table . '.customer_id', DB::table('customers')
                ->where('restaurant_id', $user->restaurant_id)
                ->select('id'));
            $hasScope = true;
        }
        if (!$hasScope) {
            foreach (['user_id', 'received_by', 'assigned_to'] as $userColumn) {
                if (!Schema::hasColumn($table, $userColumn)) {
                    continue;
                }
                $query->whereIn($table . '.' . $userColumn, DB::table('users')
                    ->where('restaurant_id', $user->restaurant_id)
                    ->when($user->branch_id, fn ($users) => $users->where('branch_id', $user->branch_id))
                    ->select('id'));
                $hasScope = true;
                break;
            }
        }
        abort_unless($hasScope, 403, 'This module does not support tenant-scoped access.');

        return $query;
    }

    private function modules(): array
    {
        return [
            // POS - operational views only
            'pos.held' => $this->module('Held Orders', 'Point of Sale', 'fas fa-pause', 'Review and resume orders that were placed on hold.', ['orders' => 'Orders']),
            'pos.current' => $this->module('Current Orders', 'Point of Sale', 'fas fa-clock', 'View active POS orders that are still being processed.', ['orders' => 'Orders']),
            'pos.history' => $this->module('Order History', 'Point of Sale', 'fas fa-clock-rotate-left', 'Review previous point-of-sale transactions.', ['orders' => 'Orders']),
            'pos.returns' => $this->module('Returns / Refunds', 'Point of Sale', 'fas fa-rotate-left', 'Manage approved returns, reversals and customer refunds.', ['orders' => 'Orders', 'payments' => 'Payments']),

            // Kitchen - never manually add kitchen rows
            'kitchen.index' => $this->module('Kitchen Display', 'Kitchen', 'fas fa-display', 'Kitchen order display for food preparation teams.', ['orders' => 'Orders']),
            'kitchen.pending' => $this->module('Pending', 'Kitchen', 'fas fa-hourglass-start', 'Orders waiting for kitchen preparation.', ['orders' => 'Orders']),
            'kitchen.preparing' => $this->module('Preparing', 'Kitchen', 'fas fa-fire', 'Orders currently being prepared.', ['orders' => 'Orders']),
            'kitchen.ready' => $this->module('Ready', 'Kitchen', 'fas fa-bell-concierge', 'Orders ready for serving, pickup or dispatch.', ['orders' => 'Orders']),
            'kitchen.completed' => $this->module('Completed', 'Kitchen', 'fas fa-circle-check', 'Completed kitchen orders.', ['orders' => 'Orders']),

            // Menu
            'menu.categories' => $this->module('Menu Categories', 'Menu', 'fas fa-layer-group', 'Organise food, drinks and services into categories.', ['menu_categories' => 'Categories'], true),
            'menu.addons' => $this->module('Menu Add-ons', 'Menu', 'fas fa-circle-plus', 'Manage optional extras customers can add to menu items.', ['menu_item_addons' => 'Add-ons'], true),
            'menu.variants' => $this->module('Menu Variants', 'Menu', 'fas fa-code-branch', 'Manage item sizes, portions and other menu variants.', ['menu_item_variants' => 'Variants'], true),
            'menu.recipes' => $this->module('Recipes', 'Menu', 'fas fa-book', 'Define ingredient quantities used for menu items.', ['recipes' => 'Recipes', 'recipe_items' => 'Recipe Items'], true, 'recipes'),
            'menu.availability' => $this->module('Menu Availability', 'Menu', 'fas fa-toggle-on', 'Control which food, drinks and services are currently available.', ['menu_items' => 'Menu Items']),

            // Tables
            'tables.floors' => $this->module('Floors / Sections', 'Tables', 'fas fa-building', 'Organise tables by floor, room or restaurant section.', ['floors' => 'Floors / Sections'], true),
            'tables.tables' => $this->module('Restaurant Tables', 'Tables', 'fas fa-chair', 'Manage restaurant tables, capacity and status.', ['restaurant_tables' => 'Tables'], true),
            'tables.reservations' => $this->module('Reservations', 'Tables', 'fas fa-calendar-check', 'Manage customer table bookings and reservation status.', ['reservations' => 'Reservations'], true),
            'tables.qr' => $this->module('QR Ordering', 'Tables', 'fas fa-qrcode', 'Generate and manage QR ordering links for tables.', ['restaurant_tables' => 'Tables']),

            // Customers
            'customers.customers' => $this->module('Customers', 'Customers', 'fas fa-user-group', 'Manage restaurant customers and contact details.', ['customers' => 'Customers'], true),
            'customers.loyalty' => $this->module('Loyalty', 'Customers', 'fas fa-star', 'Manage loyalty balances, points and rewards.', ['loyalty_accounts' => 'Loyalty Accounts'], true),
            'customers.feedback' => $this->module('Feedback', 'Customers', 'fas fa-comments', 'Review customer ratings, feedback and service comments.', ['feedback' => 'Feedback']),
            'customers.accounts' => $this->module('Customer Accounts', 'Customers', 'fas fa-address-card', 'Manage balances, credit and account activity for customers.', ['customers' => 'Customers']),

            // Inventory
            'inventory.products' => $this->module('Inventory Products', 'Inventory', 'fas fa-box', 'Manage ingredients, supplies and stock items.', ['inventory_items' => 'Inventory Items'], true),
            'inventory.categories' => $this->module('Inventory Categories', 'Inventory', 'fas fa-tags', 'Organise stock products into inventory categories.', ['inventory_categories' => 'Categories'], true),
            'inventory.stock' => $this->module('Stock', 'Inventory', 'fas fa-warehouse', 'View current branch stock quantities and valuation.', ['inventory_stocks' => 'Stock Balances']),
            'inventory.adjustments' => $this->module('Stock Adjustments', 'Inventory', 'fas fa-sliders', 'Record stock corrections with a complete audit trail.', ['stock_adjustments' => 'Adjustments'], true),
            'inventory.transfers' => $this->module('Stock Transfers', 'Inventory', 'fas fa-right-left', 'Transfer stock between restaurant branches or stores.', ['stock_transfers' => 'Transfers'], true),
            'inventory.wastage' => $this->module('Wastage', 'Inventory', 'fas fa-trash-can', 'Record expired, damaged and wasted inventory.', ['wastages' => 'Wastage'], true),
            'inventory.low-stock' => $this->module('Low Stock', 'Inventory', 'fas fa-triangle-exclamation', 'Identify items below their configured reorder levels.', ['inventory_stocks' => 'Stock Balances']),

            // Purchasing
            'purchasing.suppliers' => $this->module('Suppliers', 'Purchasing', 'fas fa-truck', 'Manage suppliers and vendor contact information.', ['suppliers' => 'Suppliers'], true),
            'purchasing.purchase-orders' => $this->module('Purchase Orders', 'Purchasing', 'fas fa-file-circle-plus', 'Create and monitor orders sent to suppliers.', ['purchase_orders' => 'Purchase Orders'], true),
            'purchasing.purchases' => $this->module('Purchases', 'Purchasing', 'fas fa-cart-shopping', 'Record received stock purchases and supplier invoices.', ['purchases' => 'Purchases'], true),
            'purchasing.supplier-payments' => $this->module('Supplier Payments', 'Purchasing', 'fas fa-money-check-dollar', 'Record and review payments made to suppliers.', ['supplier_payments' => 'Supplier Payments'], true),
            'purchasing.payables' => $this->module('Payables', 'Purchasing', 'fas fa-file-invoice-dollar', 'Review outstanding supplier balances.', ['purchases' => 'Purchases']),

            // Finance
            'finance.payments' => $this->module('Payments', 'Finance', 'fas fa-money-bill-transfer', 'Review restaurant payment transactions.', ['payments' => 'Payments']),
            'finance.expenses' => $this->module('Expenses', 'Finance', 'fas fa-wallet', 'Record and categorise restaurant operating expenses.', ['expenses' => 'Expenses'], true),
            'finance.receivables' => $this->module('Receivables', 'Finance', 'fas fa-hand-holding-dollar', 'Review money owed to the restaurant.', ['orders' => 'Orders']),
            'finance.cash-register' => $this->module('Cash Register', 'Finance', 'fas fa-cash-register', 'Manage till sessions, opening balances and cash movements.', ['cash_register_sessions' => 'Register Sessions'], true),
            'finance.accounts' => $this->module('Accounts', 'Finance', 'fas fa-building-columns', 'Manage chart of accounts and restaurant financial accounts.', ['accounts' => 'Accounts'], true),
            'finance.general-ledger' => $this->module('General Ledger', 'Finance', 'fas fa-book', 'Review accounting journal and ledger activity.', ['journal_entries' => 'Journal Entries']),
            'finance.reconciliation' => $this->module('Reconciliation', 'Finance', 'fas fa-scale-balanced', 'Reconcile payments, bank movements and cash balances.', ['payments' => 'Payments']),

            // Employees
            'employees.staff' => $this->module('Staff', 'Employees', 'fas fa-users-gear', 'Manage restaurant employees and system users.', ['users' => 'Users'], true),
            'employees.roles' => $this->module('Roles', 'Employees', 'fas fa-user-shield', 'Manage staff roles and access levels.', ['roles' => 'Roles'], true),
            'employees.attendance' => $this->module('Attendance', 'Employees', 'fas fa-user-clock', 'Track employee attendance and time records.', ['attendance' => 'Attendance'], true),
            'employees.shifts' => $this->module('Shifts', 'Employees', 'fas fa-calendar-days', 'Plan and manage restaurant employee shifts.', ['shifts' => 'Shifts'], true),
            'employees.payroll' => $this->module('Payroll', 'Employees', 'fas fa-money-check', 'Manage restaurant payroll and salary processing.', ['payrolls' => 'Payroll Records'], true),

            // Direct modules - read-only/system-driven
            'reports.index' => $this->module('Reports', 'Reports', 'fas fa-chart-column', 'Access sales, stock, purchasing, finance and management reports.', []),
            'notifications.index' => $this->module('Notifications', 'Notifications', 'fas fa-bell', 'Review system alerts and staff notifications.', ['notifications' => 'Notifications']),
            'chatbot.index' => $this->module('Chatbot', 'Chatbot', 'fas fa-robot', 'Manage chatbot conversations and AI support.', ['chat_conversations' => 'Conversations', 'chat_messages' => 'Messages']),

            // Settings - settings pages should not show a generic Add button
            'settings.restaurant' => $this->setting('Restaurant', 'fas fa-store', 'Restaurant profile, contact details and operational defaults.'),
            'settings.branches' => $this->module('Branches', 'Settings', 'fas fa-code-branch', 'Manage restaurant branches and locations.', ['branches' => 'Branches'], true),
            'settings.taxes' => $this->setting('Taxes', 'fas fa-percent', 'Configure restaurant taxes and tax rates.'),
            'settings.payment-gateways' => $this->setting('Payment Gateways', 'fas fa-credit-card', 'Configure cashless and online payment providers.'),
            'settings.receipt' => $this->setting('Receipt', 'fas fa-receipt', 'Configure receipt branding, footer text and print preferences.'),
            'settings.invoice' => $this->setting('Invoice', 'fas fa-file-invoice', 'Configure invoice numbering, branding and payment details.'),
            'settings.email' => $this->setting('Email', 'fas fa-envelope', 'Configure outgoing restaurant email settings.'),
            'settings.sms' => $this->setting('SMS', 'fas fa-message', 'Configure SMS notifications and provider settings.'),
            'settings.ai' => $this->setting('AI', 'fas fa-brain', 'Configure AI and chatbot provider settings.'),
            'settings.accessibility' => $this->setting('Accessibility', 'fas fa-universal-access', 'Configure accessibility options and defaults.'),
            'settings.backups' => $this->setting('Backups', 'fas fa-database', 'Manage application and database backups.'),
            'settings.roles-permissions' => $this->setting('Roles & Permissions', 'fas fa-user-lock', 'Manage role permissions and system access.', ['roles' => 'Roles']),
            'settings.activity-logs' => $this->setting('Activity Logs', 'fas fa-clock-rotate-left', 'Review important user and system activity.', ['activity_logs' => 'Activity Logs']),
        ];
    }

    private function module(
        string $title,
        string $group,
        string $icon,
        string $description,
        array $tables = [],
        bool $allowCreate = false,
        ?string $createTable = null,
        array $fields = [],
        array $displayColumns = []
    ): array {
        return [
            'title' => $title,
            'group' => $group,
            'icon' => $icon,
            'description' => $description,
            'tables' => $tables,
            'create_table' => $createTable,
            'allow_create' => $allowCreate,
            'allow_edit' => $allowCreate,
            'allow_delete' => $allowCreate,
            'fields' => $fields,
            'display_columns' => $displayColumns,
        ];
    }

    private function setting(string $title, string $icon, string $description, array $tables = []): array
    {
        return $this->module($title, 'Settings', $icon, $description, $tables, false);
    }
}
