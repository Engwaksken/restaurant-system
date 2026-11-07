<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Order;
use App\Models\Restaurant;
use App\Services\Settings\RestaurantSettings;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Sends SMS through the restaurant's configured provider
 * (Africa's Talking or Twilio). Order notifications never throw: a failed
 * SMS is reported and the order flow continues.
 */
class SmsService
{
    public function __construct(private readonly RestaurantSettings $settings)
    {
    }

    /** Text the customer that their online order was received. */
    public function orderPlaced(Order $order, string $trackingUrl): void
    {
        $this->notify($order, 'notify_order_placed', 'template_order_placed', ['{link}' => $trackingUrl]);
    }

    /** Text the customer that their order is ready. */
    public function orderReady(Order $order): void
    {
        $this->notify($order, 'notify_order_ready', 'template_order_ready');
    }

    /**
     * @param bool $force send even when notifications are switched off (used by "Send test SMS")
     */
    public function send(Restaurant $restaurant, string $to, string $message, bool $force = false): void
    {
        $sms = $this->settings->group($restaurant->id, 'sms');
        if (!$force && !filter_var($sms['enabled'] ?? false, FILTER_VALIDATE_BOOL)) {
            return;
        }
        $phone = self::normalisePhone($to);
        if ($phone === null) {
            throw new RuntimeException('The phone number is not valid.');
        }
        $message = mb_substr($message, 0, 459);

        if (($sms['provider'] ?? 'africastalking') === 'twilio') {
            if (empty($sms['twilio_sid']) || empty($sms['twilio_token']) || empty($sms['twilio_from'])) {
                throw new RuntimeException('Twilio is not fully configured.');
            }
            Http::timeout(15)->asForm()
                ->withBasicAuth($sms['twilio_sid'], $sms['twilio_token'])
                ->post('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sms['twilio_sid']) . '/Messages.json', [
                    'To' => $phone, 'From' => $sms['twilio_from'], 'Body' => $message,
                ])->throw();

            return;
        }

        if (empty($sms['at_username']) || empty($sms['at_api_key'])) {
            throw new RuntimeException("Africa's Talking is not fully configured.");
        }
        $sandbox = strtolower((string) $sms['at_username']) === 'sandbox';
        $payload = ['username' => $sms['at_username'], 'to' => $phone, 'message' => $message];
        if (!empty($sms['sender_id'])) {
            $payload['from'] = $sms['sender_id'];
        }
        Http::timeout(15)->asForm()->acceptJson()
            ->withHeaders(['apiKey' => $sms['at_api_key']])
            ->post(($sandbox ? 'https://api.sandbox.africastalking.com' : 'https://api.africastalking.com') . '/version1/messaging', $payload)
            ->throw();
    }

    /** E.164: 0772123456 becomes +256772123456 (Uganda default for local numbers). */
    public static function normalisePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with(trim($phone), '+')) {
            return strlen($digits) >= 9 && strlen($digits) <= 15 ? '+' . $digits : null;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '+256' . substr($digits, 1);
        }
        if (strlen($digits) >= 11 && strlen($digits) <= 15) {
            return '+' . $digits;
        }

        return null;
    }

    private function notify(Order $order, string $toggle, string $templateKey, array $extra = []): void
    {
        try {
            $order->loadMissing(['customer', 'branch.restaurant']);
            $restaurant = $order->branch?->restaurant;
            $phone = $order->customer?->phone;
            if (!$restaurant || !$phone) {
                return;
            }
            $sms = $this->settings->group($restaurant->id, 'sms');
            if (!filter_var($sms['enabled'] ?? false, FILTER_VALIDATE_BOOL) || !filter_var($sms[$toggle] ?? false, FILTER_VALIDATE_BOOL)) {
                return;
            }
            $message = strtr((string) $sms[$templateKey], $extra + [
                '{name}' => strtok((string) $order->customer->name, ' ') ?: 'there',
                '{restaurant}' => $restaurant->name,
                '{order}' => $order->order_number,
                '{total}' => ($restaurant->currency ?: config('restaurant.currency')) . ' ' . number_format((float) $order->total),
            ]);
            $this->send($restaurant, $phone, $message);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
