<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ListUnassignedTenants extends Command
{
    protected $signature = 'tenants:unassigned';

    protected $description = 'List existing records that have no restaurant tenant yet, so they can be reviewed and assigned';

    private const ENTITIES = [
        'user' => 'users',
        'customer' => 'customers',
        'inventory_item' => 'inventory_items',
        'inventory_category' => 'inventory_categories',
        'expense_category' => 'expense_categories',
        'supplier' => 'suppliers',
        'chat_conversation' => 'chat_conversations',
    ];

    public function handle(): int
    {
        $total = 0;

        foreach (self::ENTITIES as $entity => $table) {
            $ids = DB::table($table)->whereNull('restaurant_id')->orderBy('id')->pluck('id');

            if ($ids->isEmpty()) {
                continue;
            }

            $total += $ids->count();
            $this->warn("{$entity}: {$ids->count()} unassigned");
            $this->line('  IDs: '.$ids->implode(', '));
            $this->line("  Assign with: php artisan tenants:assign {$entity} <id> <restaurant-id>");
        }

        if ($total === 0) {
            $this->info('No unassigned tenant records found.');
        }

        return self::SUCCESS;
    }
}
