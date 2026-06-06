<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Allow admin to view the list of staff users.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    /**
     * Admin can manage staff accounts across Accounting and Registrar departments.
     * Admin cannot manage other Admin accounts via the staff panel.
     */
    public function manageAdmins(User $user, User $model): bool
    {
        return $user->isAdmin()
            && $user->is_active
            && in_array($model->department, ['Accounting', 'Registrar'], true);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin()
            && $user->is_active
            && in_array($model->department, ['Accounting', 'Registrar'], true);
    }

    public function update(User $user, User $model): bool
    {
        // Admin managing their own account
        if ($user->id === $model->id && $user->isAdmin()) {
            return $user->is_active;
        }

        // Admin managing Accounting or Registrar staff
        return $user->isAdmin()
            && $user->is_active
            && in_array($model->department, ['Accounting', 'Registrar'], true);
    }

    public function delete(User $user, User $model): bool
    {
        // Cannot delete self
        if ($user->id === $model->id) return false;

        return $user->isAdmin()
            && $user->is_active
            && in_array($model->department, ['Accounting', 'Registrar'], true);
    }

    public function deactivate(User $user, User $model): bool
    {
        if ($user->id === $model->id) return false;

        return $user->isAdmin()
            && $user->is_active
            && in_array($model->department, ['Accounting', 'Registrar'], true);
    }
}