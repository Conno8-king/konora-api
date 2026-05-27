<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class OrganizerSeeder extends Seeder
{
    /**
     * Demo organizers for seeded events (password: `password` for all).
     *
     * @return list<array{name: string, email: string, phone: string}>
     */
    public static function organizerDefinitions(): array
    {
        return [
            ['name' => 'Konora Organizer', 'email' => 'organizer@konora.test', 'phone' => '08000000001'],
            ['name' => 'Lagos Live Events', 'email' => 'lagos.live@konora.test', 'phone' => '08010000002'],
            ['name' => 'Abuja Collective', 'email' => 'abuja.collective@konora.test', 'phone' => '08020000003'],
            ['name' => 'Tech & Culture NG', 'email' => 'tech.culture@konora.test', 'phone' => '08030000004'],
        ];
    }

    public function run(): void
    {
        foreach (self::organizerDefinitions() as $row) {
            User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'phone' => $row['phone'],
                    'password' => 'password',
                    'role' => 'organizer',
                ]
            );
        }
    }
}
