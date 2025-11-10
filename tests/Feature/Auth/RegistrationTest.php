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

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
