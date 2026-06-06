<?php

namespace App\Policies;

use App\Models\User;

class FeeManagementPolicy
{
    private function adminPass(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    /**
     * Manage system-wide fee settings (fee types, rates, academic year configs).
     * Disbursing Officer + Admin.
     */
    public function manageSystemFees(User $user): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active && $user->isDisbursingOfficer();
    }

    /**
     * View fee settings (read-only).
     * Disbursing Officer + Admin.
     */
    public function viewFeeSettings(User $user): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active && $user->isDisbursingOfficer();
    }
}