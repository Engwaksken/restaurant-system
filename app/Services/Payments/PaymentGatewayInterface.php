<?php
namespace App\Services\Payments;
interface PaymentGatewayInterface {
    public function initiate(array $data): array;
    public function verify(string $reference): array;
}
