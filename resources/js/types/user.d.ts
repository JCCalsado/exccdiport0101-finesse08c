// Base User type — mirrors the resolveAuthUser() output in HandleInertiaRequests.
// Keep this in sync whenever new shared fields are added to that method.
export interface User {
    id: number;
    name: string;           // "LAST, First MI." — computed accessor
    first_name: string;
    last_name: string;
    middle_name?: string | null;
    middle_initial?: string | null;
    suffix?: string | null;
    gender?: string | null;
    civil_status?: string | null;
    email: string;

    /** Top-level role. 'registrar' was added in the 2026-06 role decomposition. */
    role: 'admin' | 'accounting' | 'registrar' | 'student';

    /**
     * Accounting sub-role. Only populated when role = 'accounting'.
     * null for admin, registrar, and student users.
     */
    accounting_type?: 'cashier' | 'bookkeeper' | 'disbursing_officer' | null;

    /**
     * Registration queue badge counts.
     * registrar_queue  → Registrar's pending academic-review queue
     * finance_queue    → Disbursing Officer's registrar-cleared finance queue
     * Both are 0 for roles that do not own a queue (Cashier, Bookkeeper, Student).
     */
    registration_counts?: {
        registrar_queue: number;
        finance_queue:   number;
    };

    avatar?: string | null;
    profile_picture?: string | null;
    email_verified_at?: string | null;
    is_active?: boolean;
    faculty?: string | null;
    department?: string | null;
}

// StudentUser extends User with student-specific fields
export interface StudentUser extends User {
    account_id: string;
    course: string;
    year_level: string;
    is_irregular?: boolean;
    status?: 'active' | 'graduated' | 'dropped';

    phone?: string | null;
    birthday?: string | null;   // ISO date string "YYYY-MM-DD"

    // Decomposed address — old single `address` column was dropped in the 2026_05_11 migration
    address_house_lot_unit?: string | null;
    address_street_name?: string | null;
    address_barangay?: string | null;
    address_municipality_city?: string | null;
    address_province?: string | null;
    address_zip?: string | null;

    guardian_name?: string | null;
    guardian_contact?: string | null;
    emergency_contact?: string | null;
}