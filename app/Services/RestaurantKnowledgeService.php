<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RestaurantKnowledgeService
{
    /**
     * Build restaurant context relevant to the user's question.
     */
    public function context(
        string $query,
        int $limit = 20
    ): array {
        $query = trim($query);

        $terms = $this->terms($query);

        return [
            'restaurant' =>
                $this->restaurantProfile(),

            'branches' =>
                $this->branches(
                    $query,
                    $terms,
                    $limit
                ),

            'menu_categories' =>
                $this->menuCategories(
                    $query,
                    $terms,
                    $limit
                ),

            'menu_items' =>
                $this->menuItems(
                    $query,
                    $terms,
                    $limit
                ),

            'tables' =>
                $this->restaurantTables(
                    $query,
                    $terms,
                    $limit
                ),

            'reservations' =>
                $this->reservations(
                    $query,
                    $terms,
                    $limit
                ),

            'inventory' =>
                $this->inventory(
                    $query,
                    $terms,
                    $limit
                ),

            'suppliers' =>
                $this->suppliers(
                    $query,
                    $terms,
                    $limit
                ),

            'orders' =>
                $this->orders(
                    $query,
                    $terms,
                    $limit
                ),

            'payments' =>
                $this->payments(
                    $query,
                    $terms,
                    $limit
                ),

            'summary' =>
                $this->summary(),
        ];
    }

    /**
     * Convert retrieved restaurant information into text for Gemini.
     */
    public function plainTextContext(
        string $query,
        int $limit = 20
    ): string {
        $context = $this->context(
            $query,
            $limit
        );

        $sections = [];

        foreach ($context as $name => $value) {

            if (
                $value === []
                || $value === null
                || $value === ''
            ) {
                continue;
            }

            $sections[] =
                strtoupper(
                    str_replace(
                        '_',
                        ' ',
                        $name
                    )
                )
                . ":\n"
                . json_encode(
                    $value,
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                );
        }

        return implode(
            "\n\n",
            $sections
        );
    }

    /**
     * Restaurant profile / restaurant settings.
     */
    private function restaurantProfile(): array
    {
        foreach (
            [
                'restaurants',
                'restaurant_settings',
                'settings',
            ] as $table
        ) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            try {
                $row =
                    DB::table($table)
                        ->first();

                if (!$row) {
                    continue;
                }

                return $this->safeRow(
                    (array) $row,
                    [
                        'id',
                        'name',
                        'restaurant_name',
                        'system_name',
                        'email',
                        'phone',
                        'telephone',
                        'address',
                        'city',
                        'country',
                        'currency',
                        'opening_time',
                        'closing_time',
                        'description',
                        'tagline',
                        'website',
                    ]
                );

            } catch (Throwable $e) {
                report($e);
            }
        }

        return [];
    }

    /**
     * Restaurant branches.
     */
    private function branches(
        string $question,
        array $terms,
        int $limit
    ): array {
        $broadQuery = $this->containsAny(
            $question,
            [
                'branches',
                'branch',
                'locations',
                'location',
            ]
        );

        return $this->searchTable(
            table: 'branches',
            terms: $broadQuery ? [] : $terms,
            searchColumns: [
                'name',
                'code',
                'phone',
                'email',
                'address',
                'city',
            ],
            selectColumns: [
                'id',
                'name',
                'code',
                'phone',
                'email',
                'address',
                'city',
                'status',
                'is_active',
            ],
            limit: $limit
        );
    }

    /**
     * Menu categories.
     */
    private function menuCategories(
        string $question,
        array $terms,
        int $limit
    ): array {
        $broadQuery = $this->containsAny(
            $question,
            [
                'menu',
                'food',
                'foods',
                'drink',
                'drinks',
                'category',
                'categories',
                'available',
                'availability',
            ]
        );

        return $this->searchTable(
            table: 'menu_categories',
            terms: $broadQuery ? [] : $terms,
            searchColumns: [
                'name',
                'title',
                'description',
            ],
            selectColumns: [
                'id',
                'name',
                'title',
                'description',
                'is_active',
                'status',
                'display_order',
            ],
            limit: $limit
        );
    }

    /**
     * Menu items.
     *
     * Broad menu questions should return actual menu records instead of
     * searching for words such as "available" inside item names.
     */
    private function menuItems(
        string $question,
        array $terms,
        int $limit
    ): array {
        if (!Schema::hasTable('menu_items')) {
            return [];
        }

        try {
            $query =
                DB::table('menu_items as mi');

            /*
            |--------------------------------------------------------------------------
            | Select Existing Menu Columns
            |--------------------------------------------------------------------------
            */

            $select = [];

            foreach (
                [
                    'id',
                    'name',
                    'title',
                    'description',
                    'price',
                    'sale_price',
                    'cost_price',
                    'preparation_minutes',
                    'is_available',
                    'is_active',
                    'status',
                    'category_id',
                    'sku',
                    'code',
                ] as $column
            ) {
                if (
                    Schema::hasColumn(
                        'menu_items',
                        $column
                    )
                ) {
                    $select[] =
                        'mi.' . $column;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Menu Category
            |--------------------------------------------------------------------------
            */

            if (
                Schema::hasTable(
                    'menu_categories'
                )
                && Schema::hasColumn(
                    'menu_items',
                    'category_id'
                )
                && Schema::hasColumn(
                    'menu_categories',
                    'id'
                )
            ) {
                $query->leftJoin(
                    'menu_categories as mc',
                    'mc.id',
                    '=',
                    'mi.category_id'
                );

                if (
                    Schema::hasColumn(
                        'menu_categories',
                        'name'
                    )
                ) {
                    $select[] =
                        'mc.name as category_name';

                } elseif (
                    Schema::hasColumn(
                        'menu_categories',
                        'title'
                    )
                ) {
                    $select[] =
                        'mc.title as category_name';
                }
            }

            if ($select !== []) {
                $query->select($select);
            }

            /*
            |--------------------------------------------------------------------------
            | Detect Menu Intent
            |--------------------------------------------------------------------------
            */

            $isMenuQuestion =
                $this->containsAny(
                    $question,
                    [
                        'menu',
                        'menus',
                        'food',
                        'foods',
                        'meal',
                        'meals',
                        'dish',
                        'dishes',
                        'drink',
                        'drinks',
                        'item',
                        'items',
                    ]
                );

            $asksAvailability =
                $this->containsAny(
                    $question,
                    [
                        'available',
                        'availability',
                        'currently available',
                        'in stock',
                        'what can i order',
                        'what can we order',
                        'what do you have',
                    ]
                );

            $asksUnavailable =
                $this->containsAny(
                    $question,
                    [
                        'unavailable',
                        'not available',
                        'out of menu',
                    ]
                );

            /*
            |--------------------------------------------------------------------------
            | Availability Filters
            |--------------------------------------------------------------------------
            */

            if ($asksAvailability) {

                if (
                    Schema::hasColumn(
                        'menu_items',
                        'is_available'
                    )
                ) {
                    $query->where(
                        'mi.is_available',
                        true
                    );
                }

                if (
                    Schema::hasColumn(
                        'menu_items',
                        'is_active'
                    )
                ) {
                    $query->where(
                        'mi.is_active',
                        true
                    );
                }

            } elseif ($asksUnavailable) {

                if (
                    Schema::hasColumn(
                        'menu_items',
                        'is_available'
                    )
                ) {
                    $query->where(
                        'mi.is_available',
                        false
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Search Specific Menu Item
            |--------------------------------------------------------------------------
            |
            | Don't text-search generic menu questions.
            |
            */

            if (
                !$isMenuQuestion
                && !$asksAvailability
                && !$asksUnavailable
            ) {
                $searchable = [];

                foreach (
                    [
                        'name',
                        'title',
                        'description',
                        'sku',
                        'code',
                    ] as $column
                ) {
                    if (
                        Schema::hasColumn(
                            'menu_items',
                            $column
                        )
                    ) {
                        $searchable[] =
                            'mi.' . $column;
                    }
                }

                $this->applyTerms(
                    $query,
                    $terms,
                    $searchable
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Sorting
            |--------------------------------------------------------------------------
            */

            if (
                Schema::hasColumn(
                    'menu_items',
                    'is_available'
                )
            ) {
                $query->orderByDesc(
                    'mi.is_available'
                );
            }

            if (
                Schema::hasColumn(
                    'menu_items',
                    'display_order'
                )
            ) {
                $query->orderBy(
                    'mi.display_order'
                );
            }

            $query->orderBy(
                'mi.id'
            );

            return $query
                ->limit($limit)
                ->get()
                ->map(
                    fn ($row) =>
                        (array) $row
                )
                ->all();

        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Restaurant tables.
     */
    private function restaurantTables(
        string $question,
        array $terms,
        int $limit
    ): array {
        $broadQuery =
            $this->containsAny(
                $question,
                [
                    'tables',
                    'table',
                    'seats',
                    'seating',
                    'capacity',
                ]
            );

        return $this->searchTable(
            table: 'restaurant_tables',
            terms: $broadQuery ? [] : $terms,
            searchColumns: [
                'name',
                'number',
                'table_number',
                'code',
                'status',
            ],
            selectColumns: [
                'id',
                'name',
                'number',
                'table_number',
                'code',
                'capacity',
                'status',
                'is_active',
                'floor_id',
            ],
            limit: $limit
        );
    }

    /**
     * Reservations.
     */
    private function reservations(
        string $question,
        array $terms,
        int $limit
    ): array {
        if (!Schema::hasTable('reservations')) {
            return [];
        }

        $broadQuery =
            $this->containsAny(
                $question,
                [
                    'reservation',
                    'reservations',
                    'booking',
                    'bookings',
                    'reserved',
                ]
            );

        return $this->searchTable(
            table: 'reservations',
            terms: $broadQuery ? [] : $terms,
            searchColumns: [
                'reference',
                'customer_name',
                'customer_phone',
                'status',
                'notes',
            ],
            selectColumns: [
                'id',
                'reference',
                'customer_name',
                'customer_phone',
                'customer_email',
                'reservation_date',
                'reservation_time',
                'guest_count',
                'party_size',
                'status',
                'notes',
                'table_id',
                'created_at',
            ],
            limit: $limit,
            latest: true
        );
    }

    /**
     * Inventory.
     */
    private function inventory(
        string $question,
        array $terms,
        int $limit
    ): array {
        if (!Schema::hasTable('inventory_items')) {
            return [];
        }

        try {
            $query =
                DB::table(
                    'inventory_items'
                );

            $select = [];

            foreach (
                [
                    'id',
                    'name',
                    'sku',
                    'code',
                    'description',
                    'unit',
                    'quantity',
                    'stock_quantity',
                    'reorder_level',
                    'minimum_stock',
                    'cost_price',
                    'selling_price',
                    'status',
                    'is_active',
                ] as $column
            ) {
                if (
                    Schema::hasColumn(
                        'inventory_items',
                        $column
                    )
                ) {
                    $select[] = $column;
                }
            }

            if ($select !== []) {
                $query->select($select);
            }

            /*
            |--------------------------------------------------------------------------
            | Low Stock Intent
            |--------------------------------------------------------------------------
            */

            $lowStock =
                $this->containsAny(
                    $question,
                    [
                        'low stock',
                        'running low',
                        'reorder',
                        'below reorder',
                        'shortage',
                    ]
                );

            if ($lowStock) {

                if (
                    Schema::hasColumn(
                        'inventory_items',
                        'stock_quantity'
                    )
                    && Schema::hasColumn(
                        'inventory_items',
                        'reorder_level'
                    )
                ) {
                    $query->whereColumn(
                        'stock_quantity',
                        '<=',
                        'reorder_level'
                    );

                } elseif (
                    Schema::hasColumn(
                        'inventory_items',
                        'quantity'
                    )
                    && Schema::hasColumn(
                        'inventory_items',
                        'reorder_level'
                    )
                ) {
                    $query->whereColumn(
                        'quantity',
                        '<=',
                        'reorder_level'
                    );

                } elseif (
                    Schema::hasColumn(
                        'inventory_items',
                        'quantity'
                    )
                    && Schema::hasColumn(
                        'inventory_items',
                        'minimum_stock'
                    )
                ) {
                    $query->whereColumn(
                        'quantity',
                        '<=',
                        'minimum_stock'
                    );
                }

            } else {

                $inventoryQuestion =
                    $this->containsAny(
                        $question,
                        [
                            'inventory',
                            'stock',
                            'ingredient',
                            'ingredients',
                            'supplies',
                        ]
                    );

                if (!$inventoryQuestion) {
                    $this->applyTerms(
                        $query,
                        $terms,
                        array_values(
                            array_filter(
                                [
                                    'name',
                                    'sku',
                                    'code',
                                    'description',
                                    'unit',
                                ],
                                fn (string $column) =>
                                    Schema::hasColumn(
                                        'inventory_items',
                                        $column
                                    )
                            )
                        )
                    );
                }
            }

            if (
                Schema::hasColumn(
                    'inventory_items',
                    'id'
                )
            ) {
                $query->orderByDesc('id');
            }

            return $query
                ->limit($limit)
                ->get()
                ->map(
                    fn ($row) =>
                        (array) $row
                )
                ->all();

        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Suppliers.
     */
    private function suppliers(
        string $question,
        array $terms,
        int $limit
    ): array {
        $broadQuery =
            $this->containsAny(
                $question,
                [
                    'supplier',
                    'suppliers',
                    'vendor',
                    'vendors',
                ]
            );

        return $this->searchTable(
            table: 'suppliers',
            terms: $broadQuery ? [] : $terms,
            searchColumns: [
                'name',
                'contact_person',
                'phone',
                'email',
                'address',
            ],
            selectColumns: [
                'id',
                'name',
                'contact_person',
                'phone',
                'email',
                'address',
                'status',
                'is_active',
            ],
            limit: $limit
        );
    }

    /**
     * Orders.
     */
    private function orders(
        string $question,
        array $terms,
        int $limit
    ): array {
        if (!Schema::hasTable('orders')) {
            return [];
        }

        try {
            $query =
                DB::table('orders');

            $select = [];

            foreach (
                [
                    'id',
                    'order_number',
                    'reference',
                    'order_type',
                    'type',
                    'customer_name',
                    'customer_phone',
                    'table_id',
                    'status',
                    'payment_status',
                    'subtotal',
                    'tax_amount',
                    'discount_amount',
                    'grand_total',
                    'total_amount',
                    'total',
                    'balance',
                    'balance_due',
                    'created_at',
                ] as $column
            ) {
                if (
                    Schema::hasColumn(
                        'orders',
                        $column
                    )
                ) {
                    $select[] = $column;
                }
            }

            if ($select !== []) {
                $query->select($select);
            }

            $orderQuestion =
                $this->containsAny(
                    $question,
                    [
                        'order',
                        'orders',
                        'sale',
                        'sales',
                    ]
                );

            /*
            |--------------------------------------------------------------------------
            | Order Status Intent
            |--------------------------------------------------------------------------
            */

            if (
                Schema::hasColumn(
                    'orders',
                    'status'
                )
            ) {
                if (
                    $this->containsAny(
                        $question,
                        [
                            'pending',
                            'waiting',
                        ]
                    )
                ) {
                    $query->where(
                        'status',
                        'pending'
                    );

                } elseif (
                    $this->containsAny(
                        $question,
                        [
                            'preparing',
                            'preparation',
                            'being prepared',
                        ]
                    )
                ) {
                    $query->where(
                        'status',
                        'preparing'
                    );

                } elseif (
                    $this->containsAny(
                        $question,
                        [
                            'ready',
                        ]
                    )
                ) {
                    $query->where(
                        'status',
                        'ready'
                    );

                } elseif (
                    $this->containsAny(
                        $question,
                        [
                            'completed',
                            'complete',
                        ]
                    )
                ) {
                    $query->where(
                        'status',
                        'completed'
                    );

                } elseif (
                    $this->containsAny(
                        $question,
                        [
                            'cancelled',
                            'canceled',
                        ]
                    )
                ) {
                    $query->where(
                        'status',
                        'cancelled'
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Order Type Intent
            |--------------------------------------------------------------------------
            */

            $typeColumn = null;

            if (
                Schema::hasColumn(
                    'orders',
                    'order_type'
                )
            ) {
                $typeColumn = 'order_type';

            } elseif (
                Schema::hasColumn(
                    'orders',
                    'type'
                )
            ) {
                $typeColumn = 'type';
            }

            if ($typeColumn !== null) {

                if (
                    $this->containsAny(
                        $question,
                        [
                            'dine in',
                            'dine-in',
                            'dinein',
                        ]
                    )
                ) {
                    $query->where(
                        $typeColumn,
                        'dine_in'
                    );

                } elseif (
                    $this->containsAny(
                        $question,
                        [
                            'takeaway',
                            'take away',
                            'pickup',
                        ]
                    )
                ) {
                    $query->where(
                        $typeColumn,
                        'takeaway'
                    );

                } elseif (
                    $this->containsAny(
                        $question,
                        [
                            'delivery',
                            'deliveries',
                        ]
                    )
                ) {
                    $query->where(
                        $typeColumn,
                        'delivery'
                    );

                } elseif (
                    $this->containsAny(
                        $question,
                        [
                            'online order',
                            'online orders',
                        ]
                    )
                ) {
                    $query->where(
                        $typeColumn,
                        'online'
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Specific Search
            |--------------------------------------------------------------------------
            */

            if (!$orderQuestion) {
                $searchable = [];

                foreach (
                    [
                        'order_number',
                        'reference',
                        'customer_name',
                        'customer_phone',
                        'status',
                        'payment_status',
                    ] as $column
                ) {
                    if (
                        Schema::hasColumn(
                            'orders',
                            $column
                        )
                    ) {
                        $searchable[] = $column;
                    }
                }

                $this->applyTerms(
                    $query,
                    $terms,
                    $searchable
                );
            }

            if (
                Schema::hasColumn(
                    'orders',
                    'created_at'
                )
            ) {
                $query->orderByDesc(
                    'created_at'
                );

            } elseif (
                Schema::hasColumn(
                    'orders',
                    'id'
                )
            ) {
                $query->orderByDesc('id');
            }

            return $query
                ->limit($limit)
                ->get()
                ->map(
                    fn ($row) =>
                        (array) $row
                )
                ->all();

        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Payments.
     */
    private function payments(
        string $question,
        array $terms,
        int $limit
    ): array {
        $paymentQuestion =
            $this->containsAny(
                $question,
                [
                    'payment',
                    'payments',
                    'paid',
                    'transaction',
                    'transactions',
                    'money',
                ]
            );

        return $this->searchTable(
            table: 'payments',
            terms: $paymentQuestion
                ? []
                : $terms,
            searchColumns: [
                'reference',
                'transaction_reference',
                'payment_method',
                'method',
                'status',
            ],
            selectColumns: [
                'id',
                'order_id',
                'reference',
                'transaction_reference',
                'payment_method',
                'method',
                'amount',
                'status',
                'created_at',
            ],
            limit: $limit,
            latest: true
        );
    }

    /**
     * Restaurant system summary.
     */
    private function summary(): array
    {
        $summary = [];

        foreach (
            [
                'menu_items' =>
                    'menu_items',

                'menu_categories' =>
                    'menu_categories',

                'orders' =>
                    'orders',

                'customers' =>
                    'customers',

                'suppliers' =>
                    'suppliers',

                'inventory_items' =>
                    'inventory_items',

                'restaurant_tables' =>
                    'restaurant_tables',

                'reservations' =>
                    'reservations',
            ] as $key => $table
        ) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            try {
                $summary[$key] =
                    DB::table($table)
                        ->count();

            } catch (Throwable $e) {
                report($e);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Menu Availability Summary
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasTable(
                'menu_items'
            )
            && Schema::hasColumn(
                'menu_items',
                'is_available'
            )
        ) {
            try {
                $summary['available_menu_items'] =
                    DB::table('menu_items')
                        ->where(
                            'is_available',
                            true
                        )
                        ->count();

                $summary['unavailable_menu_items'] =
                    DB::table('menu_items')
                        ->where(
                            'is_available',
                            false
                        )
                        ->count();

            } catch (Throwable $e) {
                report($e);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Orders by Status
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasTable('orders')
            && Schema::hasColumn(
                'orders',
                'status'
            )
        ) {
            try {
                $summary['orders_by_status'] =
                    DB::table('orders')
                        ->select(
                            'status',
                            DB::raw(
                                'COUNT(*) AS total'
                            )
                        )
                        ->groupBy('status')
                        ->pluck(
                            'total',
                            'status'
                        )
                        ->all();

            } catch (Throwable $e) {
                report($e);
            }
        }

        return $summary;
    }

    /**
     * Generic schema-safe table search.
     */
    private function searchTable(
        string $table,
        array $terms,
        array $searchColumns,
        array $selectColumns,
        int $limit,
        bool $latest = false
    ): array {
        if (!Schema::hasTable($table)) {
            return [];
        }

        try {
            $query =
                DB::table($table);

            $searchable =
                array_values(
                    array_filter(
                        $searchColumns,
                        fn (string $column) =>
                            Schema::hasColumn(
                                $table,
                                $column
                            )
                    )
                );

            $select =
                array_values(
                    array_filter(
                        $selectColumns,
                        fn (string $column) =>
                            Schema::hasColumn(
                                $table,
                                $column
                            )
                    )
                );

            if ($select !== []) {
                $query->select($select);
            }

            $this->applyTerms(
                $query,
                $terms,
                $searchable
            );

            if (
                $latest
                && Schema::hasColumn(
                    $table,
                    'created_at'
                )
            ) {
                $query->orderByDesc(
                    'created_at'
                );

            } elseif (
                Schema::hasColumn(
                    $table,
                    'id'
                )
            ) {
                $query->orderByDesc('id');
            }

            return $query
                ->limit($limit)
                ->get()
                ->map(
                    fn ($row) =>
                        (array) $row
                )
                ->all();

        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Apply search terms across available columns.
     */
    private function applyTerms(
        Builder $query,
        array $terms,
        array $columns
    ): void {
        if (
            $terms === []
            || $columns === []
        ) {
            return;
        }

        $query->where(
            function (
                Builder $outer
            ) use (
                $terms,
                $columns
            ): void {
                foreach ($terms as $term) {

                    $outer->orWhere(
                        function (
                            Builder $inner
                        ) use (
                            $term,
                            $columns
                        ): void {
                            foreach (
                                $columns
                                as $column
                            ) {
                                $inner->orWhere(
                                    $column,
                                    'like',
                                    '%'
                                    . $term
                                    . '%'
                                );
                            }
                        }
                    );
                }
            }
        );
    }

    /**
     * Extract meaningful search terms.
     */
    private function terms(
        string $query
    ): array {
        $words =
            preg_split(
                '/\s+/u',
                mb_strtolower($query)
            )
            ?: [];

        /*
        |--------------------------------------------------------------------------
        | General question words
        |--------------------------------------------------------------------------
        |
        | Business-intent words such as "available", "pending", "stock",
        | "menu" and "orders" are deliberately excluded from text searching
        | because they are handled as structured query intent.
        |
        */

        $stopWords = [
            'a',
            'an',
            'the',
            'is',
            'are',
            'was',
            'were',
            'be',
            'been',
            'to',
            'of',
            'for',
            'and',
            'or',
            'in',
            'on',
            'at',
            'from',
            'with',
            'about',
            'what',
            'which',
            'who',
            'how',
            'when',
            'where',
            'do',
            'does',
            'did',
            'can',
            'could',
            'would',
            'should',
            'please',
            'tell',
            'show',
            'give',
            'me',
            'our',
            'restaurant',
            'we',
            'i',

            /*
            | Structured intent words
            */

            'available',
            'availability',
            'menu',
            'menus',
            'item',
            'items',
            'order',
            'orders',
            'pending',
            'stock',
            'inventory',
            'reservation',
            'reservations',
            'payment',
            'payments',
            'supplier',
            'suppliers',
            'table',
            'tables',
        ];

        $clean = [];

        foreach ($words as $word) {

            $word =
                trim(
                    $word,
                    " \t\n\r\0\x0B.,!?;:'\"()[]{}"
                );

            if (
                mb_strlen($word) < 2
                || in_array(
                    $word,
                    $stopWords,
                    true
                )
            ) {
                continue;
            }

            $clean[] = $word;
        }

        return array_values(
            array_unique(
                array_slice(
                    $clean,
                    0,
                    12
                )
            )
        );
    }

    /**
     * Detect semantic intent words/phrases.
     */
    private function containsAny(
        string $question,
        array $phrases
    ): bool {
        $question =
            mb_strtolower(
                trim($question)
            );

        foreach ($phrases as $phrase) {

            if (
                str_contains(
                    $question,
                    mb_strtolower($phrase)
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return safe restaurant profile fields.
     */
    private function safeRow(
        array $row,
        array $allowed
    ): array {
        return array_intersect_key(
            $row,
            array_flip($allowed)
        );
    }
}