<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketTier;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrganizerAnalyticsController extends Controller
{
    use ApiResponse;

    public function stats()
    {
        $organizerId = (int) auth()->id();

        $totalEvents = Event::query()
            ->where('user_id', $organizerId)
            ->count();

        $totalTicketsSold = (int) TicketTier::query()
            ->whereHas('event', fn (Builder $q) => $q->where('user_id', $organizerId))
            ->sum('sold_count');

        $organizerEventIds = Event::query()
            ->where('user_id', $organizerId)
            ->pluck('id')
            ->all();

        $totalRevenue = (float) Payment::query()
            ->where('status', 'success')
            ->where(function (Builder $q) use ($organizerId, $organizerEventIds): void {
                $q->where(function (Builder $q2) use ($organizerId): void {
                    $q2->whereNotNull('ticket_id')
                        ->whereHas('ticket.event', fn (Builder $e) => $e->where('user_id', $organizerId));
                });
                if (! empty($organizerEventIds)) {
                    $q->orWhere(function (Builder $q2) use ($organizerEventIds): void {
                        $q2->whereNull('ticket_id')
                            ->whereIn('metadata->event_id', $organizerEventIds);
                    });
                }
            })
            ->sum('amount');

        $upcomingEvents = Event::query()
            ->where('user_id', $organizerId)
            ->where('status', 'published')
            ->whereDate('date', '>=', today())
            ->count();

        $recentPurchases = Ticket::query()
            ->whereNotNull('purchased_at')
            ->where('status', '!=', 'cancelled')
            ->whereHas('event', fn (Builder $q) => $q->where('user_id', $organizerId))
            ->with([
                'user:id,name',
                'event:id,title',
                'ticketTier:id,name,custom_name',
            ])
            ->orderByDesc('purchased_at')
            ->limit(5)
            ->get()
            ->map(fn (Ticket $ticket) => [
                'buyer_name' => $this->formatBuyerName($ticket->user?->name),
                'event_title' => $ticket->event?->title,
                'tier_name' => $this->tierDisplayName($ticket->ticketTier),
                'purchased_at' => optional($ticket->purchased_at)->diffForHumans(),
            ])
            ->values();

        return $this->successResponse([
            'total_events' => $totalEvents,
            'total_tickets_sold' => $totalTicketsSold,
            'total_revenue' => $totalRevenue,
            'upcoming_events' => $upcomingEvents,
            'recent_purchases' => $recentPurchases,
        ], 'Organizer statistics retrieved successfully.');
    }

    public function analytics(Event $event)
    {
        $this->authorize('update', $event);

        $tiers = TicketTier::query()
            ->where('event_id', $event->id)
            ->orderBy('id')
            ->get();

        $ticketsByTier = $tiers->map(function (TicketTier $tier) {
            $unitPrice = (float) $tier->price;
            $sold = (int) $tier->sold_count;

            return [
                'tier_id' => $tier->id,
                'tier_name' => $this->tierDisplayName($tier),
                'sold' => $sold,
                'capacity' => (int) $tier->capacity,
                'unit_price' => $unitPrice,
                'revenue' => round($sold * $unitPrice, 2),
            ];
        })->values();

        $totalRevenue = (float) Payment::query()
            ->where('status', 'success')
            ->where(function (Builder $q) use ($event): void {
                $q->where(function (Builder $q2) use ($event): void {
                    $q2->whereNotNull('ticket_id')
                        ->whereHas('ticket', fn (Builder $t) => $t->where('event_id', $event->id));
                })->orWhere(function (Builder $q2) use ($event): void {
                    $q2->whereNull('ticket_id')
                        ->where('metadata->event_id', $event->id);
                });
            })
            ->sum('amount');

        $dailySales = $this->dailySalesForEvent($event->id);

        $totalSold = (int) $tiers->sum('sold_count');
        $totalCapacity = (int) $tiers->sum('capacity');
        $totalTiers = $tiers->count();

        $attendanceRate = $totalCapacity > 0
            ? round(($totalSold / $totalCapacity) * 100, 1)
            : 0.0;

        return $this->successResponse([
            'tickets_by_tier' => $ticketsByTier,
            'daily_sales' => $dailySales,
            'total_revenue' => $totalRevenue,
            'attendance_rate' => $attendanceRate,
            'summary' => [
                'total_sold' => $totalSold,
                'total_capacity' => $totalCapacity,
                'total_tiers' => $totalTiers,
            ],
        ], 'Event analytics retrieved successfully.');
    }

    /**
     * Build a zero-filled 30-day sales series for the given event, ending today.
     *
     * @return Collection<int, array{date: string, count: int}>
     */
    private function dailySalesForEvent(int $eventId): Collection
    {
        $end = CarbonImmutable::today();
        $start = $end->subDays(29);

        $driver = DB::connection()->getDriverName();
        $dateExpression = $driver === 'sqlite'
            ? "date(purchased_at)"
            : 'DATE(purchased_at)';

        $counts = Ticket::query()
            ->where('event_id', $eventId)
            ->whereNotNull('purchased_at')
            ->whereBetween('purchased_at', [$start->startOfDay(), $end->endOfDay()])
            ->selectRaw("$dateExpression as sale_date, COUNT(*) as total")
            ->groupBy('sale_date')
            ->pluck('total', 'sale_date');

        return collect(range(0, 29))->map(function (int $offset) use ($start, $counts) {
            $date = $start->addDays($offset)->toDateString();

            return [
                'date' => $date,
                'count' => (int) ($counts[$date] ?? 0),
            ];
        });
    }

    private function tierDisplayName(?TicketTier $tier): ?string
    {
        if ($tier === null) {
            return null;
        }

        return $tier->custom_name ?: $tier->name;
    }

    private function formatBuyerName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $clean = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($clean === '') {
            return null;
        }

        $parts = explode(' ', $clean);
        if (count($parts) === 1) {
            return $parts[0];
        }

        $first = $parts[0];
        $lastInitial = mb_strtoupper(mb_substr(end($parts), 0, 1));

        return "$first $lastInitial.";
    }
}
