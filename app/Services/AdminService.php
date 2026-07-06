<?php

namespace App\Services;

use App\Enums\AccountingTypeEnum;
use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminService
{
    public function createAdmin(array $data, int|User|null $createdBy = null): User
    {
        if (is_int($createdBy)) {
            $createdBy = User::find($createdBy);
        }

        if (isset($data['password']) && ! isset($data['password_confirmation'])) {
            $data['password_confirmation'] = $data['password'];
        }

        $validated  = $this->validateAdminData($data);
        $department = $validated['department'] ?? 'Administrator';

        $role           = $this->resolveRole($department);
        $accountingType = $this->resolveAccountingType($department, $validated);

        $admin = User::create([
            'last_name'       => $validated['last_name'],
            'first_name'      => $validated['first_name'],
            'middle_initial'  => $validated['middle_initial'] ?? null,
            'email'           => $validated['email'],
            'password'        => Hash::make($validated['password']),
            'role'            => $role,
            'accounting_type' => $accountingType,
            'department'      => $department,
            'is_active'       => $validated['is_active'] ?? true,
            'updated_by'      => $createdBy?->id,
        ]);

        $admin->forceFill(['created_by' => $createdBy?->id])->save();
        $admin->acceptTerms();

        return $admin;
    }

    public function updateAdmin(User $admin, array $data, int|User|null $updatedBy = null): User
    {
        if (is_int($updatedBy)) {
            $updatedBy = User::find($updatedBy);
        }

        if (isset($data['password']) && ! isset($data['password_confirmation'])) {
            $data['password_confirmation'] = $data['password'];
        }

        $validated = $this->validateAdminUpdateData($data, $admin->id);

        $department     = array_key_exists('department', $validated) ? $validated['department'] : $admin->department;
        $role           = $this->resolveRole($department);
        $accountingType = $this->resolveAccountingType($department, $validated, $admin);

        $updateData = [
            'last_name'       => $validated['last_name']      ?? $admin->last_name,
            'first_name'      => $validated['first_name']     ?? $admin->first_name,
            'middle_initial'  => $validated['middle_initial'] ?? $admin->middle_initial,
            'email'           => $validated['email']          ?? $admin->email,
            'department'      => $department,
            'role'            => $role,
            'accounting_type' => $accountingType,
            'is_active'       => $validated['is_active']      ?? $admin->is_active,
            'updated_by'      => $updatedBy?->id,
        ];

        if ($updateData['email'] !== $admin->email) {
            $updateData['email_verified_at'] = null;
        }

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $admin->update($updateData);

        return $admin->refresh();
    }

    public function deactivateAdmin(User $admin, ?User $performedBy = null): bool
    {
        // Allow deactivation for admin-role users even if `department` is not set
        $department = $admin->department;
        if ($admin->role === UserRoleEnum::ADMIN) {
            // Treat null/empty department as Administrator for legacy/tests
            $department = $department ?? 'Administrator';
        }

        if (! in_array($department, ['Administrator', 'Accounting', 'Registrar'], true)) {
            throw new \InvalidArgumentException('User is not a deactivatable staff member.');
        }

        if ($performedBy && $performedBy->id === $admin->id) {
            throw new \InvalidArgumentException('You cannot deactivate your own account. Ask another admin to deactivate you.');
        }

        return $admin->update(['is_active' => false]);
    }

    public function reactivateAdmin(User $admin): bool
    {
        if (! in_array($admin->department, ['Administrator', 'Accounting', 'Registrar'], true)) {
            throw new \InvalidArgumentException('User is not a reactivatable staff member.');
        }

        return $admin->update(['is_active' => true]);
    }

    public function hasPermission(User $admin, string $permission): bool
    {
        if (! $admin->isAdmin()) {
            return false;
        }

        return $admin->hasPermission($permission);
    }

    public function getActiveAdmins()
    {
        return User::admins()
            ->where('is_active', true)
            ->with(['createdByUser', 'updatedByUser'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getAdminStats(): array
    {
        $allStaff      = User::whereIn('department', ['Administrator', 'Accounting', 'Registrar'])->get();
        $allAdmins     = $allStaff->where('department', 'Administrator');
        $allAccounting = $allStaff->where('department', 'Accounting');
        $allRegistrar  = $allStaff->where('department', 'Registrar');

        $activeAdmins     = $allAdmins->where('is_active', true);
        $activeAccounting = $allAccounting->where('is_active', true);
        $activeRegistrar  = $allRegistrar->where('is_active', true);

        return [
            'total_admins'             => $allAdmins->count(),
            'total_active_admins'      => $activeAdmins->count(),
            'total_accounting'         => $allAccounting->count(),
            'total_active_accounting'  => $activeAccounting->count(),
            'total_registrar'          => $allRegistrar->count(),
            'total_active_registrar'   => $activeRegistrar->count(),
            'terms_accepted'           => $allAdmins->filter(fn ($a) => $a->terms_accepted_at !== null)->count(),
            'last_login_avg_days'      => $this->calculateAverageLastLogin($activeAdmins),
        ];
    }

    public function logAdminAction(int|User $admin, string $action, string $model = '', int $modelId = 0, array $details = []): void
    {
        // Implement audit logging if needed.
    }

    // ── Private Helpers ────────────────────────────────────────────────────

    /**
     * Map department string to UserRoleEnum.
     *
     * Accounting → accounting (sub-role tracked via accounting_type column)
     * Registrar  → registrar  (dedicated role, NOT admin)
     * default    → admin      (Administrator department)
     */
    private function resolveRole(string $department): UserRoleEnum
    {
        return match ($department) {
            'Accounting' => UserRoleEnum::ACCOUNTING,
            'Registrar'  => UserRoleEnum::REGISTRAR,
            default      => UserRoleEnum::ADMIN,
        };
    }

    private function resolveAccountingType(string $department, array $validated, ?User $existing = null): ?AccountingTypeEnum
    {
        if ($department !== 'Accounting') {
            return null;
        }

        $raw = $validated['accounting_type']
            ?? $existing?->accounting_type?->value
            ?? null;

        if ($raw === null) {
            return null;
        }

        return AccountingTypeEnum::from($raw);
    }

    private function validateAdminData(array $data, ?int $userId = null): array
    {
        $rules = User::getAdminValidationRules($userId);
        return validator($data, $rules)->validate();
    }

    private function validateAdminUpdateData(array $data, int $userId): array
    {
        $rules = User::getAdminValidationRules($userId);

        $updateRules = [];
        foreach ($rules as $field => $rule) {
            $ruleString = is_array($rule) ? implode('|', $rule) : $rule;
            $ruleString = preg_replace('/(?<![_a-zA-Z])required(?![_a-zA-Z:])/', '', $ruleString);
            $ruleString = trim($ruleString, '|');
            $updateRules[$field] = 'sometimes|' . $ruleString;
        }

        return validator($data, $updateRules)->validate();
    }

    private function calculateAverageLastLogin($admins): ?int
    {
        $loggedIn = $admins->filter(fn ($a) => $a->last_login_at !== null);

        if ($loggedIn->isEmpty()) {
            return null;
        }

        $totalDays = $loggedIn->sum(fn ($a) => now()->diffInDays($a->last_login_at));

        return (int) ($totalDays / $loggedIn->count());
    }
}