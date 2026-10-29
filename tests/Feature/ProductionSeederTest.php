<?php

namespace Tests\Feature;

use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seeder_is_repeatable_and_does_not_create_demo_accounts_or_reports(): void
    {
        $this->seed(ProductionSeeder::class);
        DB::table('categories')->where('name', 'Sanitation')->update(['is_active' => false]);
        DB::table('urgent_keywords')->where('keyword', 'fire')->update(['language' => 'custom']);
        $this->seed(ProductionSeeder::class);

        $this->assertDatabaseCount('categories', 7);
        $this->assertDatabaseCount('site_settings', 1);
        $this->assertDatabaseCount('urgent_keywords', 13);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseHas('categories', ['name' => 'Sanitation', 'is_active' => false]);
        $this->assertDatabaseHas('urgent_keywords', ['keyword' => 'fire', 'language' => 'custom']);
    }
}
