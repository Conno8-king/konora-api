<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'organizer@konora.test'],
            [
                'name' => 'Konora Organizer',
                'phone' => '08000000001',
                'password' => 'password',
                'role' => 'organizer',
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'user@konora.test'],
            [
                'name' => 'Konora User',
                'phone' => '08000000002',
                'password' => 'password',
                'role' => 'user',
            ]
        );

        $this->call([
            OrganizerSeeder::class,
            EventSeeder::class,
            TicketSeeder::class,
        ]);
    }
}
