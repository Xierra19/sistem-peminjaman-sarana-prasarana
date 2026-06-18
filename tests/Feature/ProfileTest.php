<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'phone' => '081234567890',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('081234567890', $user->phone);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_email_address_cannot_be_updated_from_the_profile(): void
    {
        $user = User::factory()->create();
        $originalEmail = $user->email;
        $originalVerifiedAt = $user->email_verified_at;

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'changed@student.esaunggul.ac.id',
                'phone' => $user->phone,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame($originalEmail, $user->email);
        $this->assertTrue($originalVerifiedAt->equalTo($user->email_verified_at));
    }

    public function test_user_cannot_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response->assertMethodNotAllowed();
        $this->assertNotNull($user->fresh());
    }
}
