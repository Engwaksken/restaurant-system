<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\FlutterwaveGateway;
use App\Services\Payments\PesapalGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\ValidationException;
use Throwable;

class PublicOrderPaymentController extends Controller
{
    public function pay(
        Request $request,
        string $order,
        FlutterwaveGateway $flutterwave,
        PesapalGateway $pesapal,
    ): RedirectResponse {
        $record = $this->findPublicOrder($order);
        $data = $request->validate(['gateway' => ['required', 'in:flutterwave,pesapal']]);
        abort_unless(filter_var($record->customer?->email, FILTER_VALIDATE_EMAIL), 422, 'A valid email address is required for online payment.');
        $provider = $data['gateway'];
        $settings = config('restaurant.gateways.' . $provider);
        abort_unless(($settings['enabled'] ?? false) && $this->hasGatewayCredentials($provider, $settings), 503, 'This online payment method is not available.');
        abort_if((float) $record->balance <= 0, 422, 'This order has no outstanding balance.');

        $payment = DB::transaction(function () use ($record, $provider): Payment {
            $lockedOrder = Order::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            abort_if((float) $lockedOrder->balance <= 0, 422, 'This order has no outstanding balance.');
            abort_if(
                Payment::query()->where('order_id', $lockedOrder->id)->where('status', 'pending')->exists(),
                409,
                'A payment attempt is already in progress.'
            );

            return Payment::create([
                'order_id' => $lockedOrder->id,
                'customer_id' => $lockedOrder->customer_id,
                'receipt_number' => 'WEB-' . strtoupper((string) \Illuminate\Support\Str::uuid()),
                'payment_method' => $provider,
                'amount' => $lockedOrder->balance,
                'transaction_reference' => 'WEB-' . strtoupper((string) \Illuminate\Support\Str::uuid()),
                'status' => 'pending',
            ]);
        });

        try {
            $customer = $record->customer;
            $returnUrl = route('order.payment.return', ['order' => $order, 'payment' => $payment->id]);
            if ($provider === 'flutterwave') {
                $response = $flutterwave->initiate([
                    'tx_ref' => $payment->transaction_reference,
                    'amount' => (float) $payment->amount,
                    'currency' => config('restaurant.currency'),
                    'redirect_url' => $returnUrl,
                    'customer' => [
                        'email' => $customer?->email,
                        'phonenumber' => $customer?->phone,
                        'name' => $customer?->name,
                    ],
                    'customizations' => ['title' => config('app.name')],
                ]);
                $redirectUrl = $response['data']['link'] ?? null;
                $payment->gateway_response = $response;
            } else {
                abort_unless($settings['notification_id'] ?? null, 503, 'Pesapal IPN is not configured.');
                $response = $pesapal->initiate([
                    'id' => $payment->transaction_reference,
                    'currency' => config('restaurant.currency'),
                    'amount' => (float) $payment->amount,
                    'description' => 'Restaurant order ' . $record->order_number,
                    'callback_url' => $returnUrl,
                    'notification_id' => $settings['notification_id'],
                    'billing_address' => [
                        'email_address' => $customer?->email,
                        'phone_number' => $customer?->phone,
                        'first_name' => $customer?->name,
                    ],
                ]);
                $redirectUrl = $response['redirect_url'] ?? null;
                abort_unless(is_scalar($response['order_tracking_id'] ?? null) && (string) $response['order_tracking_id'] !== '', 502, 'The payment provider returned no transaction reference.');
                $response['requested_redirect_url'] = $redirectUrl;
                $payment->gateway_response = $response;
            }

            abort_unless($this->isTrustedRedirect($provider, $redirectUrl, $settings), 502, 'The payment provider returned an invalid checkout URL.');
            $payment->save();

            return redirect()->away($redirectUrl);
        } catch (Throwable $exception) {
            $gatewayRejected = $exception instanceof RequestException
                && $exception->response->status() >= 400
                && $exception->response->status() < 500;
            $payment->forceFill([
                'status' => $gatewayRejected ? 'failed' : 'pending',
            ])->save();
            if (!$payment->gateway_response) {
                $payment->forceFill(['gateway_response' => [
                    'checkout_start' => $gatewayRejected ? 'rejected' : 'outcome_unknown',
                    'error_type' => class_basename($exception),
                ]])->save();
            }
            report($exception);

            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            return back()->withErrors(['gateway' => 'The payment service could not start checkout. Please try again.']);
        }
    }

    public function complete(
        string $order,
        int $payment,
        FlutterwaveGateway $flutterwave,
        PesapalGateway $pesapal,
    ): RedirectResponse {
        $record = $this->findPublicOrder($order);
        $attempt = Payment::query()
            ->where('order_id', $record->id)
            ->whereKey($payment)
            ->firstOrFail();

        if ($attempt->status === 'successful') {
            return redirect()->route('order.track', $order)->with('success', 'Payment is confirmed.');
        }
        abort_unless($attempt->status === 'pending', 404);

        $provider = $attempt->payment_method;
        abort_unless(in_array($provider, ['flutterwave', 'pesapal'], true), 404);
        $verificationReference = $provider === 'pesapal'
            ? (string) data_get($attempt->gateway_response, 'order_tracking_id', '')
            : (string) $attempt->transaction_reference;
        abort_unless($verificationReference !== '', 422, 'The payment reference is unavailable.');

        try {
            $verification = $provider === 'flutterwave'
                ? $flutterwave->verify($verificationReference)
                : $pesapal->verify($verificationReference);
        } catch (Throwable $exception) {
            report($exception);
            return redirect()->route('order.track', $order)->with('error', 'Payment verification is temporarily unavailable. Please try again.');
        }

        $details = $provider === 'flutterwave'
            ? (array) ($verification['data'] ?? [])
            : $verification;
        $status = strtolower((string) ($provider === 'flutterwave'
            ? ($details['status'] ?? '')
            : ($details['payment_status_description'] ?? $details['status'] ?? '')));
        $verifiedReference = (string) ($provider === 'flutterwave'
            ? ($details['tx_ref'] ?? '')
            : ($details['merchant_reference'] ?? ''));
        $amount = (float) ($details['amount'] ?? 0);
        $currency = strtoupper((string) ($details['currency'] ?? ''));
        $paid = in_array($status, ['successful', 'completed', 'approved'], true)
            && abs(round($amount, 2) - round((float) $attempt->amount, 2)) < 0.001
            && $currency === strtoupper((string) config('restaurant.currency'))
            && $verifiedReference !== ''
            && hash_equals((string) $attempt->transaction_reference, $verifiedReference)
            && ($provider !== 'flutterwave' || ($verification['status'] ?? null) === 'success');

        if (!$paid) {
            if (in_array($status, ['failed', 'declined', 'cancelled', 'canceled'], true)) {
                $attempt->forceFill(['status' => 'failed', 'gateway_response' => $verification])->save();
            }

            return redirect()->route('order.track', $order)->with('error', 'Payment has not been confirmed.');
        }

        DB::transaction(function () use ($record, $attempt, $verification): void {
            $lockedOrder = Order::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($lockedPayment->status !== 'pending') {
                return;
            }

            $lockedPayment->forceFill([
                'status' => 'successful',
                'gateway_response' => $verification,
                'paid_at' => now(),
            ])->save();
            $paidAmount = (float) $lockedOrder->payments()->where('status', 'successful')->sum('amount');
            $balance = max(0, (float) $lockedOrder->total - $paidAmount);
            $lockedOrder->update([
                'paid_amount' => $paidAmount,
                'balance' => $balance,
                'status' => $balance <= 0 ? 'completed' : $lockedOrder->status,
                'completed_at' => $balance <= 0 ? now() : $lockedOrder->completed_at,
            ]);
        });

        return redirect()->route('order.track', $order)->with('success', 'Payment is confirmed.');
    }

    private function findPublicOrder(string $token): Order
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token) === 1, 404);

        return Order::query()
            ->where('tracking_token_hash', hash('sha256', $token))
            ->with(['customer', 'branch'])
            ->firstOrFail();
    }

    private function hasGatewayCredentials(string $provider, array $settings): bool
    {
            return $provider === 'flutterwave'
                ? !empty($settings['secret_key']) && !empty($settings['base_url'])
            : !empty($settings['consumer_key']) && !empty($settings['consumer_secret']) && !empty($settings['notification_id']) && !empty($settings['base_url']);
    }

    private function isTrustedRedirect(string $provider, mixed $url, array $settings): bool
    {
        if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            return false;
        }
        $host = strtolower($parts['host']);

        if ($provider === 'flutterwave') {
            return $host === 'flutterwave.com' || str_ends_with($host, '.flutterwave.com');
        }

        $configuredHost = strtolower((string) parse_url((string) $settings['base_url'], PHP_URL_HOST));
        return $host === $configuredHost || $host === 'pay.pesapal.com';
    }
}
