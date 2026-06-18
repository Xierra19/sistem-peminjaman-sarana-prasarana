<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\UserAccountStatusLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserAccountActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_deactivate_a_student_account_and_clear_database_sessions(): void
    {
        config(['session.driver' => 'database']);

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $student = User::factory()->create(['role' => User::ROLE_USER]);

        DB::table('sessions')->insert([
            'id' => 'student-session',
            'user_id' => $student->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $response = $this
            ->actingAs($admin)
            ->patch(route('admin.users.deactivate', $student), [
                'reason' => 'Spam pengajuan berulang.',
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('success', 'Akun mahasiswa berhasil dinonaktifkan.');

        $student->refresh();

        $this->assertFalse($student->isActive());
        $this->assertSame('Spam pengajuan berulang.', $student->deactivation_reason);
        $this->assertSame($admin->id, $student->deactivated_by);
        $this->assertNotNull($student->deactivated_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'student-session']);
        $this->assertDatabaseHas('user_account_status_logs', [
            'user_id' => $student->id,
            'actor_id' => $admin->id,
            'action' => UserAccountStatusLog::ACTION_DEACTIVATED,
            'reason' => 'Spam pengajuan berulang.',
        ]);
    }

    public function test_deactivation_requires_a_reason(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $student = User::factory()->create(['role' => User::ROLE_USER]);

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.deactivate', $student), ['reason' => '']);

        $response
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors('reason');

        $this->assertTrue($student->fresh()->isActive());
    }

    public function test_super_admin_can_reactivate_a_student_account(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $student = User::factory()->create([
            'role' => User::ROLE_USER,
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivation_reason' => 'Spam pengajuan.',
            'deactivated_by' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->patch(route('admin.users.activate', $student));

        $response
            ->assertRedirect()
            ->assertSessionHas('success', 'Akun mahasiswa berhasil diaktifkan kembali.');

        $student->refresh();

        $this->assertTrue($student->isActive());
        $this->assertNull($student->deactivated_at);
        $this->assertNull($student->deactivation_reason);
        $this->assertNull($student->deactivated_by);
        $this->assertDatabaseHas('user_account_status_logs', [
            'user_id' => $student->id,
            'actor_id' => $admin->id,
            'action' => UserAccountStatusLog::ACTION_ACTIVATED,
        ]);
    }

    public function test_non_super_admin_cannot_change_account_activation(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_BAP]);
        $student = User::factory()->create(['role' => User::ROLE_USER]);

        $response = $this
            ->actingAs($admin)
            ->patch(route('admin.users.deactivate', $student), [
                'reason' => 'Tidak berwenang.',
            ]);

        $response->assertForbidden();
        $this->assertTrue($student->fresh()->isActive());
    }

    public function test_admin_accounts_cannot_be_deactivated_through_student_activation_action(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $otherAdmin = User::factory()->create(['role' => User::ROLE_ADMIN_BAP]);

        $response = $this
            ->actingAs($superAdmin)
            ->patch(route('admin.users.deactivate', $otherAdmin), [
                'reason' => 'Tidak berlaku untuk admin.',
            ]);

        $response->assertSessionHas('error', 'Hanya akun mahasiswa yang dapat dinonaktifkan.');
        $this->assertTrue($otherAdmin->fresh()->isActive());
    }
}
