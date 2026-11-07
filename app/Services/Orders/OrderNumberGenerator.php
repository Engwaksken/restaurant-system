<?php

declare(strict_types=1);

namespace App\Services\Orders;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Short, readable, chronologically sortable order numbers:
 * PREFIX-YYMM-NNNN, e.g. WEB-2611-0001 for the first web order of November 2026.
 *
 * The counter restarts every month and grows past 9999 if needed. Numbers are
 * allocated under a row lock, so concurrent checkouts never get the same one.
 * Call inside the transaction that creates the order so a rolled-back order
 * also rolls back its number.
 */
class OrderNumberGenerator
{
    public function next(string $prefix, ?Carbon $at = null): string
    {
        $period = ($at ?? now())->format('ym');
        $key = $prefix . '-' . $period;

        $value = DB::transaction(function () use ($key): int {
            DB::table('order_number_sequences')->insertOrIgnore([
                'sequence_key' => $key, 'last_value' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $row = DB::table('order_number_sequences')->where('sequence_key', $key)->lockForUpdate()->first();
            $next = (int) $row->last_value + 1;
            DB::table('order_number_sequences')->where('sequence_key', $key)->update(['last_value' => $next, 'updated_at' => now()]);

            return $next;
        });

        return $key . '-' . str_pad((string) $value, 4, '0', STR_PAD_LEFT);
    }
}
