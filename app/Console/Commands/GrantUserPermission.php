<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GrantUserPermission extends Command
{
    protected $signature = 'users:permission {user : User ID} {ability : Permission ability} {--revoke : Remove the permission} {--super-admin : Confirm granting the wildcard admin ability}';

    protected $description = 'Grant or revoke a user permission';

    public function handle(): int
    {
        $user = User::query()->find($this->argument('user'));
        $ability = (string) $this->argument('ability');
        if (!$user || preg_match('/^[a-z][a-z0-9._-]{0,189}$/', $ability) !== 1) {
            $this->error('User or ability is invalid.');
            return self::FAILURE;
        }
        if ($ability === 'admin' && !$this->option('super-admin')) {
            $this->error('Granting the admin wildcard requires --super-admin.');
            return self::FAILURE;
        }

        $query = DB::table('user_permissions')->where('user_id', $user->id)->where('ability', $ability);
        if ($this->option('revoke')) {
            $query->delete();
            $this->info("Revoked {$ability} from user {$user->id}.");
            return self::SUCCESS;
        }

        DB::table('user_permissions')->updateOrInsert(
            ['user_id' => $user->id, 'ability' => $ability],
            ['created_at' => now(), 'updated_at' => now()]
        );
        $this->info("Granted {$ability} to user {$user->id}.");
        return self::SUCCESS;
    }
}
