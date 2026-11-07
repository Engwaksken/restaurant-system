<?php

declare(strict_types=1);

namespace App\Services\Backups;

use App\Models\Restaurant;
use App\Services\Settings\RestaurantSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Exports one restaurant's data to a ZIP of JSON-lines files (one per table)
 * plus a manifest. Only rows that belong to the restaurant are included, so
 * a backup can never contain another restaurant's records. Passwords, tokens
 * and stored secrets are left out.
 *
 * Ownership is worked out from the schema: tables with restaurant_id or a
 * *branch_id column, then tables pointing at those through <name>_id
 * columns (e.g. order_items.order_id -> orders). Tables that cannot be tied to
 * the restaurant are skipped and listed in the manifest.
 */
class RestaurantBackupService
{
    private const SKIP_TABLES = [
        'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions',
        'password_reset_tokens', 'order_number_sequences', 'payment_gateway_settings',
    ];

    private const SKIP_COLUMNS = ['password', 'remember_token', 'tracking_token_hash', 'credentials', 'qr_token'];

    public function __construct(private readonly RestaurantSettings $settings)
    {
    }

    public static function directory(int $restaurantId): string
    {
        return 'backups/restaurant-' . $restaurantId;
    }

    /** @return string the stored file name */
    public function create(Restaurant $restaurant): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required for backups.');
        }

        $disk = Storage::disk('local');
        $directory = self::directory($restaurant->id);
        $disk->makeDirectory($directory);
        $name = 'backup-' . now()->format('Ymd-His') . '-' . Str::lower(Str::random(6)) . '.zip';
        $path = $disk->path($directory . '/' . $name);

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the backup file.');
        }

        $scopes = $this->resolveScopes($restaurant->id);
        $counts = [];
        $temporary = [];
        foreach ($scopes['included'] as $table => $scope) {
            $columns = array_values(array_diff(Schema::getColumnListing($table), self::SKIP_COLUMNS));
            $tmp = tempnam(sys_get_temp_dir(), 'bk');
            $handle = fopen($tmp, 'wb');
            $count = 0;
            $query = $scope(DB::table($table))->select($columns);
            if (in_array('id', $columns, true)) {
                $query->orderBy('id');
            }
            if ($table === 'restaurant_settings') {
                $query->where('is_secret', false);
            }
            foreach ($query->cursor() as $row) {
                fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
                $count++;
            }
            fclose($handle);
            $zip->addFile($tmp, 'tables/' . $table . '.jsonl');
            $temporary[] = $tmp;
            $counts[$table] = $count;
        }

        $zip->addFromString('manifest.json', json_encode([
            'restaurant' => ['id' => $restaurant->id, 'name' => $restaurant->name, 'code' => $restaurant->code],
            'created_at' => now()->toIso8601String(),
            'format' => 'One JSON object per line, one file per table.',
            'tables' => $counts,
            'not_included' => $scopes['skipped'],
            'excluded_columns' => self::SKIP_COLUMNS,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $zip->close();
        foreach ($temporary as $tmp) {
            @unlink($tmp);
        }

        $this->prune($restaurant->id);

        return $name;
    }

    /** @return Collection<int, array{name: string, size: int, created_at: \Illuminate\Support\Carbon}> */
    public function list(int $restaurantId): Collection
    {
        $disk = Storage::disk('local');

        return collect($disk->files(self::directory($restaurantId)))
            ->filter(fn (string $file) => str_ends_with($file, '.zip'))
            ->map(fn (string $file) => [
                'name' => basename($file),
                'size' => $disk->size($file),
                'created_at' => \Illuminate\Support\Carbon::createFromTimestamp($disk->lastModified($file)),
            ])
            ->sortByDesc('created_at')
            ->values();
    }

    /** Absolute path to one of this restaurant's backups, or null. */
    public function path(int $restaurantId, string $name): ?string
    {
        if (!preg_match('/^backup-[0-9]{8}-[0-9]{6}-[a-z0-9]{6}\.zip$/', $name)) {
            return null;
        }
        $relative = self::directory($restaurantId) . '/' . $name;

        return Storage::disk('local')->exists($relative) ? Storage::disk('local')->path($relative) : null;
    }

    public function delete(int $restaurantId, string $name): bool
    {
        return $this->path($restaurantId, $name) !== null
            && Storage::disk('local')->delete(self::directory($restaurantId) . '/' . $name);
    }

    public function prune(int $restaurantId): void
    {
        $keep = max(1, (int) $this->settings->get($restaurantId, 'backups', 'keep', 7));
        $this->list($restaurantId)->slice($keep)->each(fn (array $backup) => $this->delete($restaurantId, $backup['name']));
    }

    /**
     * @return array{included: array<string, \Closure>, skipped: list<string>}
     */
    private function resolveScopes(int $restaurantId): array
    {
        $tables = collect(Schema::getTables())->pluck('name')
            ->map(fn ($name) => Str::afterLast((string) $name, '.'))
            ->reject(fn ($name) => in_array($name, self::SKIP_TABLES, true))
            ->unique()->values();
        $columns = $tables->mapWithKeys(fn ($table) => [$table => Schema::getColumnListing($table)]);

        $branchIds = DB::table('branches')->where('restaurant_id', $restaurantId)->pluck('id')->all();
        $scopes = ['restaurants' => fn ($query) => $query->where('id', $restaurantId)];

        foreach ($tables as $table) {
            if ($table === 'restaurants') {
                continue;
            }
            $cols = $columns[$table];
            if (in_array('restaurant_id', $cols, true)) {
                $scopes[$table] = fn ($query) => $query->where('restaurant_id', $restaurantId);
            } elseif ($branchColumns = array_values(array_filter($cols, fn ($col) => $col === 'branch_id' || str_ends_with($col, '_branch_id')))) {
                $scopes[$table] = fn ($query) => $query->where(function ($inner) use ($branchColumns, $branchIds): void {
                    foreach ($branchColumns as $column) {
                        $inner->orWhereIn($column, $branchIds);
                    }
                });
            }
        }

        // Child tables: follow <singular>_id foreign keys to tables already scoped.
        do {
            $added = false;
            foreach ($tables as $table) {
                if (isset($scopes[$table])) {
                    continue;
                }
                foreach ($columns[$table] as $column) {
                    if (!str_ends_with($column, '_id')) {
                        continue;
                    }
                    $parent = Str::plural(Str::beforeLast($column, '_id'));
                    if (isset($scopes[$parent]) && in_array('id', $columns[$parent] ?? [], true)) {
                        $parentScope = $scopes[$parent];
                        $scopes[$table] = fn ($query) => $query->whereIn($column, $parentScope(DB::table($parent))->select('id'));
                        $added = true;
                        break;
                    }
                }
            }
        } while ($added);

        return [
            'included' => collect($scopes)->only($tables->push('restaurants')->all())->all(),
            'skipped' => $tables->reject(fn ($table) => isset($scopes[$table]))->values()->all(),
        ];
    }
}
