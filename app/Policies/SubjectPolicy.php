<?php

namespace App\Policies;

use App\Models\User;

class SubjectPolicy
{
    private function adminPass(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    /**
     * View subject list.
     * Registrar + Disbursing Officer (needs read for assessment creation) + Admin.
     */
    public function viewAny(User $user): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active
            && ($user->isRegistrar() || $user->isDisbursingOfficer());
    }

    /**
     * View a single subject.
     */
    public function view(User $user): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active
            && ($user->isRegistrar() || $user->isDisbursingOfficer());
    }

    /**
     * Create a subject.
     * Registrar + Admin only.
     */
    public function create(User $user): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active && $user->isRegistrar();
    }

    /**
     * Update a subject.
     * Registrar + Admin only.
     */
    public function update(User $user): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active && $user->isRegistrar();
    }

    /**
     * Delete a subject.
     * Registrar + Admin only.
     */
    public function delete(User $user): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active && $user->isRegistrar();
    }
}