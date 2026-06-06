<?php

namespace App\Policies;

use App\Models\StudentRegistration;
use App\Models\User;

class StudentRegistrationPolicy
{
    private function adminPass(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    // ── Registrar Stage ────────────────────────────────────────────────────

    /**
     * View the Registrar's academic-review queue (pending + needs_revision/registrar).
     */
    public function viewRegistrarQueue(User $user): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active && $user->isRegistrar();
    }

    /**
     * Approve, reject, or request revision at the Registrar stage.
     */
    public function actAsRegistrar(User $user, StudentRegistration $registration): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active
            && $user->isRegistrar()
            && $registration->isRegistrarActionable();
    }

    // ── Finance Stage ──────────────────────────────────────────────────────

    /**
     * View the Finance (Disbursing Officer) queue (registrar_cleared + needs_revision/finance).
     */
    public function viewFinanceQueue(User $user): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active && $user->isDisbursingOfficer();
    }

    /**
     * Approve, reject, or request revision at the Finance stage.
     */
    public function actAsFinance(User $user, StudentRegistration $registration): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active
            && $user->isDisbursingOfficer()
            && $registration->isFinanceActionable();
    }

    // ── Legacy method kept for existing code that references it ───────────

    /**
     * @deprecated  Use actAsRegistrar / actAsFinance instead.
     */
    public function approve(User $user, StudentRegistration $registration): bool
    {
        if ($this->adminPass($user)) return true;
        return ($user->isDisbursingOfficer() && $registration->isFinanceActionable())
            || ($user->isRegistrar() && $registration->isRegistrarActionable());
    }
}