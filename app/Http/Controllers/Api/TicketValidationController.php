<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\ValidateTicketRequest;
use App\Models\TicketTier;
use App\Services\TicketCheckInService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TicketValidationController extends Controller
{
    public function __construct(private readonly TicketCheckInService $checkInService) {}

    public function validateTicket(ValidateTicketRequest $request): JsonResponse
    {
        $outcome = $this->checkInService->checkIn(
            code: $request->string('code')->toString(),
            organizer: $request->user(),
            request: $request,
        );

        return match ($outcome->result) {
            'not_found' => response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], Response::HTTP_NOT_FOUND),

            'wrong_event' => response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'errors' => (object) [],
            ], Response::HTTP_FORBIDDEN),

            'already_used' => response()->json([
                'success' => false,
                'message' => 'Ticket already used',
                'used_at' => $outcome->ticket?->updated_at,
            ], Response::HTTP_CONFLICT),

            'cancelled' => response()->json([
                'success' => false,
                'message' => 'Ticket is cancelled',
            ], Response::HTTP_UNPROCESSABLE_ENTITY),

            'valid' => response()->json([
                'success' => true,
                'message' => 'Ticket validated successfully',
                'data' => [
                    'attendee_name' => $outcome->ticket?->user?->name,
                    'event_title' => $outcome->ticket?->event?->title,
                    'tier_name' => $this->tierLabel($outcome->ticket?->ticketTier),
                    'ticket_ref' => $outcome->ticket?->payment_reference,
                    'purchased_at' => $outcome->ticket?->purchased_at,
                ],
            ]),
        };
    }

    private function tierLabel(?TicketTier $tier): ?string
    {
        if (! $tier) {
            return null;
        }

        if ($tier->name === 'custom' && $tier->custom_name) {
            return $tier->custom_name;
        }

        return (string) $tier->name;
    }
}
