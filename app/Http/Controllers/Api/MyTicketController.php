<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\IndexMyTicketsRequest;
use App\Http\Resources\MyTicketResource;
use App\Models\Ticket;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\HttpFoundation\Response;

class MyTicketController extends Controller
{
    use ApiResponse;

    public function index(IndexMyTicketsRequest $request)
    {
        $filter = $request->validated('filter') ?? 'upcoming';

        $query = $request->user()
            ->tickets()
            ->with([
                'event' => fn ($q) => $q->withTrashed(),
                'ticketTier',
            ]);

        $this->applyFilter($query, $filter);

        $tickets = $query
            ->latest('purchased_at')
            ->latest('id')
            ->paginate(20);

        return MyTicketResource::collection($tickets)->additional([
            'success' => true,
            'message' => 'Tickets retrieved successfully.',
        ]);
    }

    public function show(Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'event' => fn ($q) => $q->withTrashed(),
            'ticketTier',
        ]);

        return $this->successResponse(
            new MyTicketResource($ticket),
            'Ticket retrieved successfully.',
            Response::HTTP_OK
        );
    }

    /**
     * @param  Builder<Ticket>|HasMany<Ticket, \App\Models\User>  $query
     */
    private function applyFilter(Builder|HasMany $query, string $filter): void
    {
        match ($filter) {
            'cancelled' => $query->where('tickets.status', 'cancelled'),
            'past' => $query->where(function (Builder $q): void {
                $q->where('tickets.status', 'used')
                    ->orWhere(function (Builder $q2): void {
                        $q2->where('tickets.status', 'active')
                            ->whereHas('event', fn (Builder $e) => $e->wherePastForTicketListing());
                    });
            }),
            default => $query->where('tickets.status', 'active')
                ->whereHas('event', fn (Builder $e) => $e->whereUpcomingForTicketListing()),
        };
    }
}
