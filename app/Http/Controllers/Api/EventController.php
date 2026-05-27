<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\IndexEventRequest;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\TicketTier;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class EventController extends Controller
{
    use ApiResponse;

    /**
     * @unauthenticated
     */
    public function index(IndexEventRequest $request)
    {
        $query = Event::query()
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->with([
                'ticketTiers',
                'user:id,name',
            ]);

        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $query->where('title', 'like', '%'.$term.'%');
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('date', [
                $request->date('date_from')->format('Y-m-d'),
                $request->date('date_to')->format('Y-m-d'),
            ]);
        } elseif ($request->filled('date_from')) {
            $query->where('date', '>=', $request->date('date_from')->format('Y-m-d'));
        } elseif ($request->filled('date_to')) {
            $query->where('date', '<=', $request->date('date_to')->format('Y-m-d'));
        }

        if ($request->filled('price_min') || $request->filled('price_max')) {
            $min = $request->has('price_min') ? (float) $request->input('price_min') : 0.0;
            $max = $request->has('price_max') ? (float) $request->input('price_max') : PHP_FLOAT_MAX;
            $query->whereHas('ticketTiers', function ($q) use ($min, $max): void {
                $q->whereBetween('price', [$min, $max]);
            });
        }

        $events = $query->latest('date')->paginate(12);

        return EventResource::collection($events)->additional([
            'success' => true,
            'message' => 'Events retrieved successfully.',
        ]);
    }

    /**
     * @unauthenticated
     */
    public function show(int $id)
    {
        $event = Event::query()
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->whereKey($id)
            ->with(['ticketTiers', 'user:id,name'])
            ->firstOrFail();

        return $this->successResponse(
            new EventResource($event),
            'Event retrieved successfully.'
        );
    }

    public function store(StoreEventRequest $request)
    {
        $validated = $request->validated();
        $path = $request->file('banner')->store('banners', 'public');

        $event = DB::transaction(function () use ($validated, $path, $request) {
            $category = $validated['category'];
            $event = Event::create([
                'user_id' => (int) $request->user()->id,
                'title' => $validated['title'],
                'description' => $validated['description'],
                'category' => $category === 'custom' ? 'custom' : $category,
                'custom_category' => $category === 'custom' ? $validated['custom_category'] : null,
                'date' => $validated['date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'venue_name' => $validated['venue_name'],
                'venue_address' => $validated['venue_address'],
                'banner_path' => $path,
                'visibility' => 'public',
                'status' => 'draft',
            ]);

            $rows = array_map(fn (array $t) => $this->makeTierAttributes($t, true), $validated['ticket_tiers']);
            $event->ticketTiers()->createMany($rows);

            return $event->load(['ticketTiers', 'user:id,name']);
        });

        return $this->successResponse(
            new EventResource($event),
            'Event created successfully.',
            Response::HTTP_CREATED
        );
    }

    public function organizerIndex()
    {
        $events = Event::query()
            ->where('user_id', auth()->id())
            ->with('ticketTiers')
            ->latest()
            ->paginate(12);

        return EventResource::collection($events)->additional([
            'success' => true,
            'message' => 'Your events retrieved successfully.',
        ]);
    }

    public function organizerShow(Event $event)
    {
        $this->authorize('update', $event);

        $event->load(['ticketTiers', 'user:id,name']);

        return $this->successResponse(
            new EventResource($event),
            'Event retrieved successfully.'
        );
    }

    public function update(UpdateEventRequest $request, Event $event)
    {
        $this->authorize('update', $event);

        $validated = $request->validated();

        if (! empty($validated['delete_tier_ids'])) {
            $toDelete = TicketTier::query()
                ->where('event_id', $event->id)
                ->whereIn('id', $validated['delete_tier_ids'])
                ->get();
            foreach ($toDelete as $t) {
                if ($t->sold_count > 0) {
                    return $this->errorResponse(
                        'Cannot remove a tier that has sold tickets.',
                        ['delete_tier_ids' => ['One or more tiers have sales.']],
                        Response::HTTP_CONFLICT
                    );
                }
            }
        }

        DB::transaction(function () use ($request, $event, $validated) {
            $updates = collect($validated)
                ->only([
                    'title', 'description', 'date', 'start_time', 'end_time',
                    'venue_name', 'venue_address', 'visibility',
                ])
                ->all();

            if (array_key_exists('category', $validated)) {
                $cat = $validated['category'];
                $updates['category'] = $cat === 'custom' ? 'custom' : $cat;
                $updates['custom_category'] = $cat === 'custom' ? ($validated['custom_category'] ?? null) : null;
            }

            if ($updates !== []) {
                $event->update($updates);
            }

            if ($request->hasFile('banner')) {
                if ($event->banner_path) {
                    Storage::disk('public')->delete($event->banner_path);
                }
                $event->update([
                    'banner_path' => $request->file('banner')->store('banners', 'public'),
                ]);
            }

            if (! empty($validated['delete_tier_ids'])) {
                TicketTier::query()
                    ->where('event_id', $event->id)
                    ->whereIn('id', $validated['delete_tier_ids'])
                    ->delete();
            }

            if (! empty($validated['ticket_tiers']) && is_array($validated['ticket_tiers'])) {
                foreach ($validated['ticket_tiers'] as $row) {
                    if (! empty($row['id'])) {
                        $tier = TicketTier::query()
                            ->where('event_id', $event->id)
                            ->whereKey($row['id'])
                            ->firstOrFail();
                        $tier->update($this->makeTierAttributes($row, false));
                    } else {
                        $event->ticketTiers()->create($this->makeTierAttributes($row, true));
                    }
                }
            }
        });

        $event->refresh()->load(['ticketTiers', 'user:id,name']);

        return $this->successResponse(
            new EventResource($event),
            'Event updated successfully.'
        );
    }

    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);

        if ($event->tickets()->where('status', 'active')->exists()) {
            return $this->errorResponse(
                'Cannot delete an event with active tickets.',
                ['event' => ['This event has active tickets.']],
                Response::HTTP_CONFLICT
            );
        }

        $event->delete();

        return $this->successResponse(null, 'Event deleted successfully.');
    }

    public function publish(Event $event)
    {
        $this->authorize('publish', $event);

        if (! $event->ticketTiers()->where('capacity', '>', 0)->exists()) {
            return $this->errorResponse(
                'At least one ticket tier with capacity greater than zero is required to publish.',
                ['status' => ['No tier with available capacity.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $event->update(['status' => 'published']);
        $event->load(['ticketTiers', 'user:id,name']);

        return $this->successResponse(
            new EventResource($event),
            'Event published successfully.'
        );
    }

    public function end(Event $event)
    {
        $this->authorize('end', $event);

        $event->update(['status' => 'ended']);
        $event->load(['ticketTiers', 'user:id,name']);

        return $this->successResponse(
            new EventResource($event),
            'Event ended successfully.'
        );
    }

    /**
     * @param  array<string, mixed>  $tier
     * @return array<string, mixed>
     */
    private function makeTierAttributes(array $tier, bool $forCreate): array
    {
        $name = $tier['name'];
        $attrs = [
            'name' => $name,
            'custom_name' => $name === 'custom' ? ($tier['custom_name'] ?? null) : null,
            'description' => $tier['description'] ?? null,
            'price' => $tier['price'],
            'capacity' => $tier['capacity'],
            'sales_start' => $tier['sales_start'] ?? null,
            'sales_end' => $tier['sales_end'] ?? null,
        ];

        if ($forCreate) {
            $attrs['sold_count'] = 0;
        }

        return $attrs;
    }
}
