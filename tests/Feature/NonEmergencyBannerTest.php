<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NonEmergencyBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_the_redesigned_notice_and_home_sections(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Need help right now? Go to your barangay hall or call')
            ->assertSee('This portal is for everyday concerns and is checked during office hours.')
            ->assertSee('Speak up.')
            ->assertSee('See it through.')
            ->assertSee('Sample report')
            ->assertSee('Streetlights')
            ->assertSee('Your name stays with you')
            ->assertSee('Community care, together')
            ->assertSee('Share a concern')
            ->assertSee('Check a report')
            ->assertDontSee('non-emergency-banner');

        $html = $response->getContent();
        $this->assertSame(4, substr_count($html, 'data-status-step'));
        $this->assertLessThan(strpos($html, '<section class="home-hero">'), strpos($html, '<section class="home-notice"'));
    }

    public function test_category_quick_pick_survives_the_required_notice(): void
    {
        $now = now();
        $userId = DB::table('users')->insertGetId([
            'name' => 'Demo Resident',
            'email' => 'resident@example.test',
            'password' => Hash::make('DemoPassword123!'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('user_roles')->insert([
            'user_id' => $userId,
            'role' => 'resident',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('categories')->insertOrIgnore([
            'name' => 'Sanitation',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs(\App\Models\User::findOrFail($userId))
            ->get('/reports/new?category=Sanitation')
            ->assertOk()
            ->assertSee('name="category" value="Sanitation"', false);

        $this->post('/reports/notice', [
            'non_emergency_acknowledged' => '1',
            'category' => 'Sanitation',
        ])->assertRedirect('/reports/new?category=Sanitation');

        $this->get('/reports/new?category=Sanitation')
            ->assertOk()
            ->assertSee('<option value="Sanitation" selected>Sanitation</option>', false);
    }
}
