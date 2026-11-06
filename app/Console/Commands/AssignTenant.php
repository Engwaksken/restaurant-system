<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignTenant extends Command
{
    protected $signature = 'tenants:assign {entity : user, customer, inventory_item, inventory_category, expense_category, supplier, or chat_conversation} {id : Record ID} {restaurant : Restaurant ID} {--branch= : Optional branch ID for a user} {--force : Reassign a record already owned by another restaurant}';

    protected $description = 'Assign an existing record to a reviewed restaurant tenant';

    public function handle(): int
    {
        $entity = $this->argument('entity');
        $restaurant = Restaurant::query()->find($this->argument('restaurant'));
        $tables = [
            'inventory_item' => 'inventory_items',
            'inventory_category' => 'inventory_categories',
            'expense_category' => 'expense_categories',
            'supplier' => 'suppliers',
            'chat_conversation' => 'chat_conversations',
        ];
        if (!in_array($entity, ['user', 'customer', ...array_keys($tables)], true) || !$restaurant) {
            $this->error('Choose a supported entity and provide a valid restaurant ID.');
            return self::FAILURE;
        }

        if ($entity === 'user') {
            $record = User::query()->find($this->argument('id'));
            if (!$record) {
                $this->error('User not found.');
                return self::FAILURE;
            }
            $branchId = $this->option('branch');
            if ($branchId !== null && !Branch::query()->whereKey($branchId)->where('restaurant_id', $restaurant->id)->exists()) {
                $this->error('The selected branch does not belong to the selected restaurant.');
                return self::FAILURE;
            }
            if ($record->restaurant_id !== null && (int) $record->restaurant_id !== (int) $restaurant->id && !$this->option('force')) {
                $this->error('User belongs to another restaurant. Use --force only after reviewing the reassignment.');
                return self::FAILURE;
            }
            $attributes = ['restaurant_id' => $restaurant->id];
            if ($branchId !== null || $record->branch_id === null || (int) $record->restaurant_id !== (int) $restaurant->id) {
                $attributes['branch_id'] = $branchId;
            }
            $record->forceFill($attributes)->save();
        } elseif ($entity === 'customer') {
            if ($this->option('branch') !== null) {
                $this->error('--branch can only be used with a user.');
                return self::FAILURE;
            }
            $record = Customer::query()->find($this->argument('id'));
            if (!$record) {
                $this->error('Customer not found.');
                return self::FAILURE;
            }
            if ($record->restaurant_id !== null && (int) $record->restaurant_id !== (int) $restaurant->id && !$this->option('force')) {
                $this->error('Customer belongs to another restaurant. Use --force only after reviewing the reassignment.');
                return self::FAILURE;
            }
            $record->forceFill(['restaurant_id' => $restaurant->id])->save();
        } else {
            if ($this->option('branch') !== null) {
                $this->error('--branch can only be used with a user.');
                return self::FAILURE;
            }
            $table = $tables[$entity];
            $record = DB::table($table)->where('id', $this->argument('id'))->first();
            if (!$record) {
                $this->error('Record not found.');
                return self::FAILURE;
            }
            if ($record->restaurant_id !== null && (int) $record->restaurant_id !== (int) $restaurant->id && !$this->option('force')) {
                $this->error('Record belongs to another restaurant. Use --force only after reviewing the reassignment.');
                return self::FAILURE;
            }
            DB::table($table)->where('id', $this->argument('id'))->update([
                'restaurant_id' => $restaurant->id,
                'updated_at' => now(),
            ]);
            $record = (object) ['id' => $this->argument('id')];
        }

        $this->info("Assigned {$entity} {$record->getKey()} to restaurant {$restaurant->id}.");
        return self::SUCCESS;
    }
}
