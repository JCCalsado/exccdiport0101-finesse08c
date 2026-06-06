<?php

namespace App\Policies;

use App\Models\User;

class FinancialReportPolicy
{
    private function adminPass(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    /**
     * View financial reports and collection summaries.
     *
     * Bookkeeper: primary consumer — reconciliation, audit, COA/BIR prep.
     * Disbursing Officer: needs read access to verify daily collections,
     *   sign off on remittance reports, cross-reference payment approvals.
     * Admin: always.
     * Cashier: excluded. They record; they don't review aggregates.
     * Registrar: excluded. No financial domain.
     */
    public function viewAny(User $user): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active
            && $user->isAccounting()
            && ($user->isBookkeeper() || $user->isDisbursingOfficer());
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Export / download report data.
     * Same access as view.
     */
    public function export(User $user): bool
    {
        return $this->viewAny($user);
    }
}