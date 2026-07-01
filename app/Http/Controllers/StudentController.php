<?php

namespace App\Http\Controllers;

use App\Enums\UserRoleEnum;
use App\Models\Student;
use App\Models\User;
use App\Models\Payment;
use App\Models\StudentAssessment;
use App\Models\StudentStatusLog;
use App\Models\Workflow;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function __construct(protected WorkflowService $workflowService)
    {
    }

    // ── INDEX — active/pending/suspended students only ────────────────────────
    public function index(Request $request): Response
    {
        $query = Student::with(['user', 'payments', 'transactions', 'account', 'workflowInstances.workflow']);

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->whereHas('user', function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', "%{$searchTerm}%")
                  ->orWhere('last_name', 'like', "%{$searchTerm}%")
                  ->orWhere('email', 'like', "%{$searchTerm}%")
                  ->orWhere('phone', 'like', "%{$searchTerm}%")
                  ->orWhere('address', 'like', "%{$searchTerm}%");
            })
            ->orWhere('student_id', 'like', "%{$searchTerm}%")
            ->orWhere('student_number', 'like', "%{$searchTerm}%");
        }

        if ($request->filled('course')) {
            $query->whereHas('user', fn ($q) => $q->where('course', $request->course));
        }

        if ($request->filled('year_level')) {
            $query->whereHas('user', fn ($q) => $q->where('year_level', $request->year_level));
        }

        if ($request->filled('status')) {
            $query->where('enrollment_status', $request->status);
        } else {
            // Default view excludes archived students
            $query->whereIn('enrollment_status', ['active', 'pending', 'suspended']);
        }

        $students = $query->orderBy('created_at', 'desc')->paginate(10);

        return Inertia::render('Students/Index', [
            'students' => $students,
            'filters'  => $request->only(['search', 'status', 'course', 'year_level']),
        ]);
    }

    // ── ARCHIVE — graduated, dropped, and inactive students ──────────────────
    public function archive(Request $request): Response
    {
        $archiveStatuses = ['graduated', 'dropped', 'inactive'];

        $query = Student::with(['user', 'account']);

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->whereHas('user', function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', "%{$searchTerm}%")
                  ->orWhere('last_name', 'like', "%{$searchTerm}%")
                  ->orWhere('email', 'like', "%{$searchTerm}%");
            })
            ->orWhere('student_id', 'like', "%{$searchTerm}%")
            ->orWhere('student_number', 'like', "%{$searchTerm}%");
        }

        if ($request->filled('status') && in_array($request->status, $archiveStatuses)) {
            $query->where('enrollment_status', $request->status);
        } else {
            $query->whereIn('enrollment_status', $archiveStatuses);
        }

        $students = $query->orderBy('updated_at', 'desc')->paginate(15);

        $counts = Student::whereIn('enrollment_status', $archiveStatuses)
            ->selectRaw('enrollment_status, COUNT(*) as count')
            ->groupBy('enrollment_status')
            ->pluck('count', 'enrollment_status');

        return Inertia::render('Students/Archive', [
            'students' => $students,
            'filters'  => $request->only(['search', 'status']),
            'counts'   => [
                'graduated' => (int) ($counts['graduated'] ?? 0),
                'dropped'   => (int) ($counts['dropped']   ?? 0),
                'inactive'  => (int) ($counts['inactive']  ?? 0),
            ],
        ]);
    }

    // ── REINSTATE — move dropped/inactive student back to active ─────────────
    /**
     * POST /students/{student}/reinstate
     *
     * Only students with status "dropped" or "inactive" may be reinstated.
     * Graduated students must go through proper re-enrollment, not this route.
     */
    public function reinstate(Request $request, Student $student)
    {
        $reinstatable = ['dropped', 'inactive'];

        if (! in_array($student->enrollment_status, $reinstatable)) {
            return back()->with(
                'error',
                "Only dropped or inactive students can be reinstated. Current status: {$student->enrollment_status}."
            );
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $fromStatus = $student->enrollment_status;

        // Update both the student's enrollment status and the user's status
        $student->update(['enrollment_status' => 'active']);

        if ($student->user) {
            $student->user->update(['status' => User::STATUS_ACTIVE]);
        }

        // Write the audit log
        StudentStatusLog::create([
            'student_id'  => $student->id,
            'changed_by'  => auth()->id(),
            'from_status' => $fromStatus,
            'to_status'   => 'active',
            'reason'      => $request->input('reason'),
            'action'      => 'reinstate',
        ]);

        $name = $student->user
            ? $student->user->name
            : $student->student_id;

        return back()->with('success', "{$name} has been reinstated and is now active.");
    }

    // ── CREATE / STORE ────────────────────────────────────────────────────────

    public function create(): Response
    {
        return Inertia::render('Students/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id'        => 'required|string|unique:students',
            'last_name'         => 'required|string|max:255',
            'first_name'        => 'required|string|max:255',
            'middle_initial'    => 'nullable|string|max:10',
            'email'             => 'required|email|unique:users',
            'course'            => 'required|string',
            'year_level'        => 'required|string',
            'birthday'          => 'nullable|date',
            'phone'             => 'nullable|string',
            'address'           => 'nullable|string',
            'enrollment_status' => 'sometimes|in:pending,active,suspended,graduated,dropped,inactive',
            'user_id'           => 'nullable|exists:users,id',
        ]);

        if (empty($validated['user_id'])) {
            $user = User::create([
                'email'          => $validated['email'],
                'last_name'      => $validated['last_name'],
                'first_name'     => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'],
                'course'         => $validated['course'],
                'year_level'     => $validated['year_level'],
                'birthday'       => $validated['birthday'],
                'phone'          => $validated['phone'],
                'address'        => $validated['address'],
                'role'           => UserRoleEnum::STUDENT,
                'password'       => bcrypt('temporary' . time()),
            ]);
            $validated['user_id'] = $user->id;
        }

        if (! isset($validated['student_number'])) {
            $validated['student_number'] = 'STU-' . strtoupper(uniqid());
        }

        $validated['enrollment_status'] = $validated['enrollment_status'] ?? 'pending';

        unset(
            $validated['last_name'],
            $validated['first_name'],
            $validated['middle_initial'],
            $validated['email'],
            $validated['course'],
            $validated['year_level'],
            $validated['birthday'],
            $validated['phone'],
            $validated['address'],
        );

        $student = Student::create($validated);

        if ($student->enrollment_status === 'pending') {
            $workflow = Workflow::active()
                ->where('type', 'student')
                ->where('name', 'like', '%enrollment%')
                ->first();

            if ($workflow) {
                try {
                    $this->workflowService->startWorkflow($workflow, $student, auth()->id());
                    return redirect()->route('students.show', $student)
                        ->with('success', 'Student created and enrollment workflow started');
                } catch (\Exception $e) {
                    logger()->error('Failed to start workflow: ' . $e->getMessage());
                    return redirect()->route('students.show', $student)
                        ->with('warning', 'Student created but workflow failed to start');
                }
            }
        }

        return redirect()->route('students.show', $student)
            ->with('success', 'Student created successfully');
    }

    // ── SHOW ──────────────────────────────────────────────────────────────────

    public function show($id): Response
    {
        $student = Student::with([
            'payments',
            'user.account',
            'workflowInstances.workflow',
            'workflowInstances.approvals.approver',
            'accountingTransactions',
        ])->findOrFail($id);

        $activeWorkflow = $student->workflowInstances()
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['workflow', 'approvals'])
            ->first();

        return Inertia::render('Students/StudentProfile', [
            'student'        => $student,
            'activeWorkflow' => $activeWorkflow,
        ]);
    }

    // ── STORE PAYMENT ─────────────────────────────────────────────────────────

    public function storePayment(Request $request, Student $student)
    {
        $request->validate([
            'amount'           => 'required|numeric|min:0.01',
            'description'      => 'required|string|max:255',
            'payment_method'   => 'required|string',
            'reference_number' => 'nullable|string',
            'status'           => 'required|string',
            'paid_at'          => 'required|date',
        ]);

        $user = $request->user();

        // FIX: Use Enum comparison — $user->role is a UserRoleEnum object, NOT a string.
        // The previous `$user->role === 'student'` was ALWAYS false (Enum !== string),
        // meaning the ownership guard never ran and any user could record a payment
        // against any student. This is the correct pattern per Claude.md.
        if ($user->role === UserRoleEnum::STUDENT) {
            if (! $user->student || $user->student->id !== $student->id) {
                abort(403, 'Unauthorized payment submission.');
            }
        }

        $student->payments()->create($request->only([
            'amount', 'description', 'payment_method', 'reference_number', 'status', 'paid_at',
        ]));

        return back()->with('success', 'Payment recorded successfully!');
    }

    // ── EDIT / UPDATE ─────────────────────────────────────────────────────────

    public function edit(Student $student): Response
    {
        return Inertia::render('Students/Edit', ['student' => $student]);
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'student_id'        => 'required|string|unique:students,student_id,' . $student->id,
            'first_name'        => 'required|string|max:255',
            'last_name'         => 'required|string|max:255',
            'middle_initial'    => 'nullable|string|max:10',
            'email'             => 'required|email|unique:users,email,' . $student->user_id,
            'course'            => 'required|string|max:255',
            'year_level'        => 'required|string',
            'birthday'          => 'nullable|date',
            'phone'             => 'nullable|string|max:20',
            'address'           => 'nullable|string|max:500',
            'enrollment_status' => 'sometimes|in:pending,active,suspended,graduated,dropped,inactive',
        ]);

        if ($student->user) {
            $student->user->update([
                'first_name'     => $validated['first_name'],
                'last_name'      => $validated['last_name'],
                'middle_initial' => $validated['middle_initial'],
                'email'          => $validated['email'],
                'course'         => $validated['course'],
                'year_level'     => $validated['year_level'],
                'birthday'       => $validated['birthday'],
                'phone'          => $validated['phone'],
                'address'        => $validated['address'],
            ]);
        }

        $newStatus     = $validated['enrollment_status'] ?? $student->enrollment_status;
        $statusChanged = $student->enrollment_status !== $newStatus;

        $student->update([
            'student_id'        => $validated['student_id'],
            'enrollment_status' => $newStatus,
        ]);

        // Log status change if it happened via the general edit form
        if ($statusChanged) {
            StudentStatusLog::create([
                'student_id'  => $student->id,
                'changed_by'  => auth()->id(),
                'from_status' => $student->getOriginal('enrollment_status'),
                'to_status'   => $newStatus,
                'reason'      => null,
                'action'      => 'manual_edit',
            ]);

            if ($newStatus === 'active') {
                $activeWorkflow = $student->workflowInstances()->where('status', 'in_progress')->first();
                if ($activeWorkflow) {
                    $activeWorkflow->update(['status' => 'completed', 'completed_at' => now()]);
                }
            }
        }

        return redirect()->route('students.show', $student)
            ->with('success', 'Student updated successfully!');
    }

    // ── DESTROY ───────────────────────────────────────────────────────────────

    public function destroy(Student $student)
    {
        $student->delete();
        return redirect()->route('students.index')
            ->with('success', 'Student deleted successfully!');
    }

    // ── STUDENT PROFILE (self-view) ───────────────────────────────────────────

    public function studentProfile(Request $request): Response
    {
        $user = $request->user();

        // FIX: Use Enum comparison — $user->role is a UserRoleEnum object, NOT a string.
        // The previous `$user->role === 'student'` was ALWAYS false, causing every caller
        // (including students themselves) to hit the else branch and receive the first
        // student record in the database instead of their own profile. Critical data leak.
        if ($user->role === UserRoleEnum::STUDENT) {
            $student = Student::where('user_id', $user->id)->firstOrFail();
        } else {
            $student = Student::with('payments')->first();
        }

        $student->load([
            'payments',
            'user.account',
            'workflowInstances.workflow',
            'workflowInstances.approvals',
        ]);

        $activeWorkflow = $student->workflowInstances()
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['workflow', 'approvals'])
            ->first();

        return Inertia::render('Students/StudentProfile', [
            'student'        => $student,
            'activeWorkflow' => $activeWorkflow,
        ]);
    }

    // ── WORKFLOW HELPERS ──────────────────────────────────────────────────────

    public function advanceWorkflow(Student $student)
    {
        $activeWorkflow = $student->workflowInstances()->where('status', 'in_progress')->first();

        if (! $activeWorkflow) {
            return back()->withErrors(['error' => 'No active workflow found for this student']);
        }

        try {
            $this->workflowService->advanceWorkflow($activeWorkflow, auth()->id());
            return back()->with('success', 'Workflow advanced to next step');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to advance workflow: ' . $e->getMessage()]);
        }
    }

    public function workflowHistory(Student $student): Response
    {
        $student->load('user');

        $workflows = $student->workflowInstances()
            ->with(['workflow', 'approvals.approver', 'workflowable'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($instance) {
                $decidedApproval = $instance->approvals
                    ->whereIn('status', ['approved', 'rejected'])
                    ->sortByDesc('approved_at')
                    ->first();

                $anyApproval = $decidedApproval ?? $instance->approvals->first();

                // Extract course from the workflowable entity if available
                $course = null;
                if ($instance->workflowable_type === 'App\\Models\\Transaction' && $instance->workflowable) {
                    $transactionMeta = $instance->workflowable->meta ?? [];
                    $course          = $transactionMeta['course'] ?? null;
                } elseif ($instance->workflowable_type === 'App\\Models\\Student' && $instance->workflowable) {
                    $latestAssessment = $instance->workflowable->user?->latestAssessment;
                    $course           = $latestAssessment?->course;
                }

                return [
                    'id'            => $instance->id,
                    'workflow_type' => $instance->workflow?->name ?? 'Payment Approval',
                    'description'   => $instance->workflow?->description
                                        ?? ('Step: ' . $instance->current_step),
                    'status'        => $instance->status,
                    'current_step'  => $instance->current_step,
                    'approver_name' => $anyApproval?->approver
                                        ? $anyApproval->approver->name
                                        : null,
                    'comment'       => $anyApproval?->comments ?? null,
                    'course'        => $course,
                    'created_at'    => $instance->created_at,
                    'updated_at'    => $instance->updated_at,
                    'completed_at'  => $instance->completed_at,
                ];
            });

        // Assessment history — each assessment is a billing event.
        // Charge transactions (kind='charge') were intentionally removed from
        // StudentFees/Show.vue's Payment History table and surfaced here instead,
        // so staff can see exactly what was assessed without cluttering payment records.
        $assessments = StudentAssessment::where('user_id', $student->user_id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($a) => [
                'id'               => $a->id,
                'course'           => $a->course,
                'year_level'       => $a->year_level,
                'semester'         => $a->semester,
                'school_year'      => $a->school_year,
                'total_assessment' => (float) $a->total_assessment,
                'tuition_fee'      => (float) $a->tuition_fee,
                'other_fees'       => (float) $a->other_fees,
                'status'           => $a->status,
                'created_at'       => $a->created_at,
            ]);

        return Inertia::render('Students/WorkflowHistory', [
            'account_id'  => $student->user?->account_id ?? $student->student_id,
            'workflows'   => $workflows,
            'assessments' => $assessments,
        ]);
    }
}