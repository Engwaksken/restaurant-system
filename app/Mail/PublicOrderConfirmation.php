<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\Settings\MailSettings;
use App\Support\Brand;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PublicOrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly string $trackingToken,
    ) {
    }

    public function build(): static
    {
        $this->order->loadMissing(['items', 'customer', 'branch.restaurant']);
        $brand = Brand::for($this->order->branch?->restaurant);

        $mail = $this
            ->subject('Order ' . $this->order->order_number . ' received - ' . $brand->name())
            ->view('mail.orders.confirmation', ['brand' => $brand]);

        // Keep the configured sending address (SPF/DKIM) but show the restaurant's name
        // (or the "From name" set in Email settings, applied by MailSettings).
        if (config('mail.from.address')) {
            $fromName = (string) config('mail.from.name');
            $mail->from((string) config('mail.from.address'), $fromName !== '' && $fromName !== config('app.name') ? $fromName : $brand->name());
        }
        $replyTo = $this->order->branch?->restaurant
            ? app(MailSettings::class)->replyTo($this->order->branch->restaurant)
            : $brand->email();
        if ($replyTo) {
            $mail->replyTo($replyTo, $brand->name());
        }

        return $mail;
    }
}
