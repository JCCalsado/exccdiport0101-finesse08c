<?php

namespace App\Policies;

use App\Enums\AccountingTypeEnum;
use App\Enums\UserRoleEnum;
use App\Models\OtherCharge;
use App\Models\User;

class OtherChargePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRoleEnum::ADMIN
            || $user->role === UserRoleEnum::ACCOUNTING;
    }

    public function view(User $user, OtherCharge $charge): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->isDisbursingOfficerOrAdmin($user);
    }

    public function update(User $user, OtherCharge $charge): bool
    {
        return $this->isDisbursingOfficerOrAdmin($user);
    }

    public function delete(User $user, OtherCharge $charge): bool
    {
        if (! $this->isDisbursingOfficerOrAdmin($user)) {
            return false;
        }

        if ($charge->is_published && $charge->payments()->exists()) {
            return false;
        }

        return true;
    }

    public function publish(User $user, OtherCharge $charge): bool
    {
        return $this->isDisbursingOfficerOrAdmin($user);
    }

    public function recordPayment(User $user, OtherCharge $charge): bool
    {
        if ($user->role === UserRoleEnum::ADMIN) return true;

        if ($user->role === UserRoleEnum::ACCOUNTING) {
            return in_array($user->accounting_type, [
                AccountingTypeEnum::DISBURSING_OFFICER,
                AccountingTypeEnum::CASHIER,
            ]);
        }

        return false;
    }

    /**
     * OPTION D — approve/reject a bank-transfer proof.
     *
     * Restricted to Disbursing Officer + Admin, NOT Cashier. recordPayment()
     * (OTC) is Cashier-eligible because the cashier is physically handing the
     * receipt in person. Bank-transfer approval is verifying an external,
     * unwitnessed document — same trust tier as publish()/update(), which is
     * why this reuses isDisbursingOfficerOrAdmin() rather than mirroring
     * recordPayment()'s wider Cashier-inclusive check.
     */
    public function approvePayment(User $user, OtherCharge $charge): bool
    {
        return $this->isDisbursingOfficerOrAdmin($user);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function isDisbursingOfficerOrAdmin(User $user): bool
    {
        if ($user->role === UserRoleEnum::ADMIN) return true;

        return $user->role === UserRoleEnum::ACCOUNTING
            && $user->accounting_type === AccountingTypeEnum::DISBURSING_OFFICER;
    }
}
