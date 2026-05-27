<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketTier\StoreTicketTierRequest;
use App\Http\Requests\TicketTier\UpdateTicketTierRequest;
use App\Http\Resources\TicketTierResource;
use App\Models\Event;
use App\Models\TicketTier;
use App\Support\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class TicketTierController extends Controller
{
    use ApiResponse;

    public function store(StoreTicketTierRequest $request, Event $event)
    {
        $this->authorize('update', $event);

        $data = $request->validated();
        $tier = $event->ticketTiers()->create($this->storeTierAttributes($data));

        return $this->successResponse(
            new TicketTierResource($tier),
            'Ticket tier created successfully.',
            Response::HTTP_CREATED
        );
    }

    public function update(UpdateTicketTierRequest $request, TicketTier $tier)
    {
        $this->authorize('update', $tier->event);

        $validated = $request->validated();

        if (isset($validated['name']) && $validated['name'] !== 'custom') {
            $validated['custom_name'] = null;
        }

        $tier->update($validated);

        return $this->successResponse(
            new TicketTierResource($tier->fresh()),
            'Ticket tier updated successfully.'
        );
    }

    public function destroy(TicketTier $tier)
    {
        $this->authorize('update', $tier->event);

        if ($tier->sold_count > 0) {
            return $this->errorResponse(
                'Cannot delete a tier that has sold tickets.',
                ['tier' => ['Tier has sales.']],
                Response::HTTP_CONFLICT
            );
        }

        $tier->delete();

        return $this->successResponse(null, 'Ticket tier deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function storeTierAttributes(array $data): array
    {
        $name = $data['name'];

        return [
            'name' => $name,
            'custom_name' => $name === 'custom' ? ($data['custom_name'] ?? null) : null,
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'capacity' => $data['capacity'],
            'sold_count' => 0,
            'sales_start' => $data['sales_start'] ?? null,
            'sales_end' => $data['sales_end'] ?? null,
        ];
    }
}
