<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
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
            'nim' => '20220801005',
            'phone' => '081234567890',
            'email' => 'tester@student.esaunggul.ac.id',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $user = User::where('email', 'tester@student.esaunggul.ac.id')->first();

        $response->assertRedirect(route('verification.notice'));
        $this->assertNotNull($user);
        $this->assertSame('20220801005', $user->nim);
        $this->assertNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);

        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user): bool {
            $mail = $notification->toMail($user);
            $rendered = $mail->render();

            $this->assertSame('Verifikasi Alamat Email Anda', $mail->subject);
            $this->assertSame('Verifikasi Email', $mail->actionText);
            $this->assertStringContainsString('Klik tombol berikut untuk memverifikasi alamat email', $rendered);
            $this->assertStringContainsString('Jika tombol "Verifikasi Email" tidak berfungsi', $rendered);
            $this->assertStringContainsString('Seluruh hak cipta dilindungi.', $rendered);
            $this->assertStringNotContainsString('Verify Email Address', $rendered);
            $this->assertStringNotContainsString("If you're having trouble", $rendered);

            return true;
        });
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
            'nim' => '20220801006',
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

    public function test_nim_is_required_and_must_contain_exactly_eleven_digits(): void
    {
        $payload = [
            'name' => 'Tester',
            'phone' => '081234567890',
            'email' => 'tester@student.esaunggul.ac.id',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $this->from('/register')
            ->post('/register', $payload)
            ->assertRedirect('/register')
            ->assertSessionHasErrors('nim');

        $this->from('/register')
            ->post('/register', [...$payload, 'nim' => '2022080100A'])
            ->assertRedirect('/register')
            ->assertSessionHasErrors('nim');
    }

    public function test_nim_must_be_unique(): void
    {
        User::factory()->create(['nim' => '20220801005']);

        $this->from('/register')
            ->post('/register', [
                'name' => 'Tester',
                'nim' => '20220801005',
                'phone' => '081234567890',
                'email' => 'tester@student.esaunggul.ac.id',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertRedirect('/register')
            ->assertSessionHasErrors('nim');

        $this->assertGuest();
    }

    #[DataProvider('invalidPasswordProvider')]
    public function test_password_must_contain_uppercase_lowercase_and_number(string $password): void
    {
        $this->from('/register')
            ->post('/register', [
                'name' => 'Tester',
                'nim' => '20220801005',
                'phone' => '081234567890',
                'email' => 'tester@student.esaunggul.ac.id',
                'password' => $password,
                'password_confirmation' => $password,
            ])
            ->assertRedirect('/register')
            ->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public static function invalidPasswordProvider(): array
    {
        return [
            'too short' => ['Pass1'],
            'without uppercase letter' => ['password123'],
            'without lowercase letter' => ['PASSWORD123'],
            'without number' => ['PasswordOnly'],
        ];
    }
}
