<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar, SidebarContent, SidebarFooter, SidebarHeader,
    SidebarMenu, SidebarMenuButton, SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Banknote, BarChart3, Bell, BookOpen, CheckCircle2, ClipboardList,
    CreditCard, GraduationCap, History, LayoutGrid, LayoutTemplate,
    Receipt, Settings, Users, Zap,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const safeRoute = (name: string, params?: any): string => {
    try { return route(name, params); } catch { return '#'; }
};

const page = usePage();

const userRole    = computed(() => (page.props.auth as any)?.user?.role           ?? 'student');
const accountingType = computed(() => (page.props.auth as any)?.user?.accounting_type ?? null);
const registrationCounts = computed(() => (page.props.auth as any)?.user?.registration_counts ?? { registrar_queue: 0, finance_queue: 0 });

// ── Accounting sub-role helpers ──────────────────────────────────────────────
const isDisbursingOfficer = computed(() => accountingType.value === 'disbursing_officer');
const isCashier           = computed(() => accountingType.value === 'cashier');
const isBookkeeper        = computed(() => accountingType.value === 'bookkeeper');

const mainNavItems = computed<NavItem[]>(() => {
    const role = userRole.value;

    // ── Student ──────────────────────────────────────────────────────────────
    if (role === 'student') {
        return [
            { title: 'Dashboard',           href: safeRoute('student.dashboard'),  icon: LayoutGrid },
            { title: 'My Account',          href: safeRoute('student.account'),    icon: CreditCard },
            { title: 'Transaction History', href: safeRoute('transactions.index'), icon: History    },
        ];
    }

    // ── Admin ────────────────────────────────────────────────────────────────
    if (role === 'admin') {
        const financeQueueCount    = registrationCounts.value.finance_queue   ?? 0;
        const registrarQueueCount  = registrationCounts.value.registrar_queue ?? 0;
        const totalPending         = financeQueueCount + registrarQueueCount;

        return [
            { title: 'Dashboard',          href: safeRoute('admin.dashboard'),                     icon: LayoutGrid    },
            { title: 'Users',              href: safeRoute('users.index'),                         icon: Users         },
            { title: 'Student Overview',   href: safeRoute('student-fees.index'),                  icon: GraduationCap },
            { title: 'Student Archive',    href: safeRoute('students.archive'),                    icon: History       },
            { title: 'Financial Reports',  href: safeRoute('accounting.financial-reports'),        icon: BarChart3     },
            { title: 'Curriculum Presets', href: safeRoute('accounting.curriculum-presets.index'), icon: LayoutTemplate },
            { title: 'Subjects',           href: safeRoute('accounting.subjects.index'),           icon: BookOpen      },
            {
                title: 'Registration Approvals',
                href:  safeRoute('accounting.registrations.index'),
                icon:  ClipboardList,
                badge: totalPending > 0 ? String(totalPending) : undefined,
            },
            {
                title: 'Registrar Queue',
                href:  safeRoute('registrar.registrations.index'),
                icon:  ClipboardList,
                badge: registrarQueueCount > 0 ? String(registrarQueueCount) : undefined,
            },
        ];
    }

    // ── Registrar ────────────────────────────────────────────────────────────
    if (role === 'registrar') {
        const queueCount = registrationCounts.value.registrar_queue ?? 0;

        return [
            { title: 'Dashboard', href: safeRoute('registrar.dashboard'), icon: LayoutGrid },
            {
                title: 'Registration Queue',
                href:  safeRoute('registrar.registrations.index'),
                icon:  ClipboardList,
                badge: queueCount > 0 ? String(queueCount) : undefined,
            },
            { title: 'Curriculum Presets', href: safeRoute('accounting.curriculum-presets.index'), icon: LayoutTemplate },
            { title: 'Subjects',           href: safeRoute('accounting.subjects.index'),           icon: BookOpen      },
            { title: 'Notifications',      href: safeRoute('accounting.notifications.index'),      icon: Bell          },
        ];
    }

    // ── Accounting (sub-role-aware) ──────────────────────────────────────────
    if (role === 'accounting') {
        const financeQueue = registrationCounts.value.finance_queue ?? 0;
        const items: NavItem[] = [
            { title: 'Dashboard', href: safeRoute('accounting.dashboard'), icon: Banknote },
        ];

        // Disbursing Officer + Cashier: see fee management
        if (isDisbursingOfficer.value || isCashier.value) {
            items.push({ title: 'Student Fee Management', href: safeRoute('student-fees.index'), icon: Receipt });
            items.push({ title: 'Auto Assessment', href: safeRoute('accounting.auto-assess.index'), icon: Zap });
        }

        // Disbursing Officer only: payment approvals, fee settings, registration approvals,
        //   curriculum presets (read context for assessment creation), subjects (read context)
        if (isDisbursingOfficer.value) {
            items.push(
                { title: 'Payment Approvals', href: safeRoute('approvals.index'), icon: CheckCircle2 },
                {
                    title: 'Registration Approvals',
                    href:  safeRoute('accounting.registrations.index'),
                    icon:  ClipboardList,
                    badge: financeQueue > 0 ? String(financeQueue) : undefined,
                },
                { title: 'Fee Settings',       href: safeRoute('accounting.fee-settings.index'),           icon: Settings       },
                { title: 'Curriculum Presets', href: safeRoute('accounting.curriculum-presets.index'),     icon: LayoutTemplate },
                { title: 'Subjects',           href: safeRoute('accounting.subjects.index'),               icon: BookOpen       },
            );
        }

        // Bookkeeper + Disbursing Officer: financial reports
        if (isBookkeeper.value || isDisbursingOfficer.value) {
            items.push({ title: 'Financial Reports', href: safeRoute('accounting.financial-reports'), icon: BarChart3 });
        }

        return items;
    }

    return [];
});

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>