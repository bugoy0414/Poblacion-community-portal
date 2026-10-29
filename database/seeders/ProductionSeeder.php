<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
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
        ] as $name) {
            DB::table('categories')->insertOrIgnore([
                'name' => $name,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('site_settings')->insertOrIgnore([
            'id' => 1,
            'barangay_name' => 'Barangay Community Portal',
            'hotline_label' => 'Barangay hotline',
            'hotline_number' => 'PLACEHOLDER',
            'police_number' => '911',
            'office_hours' => 'PLACEHOLDER — set office hours',
            'acknowledgment_target' => 'PLACEHOLDER — set target time',
            'overdue_days' => 3,
            'privacy_notice' => 'Replace this placeholder with a privacy notice reviewed under RA 10173.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            ['fire', 'en'],
            ['help', 'en'],
            ['gun', 'en'],
            ['bleeding', 'en'],
            ['drowning', 'en'],
            ['sunog', 'fil'],
            ['saklolo', 'fil'],
            ['tulong', 'fil'],
            ['may baril', 'fil'],
            ['inaatake', 'fil'],
            ['tabang', 'ceb'],
            ['naay baril', 'ceb'],
            ['nalumos', 'ceb'],
        ] as [$keyword, $language]) {
            DB::table('urgent_keywords')->insertOrIgnore([
                'keyword' => $keyword,
                'language' => $language,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
