<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\TicketTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrganizerEventShowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'organizer', string $name = 'Owner Org'): User
    {
        return User::create([
            'name' => $name,
            'email' => $name.'-'.uniqid().'@konora.test',
            'phone' => '08000000000',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }

    private function makeEvent(User $organizer, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'user_id' => $organizer->id,
            'title' => 'Draft Concert',
            'description' => 'Private description.',
            'category' => 'music',
            'date' => Carbon::today()->addDays(14)->toDateString(),
            'start_time' => '19:00:00',
            'end_time' => '23:00:00',
            'venue_name' => 'Studio One',
            'venue_address' => '12 Demo Street',
            'visibility' => 'public',
            'status' => 'draft',
        ], $overrides));
    }

    public function test_returns_event_with_tiers_for_owner_even_when_draft(): void
    {
        $owner = $this->makeUser('organizer', 'Owner Org');
        $event = $this->makeEvent($owner);
        TicketTier::create([
            'event_id' => $event->id,
            'name' => 'VIP',
            'price' => 25000,
            'capacity' => 50,
            'sold_count' => 0,
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/organizer/events/{$event->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonCount(1, 'data.ticket_tiers')
            ->assertJsonPath('data.ticket_tiers.0.name', 'VIP');
    }

    public function test_returns_403_for_event_owned_by_another_organizer(): void
    {
        $owner = $this->makeUser('organizer', 'Owner Org');
        $other = $this->makeUser('organizer', 'Other Org');
        $event = $this->makeEvent($owner);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/organizer/events/{$event->id}")
            ->assertStatus(403);
    }

    public function test_returns_403_for_non_organizer_user(): void
    {
        $owner = $this->makeUser('organizer', 'Owner Org');
        $regular = $this->makeUser('user', 'Regular Buyer');
        $event = $this->makeEvent($owner);

        $this->actingAs($regular, 'sanctum')
            ->getJson("/api/organizer/events/{$event->id}")
            ->assertStatus(403);
    }

    public function test_returns_401_when_unauthenticated(): void
    {
        $owner = $this->makeUser('organizer', 'Owner Org');
        $event = $this->makeEvent($owner);

        $this->getJson("/api/organizer/events/{$event->id}")
            ->assertStatus(401);
    }

    public function test_returns_404_when_event_missing(): void
    {
        $owner = $this->makeUser('organizer', 'Owner Org');

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/organizer/events/999999')
            ->assertStatus(404);
    }
}
