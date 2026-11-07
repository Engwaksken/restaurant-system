<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\PaymentGatewaySetting;
use App\Models\Restaurant;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves online payment gateway settings for a restaurant.
 *
 * Settings saved in the admin (restaurant_payment_gateways) take precedence;
 * a gateway the restaurant has never saved falls back to the .env values in
 * config/restaurant.php. The resolved values are written into
 * config('restaurant.gateways') for the current request so the gateway
 * clients and checkout flow read one source.
 */
class GatewaySettings
{
    /** Field definitions, API endpoints and allowed hosts per gateway. */
    public const GATEWAYS = [
        'flutterwave' => [
            'label' => 'Flutterwave',
            'icon' => 'fas fa-credit-card',
            'description' => 'Cards, mobile money and bank transfers across Africa.',
            'docs' => 'https://developer.flutterwave.com/docs/integration-guides/authentication',
            'base_urls' => ['test' => 'https://api.flutterwave.com/v3', 'live' => 'https://api.flutterwave.com/v3'],
            'fields' => [
                'secret_key' => ['label' => 'Secret key', 'secret' => true, 'help' => 'Starts with FLWSECK_TEST- for test mode or FLWSECK- for live. Find it under Settings, API keys in your Flutterwave dashboard.'],
            ],
            'required' => ['secret_key'],
        ],
        'pesapal' => [
            'label' => 'Pesapal',
            'icon' => 'fas fa-globe',
            'description' => 'Cards and mobile money in Uganda, Kenya, Tanzania and beyond.',
            'docs' => 'https://developer.pesapal.com/how-to-integrate/e-commerce/api-30-json/authentication',
            'base_urls' => ['test' => 'https://cybqa.pesapal.com/pesapalv3', 'live' => 'https://pay.pesapal.com/v3'],
            'fields' => [
                'consumer_key' => ['label' => 'Consumer key', 'secret' => false, 'help' => 'From your Pesapal merchant dashboard.'],
                'consumer_secret' => ['label' => 'Consumer secret', 'secret' => true, 'help' => 'From your Pesapal merchant dashboard.'],
                'notification_id' => ['label' => 'IPN notification ID', 'secret' => false, 'help' => 'The ID Pesapal gives you after you register your IPN URL.'],
            ],
            'required' => ['consumer_key', 'consumer_secret', 'notification_id'],
        ],
        'iotec' => [
            'label' => 'ioTec Pay',
            'icon' => 'fas fa-mobile-screen-button',
            'description' => 'MTN and Airtel mobile money in Uganda. Customers approve a payment prompt on their phone.',
            'docs' => 'https://pay.iotec.io/docs',
            'base_urls' => ['test' => 'https://pay.iotec.io', 'live' => 'https://pay.iotec.io'],
            'fields' => [
                'client_id' => ['label' => 'Client ID', 'secret' => false, 'help' => 'From your ioTec Pay account under API credentials.'],
                'client_secret' => ['label' => 'Client secret', 'secret' => true, 'help' => 'From your ioTec Pay account under API credentials.'],
                'wallet_id' => ['label' => 'Wallet ID', 'secret' => false, 'help' => 'The ioTec wallet that receives customer payments.'],
            ],
            'required' => ['client_id', 'client_secret', 'wallet_id'],
            // Payment is approved on the customer's phone, not on a hosted checkout page.
            'flow' => 'prompt',
        ],
    ];

    /** Override config('restaurant.gateways') with this restaurant's saved settings. */
    public function apply(?Restaurant $restaurant): void
    {
        if ($restaurant === null || !Schema::hasTable('restaurant_payment_gateways')) {
            return;
        }

        $saved = PaymentGatewaySetting::query()->where('restaurant_id', $restaurant->id)->get()->keyBy('gateway');
        foreach (self::GATEWAYS as $gateway => $definition) {
            $setting = $saved->get($gateway);
            if (!$setting) {
                continue;
            }
            $credentials = (array) ($setting->credentials ?? []);
            $mode = $setting->mode === 'live' ? 'live' : 'test';
            $resolved = ['enabled' => $setting->is_enabled, 'mode' => $mode, 'base_url' => $definition['base_urls'][$mode]];
            foreach (array_keys($definition['fields']) as $field) {
                $resolved[$field] = $credentials[$field] ?? null;
            }
            config(['restaurant.gateways.' . $gateway => $resolved]);
        }
    }

    /** Gateways that are switched on and have every required credential: [key => label]. */
    public function enabled(): array
    {
        return collect(config('restaurant.gateways', []))
            ->filter(fn ($settings, string $gateway): bool => is_array($settings) && $this->isReady($gateway, $settings))
            ->map(fn ($settings, string $gateway): string => self::GATEWAYS[$gateway]['label'] ?? ucfirst($gateway))
            ->all();
    }

    public function isReady(string $gateway, array $settings): bool
    {
        if (!isset(self::GATEWAYS[$gateway]) || !($settings['enabled'] ?? false) || empty($settings['base_url'])) {
            return false;
        }
        foreach (self::GATEWAYS[$gateway]['required'] as $field) {
            if (empty($settings[$field])) {
                return false;
            }
        }

        return true;
    }

    /** Show only the end of a stored secret. */
    public static function mask(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return str_repeat('*', 8) . substr($value, -4);
    }
}
