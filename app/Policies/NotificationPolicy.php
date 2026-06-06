<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

class NotificationPolicy
{
    private function adminPass(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    /**
     * View the notification list.
     * Registrar and Admin.
     */
    public function viewAny(User $user): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active && $user->isRegistrar();
    }

    /**
     * View a single notification.
     */
    public function view(User $user, Notification $notification): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active && $user->isRegistrar();
    }

    /**
     * Create a notification.
     * Registrar and Admin only.
     */
    public function create(User $user): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active && $user->isRegistrar();
    }

    /**
     * Update a notification.
     * Registrar and Admin only.
     */
    public function update(User $user, Notification $notification): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active && $user->isRegistrar();
    }

    /**
     * Delete a notification.
     * Registrar and Admin only.
     */
    public function delete(User $user, Notification $notification): bool
    {
        if ($this->adminPass($user)) return true;
        return $user->is_active && $user->isRegistrar();
    }
}