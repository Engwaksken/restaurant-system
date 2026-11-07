<?php

declare(strict_types=1);

namespace App\Services\Payments;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * ioTec Pay mobile money collections (https://pay.iotec.io).
 *
 * Auth is OAuth 2.0 client credentials at id.iotec.io (tokens last 300s).
 * A collection sends an approval prompt to the payer's phone; the result is
 * read back with the status-by-external-id endpoint.
 */
class IotecGateway
{
    private const TOKEN_URL = 'https://id.iotec.io/connect/token';

    /** ioTec rejects collections below this amount. */
    public const MINIMUM_AMOUNT = 500;

    public function collect(array $data): array
    {
        return Http::timeout(30)->acceptJson()->withToken($this->token())
            ->post($this->base() . '/api/collections/collect', $data)
            ->throw()->json() ?? [];
    }

    public function statusByExternalId(string $externalId): array
    {
        return Http::timeout(20)->acceptJson()->withToken($this->token())
            ->get($this->base() . '/api/collections/external-id/' . rawurlencode($externalId))
            ->throw()->json() ?? [];
    }

    /** ioTec expects the payer as 256XXXXXXXXX. */
    public static function payerNumber(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '256' . substr($digits, 1);
        }

        return preg_match('/^256\d{9}$/', $digits) ? $digits : null;
    }

    private function base(): string
    {
        return rtrim((string) config('restaurant.gateways.iotec.base_url', 'https://pay.iotec.io'), '/');
    }

    private function token(): string
    {
        $clientId = (string) config('restaurant.gateways.iotec.client_id');
        $secret = (string) config('restaurant.gateways.iotec.client_secret');
        if ($clientId === '' || $secret === '') {
            throw new RuntimeException('ioTec Pay is not configured.');
        }

        // Cached per credentials so restaurants never share a token.
        return Cache::remember('iotec_token:' . sha1($clientId . '|' . $secret), 240, function () use ($clientId, $secret): string {
            $json = Http::timeout(20)->asForm()->acceptJson()->post(self::TOKEN_URL, [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $secret,
            ])->throw()->json();

            return (string) ($json['access_token'] ?? throw new RuntimeException('ioTec token missing.'));
        });
    }
}
