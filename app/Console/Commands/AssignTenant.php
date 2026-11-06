<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Console\Command;

class AssignTenant extends Command
{
    protected $signature = 'tenants:assign {entity : user or customer} {id : Record ID} {restaurant : Restaurant ID} {--branch= : Optional branch ID for a user}';

    protected $description = 'Assign an existing user or customer to a reviewed restaurant tenant';

    public function handle(): int
    {
        $entity = $this->argument('entity');
        $restaurant = Restaurant::query()->find($this->argument('restaurant'));
        if (!in_array($entity, ['user', 'customer'], true) || !$restaurant) {
            $this->error('Choose entity user/customer and provide a valid restaurant ID.');
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
            $record->forceFill(['restaurant_id' => $restaurant->id, 'branch_id' => $branchId])->save();
        } else {
            if ($this->option('branch') !== null) {
                $this->error('--branch can only be used with a user.');
                return self::FAILURE;
            }
            $record = Customer::query()->find($this->argument('id'));
            if (!$record) {
                $this->error('Customer not found.');
                return self::FAILURE;
            }
            $record->forceFill(['restaurant_id' => $restaurant->id])->save();
        }

        $this->info("Assigned {$entity} {$record->getKey()} to restaurant {$restaurant->id}.");
        return self::SUCCESS;
    }
}
