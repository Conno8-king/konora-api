<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'custom_category' => $this->custom_category,
            'date' => $this->date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'venue_name' => $this->venue_name,
            'venue_address' => $this->venue_address,
            'banner_path' => $this->banner_path,
            'banner_url' => $this->banner_path ? Storage::disk('public')->url($this->banner_path) : null,
            'visibility' => $this->visibility,
            'status' => $this->status,
            'organizer' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'ticket_tiers' => $this->when(
                $this->relationLoaded('ticketTiers'),
                fn () => TicketTierResource::collection($this->ticketTiers)
            ),
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
