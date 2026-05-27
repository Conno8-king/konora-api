<?php

namespace App\Services\Paystack;

class InitializeResult
{
    /**
     * @param  array<string, mixed>|null  $raw
     */
    public function __construct(
        public readonly string $authorizationUrl,
        public readonly string $reference,
        public readonly ?string $accessCode = null,
        public readonly ?array $raw = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'authorization_url' => $this->authorizationUrl,
            'reference' => $this->reference,
            'access_code' => $this->accessCode,
        ];
    }
}
