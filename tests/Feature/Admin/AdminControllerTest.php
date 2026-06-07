<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRoleEnum;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * AdminControllerTest
 *
 * Updated for role decomposition (2026-06):
 *  - admin_type column removed from the system entirely.
 *  - All active Admin users have equal permissions — no super/manager distinction.
 *  - Admin creates Accounting (requires accounting_type) and Registrar staff only.
 *  - Administrator accounts cannot be created via the web panel.
 *  - Valid departments: Administrator, Accounting, Registrar.
 *  - Route names: users.deactivate / users.reactivate (not admin.users.*)
 */
class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    protected AdminService $adminService;
    protected User $admin;
    protected User $secondAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminService = app(AdminService::class);

        $this->admin = User::factory()->create([
            'role'              => UserRoleEnum::ADMIN,
            'department'        => 'Administrator',
            'is_active'         => true,
            'terms_accepted_at' => now(),
        ]);

        $this->secondAdmin = User::factory()->create([
            'role'              => UserRoleEnum::ADMIN,
            'department'        => 'Administrator',
            'is_active'         => true,
            'terms_accepted_at' => now(),
        ]);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    /** @test */
    public function admin_index_page_returns_successful_response(): void
    {
        $response = $this->actingAs($this->admin)->get(route('users.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Admin/Users/Index'));
    }

    /** @test */
    public function admin_index_page_lists_accounting_and_registrar_staff(): void
    {
        User::factory()->create([
            'role'            => UserRoleEnum::ACCOUNTING,
            'accounting_type' => 'cashier',
            'department'      => 'Accounting',
        ]);
        User::factory()->create([
            'role'       => UserRoleEnum::REGISTRAR,
            'department' => 'Registrar',
        ]);

        $response = $this->actingAs($this->admin)->get(route('users.index'));

        $response->assertStatus(200);
        // setUp created 2 admins; above created 1 accounting + 1 registrar = 4 total staff
        $this->assertEquals(
            4,
            User::whereIn('department', ['Administrator', 'Accounting', 'Registrar'])->count()
        );
    }

    /** @test */
    public function unauthenticated_user_cannot_view_admin_list(): void
    {
        $response = $this->get(route('users.index'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function non_admin_user_cannot_view_admin_list(): void
    {
        $student = User::factory()->create(['role' => UserRoleEnum::STUDENT]);

        $response = $this->actingAs($student)->get(route('users.index'));

        $response->assertStatus(403);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    /** @test */
    public function create_page_returns_successful_response(): void
    {
        $response = $this->actingAs($this->admin)->get(route('users.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Admin/Users/Create'));
    }

    /** @test */
    public function all_admins_can_access_create_staff_page(): void
    {
        // All active admin users have equal permissions.
        // There is no super/manager distinction.
        $response = $this->actingAs($this->secondAdmin)->get(route('users.create'));

        $response->assertStatus(200);
    }

    /** @test */
    public function accounting_staff_can_be_created_with_valid_data(): void
    {
        $data = [
            'first_name'            => 'New',
            'last_name'             => 'Cashier',
            'email'                 => 'cashier@test.com',
            'password'              => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'department'            => 'Accounting',
            'accounting_type'       => 'cashier',
        ];

        $this->actingAs($this->admin)->post(route('users.store'), $data);

        $this->assertDatabaseHas('users', [
            'email'           => 'cashier@test.com',
            'department'      => 'Accounting',
            'accounting_type' => 'cashier',
            'role'            => 'accounting',
        ]);

        $newUser = User::where('email', 'cashier@test.com')->first();
        $this->assertTrue($newUser->hasAcceptedTerms());
        $this->assertEquals($this->admin->id, $newUser->created_by);
    }

    /** @test */
    public function registrar_staff_can_be_created(): void
    {
        $data = [
            'first_name'            => 'New',
            'last_name'             => 'Registrar',
            'email'                 => 'registrar@test.com',
            'password'              => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'department'            => 'Registrar',
        ];

        $this->actingAs($this->admin)->post(route('users.store'), $data);

        $this->assertDatabaseHas('users', [
            'email'           => 'registrar@test.com',
            'department'      => 'Registrar',
            'role'            => 'registrar',
            'accounting_type' => null,
        ]);
    }

    /** @test */
    public function administrator_accounts_cannot_be_created_via_web_panel(): void
    {
        // Sending department=Administrator is remapped to 'Accounting' by the controller.
        // Without accounting_type, validation fails — the account is never created.
        $data = [
            'first_name'            => 'Escalated',
            'last_name'             => 'Admin',
            'email'                 => 'escalated@test.com',
            'password'              => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'department'            => 'Administrator',
        ];

        $response = $this->actingAs($this->admin)->post(route('users.store'), $data);

        $response->assertSessionHasErrors('accounting_type');
        $this->assertDatabaseMissing('users', ['email' => 'escalated@test.com']);
    }

    /** @test */
    public function accounting_creation_fails_without_accounting_type(): void
    {
        $data = [
            'first_name'            => 'New',
            'last_name'             => 'Staff',
            'email'                 => 'staff@test.com',
            'password'              => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'department'            => 'Accounting',
        ];

        $response = $this->actingAs($this->admin)->post(route('users.store'), $data);

        $response->assertSessionHasErrors('accounting_type');
        $this->assertDatabaseMissing('users', ['email' => 'staff@test.com']);
    }

    /** @test */
    public function creation_validates_email_uniqueness(): void
    {
        User::factory()->create(['email' => 'taken@test.com']);

        $data = [
            'first_name'            => 'New',
            'last_name'             => 'Staff',
            'email'                 => 'taken@test.com',
            'password'              => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'department'            => 'Accounting',
            'accounting_type'       => 'cashier',
        ];

        $response = $this->actingAs($this->admin)->post(route('users.store'), $data);

        $response->assertSessionHasErrors('email');
    }

    /** @test */
    public function creation_validates_password_minimum_length(): void
    {
        $data = [
            'first_name'            => 'New',
            'last_name'             => 'Staff',
            'email'                 => 'newstaff@test.com',
            'password'              => 'weak',
            'password_confirmation' => 'weak',
            'department'            => 'Accounting',
            'accounting_type'       => 'bookkeeper',
        ];

        $response = $this->actingAs($this->admin)->post(route('users.store'), $data);

        $response->assertSessionHasErrors('password');
    }

    // ── Show / Edit ───────────────────────────────────────────────────────────

    /** @test */
    public function show_page_displays_accounting_staff_details(): void
    {
        $staff = User::factory()->create([
            'role'            => UserRoleEnum::ACCOUNTING,
            'accounting_type' => 'disbursing_officer',
            'department'      => 'Accounting',
            'created_by'      => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('users.show', $staff->id));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Admin/Users/Show'));
    }

    /** @test */
    public function show_page_displays_registrar_staff_details(): void
    {
        $staff = User::factory()->create([
            'role'       => UserRoleEnum::REGISTRAR,
            'department' => 'Registrar',
        ]);

        $response = $this->actingAs($this->admin)->get(route('users.show', $staff->id));

        $response->assertStatus(200);
    }

    /** @test */
    public function edit_page_returns_successful_response_for_accounting_staff(): void
    {
        $staff = User::factory()->create([
            'role'            => UserRoleEnum::ACCOUNTING,
            'accounting_type' => 'bookkeeper',
            'department'      => 'Accounting',
        ]);

        $response = $this->actingAs($this->admin)->get(route('users.edit', $staff->id));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Admin/Users/Edit'));
    }

    /** @test */
    public function edit_page_is_blocked_for_administrator_accounts(): void
    {
        // Admin accounts cannot be edited via the staff panel.
        $anotherAdmin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'department' => 'Administrator',
        ]);

        $response = $this->actingAs($this->admin)->get(route('users.edit', $anotherAdmin->id));

        $response->assertStatus(403);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    /** @test */
    public function accounting_staff_can_be_updated(): void
    {
        $staff = User::factory()->create([
            'role'            => UserRoleEnum::ACCOUNTING,
            'accounting_type' => 'cashier',
            'department'      => 'Accounting',
        ]);

        $data = [
            'first_name'      => 'Updated',
            'last_name'       => 'Name',
            'email'           => $staff->email,
            'department'      => 'Accounting',
            'accounting_type' => 'disbursing_officer',
        ];

        $this->actingAs($this->admin)->put(route('users.update', $staff->id), $data);

        $staff->refresh();
        $this->assertEquals('Updated', $staff->first_name);
        $this->assertEquals('disbursing_officer', $staff->accounting_type?->value);
        $this->assertEquals($this->admin->id, $staff->updated_by);
    }

    /** @test */
    public function password_can_be_updated_optionally(): void
    {
        $staff = User::factory()->create([
            'role'            => UserRoleEnum::ACCOUNTING,
            'accounting_type' => 'bookkeeper',
            'department'      => 'Accounting',
        ]);

        $oldPassword = $staff->password;

        $this->actingAs($this->admin)->put(route('users.update', $staff->id), [
            'first_name'            => 'Updated',
            'last_name'             => 'Name',
            'email'                 => $staff->email,
            'department'            => 'Accounting',
            'accounting_type'       => 'bookkeeper',
            'password'              => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $staff->refresh();
        $this->assertNotEquals($oldPassword, $staff->password);
    }

    /** @test */
    public function admin_cannot_update_administrator_accounts(): void
    {
        // Administrator accounts are not editable via the staff panel.
        // UserPolicy::update() blocks: target department must be Accounting or Registrar.
        $anotherAdmin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'department' => 'Administrator',
        ]);

        $response = $this->actingAs($this->admin)->put(route('users.update', $anotherAdmin->id), [
            'first_name' => 'Hacked',
            'last_name'  => 'Name',
            'email'      => $anotherAdmin->email,
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function student_cannot_update_staff(): void
    {
        $staff   = User::factory()->create([
            'role'            => UserRoleEnum::ACCOUNTING,
            'accounting_type' => 'cashier',
            'department'      => 'Accounting',
        ]);
        $student = User::factory()->create(['role' => UserRoleEnum::STUDENT]);

        $response = $this->actingAs($student)->put(route('users.update', $staff->id), [
            'first_name' => 'Hacked',
            'last_name'  => 'Name',
            'email'      => $staff->email,
        ]);

        $response->assertStatus(403);
    }

    // ── Deactivate / Reactivate ───────────────────────────────────────────────

    /** @test */
    public function deactivate_sets_is_active_to_false(): void
    {
        $staff = User::factory()->create([
            'role'            => UserRoleEnum::ACCOUNTING,
            'accounting_type' => 'cashier',
            'department'      => 'Accounting',
            'is_active'       => true,
        ]);

        // Route name is users.deactivate, not admin.users.deactivate
        $this->actingAs($this->admin)->post(route('users.deactivate', $staff->id));

        $staff->refresh();
        $this->assertFalse($staff->is_active);
    }

    /** @test */
    public function admin_cannot_deactivate_own_account(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('users.deactivate', $this->admin->id));

        $response->assertStatus(403);
        $this->admin->refresh();
        $this->assertTrue($this->admin->is_active);
    }

    /** @test */
    public function reactivate_sets_is_active_to_true(): void
    {
        $staff = User::factory()->create([
            'role'            => UserRoleEnum::ACCOUNTING,
            'accounting_type' => 'bookkeeper',
            'department'      => 'Accounting',
            'is_active'       => false,
        ]);

        // Route name is users.reactivate, not admin.users.reactivate
        $this->actingAs($this->admin)->post(route('users.reactivate', $staff->id));

        $staff->refresh();
        $this->assertTrue($staff->is_active);
    }

    /** @test */
    public function deactivated_admin_cannot_login(): void
    {
        $inactiveAdmin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'department' => 'Administrator',
            'is_active'  => false,
            'password'   => bcrypt('password123'),
        ]);

        $this->post('/login', [
            'email'    => $inactiveAdmin->email,
            'password' => 'password123',
        ]);

        $this->assertGuest();
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    /** @test */
    public function delete_is_forbidden(): void
    {
        $staff = User::factory()->create([
            'role'            => UserRoleEnum::ACCOUNTING,
            'accounting_type' => 'cashier',
            'department'      => 'Accounting',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('users.destroy', $staff->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $staff->id]);
    }

    // ── Security ──────────────────────────────────────────────────────────────

    /** @test */
    public function inactive_admin_cannot_access_admin_pages(): void
    {
        $inactiveAdmin = User::factory()->create([
            'role'       => UserRoleEnum::ADMIN,
            'department' => 'Administrator',
            'is_active'  => false,
        ]);

        $response = $this->actingAs($inactiveAdmin)->get(route('users.index'));

        $response->assertStatus(403);
    }

    // ── Audit fields ──────────────────────────────────────────────────────────

    /** @test */
    public function audit_fields_are_populated_on_creation(): void
    {
        $data = [
            'first_name'            => 'Audit',
            'last_name'             => 'Test',
            'email'                 => 'audit@test.com',
            'password'              => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'department'            => 'Accounting',
            'accounting_type'       => 'bookkeeper',
        ];

        $this->actingAs($this->admin)->post(route('users.store'), $data);

        $newStaff = User::where('email', 'audit@test.com')->first();
        $this->assertEquals($this->admin->id, $newStaff->created_by);
        $this->assertNotNull($newStaff->created_at);
    }

    /** @test */
    public function updated_by_is_set_on_modification(): void
    {
        $staff = User::factory()->create([
            'role'            => UserRoleEnum::ACCOUNTING,
            'accounting_type' => 'cashier',
            'department'      => 'Accounting',
        ]);

        $this->travel(1)->seconds();

        $this->actingAs($this->secondAdmin)->put(route('users.update', $staff->id), [
            'first_name'      => 'Updated',
            'last_name'       => 'Staff',
            'email'           => $staff->email,
            'department'      => 'Accounting',
            'accounting_type' => 'cashier',
        ]);

        $staff->refresh();
        $this->assertEquals($this->secondAdmin->id, $staff->updated_by);
    }

    /** @test */
    public function creation_sets_created_by_to_acting_admin(): void
    {
        $data = [
            'first_name'            => 'Log',
            'last_name'             => 'Test',
            'email'                 => 'log@test.com',
            'password'              => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'department'            => 'Accounting',
            'accounting_type'       => 'disbursing_officer',
        ];

        $this->actingAs($this->admin)->post(route('users.store'), $data);

        $newStaff = User::where('email', 'log@test.com')->first();
        $this->assertEquals($this->admin->id, $newStaff->created_by);
    }
}