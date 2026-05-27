<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketScan;
use App\Models\User;
use App\Support\CheckInOutcome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketCheckInService
{
    public function checkIn(string $code, User $organizer, Request $request): CheckInOutcome
    {
        return DB::transaction(function () use ($code, $organizer, $request): CheckInOutcome {
            $ticket = Ticket::query()
                ->where(function ($query) use ($code): void {
                    $query->where('qr_code', $code)
                        ->orWhere('payment_reference', $code);
                })
                ->with(['event', 'ticketTier', 'user:id,name,email'])
                ->lockForUpdate()
                ->first();

            $result = match (true) {
                ! $ticket => 'not_found',
                (int) $ticket->event?->user_id !== (int) $organizer->id => 'wrong_event',
                $ticket->status === 'cancelled' => 'cancelled',
                $ticket->status === 'used' => 'already_used',
                default => 'valid',
            };

            if ($result === 'valid' && $ticket) {
                $ticket->update(['status' => 'used']);
                $ticket->refresh();
                $ticket->loadMissing(['event', 'ticketTier', 'user:id,name,email']);
            }

            $scan = TicketScan::create([
                'ticket_id' => $ticket?->id,
                'scanned_by_user_id' => $organizer->id,
                'attempted_code' => $code,
                'result' => $result,
                'scanned_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            ]);

            $scan->load(['scannedBy:id,name']);
            if ($ticket) {
                $scan->setRelation('ticket', $ticket);
            }

            $firstValidScan = null;
            if ($result === 'already_used' && $ticket) {
                $firstValidScan = TicketScan::query()
                    ->where('ticket_id', $ticket->id)
                    ->where('result', 'valid')
                    ->with('scannedBy:id,name')
                    ->orderBy('scanned_at')
                    ->first();
            }

            return new CheckInOutcome(
                result: $result,
                ticket: $ticket,
                scan: $scan,
                firstValidScan: $firstValidScan,
            );
        });
    }
}
