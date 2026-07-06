<?php

namespace Tests\Feature;

use App\Models\StudentAssessment;
use App\Models\Subject;
use App\Models\User;
use App\Services\AssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssessmentServiceSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_flags_earlier_term_subject_with_no_prior_billing_record(): void
    {
        $student = User::factory()->student()->create();

        $earlierSubject = Subject::create([
            'code'       => 'CS101',
            'name'       => 'Intro to CS',
            'units'      => 3,
            'lec_units'  => 3,
            'lab_units'  => 0,
            'is_nstp'    => false,
            'year_level' => '1st Year',
            'semester'   => '1st Sem',
            'course'     => $student->course,
            'is_active'  => true,
        ]);

        $flagged = AssessmentService::detectOutOfSequenceSubjects(
            userId:               $student->id,
            assessmentYearLevel:  '2nd Year',
            assessmentSemesterDb: '2nd Sem',
            selectedSubjects:     [[
                'id'         => $earlierSubject->id,
                'code'       => $earlierSubject->code,
                'name'       => $earlierSubject->name,
                'year_level' => $earlierSubject->year_level,
                'semester'   => $earlierSubject->semester,
            ]],
        );

        $this->assertCount(1, $flagged);
        $this->assertSame($earlierSubject->id, $flagged[0]['id']);
        $this->assertSame('1st Year — 1st Sem', $flagged[0]['expected_term']);
    }

    public function test_does_not_flag_earlier_term_subject_when_prior_billing_record_exists(): void
    {
        $student = User::factory()->student()->create();

        $earlierSubject = Subject::create([
            'code'       => 'CS101',
            'name'       => 'Intro to CS',
            'units'      => 3,
            'lec_units'  => 3,
            'lab_units'  => 0,
            'is_nstp'    => false,
            'year_level' => '1st Year',
            'semester'   => '1st Sem',
            'course'     => $student->course,
            'is_active'  => true,
        ]);

        // A real prior assessment that actually billed this subject.
        $priorAssessment = StudentAssessment::create([
            'assessment_number' => 'ASMT-2025-0001',
            'user_id'           => $student->id,
            'course'            => $student->course,
            'year_level'        => '1st Year',
            'semester'          => '1st Sem',
            'school_year'       => '2025-2026',
            'lec_units'         => 3,
            'lab_units'         => 0,
            'total_assessment'  => 1000,
            'status'            => 'active',
        ]);

        DB::table('assessment_subjects')->insert([
            'student_assessment_id' => $priorAssessment->id,
            'subject_id'            => $earlierSubject->id,
            'code'                  => $earlierSubject->code,
            'name'                  => $earlierSubject->name,
            'lec_units'             => 3,
            'lab_units'             => 0,
            'is_nstp'               => false,
            'is_pathfit'            => false,
            'is_billable'           => true,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        $flagged = AssessmentService::detectOutOfSequenceSubjects(
            userId:               $student->id,
            assessmentYearLevel:  '2nd Year',
            assessmentSemesterDb: '2nd Sem',
            selectedSubjects:     [[
                'id'         => $earlierSubject->id,
                'code'       => $earlierSubject->code,
                'name'       => $earlierSubject->name,
                'year_level' => $earlierSubject->year_level,
                'semester'   => $earlierSubject->semester,
            ]],
        );

        $this->assertCount(0, $flagged);
    }

    public function test_does_not_flag_subject_in_same_or_later_term(): void
    {
        $student = User::factory()->student()->create();

        $sameTermSubject = Subject::create([
            'code'       => 'CS201',
            'name'       => 'Data Structures',
            'units'      => 3,
            'lec_units'  => 3,
            'lab_units'  => 0,
            'is_nstp'    => false,
            'year_level' => '2nd Year',
            'semester'   => '2nd Sem',
            'course'     => $student->course,
            'is_active'  => true,
        ]);

        $flagged = AssessmentService::detectOutOfSequenceSubjects(
            userId:               $student->id,
            assessmentYearLevel:  '2nd Year',
            assessmentSemesterDb: '2nd Sem',
            selectedSubjects:     [[
                'id'         => $sameTermSubject->id,
                'code'       => $sameTermSubject->code,
                'name'       => $sameTermSubject->name,
                'year_level' => $sameTermSubject->year_level,
                'semester'   => $sameTermSubject->semester,
            ]],
        );

        $this->assertCount(0, $flagged);
    }
}