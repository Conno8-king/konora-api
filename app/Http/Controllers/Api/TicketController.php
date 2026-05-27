<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketDetailResource;
use App\Http\Resources\TicketPurchaseResource;
use App\Models\Ticket;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TicketController extends Controller
{
    use ApiResponse;

    public function myTickets(Request $request): JsonResponse
    {
        $tickets = $request->user()
            ->tickets()
            ->with([
                'event' => fn ($q) => $q->withTrashed(),
                'ticketTier',
            ])
            ->orderByDesc('purchased_at')
            ->orderByDesc('id')
            ->get();

        return $this->successResponse(
            TicketPurchaseResource::collection($tickets),
            'Tickets retrieved successfully.',
        );
    }

    public function show(Ticket $ticket): JsonResponse
    {
        if ((int) $ticket->user_id !== (int) auth()->id()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $ticket->load([
            'event' => fn ($q) => $q->withTrashed(),
            'ticketTier',
        ]);

        return $this->successResponse(
            new TicketDetailResource($ticket),
            'Ticket retrieved successfully.',
        );
    }
}
