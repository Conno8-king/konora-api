<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketTier;
use App\Services\Paystack\VerifyResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketIssuanceService
{
    public const OUTCOME_SUCCESS = 'success';

    public const OUTCOME_ALREADY_FULFILLED = 'already_fulfilled';

    public const OUTCOME_GATEWAY_FAILED = 'gateway_failed';

    public const OUTCOME_SEATS_UNAVAILABLE = 'seats_unavailable';

    /**
     * Fulfill a pending payment from a verified gateway result. Safe to call
     * multiple times for the same payment — issuance only happens once.
     *
     * @return array{outcome: string, payment: Payment, tickets: \Illuminate\Support\Collection<int, Ticket>, message: ?string}
     */
    public function fulfillFromVerifiedPayment(Payment $payment, VerifyResult $result): array
    {
        if ($payment->isSuccessful()) {
            return [
                'outcome' => self::OUTCOME_ALREADY_FULFILLED,
                'payment' => $payment->fresh(),
                'tickets' => $this->loadTickets($payment),
                'message' => null,
            ];
        }

        if (! $result->isSuccessful()) {
            $payment->update(['status' => 'failed']);

            return [
                'outcome' => self::OUTCOME_GATEWAY_FAILED,
                'payment' => $payment->fresh(),
                'tickets' => collect(),
                'message' => $result->gatewayMessage ?? 'Payment was not completed.',
            ];
        }

        $metadata = (array) ($payment->metadata ?? []);
        $tierId = (int) ($metadata['tier_id'] ?? 0);
        $eventId = (int) ($metadata['event_id'] ?? 0);
        $quantity = (int) ($metadata['quantity'] ?? 0);

        $outcome = DB::transaction(function () use ($payment, $tierId, $eventId, $quantity) {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();

            if ($locked->isSuccessful()) {
                return [
                    'outcome' => self::OUTCOME_ALREADY_FULFILLED,
                    'payment' => $locked,
                    'tickets' => $this->loadTickets($locked),
                    'message' => null,
                ];
            }

            /** @var TicketTier|null $tier */
            $tier = TicketTier::query()->whereKey($tierId)->lockForUpdate()->first();

            if (! $tier || ($tier->capacity - $tier->sold_count) < $quantity) {
                return [
                    'outcome' => self::OUTCOME_SEATS_UNAVAILABLE,
                    'payment' => $locked,
                    'tickets' => collect(),
                    'message' => 'Not enough seats available.',
                ];
            }

            $locked->update(['status' => 'success']);

            $tickets = collect();
            for ($i = 0; $i < $quantity; $i++) {
                $tickets->push(Ticket::create([
                    'user_id' => $locked->user_id,
                    'event_id' => $eventId,
                    'tier_id' => $tierId,
                    'qr_code' => (string) Str::uuid(),
                    'payment_reference' => $locked->paystack_reference,
                    'status' => 'active',
                    'purchased_at' => now(),
                ]));
            }

            TicketTier::query()->whereKey($tierId)->increment('sold_count', $quantity);

            return [
                'outcome' => self::OUTCOME_SUCCESS,
                'payment' => $locked->fresh(),
                'tickets' => $tickets,
                'message' => null,
            ];
        });

        return $outcome;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Ticket>
     */
    private function loadTickets(Payment $payment): \Illuminate\Support\Collection
    {
        return Ticket::query()
            ->where('payment_reference', $payment->paystack_reference)
            ->orderBy('id')
            ->get();
    }
}
