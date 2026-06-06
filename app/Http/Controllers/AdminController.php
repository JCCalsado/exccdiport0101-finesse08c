<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function __construct(protected AdminService $adminService)
    {
        $this->middleware('auth:web');
        $this->middleware('role:admin');
    }

    /**
     * List all admin, accounting, and registrar staff.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        $admins = User::whereIn('department', ['Administrator', 'Accounting', 'Registrar'])
            ->with(['createdByUser', 'updatedByUser'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Admin/Users/Index', [
            'admins'    => $admins,
            'stats'     => $this->adminService->getAdminStats(),
            'canManage' => true,
        ]);
    }

    /**
     * View a single staff profile.
     */
    public function show(User $user): Response
    {
        $this->authorize('view', $user);

        if (! in_array($user->department, ['Administrator', 'Accounting', 'Registrar'], true)) {
            abort(404);
        }

        return Inertia::render('Admin/Users/Show', [
            'admin'     => $user->load(['createdByUser', 'updatedByUser']),
            'canManage' => in_array($user->department, ['Accounting', 'Registrar'], true),
        ]);
    }

    /**
     * Show the create form — creates Accounting or Registrar staff.
     */
    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Admin/Users/Create');
    }

    /**
     * Store a new Accounting or Registrar staff user.
     *
     * Admin may only create Accounting and Registrar users — never another
     * Administrator account — via this form. The allowed departments are
     * enforced here as a hard guard before reaching the service layer.
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $allowedDepartments = ['Accounting', 'Registrar'];
        $requestedDept      = $request->input('department', 'Accounting');
        $department         = in_array($requestedDept, $allowedDepartments, true)
                              ? $requestedDept
                              : 'Accounting';

        $data = array_merge($request->all(), ['department' => $department]);

        try {
            $admin = $this->adminService->createAdmin($data, $request->user());
            return redirect()
                ->route('users.show', $admin->id)
                ->with('flash.success', "Staff member '{$admin->name}' created successfully.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
    }

    /**
     * Show the edit form — Accounting and Registrar staff only.
     * Administrator accounts are immutable via this panel.
     */
    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        if (! in_array($user->department, ['Accounting', 'Registrar'], true)) {
            abort(403, 'Administrator accounts cannot be edited via this panel.');
        }

        return Inertia::render('Admin/Users/Edit', [
            'admin' => $user,
        ]);
    }

    /**
     * Update an Accounting or Registrar staff user.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        if (! in_array($user->department, ['Accounting', 'Registrar'], true)) {
            abort(403, 'Administrator accounts cannot be edited via this panel.');
        }

        // Prevent the department from being escalated to Administrator via the form.
        $allowedDepartments = ['Accounting', 'Registrar'];
        $requestedDept      = $request->input('department', $user->department);
        $department         = in_array($requestedDept, $allowedDepartments, true)
                              ? $requestedDept
                              : $user->department;

        $data = array_merge($request->all(), ['department' => $department]);

        try {
            $this->adminService->updateAdmin($user, $data, $request->user());
            return redirect()
                ->route('users.show', $user->id)
                ->with('flash.success', 'Staff member updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
    }

    /**
     * Hard deletion is never allowed.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);
        abort(403, 'Hard deletion of accounts is not permitted. Use deactivate instead.');
    }

    /**
     * Deactivate an Accounting or Registrar staff user.
     */
    public function deactivate(Request $request, User $user)
    {
        $this->authorize('manageAdmins', $user);

        try {
            $this->adminService->deactivateAdmin($user, $request->user());
            return back()->with('flash.success', 'Staff member deactivated.');
        } catch (\InvalidArgumentException $e) {
            abort(403, $e->getMessage());
        }
    }

    /**
     * Reactivate an Accounting or Registrar staff user.
     */
    public function reactivate(Request $request, User $user)
    {
        $this->authorize('manageAdmins', $user);

        try {
            $this->adminService->reactivateAdmin($user);
            return back()->with('flash.success', 'Staff member reactivated.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}