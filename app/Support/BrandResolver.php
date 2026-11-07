<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Restaurant;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Resolves the brand for the current request once: the signed-in staff
 * member's restaurant, otherwise the public restaurant configured by code.
 */
final class BrandResolver
{
    private ?Brand $resolved = null;

    private int|string|null $resolvedFor = null;

    public function current(): Brand
    {
        $key = auth()->id() ?? 'guest';
        if ($this->resolved === null || $this->resolvedFor !== $key) {
            $this->resolved = Brand::for($this->restaurant());
            $this->resolvedFor = $key;
        }

        return $this->resolved;
    }

    /** Drop the cached brand, e.g. after branding was just updated. */
    public function forget(): void
    {
        $this->resolved = null;
    }

    private function restaurant(): ?Restaurant
    {
        try {
            if (!Schema::hasTable('restaurants')) {
                return null;
            }

            $restaurantId = auth()->user()?->restaurant_id;
            if ($restaurantId) {
                return Restaurant::query()->find($restaurantId);
            }

            return Restaurant::query()
                ->where('code', config('restaurant.public_code'))
                ->where('is_active', true)
                ->first();
        } catch (Throwable) {
            // Branding must never take a page down (e.g. database unavailable on an error page).
            return null;
        }
    }
}
