<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderFeedback;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Customers rate a completed order once, authorised by the order's private
 * tracking token (only its hash is stored).
 */
class PublicOrderFeedbackController extends Controller
{
    public const RATEABLE_STATUSES = ['served', 'completed'];

    public function store(Request $request, string $order): RedirectResponse
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $order) === 1, 404);
        $record = Order::query()
            ->where('tracking_token_hash', hash('sha256', $order))
            ->firstOrFail();

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $back = redirect()->route('order.track', ['order' => $order]);
        if (!in_array($record->status, self::RATEABLE_STATUSES, true)) {
            return $back->with('error', 'You can rate your order once it has been completed.');
        }
        if ($record->feedback()->exists()) {
            return $back->with('error', 'Feedback for this order has already been received.');
        }

        try {
            OrderFeedback::create([
                'branch_id' => $record->branch_id,
                'order_id' => $record->id,
                'customer_id' => $record->customer_id,
                'rating' => $data['rating'],
                'comment' => isset($data['comment']) ? trim($data['comment']) ?: null : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $back->with('error', 'Feedback for this order has already been received.');
        }

        return $back->with('success', 'Thank you for your feedback.');
    }
}
