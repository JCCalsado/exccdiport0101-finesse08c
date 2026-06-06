<?php

namespace App\Http\Middleware;

use App\Enums\UserRoleEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Post-login redirect destination per top-level role.
     * accounting sub-roles all share the same dashboard; widget visibility
     * is filtered on the frontend using auth.user.accounting_type.
     */
    const ROLE_DASHBOARDS = [
        UserRoleEnum::ADMIN->value      => 'admin.dashboard',
        UserRoleEnum::ACCOUNTING->value => 'accounting.dashboard',
        UserRoleEnum::REGISTRAR->value  => 'registrar.dashboard',
        UserRoleEnum::STUDENT->value    => 'student.dashboard',
    ];

    /**
     * Restrict a route to one or more roles.
     *
     * Usage in routes:  middleware('role:accounting,admin')
     * Pass roles as a comma-separated string; order does not matter.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403, 'Access denied.');
        }

        $userRole = $user->role instanceof UserRoleEnum
            ? $user->role->value
            : $user->role;

        if (! in_array($userRole, $roles, true)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }

    /**
     * Resolve the post-login redirect route name for a given user.
     */
    public static function dashboardFor(UserRoleEnum $role): string
    {
        return self::ROLE_DASHBOARDS[$role->value] ?? 'login';
    }
}