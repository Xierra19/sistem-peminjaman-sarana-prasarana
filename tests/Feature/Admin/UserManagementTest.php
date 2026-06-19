<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_student_with_nim(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'nim' => null,
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.users.store'), [
                'name' => 'Mahasiswa Baru',
                'nim' => '20220801005',
                'email' => 'mahasiswa@student.esaunggul.ac.id',
                'phone' => '081234567890',
                'role' => User::ROLE_USER,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'nim' => '20220801005',
            'email' => 'mahasiswa@student.esaunggul.ac.id',
            'role' => User::ROLE_USER,
        ]);
    }

    public function test_super_admin_can_create_admin_without_nim(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'nim' => null,
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.users.store'), [
                'name' => 'Admin BAP',
                'email' => 'admin.bap@example.com',
                'phone' => '081234567890',
                'role' => User::ROLE_ADMIN_BAP,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $admin = User::query()->where('email', 'admin.bap@example.com')->firstOrFail();

        $this->assertNull($admin->nim);
    }

    public function test_super_admin_can_update_student_nim(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'nim' => null,
        ]);
        $student = User::factory()->create([
            'role' => User::ROLE_USER,
            'nim' => '20220801005',
        ]);

        $this->actingAs($superAdmin)
            ->put(route('admin.users.update', $student), [
                'name' => $student->name,
                'nim' => '20220802005',
                'email' => $student->email,
                'phone' => $student->phone,
                'role' => User::ROLE_USER,
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame('20220802005', $student->fresh()->nim);
    }

    public function test_student_nim_is_required_and_unique_in_user_management(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'nim' => null,
        ]);
        $existingStudent = User::factory()->create([
            'role' => User::ROLE_USER,
            'nim' => '20220801005',
        ]);
        $student = User::factory()->create([
            'role' => User::ROLE_USER,
            'nim' => '20220802005',
        ]);

        $payload = [
            'name' => $student->name,
            'email' => $student->email,
            'phone' => $student->phone,
            'role' => User::ROLE_USER,
            'password' => '',
            'password_confirmation' => '',
        ];

        $this->actingAs($superAdmin)
            ->from(route('admin.users.edit', $student))
            ->put(route('admin.users.update', $student), $payload)
            ->assertRedirect(route('admin.users.edit', $student))
            ->assertSessionHasErrors('nim');

        $this->actingAs($superAdmin)
            ->from(route('admin.users.edit', $student))
            ->put(route('admin.users.update', $student), [
                ...$payload,
                'nim' => $existingStudent->nim,
            ])
            ->assertRedirect(route('admin.users.edit', $student))
            ->assertSessionHasErrors('nim');
    }

    public function test_admin_role_does_not_require_nim_and_clears_existing_nim(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'nim' => null,
        ]);
        $user = User::factory()->create([
            'role' => User::ROLE_USER,
            'nim' => '20220801005',
        ]);

        $this->actingAs($superAdmin)
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'nim' => $user->nim,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => User::ROLE_ADMIN_BAP,
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();

        $this->assertSame(User::ROLE_ADMIN_BAP, $user->role);
        $this->assertNull($user->nim);
    }
}
