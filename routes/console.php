<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Automatic restaurant backups. Each restaurant chooses daily, weekly
| (Mondays) or off under Settings > Backups. Runs hourly and creates at most
| one backup per restaurant per day.
*/
Artisan::command('restaurant:backups', function (\App\Services\Backups\RestaurantBackupService $backups, \App\Services\Settings\RestaurantSettings $settings): void {
    foreach (\App\Models\Restaurant::query()->where('is_active', true)->get() as $restaurant) {
        $frequency = (string) $settings->get($restaurant->id, 'backups', 'frequency', 'off');
        $due = $frequency === 'daily' || ($frequency === 'weekly' && now()->isMonday());
        $latest = $backups->list($restaurant->id)->first();
        if (!$due || ($latest && $latest['created_at']->isToday())) {
            continue;
        }
        try {
            $this->info('Backed up ' . $restaurant->name . ': ' . $backups->create($restaurant));
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Backup failed for ' . $restaurant->name . ': ' . $exception->getMessage());
        }
    }
})->purpose('Create scheduled restaurant data backups');

\Illuminate\Support\Facades\Schedule::command('restaurant:backups')->hourly()->withoutOverlapping();
