<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTierResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $capacity = (int) $this->capacity;
        $sold = (int) $this->sold_count;

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'custom_name' => $this->custom_name,
            'description' => $this->description,
            'price' => $this->price,
            'capacity' => $this->capacity,
            'sold_count' => $this->sold_count,
            'seats_remaining' => max(0, $capacity - $sold),
            'sales_start' => $this->sales_start,
            'sales_end' => $this->sales_end,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
