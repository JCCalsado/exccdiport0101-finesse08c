<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\CourseUnitPreset;
use App\Models\StudentAssessment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Authorization via CurriculumPresetPolicy (bound to CurriculumPreset::class).
 *   view  → viewAny / view → Registrar + Disbursing Officer + Admin
 *   write → create / update / delete → Registrar + Admin only
 */
class CurriculumPresetController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CurriculumPreset::class);

        $selectedCourse = $request->input('course');

        $courses = CourseUnitPreset::distinct()
            ->orderBy('course')
            ->pluck('course')
            ->values()
            ->toArray();

        $query = CourseUnitPreset::withCount('presetSubjects');

        if ($selectedCourse) {
            $query->where('course', $selectedCourse);
        }

        $presets = $query
            ->orderBy('course')
            ->orderByRaw("FIELD(year_level, '1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year')")
            ->orderByRaw("FIELD(semester, '1st Sem', '2nd Sem', 'Summer')")
            ->get()
            ->map(fn($p) => [
                'id'                => $p->id,
                'course'            => $p->course,
                'year_level'        => $p->year_level,
                'semester'          => $p->semester,
                'lec_units'         => (int) $p->lec_units,
                'lab_units'         => (int) $p->lab_units,
                'lab_subject_count' => (int) $p->lab_subject_count,
                'is_active'         => (bool) $p->is_active,
                'subject_count'     => (int) $p->preset_subjects_count,
                'assessment_count'  => $this->countLinkedAssessments($p),
            ])
            ->toArray();

        return Inertia::render('Accounting/CurriculumPreset/Index', [
            'courses'        => $courses,
            'selectedCourse' => $selectedCourse,
            'presets'        => $presets,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', CurriculumPreset::class);

        $validated = $request->validate([
            'course'     => ['required', 'string', 'max:150'],
            'year_level' => ['required', 'string', 'in:1st Year,2nd Year,3rd Year,4th Year,5th Year'],
            'semester'   => ['required', 'string', 'in:1st Sem,2nd Sem,Summer'],
        ]);

        $exists = CourseUnitPreset::where('course', $validated['course'])
            ->where('year_level', $validated['year_level'])
            ->where('semester', $validated['semester'])
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'preset' => "A preset for {$validated['course']} — {$validated['year_level']} — {$validated['semester']} already exists.",
            ]);
        }

        $preset = CourseUnitPreset::create([
            'course'            => $validated['course'],
            'year_level'        => $validated['year_level'],
            'semester'          => $validated['semester'],
            'lec_units'         => 0,
            'lab_units'         => 0,
            'lab_subject_count' => 0,
            'is_active'         => true,
        ]);

        return redirect()
            ->route('accounting.curriculum-presets.subjects.index', ['preset' => $preset->id, 'new' => 1])
            ->with('success', "Preset created. Now add subjects to populate it.");
    }

    public function update(Request $request, CourseUnitPreset $preset)
    {
        $this->authorize('update', CurriculumPreset::class);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $preset->update($validated);

        $status = $validated['is_active'] ? 'activated' : 'deactivated';
        return back()->with('success', "{$preset->course} {$preset->year_level} {$preset->semester} {$status}.");
    }

    public function destroy(CourseUnitPreset $preset)
    {
        $this->authorize('delete', CurriculumPreset::class);

        if ($preset->presetSubjects()->exists()) {
            return back()->withErrors([
                'preset' => "Cannot delete this preset — it has {$preset->presetSubjects()->count()} linked subjects. Remove all subjects first via 'Manage Subjects', then delete.",
            ]);
        }

        $label = "{$preset->course} {$preset->year_level} {$preset->semester}";
        $preset->delete();

        return redirect()
            ->route('accounting.curriculum-presets.index')
            ->with('success', "Preset for {$label} deleted.");
    }

    private function countLinkedAssessments(CourseUnitPreset $preset): int
    {
        return StudentAssessment::where('semester', $preset->semester)
            ->whereHas('user', fn($q) => $q
                ->where('course', $preset->course)
                ->where('year_level', $preset->year_level)
            )
            ->count();
    }
}