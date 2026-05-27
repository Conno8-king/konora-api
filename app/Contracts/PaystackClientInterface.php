<?php

namespace App\Contracts;

use App\Services\Paystack\InitializeResult;
use App\Services\Paystack\VerifyResult;

interface PaystackClientInterface
{
    /**
     * Initialize a transaction with the gateway.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function initialize(string $email, int $amountKobo, string $reference, array $metadata = []): InitializeResult;

    /**
     * Verify a transaction by its reference.
     */
    public function verify(string $reference): VerifyResult;
}
