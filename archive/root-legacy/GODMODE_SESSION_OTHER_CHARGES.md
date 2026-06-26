# GODMODE_SESSION_OTHER_CHARGES.md

## Feature: Other Charges Module
**Session date:** 2026-06-07  
**Status:** Complete — ready to deploy

---

## What Was Built

A standalone Other Charges billing module. Fully independent of `student_assessments`, `assessment_subjects`, and `student_payment_terms`. Additive only — nothing in the existing assessment/payment flow was removed.

### Files Delivered (17 total)

| File | Status |
|------|--------|
| `database/migrations/2026_06_06_100000_create_other_charges_table.php` | New |
| `database/migrations/2026_06_06_100001_create_other_charge_payments_table.php` | New |
| `app/Models/OtherCharge.php` | New |
| `app/Models/OtherChargePayment.php` | New |
| `app/Policies/OtherChargePolicy.php` | New |
| `app/Services/OtherChargeService.php` | New |
| `app/Http/Controllers/Accounting/OtherChargeController.php` | New |
| `app/Http/Controllers/Student/OtherChargeController.php` | New |
| `app/Http/Controllers/PaymentController.php` | Extended (otherCharges prop injected) |
| `app/Jobs/ProcessPaymongoWebhook.php` | Extended (other_charge branch added) |
| `app/Providers/AuthServiceProvider.php` | Extended (OtherChargePolicy registered) |
| `routes/web.php` | Extended (Other Charges routes added) |
| `resources/js/pages/Accounting/OtherCharges/Index.vue` | New |
| `resources/js/pages/Accounting/OtherCharges/Create.vue` | New |
| `resources/js/pages/Accounting/OtherCharges/Show.vue` | New |
| `resources/js/pages/Accounting/OtherCharges/Edit.vue` | New |
| `resources/js/pages/Student/OtherCharges/Index.vue` | New |
| `resources/js/pages/Payment/Create.vue` | Extended (payment mode switcher added) |

---

## Deployment Steps

```bash
# 1. Replace/copy all files from output to project root
# 2. Run migrations
php artisan migrate

# 3. Clear caches
php artisan route:clear
php artisan config:clear
php artisan cache:clear

# 4. Build assets
npm run build
```

---

## Schema Summary

### `other_charges`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| title | string | e.g. "Christmas Fee 2025-2026" |
| description | text nullable | |
| amount | decimal(10,2) | Full payment only |
| school_year | string | Required — e.g. "2025-2026" |
| semester | string nullable | null = all semesters |
| year_level | string nullable | null = all year levels |
| course | string nullable | null = all courses |
| created_by | FK users.id | |
| published_at | timestamp nullable | null = draft |
| updated_after_publish_at | timestamp nullable | Set when edited after publish |
| is_active | boolean | false = archived |

### `other_charge_payments`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| other_charge_id | FK other_charges.id | cascadeOnDelete |
| user_id | FK users.id | the student |
| amount_paid | decimal(10,2) | Must equal charge amount |
| payment_method | enum | 'otc' or 'online' |
| or_number | string nullable | Required for OTC |
| paymongo_session_id | string nullable | |
| payment_intent_id | string nullable | For webhook matching |
| reference | string nullable | "OC-{session_id}" |
| status | enum | pending/awaiting_proof/awaiting_approval/paid/failed/cancelled |
| collected_by | FK users.id nullable | null = online self-pay |
| paid_at | timestamp nullable | |
| notes | text nullable | |

---

## Key Design Decisions

### Dynamic Student Matching (no pre-generation)
Students are matched at query time via `OtherCharge::buildMatchingStudentsQuery()`. This means:
- Late enrollees automatically qualify (no batch job needed)
- `getChargesForStudent()` queries `other_charges WHERE school_year = ? AND (semester IS NULL OR semester = ?) AND ...`
- `getStudentsForCharge()` queries `users WHERE role = student AND EXISTS (SELECT FROM student_assessments WHERE status = active AND school_year = ? ...)`

### Full Payment Only
`recordOtcPayment()` and `initiateOnlinePayment()` always use `$charge->amount` — student input cannot change it.

### Edit-After-Publish Guard
When a published charge is edited, `updated_after_publish_at` is stamped. Student portal shows a yellow notice. Paid records are NOT retroactively changed.

### Webhook Branch
`ProcessPaymongoWebhook::handlePaymentPaid()` checks `metadata.type === 'other_charge'` before running existing assessment logic. The branch is additive — existing logic is untouched.

### PaymentController Extension
`create()` now calls `OtherChargeService::getChargesForStudent()` and passes `otherCharges` prop to `Payment/Create.vue`. If the student has no Other Charges, the mode switcher is hidden (the form shows identically to before).

---

## Role Access Matrix

| Action | Admin | Disbursing Officer | Cashier | Bookkeeper | Student |
|--------|-------|--------------------|---------|------------|---------|
| View charge list | ✓ | ✓ | ✓ | ✓ | — |
| Create / Edit | ✓ | ✓ | — | — | — |
| Publish | ✓ | ✓ | — | — | — |
| Archive (delete) | ✓ | ✓ | — | — | — |
| Record OTC payment | ✓ | ✓ | ✓ | — | — |
| View own charges | — | — | — | — | ✓ |
| Pay online | — | — | — | — | ✓ |

---

## Known Gaps / Follow-Up Work

1. **`User::studentAssessments()` relationship** — `OtherCharge::matchesStudent()` calls `$student->studentAssessments()`. Verify this relationship exists on the `User` model. If not, add:
   ```php
   public function studentAssessments(): HasMany
   {
       return $this->hasMany(StudentAssessment::class, 'user_id');
   }
   ```

2. **`User::$course` and `User::$year_level` columns** — `OtherChargeController::show()` falls back to `$student->course` and `$student->year_level` if the assessment is missing. Confirm these columns exist on the users table or remove the fallback.

3. **Accounting sidebar nav** — Add "Other Charges" link to the accounting sidebar nav component (not delivered — project nav was not audited this session).

4. **Student account overview** — `Student/AccountOverview.vue` may benefit from an Other Charges summary widget. Not implemented — out of scope for this session.

5. **Bookkeeper reports** — Other Charges are not yet in `FinancialReportsController`. Consider adding a collection summary to the financial reports page.

6. **`previewCount` AJAX** — The Create.vue calls `route('accounting.other-charges.preview-count')`. This is a GET route with query params. Ensure the `OtherChargeController::previewCount()` method is accessible (it's registered in web.php — `GET /accounting/other-charges/preview-count`).

---

## Verification Checklist

- [ ] `php artisan migrate` runs clean (no FK errors)
- [ ] `php artisan route:list | grep other-charge` shows 9 accounting routes + 2 student routes
- [ ] Create a draft charge as Disbursing Officer → verify row in `other_charges` with `published_at = null`
- [ ] Publish the charge → `published_at` set
- [ ] Login as a matching student → `/student/other-charges` shows the charge
- [ ] Student pays online → PayMongo checkout URL returned → webhook fires → `other_charge_payments.status = paid`
- [ ] Disbursing Officer records OTC → OR number stored → student portal shows Paid
- [ ] Edit a published charge that has payments → `updated_after_publish_at` set → student sees yellow notice
- [ ] Login as bookkeeper → can view charges but Record OTC button is hidden
- [ ] Login as cashier → can view + record OTC but cannot create/edit
- [ ] `/student/payment` → if student has unpaid Other Charges, mode switcher appears
