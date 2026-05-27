<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'user'): User
    {
        return User::create([
            'name' => 'Jane Doe',
            'email' => $role.'-'.uniqid().'@konora.test',
            'phone' => '08012345678',
            'password' => Hash::make('old-password'),
            'role' => $role,
        ]);
    }

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = $this->makeUser('organizer');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Jane Doe')
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.phone', '08012345678')
            ->assertJsonPath('data.role', 'organizer');
    }

    public function test_guest_cannot_view_profile(): void
    {
        $this->getJson('/api/user/profile')->assertUnauthorized();
    }

    public function test_user_can_update_profile(): void
    {
        $user = $this->makeUser('user');
        $originalEmail = $user->email;

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/user/profile', [
                'name' => 'Updated Name',
                'phone' => '09087654321',
                'email' => 'hacker@konora.test',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.phone', '09087654321')
            ->assertJsonPath('data.email', $originalEmail);

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('09087654321', $user->phone);
        $this->assertSame($originalEmail, $user->email);
    }

    public function test_update_profile_validation_errors(): void
    {
        $user = $this->makeUser('user');

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/user/profile', [
                'name' => str_repeat('a', 101),
                'phone' => '123',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_user_can_change_password(): void
    {
        $user = $this->makeUser('user');

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/user/password', [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertOk()
            ->assertJson(['message' => 'Password updated successfully']);

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_change_password_rejects_incorrect_current_password(): void
    {
        $user = $this->makeUser('user');

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/user/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertUnprocessable()
            ->assertJson(['message' => 'Current password is incorrect']);
    }
}
