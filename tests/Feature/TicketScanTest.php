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

class TicketScanTest extends TestCase
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

    private function makeTier(Event $event): TicketTier
    {
        return TicketTier::create([
            'event_id' => $event->id,
            'name' => 'regular',
            'price' => 5000,
            'capacity' => 100,
            'sold_count' => 0,
        ]);
    }

    private function makeTicket(User $owner, Event $event, TicketTier $tier, string $qr, string $status = 'active'): Ticket
    {
        return Ticket::create([
            'user_id' => $owner->id,
            'event_id' => $event->id,
            'tier_id' => $tier->id,
            'qr_code' => $qr,
            'status' => $status,
            'purchased_at' => Carbon::now(),
        ]);
    }

    private function validateAs(User $user, string $code)
    {
        return $this->actingAs($user, 'sanctum')
            ->postJson('/api/tickets/validate', ['code' => $code]);
    }

    public function test_buyer_role_cannot_validate(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event);
        $ticket = $this->makeTicket($buyer, $event, $tier, 'qr-buyer');

        $this->validateAs($buyer, $ticket->qr_code)->assertStatus(403);
    }

    public function test_valid_first_check_in_marks_ticket_used_and_logs_row(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event);
        $ticket = $this->makeTicket($buyer, $event, $tier, 'qr-valid');

        $this->validateAs($organizer, $ticket->qr_code)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('used', $ticket->fresh()->status);
        $this->assertSame(1, TicketScan::where('ticket_id', $ticket->id)->count());
        $this->assertSame('valid', TicketScan::where('ticket_id', $ticket->id)->value('result'));
    }

    public function test_second_check_in_returns_already_used_and_logs_second_attempt(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event);
        $ticket = $this->makeTicket($buyer, $event, $tier, 'qr-twice');

        $this->validateAs($organizer, $ticket->qr_code)->assertOk();

        $this->validateAs($organizer, $ticket->qr_code)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Ticket already used');

        $this->assertSame(2, TicketScan::where('ticket_id', $ticket->id)->count());
        $this->assertSame(
            ['valid', 'already_used'],
            TicketScan::where('ticket_id', $ticket->id)->orderBy('id')->pluck('result')->all(),
        );
    }

    public function test_other_organizer_gets_forbidden_and_audit_row(): void
    {
        $organizerA = $this->makeUser('organizer');
        $organizerB = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizerA);
        $tier = $this->makeTier($event);
        $ticket = $this->makeTicket($buyer, $event, $tier, 'qr-wrong-event');

        $this->validateAs($organizerB, $ticket->qr_code)->assertForbidden();

        $this->assertSame('active', $ticket->fresh()->status);
        $this->assertSame(1, TicketScan::where('ticket_id', $ticket->id)->count());
        $this->assertSame('wrong_event', TicketScan::where('ticket_id', $ticket->id)->value('result'));
        $this->assertSame($organizerB->id, TicketScan::where('ticket_id', $ticket->id)->value('scanned_by_user_id'));
    }

    public function test_unknown_code_returns_not_found_and_logs_attempt(): void
    {
        $organizer = $this->makeUser('organizer');

        $this->validateAs($organizer, 'qr-does-not-exist')->assertNotFound();

        $this->assertSame(1, TicketScan::where('attempted_code', 'qr-does-not-exist')->count());
        $this->assertSame('not_found', TicketScan::where('attempted_code', 'qr-does-not-exist')->value('result'));
    }

    public function test_cancelled_ticket_returns_422_and_logs_cancelled(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event);
        $ticket = $this->makeTicket($buyer, $event, $tier, 'qr-cancelled', 'cancelled');

        $this->validateAs($organizer, $ticket->qr_code)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ticket is cancelled');

        $this->assertSame('cancelled', $ticket->fresh()->status);
        $this->assertSame('cancelled', TicketScan::where('ticket_id', $ticket->id)->value('result'));
    }

    public function test_check_in_works_via_payment_reference(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event);
        $ticket = Ticket::create([
            'user_id' => $buyer->id,
            'event_id' => $event->id,
            'tier_id' => $tier->id,
            'qr_code' => 'qr-by-ref',
            'payment_reference' => 'PAY-REF-AUDIT',
            'status' => 'active',
            'purchased_at' => Carbon::now(),
        ]);

        $this->validateAs($organizer, 'PAY-REF-AUDIT')->assertOk();

        $this->assertSame('used', $ticket->fresh()->status);
        $this->assertSame('PAY-REF-AUDIT', TicketScan::where('ticket_id', $ticket->id)->value('attempted_code'));
    }

    public function test_index_returns_only_calling_organizers_scans(): void
    {
        $organizerA = $this->makeUser('organizer');
        $organizerB = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $eventA = $this->makeEvent($organizerA);
        $eventB = $this->makeEvent($organizerB);
        $tierA = $this->makeTier($eventA);
        $tierB = $this->makeTier($eventB);
        $this->makeTicket($buyer, $eventA, $tierA, 'qr-a');
        $this->makeTicket($buyer, $eventB, $tierB, 'qr-b');

        $this->validateAs($organizerA, 'qr-a')->assertOk();
        $this->validateAs($organizerB, 'qr-b')->assertOk();

        $response = $this->actingAs($organizerA, 'sanctum')
            ->getJson('/api/organizer/scans')
            ->assertOk()
            ->json();

        $this->assertCount(1, $response['data']);
        $this->assertSame('qr-a', $response['data'][0]['ticket']['qr_code']);
    }

    public function test_index_supports_event_id_and_result_filters(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event1 = $this->makeEvent($organizer);
        $event2 = $this->makeEvent($organizer);
        $tier1 = $this->makeTier($event1);
        $tier2 = $this->makeTier($event2);
        $this->makeTicket($buyer, $event1, $tier1, 'qr-e1');
        $this->makeTicket($buyer, $event2, $tier2, 'qr-e2');

        $this->validateAs($organizer, 'qr-e1')->assertOk();
        $this->validateAs($organizer, 'qr-e1')->assertStatus(409);
        $this->validateAs($organizer, 'qr-e2')->assertOk();

        $byEvent = $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/organizer/scans?event_id='.$event1->id)
            ->assertOk()
            ->json('data');
        $this->assertCount(2, $byEvent);

        $byResult = $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/organizer/scans?result=already_used')
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $byResult);
        $this->assertSame('already_used', $byResult[0]['result']);
    }
}
