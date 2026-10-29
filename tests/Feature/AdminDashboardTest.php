<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_uses_a_separate_shell_from_the_public_site(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('admin-dashboard-shell')
            ->assertSee('Good day, Barangay Administrator')
            ->assertSee('User access')
            ->assertSee('Add a staff account')
            ->assertSee('Portal configuration')
            ->assertDontSee('Main navigation');
    }

    public function test_staff_inbox_uses_dashboard_shell_without_admin_controls(): void
    {
        $admin = $this->createAdmin();
        $now = now();
        $staffId = DB::table('users')->insertGetId([
            'name' => 'Barangay Staff',
            'email' => 'dashboard.staff@example.test',
            'password' => Hash::make('StaffPassword123!'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('user_roles')->insert([
            'user_id' => $staffId,
            'role' => 'staff',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('reports')->insert([
            'reference_no' => 'CR-2026-000009',
            'user_id' => $admin->id,
            'title' => 'Broken streetlight',
            'category' => 'Sanitation',
            'description' => 'Streetlight near the hall is out.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs(User::findOrFail($staffId))
            ->get('/staff')
            ->assertOk()
            ->assertSee('admin-dashboard-shell')
            ->assertSee('STAFF WORKSPACE')
            ->assertSee('Reports inbox')
            ->assertSee('Broken streetlight')
            ->assertSee('Call-resident tasks')
            ->assertDontSee('User &amp; roles')
            ->assertDontSee('Configuration')
            ->assertDontSee('Audit &amp; security');
    }

    public function test_admin_can_create_staff_with_a_fixed_staff_role(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post('/admin/staff', [
                'name' => 'New Staff Member',
                'email' => 'new.staff@example.test',
                'password' => 'StaffPassword123!',
                'password_confirmation' => 'StaffPassword123!',
            ])
            ->assertRedirect();

        $staffId = DB::table('users')->where('email', 'new.staff@example.test')->value('id');
        $this->assertNotNull($staffId);
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $staffId,
            'role' => 'staff',
        ]);
        $this->assertTrue(Hash::check('StaffPassword123!', DB::table('users')->where('id', $staffId)->value('password')));
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'staff_created',
            'entity' => 'user',
            'entity_id' => (string) $staffId,
        ]);
    }

    public function test_non_admin_cannot_create_staff_accounts(): void
    {
        $admin = $this->createAdmin();
        $now = now();
        $residentId = DB::table('users')->insertGetId([
            'name' => 'Resident',
            'email' => 'resident@example.test',
            'password' => Hash::make('ResidentPassword123!'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('user_roles')->insert([
            'user_id' => $residentId,
            'role' => 'resident',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs(User::findOrFail($residentId))
            ->post('/admin/staff', [
                'name' => 'Unauthorized Staff',
                'email' => 'unauthorized.staff@example.test',
                'password' => 'StaffPassword123!',
                'password_confirmation' => 'StaffPassword123!',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized.staff@example.test']);
    }

    public function test_admin_can_deactivate_and_reactivate_staff_without_erasing_audit_history(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin)
            ->post('/admin/staff', [
                'name' => 'Staff To Remove',
                'email' => 'remove.staff@example.test',
                'password' => 'StaffPassword123!',
                'password_confirmation' => 'StaffPassword123!',
            ])
            ->assertRedirect();
        $staffId = (int) DB::table('users')->where('email', 'remove.staff@example.test')->value('id');

        $this->delete("/admin/users/{$staffId}")
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $staffId, 'is_active' => false]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'user_deactivated',
            'entity' => 'user',
            'entity_id' => (string) $staffId,
        ]);
        $this->assertDatabaseHas('user_roles', ['user_id' => $staffId, 'role' => 'staff']);

        $this->post('/staff/login', [
            'email' => 'remove.staff@example.test',
            'password' => 'StaffPassword123!',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($admin)
            ->post("/admin/users/{$staffId}/restore")
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $staffId, 'is_active' => true]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'user_reactivated',
            'entity' => 'user',
            'entity_id' => (string) $staffId,
        ]);
    }

    public function test_admin_can_deactivate_and_reactivate_resident_accounts_without_deleting_reports(): void
    {
        $admin = $this->createAdmin();
        $now = now();
        $residentId = DB::table('users')->insertGetId([
            'name' => 'Resident Account',
            'email' => 'resident.account@example.test',
            'password' => Hash::make('ResidentPassword123!'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('user_roles')->insert([
            'user_id' => $residentId,
            'role' => 'resident',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('reports')->insert([
            'reference_no' => 'CR-2026-000001',
            'user_id' => $residentId,
            'title' => 'Streetlight concern',
            'category' => 'Sanitation',
            'description' => 'A sample concern that belongs to this resident.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($admin)
            ->delete("/admin/users/{$residentId}")
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $residentId, 'is_active' => false]);
        $this->assertDatabaseHas('reports', [
            'reference_no' => 'CR-2026-000001',
            'user_id' => $residentId,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'user_deactivated',
            'entity' => 'user',
            'entity_id' => (string) $residentId,
        ]);

        $this->actingAs($admin)
            ->post("/admin/users/{$residentId}/restore")
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $residentId, 'is_active' => true]);
        $this->assertDatabaseHas('reports', ['reference_no' => 'CR-2026-000001']);
    }

    public function test_non_admin_cannot_deactivate_user_accounts(): void
    {
        $this->createAdmin();
        $now = now();
        $staffId = DB::table('users')->insertGetId([
            'name' => 'Staff Account',
            'email' => 'staff@example.test',
            'password' => Hash::make('StaffPassword123!'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('user_roles')->insert([
            'user_id' => $staffId,
            'role' => 'staff',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $residentId = DB::table('users')->insertGetId([
            'name' => 'Resident',
            'email' => 'resident@example.test',
            'password' => Hash::make('ResidentPassword123!'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('user_roles')->insert([
            'user_id' => $residentId,
            'role' => 'resident',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs(User::findOrFail($residentId))
            ->delete("/admin/users/{$staffId}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $staffId, 'is_active' => true]);
    }

    public function test_admin_accounts_cannot_be_deactivated_through_user_controls(): void
    {
        $admin = $this->createAdmin();
        $anotherAdminId = DB::table('users')->insertGetId([
            'name' => 'Second Administrator',
            'email' => 'second.admin@example.test',
            'password' => Hash::make('AdminPassword123!'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_roles')->insert([
            'user_id' => $anotherAdminId,
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete("/admin/users/{$anotherAdminId}")
            ->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $anotherAdminId, 'is_active' => true]);
    }

    public function test_staff_and_admin_can_print_reports_and_print_is_audited(): void
    {
        $admin = $this->createAdmin();
        $staffId = DB::table('users')->insertGetId([
            'name' => 'Staff Member',
            'email' => 'print.staff@example.test',
            'password' => Hash::make('StaffPassword123!'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_roles')->insert([
            'user_id' => $staffId,
            'role' => 'staff',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $reportId = DB::table('reports')->insertGetId([
            'reference_no' => 'CR-2026-000007',
            'user_id' => $admin->id,
            'title' => 'Public streetlight report',
            'category' => 'Sanitation',
            'description' => 'The streetlight beside the community hall is out.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('report_status_history')->insert([
            'report_id' => $reportId,
            'status' => 'pending',
            'changed_by' => $staffId,
            'note' => 'Received for review.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::findOrFail($staffId))
            ->get("/staff/reports/{$reportId}/print")
            ->assertOk()
            ->assertSee('CR-2026-000007')
            ->assertSee('The streetlight beside the community hall is out.')
            ->assertSee('Received for review.')
            ->assertSee('data-print-report', false);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $staffId,
            'action' => 'printed',
            'entity' => 'report',
            'entity_id' => (string) $reportId,
        ]);

        $this->actingAs($admin)
            ->get("/staff/reports/{$reportId}/print")
            ->assertOk()
            ->assertSee('CR-2026-000007');
    }

    public function test_staff_printing_without_restricted_access_does_not_expose_report_details(): void
    {
        $admin = $this->createAdmin();
        $staffId = DB::table('users')->insertGetId([
            'name' => 'Unassigned Staff',
            'email' => 'restricted.print@example.test',
            'password' => Hash::make('StaffPassword123!'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_roles')->insert([
            'user_id' => $staffId,
            'role' => 'staff',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $reportId = DB::table('reports')->insertGetId([
            'reference_no' => 'CR-2026-000008',
            'user_id' => $admin->id,
            'title' => 'Sensitive incident narrative',
            'category' => 'Sanitation',
            'description' => 'Secret details that must not appear in print.',
            'confidentiality' => 'restricted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::findOrFail($staffId))
            ->get("/staff/reports/{$reportId}/print")
            ->assertOk()
            ->assertSee('Restricted report')
            ->assertSee('You are not authorized to print this report.')
            ->assertDontSee('Sensitive incident narrative')
            ->assertDontSee('Secret details that must not appear in print.')
            ->assertDontSee('data-print-report', false);
    }

    private function createAdmin(): User
    {
        $now = now();
        $userId = DB::table('users')->insertGetId([
            'name' => 'Barangay Administrator',
            'email' => 'admin@example.test',
            'password' => Hash::make('DemoPassword123!'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('user_roles')->insert([
            'user_id' => $userId,
            'role' => 'admin',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('categories')->insert([
            'name' => 'Sanitation',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return User::findOrFail($userId);
    }
}
