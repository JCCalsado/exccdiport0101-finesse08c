# Admin Type Removal Test Cleanup

## Summary

The application should remain on the current `department`-based authorization model.
The legacy `admin_type` concept (`super` / `manager` / `operator`) is retired and should not be restored to satisfy stale tests.

The source changes that were temporarily introduced to make stale tests pass have been reverted.

## What changed back

- `routes/web.php`
  - Removed duplicate `admin.users.*` route name registrations.
- `app/Services/AdminService.php`
  - Restored strict `department` validation in `deactivateAdmin()`.
- `app/Http/Controllers/PaymentController.php`
  - Restored the original non-nullable PayMongo key properties.
- Temporary migration `2026_07_06_000000_add_admin_type_back_to_users_table.php` has been removed.

## Current problem

The failing tests are now stale relative to the application model, not indicative of a current production bug.
They are asserting old `admin_type` policy semantics that no longer exist.

## Files to clean up

### Primary targets

- `tests/Feature/Policies/UserPolicyTest.php`
- `tests/Feature/Admin/AdminWorkflowIntegrationTest.php`
- `tests/Feature/Security/AuthorizationSecurityTest.php`
- `tests/Feature/Security/AuthenticationSecurityTest.php`
- `tests/Feature/Security/DataProtectionSecurityTest.php`
- `tests/Feature/Security/InputValidationSecurityTest.php`

### Why these files

These tests still contain assertions or fixtures that depend on:

- `admin_type`
- `manager`
- `operator`
- `hasPermission()` behavior tied to admin subtype
- `admin.users.deactivate` / `admin.users.reactivate` route names
- `users.destroy` route name expectations

## Recommended cleanup approach

### 1. Audit and classify each assertion

For each failing assertion in the target test files, decide one of the following:

- `Rewrite` — if the behavior is still valid under `department`-based authorization.
- `Delete` — if the behavior is tied to the retired `admin_type` model and is no longer meaningful.
- `Keep` — if the test is still valid and should remain unchanged.

### 2. Remove deprecated fixtures

Replace `admin_type` fixture values with department-based setup only, for example:

- `department` => `Accounting`
- `department` => `Registrar`
- `department` => `Administrator`

Do not create stub users with `admin_type` values.

### 3. Replace `hasPermission()` expectations

`User::hasPermission()` currently returns `true` for any active admin.

Any assertions that encode a permission hierarchy like:

- `manage_fees`
- `approve_payments`
- `system_settings`

should be removed or rewritten to reflect the current rule: active admins have equal permissions.

### 4. Clean up route expectations

Test code should use the current route names, not stale `admin.users.*` names.
The current app uses `users.deactivate`, `users.reactivate`, and `users.destroy`.

### 5. Verify only the active admin model

Ensure tests that expect `manager` or `operator` to be blocked on admin functions instead assert based on `department` and `is_active`.

## Next action

1. Open and edit the listed test files.
2. Remove or rewrite all `admin_type`-dependent checks.
3. Confirm behavior with the current `UserPolicy` and `User::hasPermission()` implementation.
4. Re-run the test suite.

## Notes

- This cleanup is a maintenance and alignment task, not a bug fix.
- If the original product decision was to keep `admin_type`, then a different task is required: restore the subtype model and reintroduce policy logic.
- If the decision is to keep `department`-only authorization, then the failing tests should be treated as deprecated coverage.
