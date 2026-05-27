<?php

namespace App\Services\Paystack;

use App\Contracts\PaystackClientInterface;
use App\Models\Payment;

/**
 * In-memory Paystack stand-in for local/dev environments. It mirrors the
 * authorization_url + verify shape the real gateway produces so the rest of
 * the application can stay implementation-agnostic.
 */
class PaystackMockClient implements PaystackClientInterface
{
    public function __construct(
        private readonly string $callbackUrl,
    ) {}

    public function initialize(string $email, int $amountKobo, string $reference, array $metadata = []): InitializeResult
    {
        $separator = str_contains($this->callbackUrl, '?') ? '&' : '?';
        $authorizationUrl = $this->callbackUrl.$separator.http_build_query([
            'reference' => $reference,
            'simulated' => 1,
        ]);

        return new InitializeResult(
            authorizationUrl: $authorizationUrl,
            reference: $reference,
            accessCode: 'mock_'.substr(hash('sha1', $reference), 0, 16),
            raw: [
                'email' => $email,
                'amount' => $amountKobo,
                'metadata' => $metadata,
            ],
        );
    }

    public function verify(string $reference): VerifyResult
    {
        $payment = Payment::query()->where('paystack_reference', $reference)->first();

        if (! $payment) {
            return new VerifyResult(
                status: 'failed',
                reference: $reference,
                amountKobo: 0,
                gatewayMessage: 'Unknown reference.',
            );
        }

        $amountKobo = (int) round(((float) $payment->amount) * 100);

        return new VerifyResult(
            status: 'success',
            reference: $reference,
            amountKobo: $amountKobo,
            gatewayMessage: 'Approved (simulated).',
            raw: [
                'reference' => $reference,
                'amount' => $amountKobo,
                'status' => 'success',
            ],
        );
    }
}
