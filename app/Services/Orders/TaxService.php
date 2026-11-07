<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Services\Settings\RestaurantSettings;

/**
 * Tax for orders, from Settings > Taxes. Until a restaurant saves its tax
 * settings, the RESTAURANT_TAX_RATE value from .env applies (added on top).
 *
 * Modes:
 *  - exclusive: tax is added on top of menu prices.
 *  - inclusive: menu prices already include tax; the tax portion is shown
 *    on receipts but the customer pays the menu price.
 */
class TaxService
{
    public function __construct(private readonly RestaurantSettings $settings)
    {
    }

    /** @return array{enabled: bool, name: string, rate: float, inclusive: bool} */
    public function config(?int $restaurantId): array
    {
        if ($restaurantId && $this->settings->isSaved($restaurantId, 'taxes')) {
            $taxes = $this->settings->group($restaurantId, 'taxes');
            $enabled = filter_var($taxes['enabled'] ?? false, FILTER_VALIDATE_BOOL);

            return [
                'enabled' => $enabled,
                'name' => trim((string) ($taxes['tax_name'] ?? '')) ?: 'Tax',
                'rate' => $enabled ? max(0.0, min(100.0, (float) ($taxes['rate'] ?? 0))) : 0.0,
                'inclusive' => ($taxes['mode'] ?? 'exclusive') === 'inclusive',
            ];
        }

        $rate = max(0.0, min(100.0, (float) config('restaurant.tax_rate', 0)));

        return ['enabled' => $rate > 0, 'name' => 'Tax', 'rate' => $rate, 'inclusive' => false];
    }

    /**
     * @return array{tax: float, total: float} total = amount the customer pays for $taxable
     */
    public function apply(?int $restaurantId, float $taxable): array
    {
        $tax = $this->config($restaurantId);
        $taxable = max(0.0, $taxable);
        if (!$tax['enabled'] || $tax['rate'] <= 0) {
            return ['tax' => 0.0, 'total' => round($taxable, 2)];
        }

        if ($tax['inclusive']) {
            return ['tax' => round($taxable - $taxable / (1 + $tax['rate'] / 100), 2), 'total' => round($taxable, 2)];
        }

        $amount = round($taxable * $tax['rate'] / 100, 2);

        return ['tax' => $amount, 'total' => round($taxable + $amount, 2)];
    }
}
