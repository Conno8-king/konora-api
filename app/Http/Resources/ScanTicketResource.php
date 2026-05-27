<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use App\Models\TicketTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScanTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Ticket $ticket */
        $ticket = $this->resource;
        $event = $ticket->event;
        $tier = $ticket->ticketTier;
        $attendee = $ticket->user;

        return [
            'id' => $ticket->id,
            'qr_code' => $ticket->qr_code,
            'status' => $ticket->status,
            'attendee_name' => $attendee?->name,
            'attendee_email' => $attendee?->email,
            'event_id' => $event?->id,
            'event_title' => $event?->title,
            'tier_label' => $this->tierLabel($tier),
        ];
    }

    private function tierLabel(?TicketTier $tier): string
    {
        if (! $tier) {
            return '';
        }

        if ($tier->name === 'custom' && $tier->custom_name) {
            return $tier->custom_name;
        }

        return (string) $tier->name;
    }
}
