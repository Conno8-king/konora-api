<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.paystack.mock', true);
        Config::set('services.paystack.secret', 'sk_test_unit');
        Config::set('services.paystack.callback_url', 'http://localhost:4200/checkout/callback');
    }

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
            'title' => 'Concert',
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

    public function test_initiate_requires_buyer_role(): void
    {
        $organizer = $this->makeUser('organizer');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event);

        $this->actingAs($organizer, 'sanctum')
            ->postJson('/api/payments/initiate', [
                'event_id' => $event->id,
                'tier_id' => $tier->id,
                'quantity' => 1,
                'attendee_name' => 'Ada Lovelace',
                'attendee_email' => 'ada@example.com',
                'attendee_phone' => '08012345678',
            ])
            ->assertStatus(403);
    }

    public function test_initiate_returns_authorization_url_and_creates_pending_payment(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event, ['price' => 7500]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/payments/initiate', [
                'event_id' => $event->id,
                'tier_id' => $tier->id,
                'quantity' => 2,
                'attendee_name' => 'Ada Lovelace',
                'attendee_email' => 'ada@example.com',
                'attendee_phone' => '08012345678',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['authorization_url', 'reference', 'payment']]);

        $reference = $response->json('data.reference');
        $this->assertStringStartsWith('KNR-', $reference);
        $this->assertStringContainsString('reference='.urlencode($reference), $response->json('data.authorization_url'));

        $payment = Payment::query()->where('paystack_reference', $reference)->firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->assertSame(15000.0, (float) $payment->amount);
        $this->assertSame($event->id, $payment->metadata['event_id']);
        $this->assertSame(2, $payment->metadata['quantity']);
    }

    public function test_initiate_rejects_when_not_enough_seats(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event, ['capacity' => 2, 'sold_count' => 2]);

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/payments/initiate', [
                'event_id' => $event->id,
                'tier_id' => $tier->id,
                'quantity' => 1,
                'attendee_name' => 'Ada',
                'attendee_email' => 'ada@example.com',
                'attendee_phone' => '08012345678',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Not enough seats available.');
    }

    public function test_initiate_rejects_when_tier_belongs_to_other_event(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $otherEvent = $this->makeEvent($organizer);
        $tier = $this->makeTier($otherEvent);

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/payments/initiate', [
                'event_id' => $event->id,
                'tier_id' => $tier->id,
                'quantity' => 1,
                'attendee_name' => 'Ada',
                'attendee_email' => 'ada@example.com',
                'attendee_phone' => '08012345678',
            ])
            ->assertStatus(422);
    }

    public function test_verify_issues_tickets_and_increments_sold_count(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event, ['price' => 5000]);

        $initiate = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/payments/initiate', [
                'event_id' => $event->id,
                'tier_id' => $tier->id,
                'quantity' => 3,
                'attendee_name' => 'Ada',
                'attendee_email' => 'ada@example.com',
                'attendee_phone' => '08012345678',
            ])->json();

        $reference = $initiate['data']['reference'];

        $verify = $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/payments/verify/'.$reference)
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'success')
            ->assertJsonCount(3, 'data.tickets')
            ->json();

        $this->assertSame(3, Ticket::query()->where('payment_reference', $reference)->count());
        $this->assertSame(3, (int) $tier->fresh()->sold_count);
        foreach ($verify['data']['tickets'] as $t) {
            $this->assertNotEmpty($t['qr_code']);
            $this->assertSame($reference, $t['payment_reference']);
            $this->assertSame('active', $t['status']);
        }
    }

    public function test_verify_is_idempotent(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event);

        $reference = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/payments/initiate', [
                'event_id' => $event->id,
                'tier_id' => $tier->id,
                'quantity' => 2,
                'attendee_name' => 'Ada',
                'attendee_email' => 'ada@example.com',
                'attendee_phone' => '08012345678',
            ])->json('data.reference');

        $this->actingAs($buyer, 'sanctum')->getJson('/api/payments/verify/'.$reference)->assertOk();
        $this->actingAs($buyer, 'sanctum')->getJson('/api/payments/verify/'.$reference)->assertOk();

        $this->assertSame(2, Ticket::query()->where('payment_reference', $reference)->count());
        $this->assertSame(2, (int) $tier->fresh()->sold_count);
    }

    public function test_verify_404_for_other_users_reference(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $stranger = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event);

        $reference = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/payments/initiate', [
                'event_id' => $event->id,
                'tier_id' => $tier->id,
                'quantity' => 1,
                'attendee_name' => 'Ada',
                'attendee_email' => 'ada@example.com',
                'attendee_phone' => '08012345678',
            ])->json('data.reference');

        $this->actingAs($stranger, 'sanctum')
            ->getJson('/api/payments/verify/'.$reference)
            ->assertStatus(404);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $this->postJson('/api/payments/webhook', ['event' => 'charge.success'], [
            'X-Paystack-Signature' => 'invalid',
        ])->assertStatus(401);
    }

    public function test_webhook_issues_tickets_on_charge_success(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event);

        $reference = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/payments/initiate', [
                'event_id' => $event->id,
                'tier_id' => $tier->id,
                'quantity' => 2,
                'attendee_name' => 'Ada',
                'attendee_email' => 'ada@example.com',
                'attendee_phone' => '08012345678',
            ])->json('data.reference');

        $payload = json_encode(['event' => 'charge.success', 'data' => ['reference' => $reference]]);
        $signature = hash_hmac('sha512', $payload, 'sk_test_unit');

        $this->call(
            'POST',
            '/api/payments/webhook',
            [],
            [],
            [],
            ['HTTP_X-Paystack-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload,
        )->assertOk()->assertJson(['status' => 'ok']);

        $this->assertSame(2, Ticket::query()->where('payment_reference', $reference)->count());
        $this->assertSame('success', Payment::query()->where('paystack_reference', $reference)->value('status'));
    }

    public function test_my_tickets_returns_only_callers_tickets(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $other = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event);

        Ticket::create([
            'user_id' => $buyer->id,
            'event_id' => $event->id,
            'tier_id' => $tier->id,
            'qr_code' => 'qr-buyer',
            'status' => 'active',
            'purchased_at' => Carbon::now(),
        ]);
        Ticket::create([
            'user_id' => $other->id,
            'event_id' => $event->id,
            'tier_id' => $tier->id,
            'qr_code' => 'qr-other',
            'status' => 'active',
            'purchased_at' => Carbon::now(),
        ]);

        $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/tickets/my-tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.qr_code', 'qr-buyer')
            ->assertJsonPath('data.0.event.title', $event->title);
    }

    public function test_ticket_show_403_when_not_owner(): void
    {
        $organizer = $this->makeUser('organizer');
        $owner = $this->makeUser('user');
        $other = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event);

        $ticket = Ticket::create([
            'user_id' => $owner->id,
            'event_id' => $event->id,
            'tier_id' => $tier->id,
            'qr_code' => 'qr-owner',
            'status' => 'active',
            'purchased_at' => Carbon::now(),
        ]);

        $this->actingAs($other, 'sanctum')
            ->getJson('/api/tickets/'.$ticket->id)
            ->assertStatus(403);
    }

    public function test_ticket_show_returns_detail_for_owner(): void
    {
        $organizer = $this->makeUser('organizer');
        $owner = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event);

        $ticket = Ticket::create([
            'user_id' => $owner->id,
            'event_id' => $event->id,
            'tier_id' => $tier->id,
            'qr_code' => 'qr-detail',
            'status' => 'active',
            'purchased_at' => Carbon::now(),
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/tickets/'.$ticket->id)
            ->assertOk()
            ->assertJsonPath('data.qr_code', 'qr-detail')
            ->assertJsonPath('data.event.id', $event->id)
            ->assertJsonPath('data.tier.id', $tier->id);
    }

    public function test_organizer_revenue_includes_metadata_only_payments(): void
    {
        $organizer = $this->makeUser('organizer');
        $buyer = $this->makeUser('user');
        $event = $this->makeEvent($organizer);
        $tier = $this->makeTier($event, ['price' => 5000]);

        $reference = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/payments/initiate', [
                'event_id' => $event->id,
                'tier_id' => $tier->id,
                'quantity' => 2,
                'attendee_name' => 'Ada',
                'attendee_email' => 'ada@example.com',
                'attendee_phone' => '08012345678',
            ])->json('data.reference');

        $this->actingAs($buyer, 'sanctum')->getJson('/api/payments/verify/'.$reference)->assertOk();

        $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/organizer/stats')
            ->assertOk()
            ->assertJsonPath('data.total_revenue', 10000)
            ->assertJsonPath('data.total_tickets_sold', 2);

        $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/organizer/analytics/'.$event->id)
            ->assertOk()
            ->assertJsonPath('data.total_revenue', 10000);
    }
}
