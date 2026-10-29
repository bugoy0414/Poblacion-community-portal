<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $categories = [
        'Animal Concerns',
        'Electricity',
        'Fire/Emergency',
        'Flooding',
        'Noise',
        'Other',
        'Peace & Order',
        'Public Facilities',
        'Public Health',
        'Road/Infrastructure',
        'Sanitation',
        'Social Services',
        'Water Supply',
    ];

    public function up(): void
    {
        $now = now();

        DB::table('categories')->insertOrIgnore(array_map(
            fn (string $name): array => [
                'name' => $name,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $this->categories,
        ));
    }

    public function down(): void
    {
        DB::table('categories')->whereIn('name', $this->categories)->delete();
    }
};
