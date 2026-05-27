<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TicketPurchaseResource extends JsonResource
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

        return [
            'id' => $ticket->id,
            'qr_code' => $ticket->qr_code,
            'status' => $ticket->status,
            'payment_reference' => $ticket->payment_reference,
            'purchased_at' => $ticket->purchased_at,
            'event' => $this->buildEvent($event),
            'tier' => $this->buildTier($tier),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildEvent(?Event $event): ?array
    {
        if (! $event) {
            return null;
        }

        return [
            'id' => $event->id,
            'title' => $event->title,
            'date' => $event->date,
            'start_time' => $event->start_time,
            'venue_name' => $event->venue_name,
            'banner_path' => $event->banner_path,
            'banner_url' => $event->banner_path ? Storage::disk('public')->url($event->banner_path) : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildTier(?TicketTier $tier): ?array
    {
        if (! $tier) {
            return null;
        }

        return [
            'id' => $tier->id,
            'name' => $tier->name,
            'custom_name' => $tier->custom_name,
        ];
    }
}
