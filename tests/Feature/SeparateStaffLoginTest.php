<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeparateStaffLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_login_page_does_not_link_to_the_staff_portal(): void
    {
        $this->get('/auth')
            ->assertOk()
            ->assertDontSee('/staff/login');
    }

    public function test_admin_is_rejected_from_public_login_but_can_use_staff_portal(): void
    {
        $adminId = $this->createAccount('admin@example.test', 'admin');

        $this->from('/auth')->post('/auth/login', [
            'email' => 'admin@example.test',
            'password' => 'DemoPassword123!',
        ])->assertRedirect('/auth')
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->get('/staff/login')
            ->assertOk()
            ->assertSee('Staff portal sign in.')
            ->assertSee('action="/staff/login"', false);

        $this->from('/staff/login')->post('/staff/login', [
            'email' => 'admin@example.test',
            'password' => 'DemoPassword123!',
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs(\App\Models\User::findOrFail($adminId));
    }

    public function test_resident_cannot_sign_in_through_the_staff_portal(): void
    {
        $this->createAccount('resident@example.test', 'resident');

        $this->from('/staff/login')->post('/staff/login', [
            'email' => 'resident@example.test',
            'password' => 'DemoPassword123!',
        ])->assertRedirect('/staff/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    private function createAccount(string $email, string $role): int
    {
        $now = now();
        $userId = DB::table('users')->insertGetId([
            'name' => ucfirst($role) . ' Account',
            'email' => $email,
            'password' => Hash::make('DemoPassword123!'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_roles')->insert([
            'user_id' => $userId,
            'role' => $role,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $userId;
    }
}
