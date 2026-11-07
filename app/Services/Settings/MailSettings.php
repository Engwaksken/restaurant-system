<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Restaurant;
use Illuminate\Support\Facades\Mail;

/**
 * Applies a restaurant's email settings to the mailer for the current
 * request: optional custom SMTP server and the sender identity.
 */
class MailSettings
{
    public function __construct(private readonly RestaurantSettings $settings)
    {
    }

    public function apply(?Restaurant $restaurant): void
    {
        if (!$restaurant) {
            return;
        }
        $email = $this->settings->group($restaurant->id, 'email');

        if (filter_var($email['use_custom_smtp'] ?? false, FILTER_VALIDATE_BOOL) && !empty($email['smtp_host'])) {
            $encryption = (string) ($email['smtp_encryption'] ?? 'tls');
            config([
                'mail.mailers.restaurant_smtp' => [
                    'transport' => 'smtp',
                    // "smtps" is implicit TLS (465); "smtp" upgrades with STARTTLS when offered (587).
                    'scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
                    'host' => $email['smtp_host'],
                    'port' => (int) ($email['smtp_port'] ?: 587),
                    'username' => $email['smtp_username'] ?: null,
                    'password' => $email['smtp_password'] ?: null,
                    'timeout' => 20,
                ],
                'mail.default' => 'restaurant_smtp',
            ]);
            Mail::purge('restaurant_smtp');
        }

        $from = trim((string) ($email['from_address'] ?? ''));
        config([
            'mail.from.address' => $from !== '' ? $from : config('mail.from.address'),
            'mail.from.name' => trim((string) ($email['from_name'] ?? '')) ?: $restaurant->name,
        ]);
    }

    public function sendsOrderConfirmations(?Restaurant $restaurant): bool
    {
        return $restaurant === null || $this->settings->enabled($restaurant->id, 'email', 'send_order_confirmations');
    }

    public function replyTo(Restaurant $restaurant): ?string
    {
        $replyTo = trim((string) $this->settings->get($restaurant->id, 'email', 'reply_to', ''));

        return $replyTo !== '' ? $replyTo : ($restaurant->email ?: null);
    }
}
