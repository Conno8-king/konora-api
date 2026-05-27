<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketScan;
use App\Models\TicketTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TicketValidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'user'): User
    {
        return User::create([
            'name' => 'Test '.$role,
            'email' => $role.'-'.uniqid().'@konora.test',
            'phone' => '08000000000',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }

    private function makeEvent(User $organizer): Event
    {
        return Event::create([
            'user_id' => $organizer->id,
            'title' => 'Concert '.uniqid(),
            'description' => 'Demo.',
            'category' => 'music',
            'date' => Carbon::today()->addDays(7)->toDateString(),
            'start_time' => '18:00:00',
            'end_time' => '22:00:00',
            'venue_name' => 'The Hall',
            'venue_address' => '1 Demo Lane',
            'visibility' => 'public',
            'status' => 'published',
        ]);
    }

    private function makeTier(Event $event, ?string $customName = null): TicketTier
    {
        return TicketTier::create([
            'event_id' => $event->id,
            'name' => $customName ? 'custom' : 'regular',
            'custom_name' => $customName,
            'price' => 5000,
            'capacity' => 100,
            'sold_count' => 0,
        ]);
    }

    private function makeTicket(
        User $owner,
        Event $event,
        TicketTier $tier,
        string $qr,
        ?string $paymentReference = null,
        string $status = 'active'
    ): Ticket {
        return Ticket::create([
            'user_id' => $owner->id,
            'event_id' => $event->id,
            'tier_id' => $tier->id,
            'qr_code' => $qr,
            'payment_reference' => $paymentReference,
            'status' => $status,
            'purchased_at' => Carbon::now(),
        ]);
    }

    public function test_buyer_cannot_validate_ticket(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event);
        $ticket = $this->makeTicket($buyer, $event, $tier, 'qr-buyer-validate');

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/tickets/validate', ['code' => $ticket->qr_code])
            ->assertStatus(403);
    }

    public function test_validates_active_ticket_by_qr_code(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event, 'VIP Lounge');
        $ticket = $this->makeTicket($buyer, $event, $tier, 'qr-valid-1', 'PAY-REF-001');

        $response = $this->actingAs($organizer, 'sanctum')
            ->postJson('/api/tickets/validate', ['code' => $ticket->qr_code])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Ticket validated successfully')
            ->assertJsonPath('data.attendee_name', $buyer->name)
            ->assertJsonPath('data.event_title', $event->title)
            ->assertJsonPath('data.tier_name', 'VIP Lounge')
            ->assertJsonPath('data.ticket_ref', 'PAY-REF-001');

        $this->assertSame('used', $ticket->fresh()->status);
        $this->assertSame('valid', TicketScan::where('ticket_id', $ticket->id)->value('result'));
    }

    public function test_validates_active_ticket_by_payment_reference(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event);
        $ticket = $this->makeTicket($buyer, $event, $tier, 'qr-valid-2', 'PAY-REF-002');

        $this->actingAs($organizer, 'sanctum')
            ->postJson('/api/tickets/validate', ['code' => 'PAY-REF-002'])
            ->assertOk();

        $this->assertSame('used', $ticket->fresh()->status);
    }

    public function test_returns_404_when_ticket_not_found(): void
    {
        $organizer = $this->makeUser('organizer');

        $this->actingAs($organizer, 'sanctum')
            ->postJson('/api/tickets/validate', ['code' => 'missing-code'])
            ->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Ticket not found',
            ]);

        $this->assertSame('not_found', TicketScan::where('attempted_code', 'missing-code')->value('result'));
    }

    public function test_returns_403_when_organizer_does_not_own_event(): void
    {
        $organizerA = $this->makeUser('organizer');
        $organizerB = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizerA);
        $tier = $this->makeTier($event);
        $ticket = $this->makeTicket($buyer, $event, $tier, 'qr-wrong-org');

        $this->actingAs($organizerB, 'sanctum')
            ->postJson('/api/tickets/validate', ['code' => $ticket->qr_code])
            ->assertForbidden();
    }

    public function test_returns_409_when_ticket_already_used(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event);
        $ticket = $this->makeTicket($buyer, $event, $tier, 'qr-used', status: 'used');
        $ticket->touch();

        $this->actingAs($organizer, 'sanctum')
            ->postJson('/api/tickets/validate', ['code' => $ticket->qr_code])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Ticket already used')
            ->assertJsonPath('used_at', $ticket->fresh()->updated_at->toJSON());
    }

    public function test_returns_422_when_ticket_cancelled(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event);
        $ticket = $this->makeTicket($buyer, $event, $tier, 'qr-cancelled', status: 'cancelled');

        $this->actingAs($organizer, 'sanctum')
            ->postJson('/api/tickets/validate', ['code' => $ticket->qr_code])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Ticket is cancelled',
            ]);
    }
}
