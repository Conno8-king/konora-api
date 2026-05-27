<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MyTicketResource extends JsonResource
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
            'ticket' => [
                'id' => $ticket->id,
                'user_id' => $ticket->user_id,
                'event_id' => $ticket->event_id,
                'tier_id' => $ticket->tier_id,
                'qr_code' => $ticket->qr_code,
                'payment_reference' => $ticket->payment_reference,
                'status' => $ticket->status,
                'purchased_at' => $ticket->purchased_at,
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->updated_at,
            ],
            'event_title' => $event?->title,
            'venue' => $event?->venue_name,
            'image' => $this->bannerUrl($event),
            'date_line' => $this->formatDateLine($event),
            'tier_label' => $this->tierLabel($tier),
            'price_line' => $this->formatPriceLine($tier),
            'display_status' => self::displayStatus($ticket, $event),
        ];
    }

    public static function displayStatus(?Ticket $ticket, ?Event $event): string
    {
        if (! $ticket) {
            return 'Upcoming';
        }

        if ($ticket->status === 'cancelled') {
            return 'Cancelled';
        }

        if ($ticket->status === 'used') {
            return 'Past';
        }

        if ($ticket->status === 'active' && $event && $event->attendeeViewHasEnded()) {
            return 'Past';
        }

        return 'Upcoming';
    }

    private function bannerUrl(?Event $event): ?string
    {
        if (! $event?->banner_path) {
            return null;
        }

        return Storage::disk('public')->url($event->banner_path);
    }

    private function formatDateLine(?Event $event): ?string
    {
        if (! $event) {
            return null;
        }

        $start = $event->calendarStartsAt();

        return $start?->format('M j, Y • g:i A');
    }

    private function tierLabel(?TicketTier $tier): ?string
    {
        if (! $tier) {
            return null;
        }

        if ($tier->name === 'custom' && $tier->custom_name) {
            return $tier->custom_name;
        }

        return $tier->name;
    }

    private function formatPriceLine(?TicketTier $tier): ?string
    {
        if (! $tier) {
            return null;
        }

        $amount = (float) $tier->price;
        if ($amount <= 0.01) {
            return 'Free';
        }

        return '₦'.number_format($amount, 0, '.', ',');
    }
}
