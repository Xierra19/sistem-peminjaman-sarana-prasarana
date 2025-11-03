<?php

// file: tests/Feature/Admin/SemesterUpdateTest.php

namespace Tests\Feature\Admin;

use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SemesterUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_edit_form_as_authenticated_user(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $semester = Semester::query()->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-05-01',
            'teaching_weeks_before_uts' => 7,
            'teaching_weeks_after_uts' => 7,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('admin.semester.edit'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Semester/Edit')
            ->where('semester.id', $semester->id)
        );
    }

    public function test_can_update_semester_with_valid_dates(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $semester = Semester::query()->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-05-01',
            'teaching_weeks_before_uts' => 7,
            'teaching_weeks_after_uts' => 7,
            'is_active' => false,
        ]);

        $payload = [
            'id' => $semester->id,
            'start_date' => '2025-01-08',
            'end_date' => '2025-05-20',
            'uts_start_date' => '2025-03-01',
            'uts_end_date' => '2025-03-10',
            'uas_start_date' => '2025-05-06',
            'uas_end_date' => '2025-05-16',
            'teaching_weeks_before_uts' => 8,
            'teaching_weeks_after_uts' => 6,
            'is_active' => true,
        ];

        $response = $this->actingAs($user)
            ->from(route('admin.semester.edit'))
            ->put(route('admin.semester.update'), $payload);

        $response->assertRedirect(route('admin.semester.edit'));
        $response->assertSessionHas('success');

        $semester->refresh();

        $this->assertSame('2025-01-08', $semester->start_date?->toDateString());
        $this->assertSame('2025-05-16', $semester->uas_end_date?->toDateString());
        $this->assertSame(8, $semester->teaching_weeks_before_uts);
        $this->assertSame(6, $semester->teaching_weeks_after_uts);
        $this->assertTrue($semester->is_active);
    }

    public function test_rejects_invalid_date_order_for_uts_and_uas(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $semester = Semester::query()->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-05-01',
            'teaching_weeks_before_uts' => 7,
            'teaching_weeks_after_uts' => 7,
            'is_active' => false,
        ]);

        $payload = [
            'id' => $semester->id,
            'start_date' => '2025-01-08',
            'end_date' => '2025-05-20',
            'uts_start_date' => '2025-03-10',
            'uts_end_date' => '2025-03-01',
            'uas_start_date' => '2025-05-16',
            'uas_end_date' => '2025-05-06',
            'teaching_weeks_before_uts' => 8,
            'teaching_weeks_after_uts' => 6,
        ];

        $response = $this->actingAs($user)
            ->from(route('admin.semester.edit'))
            ->put(route('admin.semester.update'), $payload);

        $response->assertRedirect(route('admin.semester.edit'));
        $response->assertSessionHasErrors(['uts_end_date', 'uas_end_date']);
    }

    public function test_marking_semester_active_deactivates_others(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $active = Semester::query()->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-05-01',
            'teaching_weeks_before_uts' => 7,
            'teaching_weeks_after_uts' => 7,
            'is_active' => true,
        ]);

        $other = Semester::query()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2025-12-01',
            'teaching_weeks_before_uts' => 7,
            'teaching_weeks_after_uts' => 7,
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)
            ->from(route('admin.semester.edit'))
            ->put(route('admin.semester.update'), [
                'id' => $other->id,
                'start_date' => '2025-08-01',
                'end_date' => '2025-12-01',
                'uts_start_date' => '2025-10-01',
                'uts_end_date' => '2025-10-08',
                'uas_start_date' => '2025-11-20',
                'uas_end_date' => '2025-11-28',
                'teaching_weeks_before_uts' => 7,
                'teaching_weeks_after_uts' => 7,
                'is_active' => true,
            ]);

        $response->assertRedirect(route('admin.semester.edit'));

        $active->refresh();
        $other->refresh();

        $this->assertFalse($active->is_active);
        $this->assertTrue($other->is_active);
    }
}

