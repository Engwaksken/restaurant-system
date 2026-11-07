<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Support\Settings\SettingsRegistry;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reads and writes per-restaurant settings defined in SettingsRegistry.
 * Secret values are encrypted at rest and never returned to views.
 */
class RestaurantSettings
{
    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    /** All values for a group, merged over the registry defaults. */
    public function group(?int $restaurantId, string $group): array
    {
        $defaults = SettingsRegistry::defaults($group);
        if (!$restaurantId) {
            return $defaults;
        }

        $cacheKey = $restaurantId . ':' . $group;
        if (!isset($this->cache[$cacheKey])) {
            $stored = [];
            if (Schema::hasTable('restaurant_settings')) {
                foreach (DB::table('restaurant_settings')->where('restaurant_id', $restaurantId)->where('group', $group)->get() as $row) {
                    $stored[$row->key] = $this->decode($row);
                }
            }
            $this->cache[$cacheKey] = $stored;
        }

        return array_merge($defaults, $this->cache[$cacheKey]);
    }

    /** Whether the restaurant has ever saved this settings page. */
    public function isSaved(int $restaurantId, string $group): bool
    {
        return Schema::hasTable('restaurant_settings')
            && DB::table('restaurant_settings')->where('restaurant_id', $restaurantId)->where('group', $group)->exists();
    }

    /** Store one value outside the form (e.g. a cached model list). */
    public function put(int $restaurantId, string $group, string $key, ?string $value): void
    {
        DB::table('restaurant_settings')->updateOrInsert(
            ['restaurant_id' => $restaurantId, 'group' => $group, 'key' => $key],
            ['value' => $value, 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()],
        );
        unset($this->cache[$restaurantId . ':' . $group]);
    }

    public function get(?int $restaurantId, string $group, string $key, mixed $default = null): mixed
    {
        return $this->group($restaurantId, $group)[$key] ?? $default;
    }

    public function enabled(?int $restaurantId, string $group, string $key): bool
    {
        return filter_var($this->get($restaurantId, $group, $key, false), FILTER_VALIDATE_BOOL);
    }

    /** Whether a secret has a stored value (without revealing it). */
    public function hasSecret(?int $restaurantId, string $group, string $key): bool
    {
        return (string) $this->get($restaurantId, $group, $key, '') !== '';
    }

    /**
     * Save validated form data for a group. Secrets are only replaced when a
     * new value is supplied, and removed when "clear_<key>" is ticked.
     */
    public function save(int $restaurantId, string $group, array $data): void
    {
        $fields = SettingsRegistry::group($group)['fields'];
        $now = now();

        DB::transaction(function () use ($restaurantId, $group, $data, $fields, $now): void {
            foreach ($fields as $key => $field) {
                $isSecret = $field['type'] === 'secret';
                if ($isSecret) {
                    if (!empty($data['clear_' . $key])) {
                        DB::table('restaurant_settings')->where(compact('group', 'key') + ['restaurant_id' => $restaurantId])->delete();
                        continue;
                    }
                    $value = trim((string) ($data[$key] ?? ''));
                    if ($value === '') {
                        continue;
                    }
                    $stored = Crypt::encryptString($value);
                } elseif ($field['type'] === 'toggle') {
                    $stored = !empty($data[$key]) ? '1' : '0';
                } else {
                    $stored = isset($data[$key]) ? trim((string) $data[$key]) : '';
                }

                DB::table('restaurant_settings')->updateOrInsert(
                    ['restaurant_id' => $restaurantId, 'group' => $group, 'key' => $key],
                    ['value' => $stored, 'is_secret' => $isSecret, 'updated_at' => $now, 'created_at' => $now],
                );
            }
        });

        unset($this->cache[$restaurantId . ':' . $group]);
    }

    private function decode(object $row): mixed
    {
        if (!$row->is_secret) {
            return $row->value;
        }

        try {
            return Crypt::decryptString((string) $row->value);
        } catch (Throwable) {
            // Unreadable after an APP_KEY change: treat as not set.
            return null;
        }
    }
}
