<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScanLogResource;
use App\Models\TicketScan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizerScanController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'event_id' => ['nullable', 'integer'],
            'result' => ['nullable', Rule::in(['valid', 'already_used', 'wrong_event', 'cancelled', 'not_found'])],
        ]);

        $organizerId = (int) $request->user()->id;

        $query = TicketScan::query()
            ->with([
                'scannedBy:id,name',
                'ticket' => fn ($q) => $q->with([
                    'event' => fn ($e) => $e->withTrashed(),
                    'ticketTier',
                    'user:id,name,email',
                ]),
            ])
            ->whereHas('ticket.event', function (Builder $q) use ($organizerId) {
                $q->withTrashed()->where('user_id', $organizerId);
            });

        if (! empty($validated['event_id'])) {
            $eventId = (int) $validated['event_id'];
            $query->whereHas('ticket', function (Builder $q) use ($eventId) {
                $q->where('event_id', $eventId);
            });
        }

        if (! empty($validated['result'])) {
            $query->where('result', $validated['result']);
        }

        $scans = $query
            ->latest('scanned_at')
            ->latest('id')
            ->paginate(20);

        return ScanLogResource::collection($scans)->additional([
            'success' => true,
            'message' => 'Scans retrieved successfully.',
        ]);
    }
}
