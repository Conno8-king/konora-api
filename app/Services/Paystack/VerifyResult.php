<?php

namespace App\Services\Paystack;

class VerifyResult
{
    /**
     * @param  array<string, mixed>|null  $raw
     */
    public function __construct(
        public readonly string $status,
        public readonly string $reference,
        public readonly int $amountKobo,
        public readonly ?string $gatewayMessage = null,
        public readonly ?array $raw = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }
}
