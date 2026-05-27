<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    /**
     * Sample tickets for user@konora.test (requires EventSeeder).
     *
     * @return list<array{event_title: string, tier_name: string, qr_code: string, status: string, payment_reference: ?string}>
     */
    private static function definitions(): array
    {
        return [
            [
                'event_title' => 'Lagos Jazz & Soul Festival',
                'tier_name' => 'General',
                'qr_code' => 'seed-qr-user-lagos-jazz',
                'status' => 'active',
                'payment_reference' => 'PAY-SEED-501',
            ],
            [
                'event_title' => 'AI in Africa Summit',
                'tier_name' => 'Early Bird',
                'qr_code' => 'seed-qr-user-ai-summit',
                'status' => 'active',
                'payment_reference' => 'PAY-SEED-502',
            ],
            [
                'event_title' => 'Laugh Out Lagos: December Special',
                'tier_name' => 'General',
                'qr_code' => 'seed-qr-user-laugh-lagos',
                'status' => 'used',
                'payment_reference' => 'PAY-SEED-503',
            ],
            [
                'event_title' => 'Product Design Lagos Meetup',
                'tier_name' => 'General',
                'qr_code' => 'seed-qr-user-pd-meetup',
                'status' => 'cancelled',
                'payment_reference' => null,
            ],
        ];
    }

    public function run(): void
    {
        $user = User::query()->where('email', 'user@konora.test')->first();
        if (! $user) {
            $this->command?->warn('user@konora.test not found; skipping TicketSeeder.');

            return;
        }

        foreach (self::definitions() as $row) {
            $event = Event::query()->where('title', $row['event_title'])->first();
            if (! $event) {
                $this->command?->warn("Event not found: {$row['event_title']}; skipping ticket {$row['qr_code']}.");

                continue;
            }

            $tier = $event->ticketTiers()->where('name', $row['tier_name'])->first();
            if (! $tier) {
                $this->command?->warn("Tier {$row['tier_name']} not found for event {$row['event_title']}; skipping.");

                continue;
            }

            Ticket::query()->updateOrCreate(
                ['qr_code' => $row['qr_code']],
                [
                    'user_id' => $user->id,
                    'event_id' => $event->id,
                    'tier_id' => $tier->id,
                    'payment_reference' => $row['payment_reference'],
                    'status' => $row['status'],
                    'purchased_at' => now()->subDays(30),
                ]
            );
        }
    }
}
