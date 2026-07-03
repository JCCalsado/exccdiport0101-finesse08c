<?php

namespace Tests\Feature;

use App\Models\StudentAssessment;
use App\Models\Subject;
use App\Services\AssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentServiceSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_detect_out_of_sequence_subjects_flags_earlier_terms_without_prior_billing(): void
    {
        $student = \App\Models\User::factory()->student()->create();

        $earlierSubject = Subject::create([
            'code' => 'CS101',
            'name' => 'Intro to CS',
            'units' => 3,
            'lec_units' => 3,
            'lab_units' => 0,
            'is_nstp' => false,
            'year_level' => '1st Year',
            'semester' => '1st Sem',
            'course' => $student->course,
            'is_active' => true,
        ]);

        $assessment = StudentAssessment::create([
            'assessment_number' => 'ASMT-2026-0001',
            'user_id' => $student->id,
            'course' => $student->course,
            'year_level' => '2nd Year',
            'semester' => '2nd Sem',
            'school_year' => '2026-2027',
            'lec_units' => 3,
            'lab_units' => 0,
            'discount_percentage' => 0,
            'total_assessment' => 1000,
            'status' => 'active',
        ]);

        $flagged = AssessmentService::detectOutOfSequenceSubjects(
            userId: $student->id,
            assessmentYearLevel: '2nd Year',
            assessmentSemesterDb: '2nd Sem',
            selectedSubjects: [[
                'id' => $earlierSubject->id,
                'code' => $earlierSubject->code,
                'name' => $earlierSubject->name,
                'year_level' => $earlierSubject->year_level,
                'semester' => $earlierSubject->semester,
            ]],
            excludeAssessmentId: $assessment->id,
        );

        $this->assertCount(1, $flagged);
        $this->assertSame($earlierSubject->id, $flagged[0]['id']);
        $this->assertSame('1st Year — 1st Sem', $flagged[0]['expected_term']);
    }
}
