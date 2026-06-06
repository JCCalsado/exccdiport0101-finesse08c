<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Models\CourseUnitPreset;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SubjectController (Accounting namespace)
 *
 * Authorization via SubjectPolicy (bound to Subject::class).
 *   view  → viewAny / view → Registrar + Disbursing Officer + Admin
 *   write → create / update / delete → Registrar + Admin only
 *
 * Note: canEditNstp() is NOT a Policy concern — it controls which fields are
 * writable within an already-authorized write action. Admin can flip is_nstp;
 * Registrar cannot. Both can write other fields.
 */
class SubjectController extends Controller
{
    private const YEAR_LEVELS = ['1st Year','2nd Year','3rd Year','4th Year','5th Year'];
    private const SEMESTERS   = ['1st Sem','2nd Sem'];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Subject::class);

        $query = Subject::query()->where('is_active', true);

        if ($request->filled('course'))      $query->where('course', $request->course);
        if ($request->filled('year_level'))  $query->where('year_level', $request->year_level);
        if ($request->filled('semester'))    $query->where('semester', $request->semester);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%");
            });
        }

        $subjects = $query
            ->orderBy('course')->orderBy('year_level')->orderBy('semester')->orderBy('code')
            ->paginate(50)
            ->through(fn (Subject $s) => [
                'id'         => $s->id,
                'code'       => $s->code,
                'name'       => $s->name,
                'lec_units'  => $s->lec_units,
                'lab_units'  => $s->lab_units,
                'year_level' => $s->year_level,
                'semester'   => $s->semester,
                'course'     => $s->course,
                'is_active'  => $s->is_active,
                'is_nstp'    => (bool) $s->is_nstp,
            ]);

        $subjects->appends($request->only(['course', 'year_level', 'semester', 'search']));

        $courses = CourseUnitPreset::distinct()->orderBy('course')->pluck('course')->values();

        return Inertia::render('Subjects/Index', [
            'subjects'     => $subjects,
            'courses'      => $courses,
            'yearLevels'   => self::YEAR_LEVELS,
            'semesters'    => self::SEMESTERS,
            'filters'      => $request->only(['course', 'year_level', 'semester', 'search']),
            'canEditNstp'  => $this->canEditNstp(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Subject::class);

        $courses = CourseUnitPreset::distinct()->orderBy('course')->pluck('course')->values();

        return Inertia::render('Subjects/Create', [
            'courses'     => $courses,
            'yearLevels'  => self::YEAR_LEVELS,
            'semesters'   => self::SEMESTERS,
            'canEditNstp' => $this->canEditNstp(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Subject::class);

        $validCourses = CourseUnitPreset::distinct()->pluck('course')->toArray();

        $rules = [
            'code'       => ['required', 'string', 'max:50', 'unique:subjects,code'],
            'name'       => ['required', 'string', 'max:255'],
            'lec_units'  => ['required', 'numeric', 'min:0', 'max:10'],
            'lab_units'  => ['required', 'integer', 'min:0', 'max:5'],
            'year_level' => ['required', 'string', Rule::in(self::YEAR_LEVELS)],
            'semester'   => ['required', 'string', Rule::in(self::SEMESTERS)],
            'course'     => ['required', 'string', Rule::in($validCourses)],
        ];

        if ($this->canEditNstp()) {
            $rules['is_nstp'] = ['sometimes', 'boolean'];
        }

        $validated = $request->validate($rules);

        $validated['lec_units'] = (float) $validated['lec_units'];
        $validated['lab_units'] = (int)   $validated['lab_units'];
        $validated['is_active'] = true;

        if (! $this->canEditNstp()) {
            $validated['is_nstp'] = str_contains(strtoupper($validated['code']), 'NSTP');
        } else {
            $validated['is_nstp'] = (bool) ($validated['is_nstp'] ?? false);
        }

        $subject = Subject::create($validated);

        return redirect()
            ->route('accounting.subjects.index')
            ->with('success', "Subject \"{$subject->code} — {$subject->name}\" created.");
    }

    public function edit(Subject $subject): Response
    {
        $this->authorize('update', Subject::class);

        $courses = CourseUnitPreset::distinct()->orderBy('course')->pluck('course')->values();

        return Inertia::render('Subjects/Edit', [
            'subject' => [
                'id'         => $subject->id,
                'code'       => $subject->code,
                'name'       => $subject->name,
                'lec_units'  => $subject->lec_units,
                'lab_units'  => $subject->lab_units,
                'year_level' => $subject->year_level,
                'semester'   => $subject->semester,
                'course'     => $subject->course,
                'is_nstp'    => (bool) $subject->is_nstp,
                'is_active'  => $subject->is_active,
            ],
            'courses'     => $courses,
            'canEditNstp' => $this->canEditNstp(),
        ]);
    }

    public function update(Request $request, Subject $subject)
    {
        $this->authorize('update', Subject::class);

        $validCourses = CourseUnitPreset::distinct()->pluck('course')->toArray();

        $rules = [
            'code'       => ['required', 'string', 'max:50', 'unique:subjects,code,' . $subject->id],
            'name'       => ['required', 'string', 'max:255'],
            'lec_units'  => ['required', 'numeric', 'min:0', 'max:10'],
            'lab_units'  => ['required', 'integer', 'min:0', 'max:5'],
            'year_level' => ['required', 'string', Rule::in(self::YEAR_LEVELS)],
            'semester'   => ['required', 'string', Rule::in(self::SEMESTERS)],
            'course'     => ['required', 'string', Rule::in($validCourses)],
            'is_active'  => ['sometimes', 'boolean'],
        ];

        if ($this->canEditNstp()) {
            $rules['is_nstp'] = ['sometimes', 'boolean'];
        }

        $validated = $request->validate($rules);

        $validated['lec_units'] = (float) $validated['lec_units'];
        $validated['lab_units'] = (int)   $validated['lab_units'];

        if (! $this->canEditNstp()) {
            unset($validated['is_nstp']);
        } else {
            $validated['is_nstp'] = (bool) ($validated['is_nstp'] ?? $subject->is_nstp);
        }

        $subject->update($validated);

        return redirect()
            ->route('accounting.subjects.index')
            ->with('success', "Subject \"{$subject->code} — {$subject->name}\" updated.");
    }

    public function inlineUpdate(Request $request, Subject $subject): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', Subject::class);

        $validated = $request->validate([
            'lec_units' => ['required', 'numeric', 'min:0', 'max:10'],
            'lab_units' => ['required', 'integer', 'min:0', 'max:5'],
        ]);

        $validated['lec_units'] = (float) $validated['lec_units'];
        $validated['lab_units'] = (int)   $validated['lab_units'];

        $subject->update($validated);

        return response()->json([
            'success'   => true,
            'lec_units' => $subject->fresh()->lec_units,
            'lab_units' => $subject->fresh()->lab_units,
        ]);
    }

    public function destroy(Subject $subject)
    {
        // Policy: delete → Registrar + Admin. Replaces the old inline isAdmin() check.
        $this->authorize('delete', Subject::class);

        $label = "{$subject->code} — {$subject->name}";
        $subject->update(['is_active' => false]);

        return redirect()
            ->route('accounting.subjects.index')
            ->with('success', "Subject \"{$label}\" deactivated.");
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Only admin role may set or change the is_nstp flag.
     * This is a FIELD-LEVEL permission, not an action gate — kept separate from Policy.
     */
    private function canEditNstp(): bool
    {
        return auth()->user()?->role === UserRoleEnum::ADMIN;
    }
}