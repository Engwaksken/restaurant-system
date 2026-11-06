<?php

namespace App\Mail;

use App\Models\Order;
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
        return $this
            ->subject('Order received: ' . $this->order->order_number)
            ->view('mail.orders.confirmation');
    }
}
