<?php

namespace App\Http\Controllers\Api;

use App\Contracts\PaystackClientInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\InitiatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\TicketResource;
use App\Models\Payment;
use App\Models\TicketTier;
use App\Services\Paystack\PaystackGatewayException;
use App\Services\TicketIssuanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PaystackClientInterface $paystack,
        private readonly TicketIssuanceService $issuance,
    ) {}

    public function initiate(InitiatePaymentRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var TicketTier|null $tier */
        $tier = TicketTier::query()->find($data['tier_id']);

        if (! $tier || (int) $tier->event_id !== (int) $data['event_id']) {
            return $this->errorResponse(
                'Ticket tier does not belong to the requested event.',
                ['tier_id' => ['Ticket tier does not belong to the requested event.']],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $seatsRemaining = (int) $tier->capacity - (int) $tier->sold_count;
        if ($seatsRemaining < (int) $data['quantity']) {
            return $this->errorResponse(
                'Not enough seats available.',
                [],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $amount = round(((float) $tier->price) * (int) $data['quantity'], 2);
        $amountKobo = (int) round($amount * 100);

        $reference = $this->generateUniqueReference();

        $payment = Payment::create([
            'user_id' => (int) $request->user()->id,
            'amount' => $amount,
            'currency' => 'NGN',
            'paystack_reference' => $reference,
            'status' => 'pending',
            'metadata' => [
                'event_id' => (int) $data['event_id'],
                'tier_id' => (int) $data['tier_id'],
                'quantity' => (int) $data['quantity'],
                'attendee_name' => $data['attendee_name'],
                'attendee_email' => $data['attendee_email'],
                'attendee_phone' => $data['attendee_phone'],
            ],
        ]);

        try {
            $result = $this->paystack->initialize(
                email: $data['attendee_email'],
                amountKobo: $amountKobo,
                reference: $reference,
                metadata: [
                    'payment_id' => $payment->id,
                    'event_id' => (int) $data['event_id'],
                    'tier_id' => (int) $data['tier_id'],
                    'quantity' => (int) $data['quantity'],
                    'attendee_name' => $data['attendee_name'],
                    'attendee_phone' => $data['attendee_phone'],
                ],
            );
        } catch (PaystackGatewayException $e) {
            $payment->update(['status' => 'failed']);

            return $this->errorResponse(
                'Could not start payment. Please try again.',
                [],
                Response::HTTP_BAD_GATEWAY,
            );
        }

        return $this->successResponse([
            'authorization_url' => $result->authorizationUrl,
            'reference' => $result->reference,
            'access_code' => $result->accessCode,
            'paystack_public_key' => config('services.paystack.public'),
            'payment' => new PaymentResource($payment->fresh()),
        ], 'Payment initialized successfully.');
    }

    public function verify(Request $request, string $reference): JsonResponse
    {
        /** @var Payment|null $payment */
        $payment = Payment::query()
            ->where('paystack_reference', $reference)
            ->where('user_id', (int) $request->user()->id)
            ->first();

        if (! $payment) {
            return $this->errorResponse('Payment not found.', [], Response::HTTP_NOT_FOUND);
        }

        try {
            $verification = $this->paystack->verify($reference);
        } catch (PaystackGatewayException $e) {
            return $this->errorResponse(
                'Could not verify payment. Please try again.',
                [],
                Response::HTTP_BAD_GATEWAY,
            );
        }

        $outcome = $this->issuance->fulfillFromVerifiedPayment($payment, $verification);

        return match ($outcome['outcome']) {
            TicketIssuanceService::OUTCOME_SUCCESS,
            TicketIssuanceService::OUTCOME_ALREADY_FULFILLED => $this->successResponse([
                'payment' => new PaymentResource($outcome['payment']),
                'tickets' => TicketResource::collection($outcome['tickets']),
            ], 'Payment verified successfully.'),
            TicketIssuanceService::OUTCOME_SEATS_UNAVAILABLE => $this->errorResponse(
                $outcome['message'] ?? 'Not enough seats available.',
                [],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ),
            default => $this->errorResponse(
                $outcome['message'] ?? 'Payment was not completed.',
                [],
                Response::HTTP_PAYMENT_REQUIRED,
            ),
        };
    }

    public function webhook(Request $request): JsonResponse
    {
        $secret = (string) config('services.paystack.secret');
        $raw = $request->getContent();
        $signature = (string) $request->header('X-Paystack-Signature', '');
        $expected = hash_hmac('sha512', $raw, $secret);

        if ($secret === '' || ! hash_equals($expected, $signature)) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($raw, true) ?: [];
        $event = (string) ($payload['event'] ?? '');

        if ($event === 'charge.success') {
            $reference = (string) ($payload['data']['reference'] ?? '');
            if ($reference !== '') {
                $payment = Payment::query()->where('paystack_reference', $reference)->first();
                if ($payment && ! $payment->isSuccessful()) {
                    try {
                        $verification = $this->paystack->verify($reference);
                        $this->issuance->fulfillFromVerifiedPayment($payment, $verification);
                    } catch (PaystackGatewayException $e) {
                        // Swallow gateway transport errors so Paystack retries the webhook later.
                    }
                }
            }
        }

        return response()->json(['status' => 'ok'], Response::HTTP_OK);
    }

    private function generateUniqueReference(): string
    {
        do {
            $candidate = 'KNR-'.strtoupper(Str::random(10));
        } while (Payment::query()->where('paystack_reference', $candidate)->exists());

        return $candidate;
    }
}
