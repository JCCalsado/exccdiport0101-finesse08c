import { usePage } from '@inertiajs/vue3';

/**
 * Returns the correct dashboard href string for the authenticated user's role.
 * Auth role is immutable within a session, so a plain string (no ComputedRef)
 * is safe and avoids .value noise at call sites.
 */
export function useDashboardRoute(): { dashboardHref: string } {
    const page = usePage();
    const role = (page.props.auth as any)?.user?.role ?? '';

    switch (role) {
        case 'admin':      return { dashboardHref: route('admin.dashboard') };
        case 'accounting': return { dashboardHref: route('accounting.dashboard') };
        case 'registrar':  return { dashboardHref: route('registrar.dashboard') };
        case 'student':    return { dashboardHref: route('student.dashboard') };
        default:           return { dashboardHref: route('dashboard') };
    }
}
