<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TicketDetailResource extends JsonResource
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
            'user_id' => $ticket->user_id,
            'qr_code' => $ticket->qr_code,
            'status' => $ticket->status,
            'payment_reference' => $ticket->payment_reference,
            'purchased_at' => $ticket->purchased_at,
            'created_at' => $ticket->created_at,
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
            'description' => $event->description,
            'category' => $event->category,
            'custom_category' => $event->custom_category,
            'date' => $event->date,
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'venue_name' => $event->venue_name,
            'venue_address' => $event->venue_address,
            'banner_path' => $event->banner_path,
            'banner_url' => $event->banner_path ? Storage::disk('public')->url($event->banner_path) : null,
            'status' => $event->status,
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
            'description' => $tier->description,
            'price' => $tier->price,
        ];
    }
}
