<?php

namespace Tests\Feature\Accounting;

use App\Enums\UserRoleEnum;
use App\Models\CourseUnitPreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OtherChargeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_uses_database_backed_course_options_when_no_assessments_exist(): void
    {
        CourseUnitPreset::create([
            'course'            => 'BS New Course',
            'year_level'        => '1st Year',
            'semester'          => '1st Sem',
            'lec_units'         => 3,
            'lab_units'         => 0,
            'lab_subject_count' => 0,
            'is_active'         => true,
        ]);

        $admin = User::factory()->create([
            'role'              => UserRoleEnum::ADMIN,
            'department'        => 'Administrator',
            'is_active'         => true,
            'terms_accepted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('accounting.other-charges.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Accounting/OtherCharges/Create')
            ->where('courses', fn ($courses) => in_array('BS New Course', $courses, true))
        );
    }
}
