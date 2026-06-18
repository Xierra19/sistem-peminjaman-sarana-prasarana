<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_user_is_redirected_to_verification_notice_after_registering(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Tester',
            'phone' => '081234567890',
            'email' => 'tester@student.esaunggul.ac.id',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $user = User::where('email', 'tester@student.esaunggul.ac.id')->first();

        $response->assertRedirect(route('verification.notice'));
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);

        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_inactive_account_email_cannot_be_registered_again(): void
    {
        User::factory()->create([
            'email' => 'blocked@student.esaunggul.ac.id',
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivation_reason' => 'Spam pengajuan.',
        ]);

        $response = $this->from('/register')->post('/register', [
            'name' => 'Blocked User',
            'phone' => '081234567890',
            'email' => 'blocked@student.esaunggul.ac.id',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response
            ->assertRedirect('/register')
            ->assertSessionHasErrors('email');

        $this->assertSame(
            1,
            User::query()->where('email', 'blocked@student.esaunggul.ac.id')->count()
        );
        $this->assertGuest();
    }
}
