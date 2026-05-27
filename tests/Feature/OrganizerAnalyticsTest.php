<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketTier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrganizerAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'organizer', string $name = 'Prosper Okonkwo'): User
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
            'title' => 'Sample Event',
            'description' => 'Demo description.',
            'category' => 'music',
            'date' => Carbon::today()->addDays(7)->toDateString(),
            'start_time' => '18:00:00',
            'end_time' => '22:00:00',
            'venue_name' => 'The Hall',
            'venue_address' => '1 Demo Lane',
            'visibility' => 'public',
            'status' => 'published',
        ], $overrides));
    }

    private function makeTier(Event $event, array $overrides = []): TicketTier
    {
        return TicketTier::create(array_merge([
            'event_id' => $event->id,
            'name' => 'regular',
            'price' => 5000,
            'capacity' => 100,
            'sold_count' => 0,
        ], $overrides));
    }

    public function test_stats_requires_organizer_role(): void
    {
        $regular = $this->makeUser('user');

        $this->actingAs($regular, 'sanctum')
            ->getJson('/api/organizer/stats')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'errors' => [],
            ]);
    }

    public function test_stats_returns_aggregates_and_recent_purchases(): void
    {
        $organizer = $this->makeUser('organizer', 'Prosper Okonkwo');
        $other = $this->makeUser('organizer', 'Other Org');
        $buyer = $this->makeUser('user', 'Ada Lovelace');

        $upcoming = $this->makeEvent($organizer, [
            'date' => Carbon::today()->addDays(3)->toDateString(),
            'status' => 'published',
        ]);
        $past = $this->makeEvent($organizer, [
            'date' => Carbon::today()->subDays(3)->toDateString(),
            'status' => 'ended',
        ]);
        $draft = $this->makeEvent($organizer, [
            'date' => Carbon::today()->addDays(5)->toDateString(),
            'status' => 'draft',
        ]);
        $foreign = $this->makeEvent($other);

        $tierA = $this->makeTier($upcoming, ['name' => 'vip', 'sold_count' => 10, 'price' => 5000]);
        $tierB = $this->makeTier($past, ['name' => 'regular', 'sold_count' => 4, 'price' => 2500]);
        $this->makeTier($foreign, ['sold_count' => 50, 'price' => 9999]);

        $ticket = Ticket::create([
            'user_id' => $buyer->id,
            'event_id' => $upcoming->id,
            'tier_id' => $tierA->id,
            'qr_code' => 'qr-'.uniqid(),
            'status' => 'active',
            'purchased_at' => Carbon::now()->subHours(2),
        ]);

        Payment::create([
            'user_id' => $buyer->id,
            'ticket_id' => $ticket->id,
            'amount' => 5000,
            'status' => 'success',
        ]);
        Payment::create([
            'user_id' => $buyer->id,
            'ticket_id' => $ticket->id,
            'amount' => 9999,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/organizer/stats')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_events', 3)
            ->assertJsonPath('data.total_tickets_sold', 14)
            ->assertJsonPath('data.total_revenue', 5000)
            ->assertJsonPath('data.upcoming_events', 1);

        $recent = $response->json('data.recent_purchases');
        $this->assertCount(1, $recent);
        $this->assertSame('Ada L.', $recent[0]['buyer_name']);
        $this->assertSame($upcoming->title, $recent[0]['event_title']);
        $this->assertSame('vip', $recent[0]['tier_name']);
        $this->assertNotEmpty($recent[0]['purchased_at']);
    }

    public function test_recent_purchases_uses_custom_tier_name_when_present(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user', 'Single');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event, [
            'name' => 'custom',
            'custom_name' => 'Backstage Pass',
        ]);

        Ticket::create([
            'user_id' => $buyer->id,
            'event_id' => $event->id,
            'tier_id' => $tier->id,
            'qr_code' => 'qr-'.uniqid(),
            'status' => 'active',
            'purchased_at' => Carbon::now()->subMinutes(10),
        ]);

        $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/organizer/stats')
            ->assertJsonPath('data.recent_purchases.0.tier_name', 'Backstage Pass')
            ->assertJsonPath('data.recent_purchases.0.buyer_name', 'Single');
    }

    public function test_analytics_returns_403_for_event_owned_by_someone_else(): void
    {
        $organizer = $this->makeUser('organizer');
        $other = $this->makeUser('organizer');
        $event = $this->makeEvent($other);

        $this->actingAs($organizer, 'sanctum')
            ->getJson("/api/organizer/analytics/{$event->id}")
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'errors' => [],
            ])
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_analytics_returns_404_for_unknown_event(): void
    {
        $organizer = $this->makeUser('organizer');

        $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/organizer/analytics/999999')
            ->assertStatus(404);
    }

    public function test_analytics_returns_full_payload_for_owner(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizer);

        $vip = $this->makeTier($event, [
            'name' => 'vip',
            'price' => 10000,
            'capacity' => 50,
            'sold_count' => 20,
        ]);
        $regular = $this->makeTier($event, [
            'name' => 'regular',
            'price' => 2500,
            'capacity' => 50,
            'sold_count' => 5,
        ]);

        $ticket1 = Ticket::create([
            'user_id' => $buyer->id,
            'event_id' => $event->id,
            'tier_id' => $vip->id,
            'qr_code' => 'qr-'.uniqid(),
            'status' => 'active',
            'purchased_at' => CarbonImmutable::today()->subDays(2),
        ]);
        $ticket2 = Ticket::create([
            'user_id' => $buyer->id,
            'event_id' => $event->id,
            'tier_id' => $regular->id,
            'qr_code' => 'qr-'.uniqid(),
            'status' => 'active',
            'purchased_at' => CarbonImmutable::today(),
        ]);

        Payment::create([
            'user_id' => $buyer->id,
            'ticket_id' => $ticket1->id,
            'amount' => 10000,
            'status' => 'success',
        ]);
        Payment::create([
            'user_id' => $buyer->id,
            'ticket_id' => $ticket2->id,
            'amount' => 2500,
            'status' => 'success',
        ]);
        Payment::create([
            'user_id' => $buyer->id,
            'ticket_id' => $ticket2->id,
            'amount' => 9999,
            'status' => 'failed',
        ]);

        $response = $this->actingAs($organizer, 'sanctum')
            ->getJson("/api/organizer/analytics/{$event->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_revenue', 12500)
            ->assertJsonPath('data.summary.total_sold', 25)
            ->assertJsonPath('data.summary.total_capacity', 100)
            ->assertJsonPath('data.summary.total_tiers', 2);

        $this->assertEqualsWithDelta(25.0, $response->json('data.attendance_rate'), 0.001);

        $tiers = $response->json('data.tickets_by_tier');
        $this->assertCount(2, $tiers);
        $this->assertSame($vip->id, $tiers[0]['tier_id']);
        $this->assertSame(20, $tiers[0]['sold']);
        $this->assertSame(50, $tiers[0]['capacity']);
        $this->assertEquals(10000.0, $tiers[0]['unit_price']);
        $this->assertEquals(200000.0, $tiers[0]['revenue']);

        $daily = $response->json('data.daily_sales');
        $this->assertCount(30, $daily);
        $this->assertSame(CarbonImmutable::today()->subDays(29)->toDateString(), $daily[0]['date']);
        $this->assertSame(CarbonImmutable::today()->toDateString(), $daily[29]['date']);
        $this->assertSame(1, $daily[29]['count']);
        $this->assertSame(1, $daily[27]['count']);
        $this->assertSame(0, $daily[0]['count']);
    }

    public function test_analytics_attendance_rate_is_zero_when_no_capacity(): void
    {
        $organizer = $this->makeUser('organizer');
        $event = $this->makeEvent($organizer);

        $this->actingAs($organizer, 'sanctum')
            ->getJson("/api/organizer/analytics/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.attendance_rate', 0)
            ->assertJsonPath('data.summary.total_capacity', 0)
            ->assertJsonPath('data.summary.total_sold', 0)
            ->assertJsonPath('data.summary.total_tiers', 0);
    }
}
