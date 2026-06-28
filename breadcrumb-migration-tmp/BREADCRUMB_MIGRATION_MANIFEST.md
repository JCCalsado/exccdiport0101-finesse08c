# Breadcrumb Migration — Manifest

## Root-cause fix (do this first, everything else depends on it)

**`resources/js/layouts/AppLayout.vue`**
Fixed a prop-name mismatch: the wrapper was passing `:items="breadcrumbs"` down to
`AppSidebarLayout.vue`, which only declares a `breadcrumbs` prop (not `items`). This
silently dropped breadcrumb data on every page using the "correct" pattern — 12 pages
were already affected before this migration even started. Changed to `:breadcrumbs="breadcrumbs"`.

## Mechanical migration (46 files)

Every file below had the exact same anti-pattern:
```vue
<script>
import Breadcrumbs from '@/components/Breadcrumbs.vue';
...
</script>
<template>
    <AppLayout>
        ...
        <Breadcrumbs :items="breadcrumbs" />
        ...
```

Changed to the canonical pattern used by `AppLayout`'s own `breadcrumbs` prop:
```vue
<script>
// Breadcrumbs.vue import removed
...
</script>
<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        ...
        <!-- inline <Breadcrumbs> render removed -->
        ...
```

Three of these (`Subjects/Create.vue`, `Subjects/Edit.vue`, `Subjects/Index.vue`) had an
extra `title="..."` attribute on `<AppLayout>` — preserved, with `:breadcrumbs` inserted
ahead of it.

No `breadcrumbs` variable (computed or const) was touched — every file already defined
one correctly; it just wasn't wired to the layout.

Files:
- resources/js/pages/Accounting/AutoAssess.vue
- resources/js/pages/Accounting/CurriculumPreset/Index.vue
- resources/js/pages/Accounting/CurriculumPreset/Subjects.vue
- resources/js/pages/Accounting/Dashboard.vue
- resources/js/pages/Accounting/FeeSettings.vue
- resources/js/pages/Accounting/FinancialReports.vue
- resources/js/pages/Accounting/Index.vue
- resources/js/pages/Accounting/PresetSubjects.vue
- resources/js/pages/Accounting/Show.vue
- resources/js/pages/Admin/Dashboard.vue
- resources/js/pages/Admin/Notifications/Form.vue
- resources/js/pages/Admin/Notifications/Index.vue
- resources/js/pages/Admin/Notifications/Show.vue
- resources/js/pages/Admin/PaymentTermsManagement.vue
- resources/js/pages/Admin/Users/Create.vue
- resources/js/pages/Admin/Users/Edit.vue
- resources/js/pages/Admin/Users/Index.vue   (also includes the name-spacing/contrast fix from the prior session — see below)
- resources/js/pages/Admin/Users/Show.vue
- resources/js/pages/Approvals/Index.vue
- resources/js/pages/Approvals/Show.vue
- resources/js/pages/Dashboard.vue
- resources/js/pages/Fees/Create.vue
- resources/js/pages/Fees/Edit.vue
- resources/js/pages/Fees/Index.vue
- resources/js/pages/Fees/Show.vue
- resources/js/pages/Notifications/Index.vue
- resources/js/pages/Payment/Create.vue
- resources/js/pages/Payment/ProofUpload.vue
- resources/js/pages/Student/AccountOverview.vue
- resources/js/pages/Student/Dashboard.vue
- resources/js/pages/Student/OtherCharges/Index.vue
- resources/js/pages/Student/OtherCharges/ProofUpload.vue
- resources/js/pages/StudentFees/Create.vue
- resources/js/pages/StudentFees/CreateStudent.vue
- resources/js/pages/StudentFees/Edit.vue
- resources/js/pages/StudentFees/EditStudent.vue
- resources/js/pages/StudentFees/Show.vue
- resources/js/pages/Students/Archive.vue
- resources/js/pages/Students/Index.vue
- resources/js/pages/Students/WorkflowHistory.vue
- resources/js/pages/Subjects/Create.vue
- resources/js/pages/Subjects/Edit.vue
- resources/js/pages/Subjects/Index.vue
- resources/js/pages/Transactions/Index.vue
- resources/js/pages/Transactions/Show.vue
- resources/js/pages/Workflows/Show.vue

## Non-mechanical fix (1 file)

**`resources/js/pages/Transactions/Create.vue`**
This page had no breadcrumb implementation at all — no `Breadcrumbs` component, no
`breadcrumbs` variable, nothing. Added one to match the sibling pages' convention:
`Dashboard > Transactions (→ transactions.index) > Create`.

## Deliberately left untouched

**`resources/js/pages/Accounting/CurriculumPreset/Show.vue`**
Also has a bare `<AppLayout>` with no breadcrumb — but its own header comment confirms
this is an intentional dead-end stub. The controller always redirects before this
component renders in real usage; it only exists to satisfy Inertia's routing requirement
and show a brief loading spinner if the redirect is ever slow. Adding a breadcrumb here
would be decorating a screen no user ever actually sees. Left alone.

**`resources/js/pages/StudentFees/Index.vue`**
Already migrated in the prior session — not touched again here.

## Verification run after migration

```
grep -rn "<AppLayout" resources/js/pages --include="*.vue" | grep -v ':breadcrumbs='
→ resources/js/pages/Accounting/CurriculumPreset/Show.vue   (intentional, see above)

grep -rln "import Breadcrumbs" resources/js/pages --include="*.vue"
→ (no results — fully clean)

grep -rl '<AppLayout[^>]*:breadcrumbs=' resources/js/pages --include="*.vue" | wc -l
→ 60   (12 originally correct + 1 fixed last session + 46 fixed this session + 1 newly added = 60)

grep -rln "Breadcrumbs.vue" resources/js --include="*.vue"
→ resources/js/components/AppHeader.vue
→ resources/js/components/AppSidebarHeader.vue
(the standalone <Breadcrumbs> component is now used in exactly the one correct place —
 inside the header chrome itself — instead of being duplicated across 59 page files)
```
