<?php

namespace App\Services\Paystack;

use App\Contracts\PaystackClientInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Live Paystack gateway. Activated by setting PAYSTACK_MOCK=false in env.
 */
class PaystackHttpClient implements PaystackClientInterface
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $callbackUrl,
        private readonly string $baseUrl = 'https://api.paystack.co',
    ) {}

    public function initialize(string $email, int $amountKobo, string $reference, array $metadata = []): InitializeResult
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->acceptJson()
                ->timeout(15)
                ->retry(1, 100, throw: false)
                ->post($this->baseUrl.'/transaction/initialize', [
                    'email' => $email,
                    'amount' => $amountKobo,
                    'reference' => $reference,
                    'callback_url' => $this->callbackUrl,
                    'metadata' => $metadata,
                ]);
        } catch (Throwable $e) {
            Log::error('Paystack initialize transport error', ['reference' => $reference, 'error' => $e->getMessage()]);
            throw new PaystackGatewayException('Unable to reach payment gateway.', previous: $e);
        }

        if ($response->failed() || ! $response->json('status')) {
            Log::warning('Paystack initialize failed', ['reference' => $reference, 'body' => $response->body()]);
            throw new PaystackGatewayException('Payment gateway rejected the request.');
        }

        $data = (array) $response->json('data', []);

        return new InitializeResult(
            authorizationUrl: (string) ($data['authorization_url'] ?? ''),
            reference: (string) ($data['reference'] ?? $reference),
            accessCode: isset($data['access_code']) ? (string) $data['access_code'] : null,
            raw: $data,
        );
    }

    public function verify(string $reference): VerifyResult
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->acceptJson()
                ->timeout(15)
                ->retry(1, 100, throw: false)
                ->get($this->baseUrl.'/transaction/verify/'.rawurlencode($reference));
        } catch (Throwable $e) {
            Log::error('Paystack verify transport error', ['reference' => $reference, 'error' => $e->getMessage()]);
            throw new PaystackGatewayException('Unable to reach payment gateway.', previous: $e);
        }

        if ($response->failed()) {
            Log::warning('Paystack verify HTTP failure', ['reference' => $reference, 'status' => $response->status()]);
            throw new PaystackGatewayException('Payment gateway could not verify the transaction.');
        }

        $data = (array) $response->json('data', []);
        $status = (string) ($data['status'] ?? 'failed');
        $amountKobo = (int) ($data['amount'] ?? 0);
        $gatewayMessage = isset($data['gateway_response']) ? (string) $data['gateway_response'] : null;

        return new VerifyResult(
            status: $status,
            reference: (string) ($data['reference'] ?? $reference),
            amountKobo: $amountKobo,
            gatewayMessage: $gatewayMessage,
            raw: $data,
        );
    }
}
