<?php

namespace App\Mail;

use App\Models\Order;
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

        // Keep the configured sending address (SPF/DKIM) but show the restaurant's name.
        if (config('mail.from.address')) {
            $mail->from((string) config('mail.from.address'), $brand->name());
        }
        if ($brand->email()) {
            $mail->replyTo($brand->email(), $brand->name());
        }

        return $mail;
    }
}
