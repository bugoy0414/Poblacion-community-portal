<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $categoryNames = [
            'Peace & Order',
            'Sanitation',
            'Road/Infrastructure',
            'Flooding',
            'Noise',
            'Fire/Emergency',
            'Other',
        ];
        foreach ($categoryNames as $name) {
            DB::table('categories')->updateOrInsert(['name' => $name], ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]);
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

        $keywords = [
            ['fire', 'en'], ['help', 'en'], ['gun', 'en'], ['bleeding', 'en'], ['drowning', 'en'],
            ['sunog', 'fil'], ['saklolo', 'fil'], ['tulong', 'fil'], ['may baril', 'fil'], ['inaatake', 'fil'],
            ['tabang', 'ceb'], ['naay baril', 'ceb'], ['nalumos', 'ceb'],
        ];
        foreach ($keywords as [$keyword, $language]) {
            DB::table('urgent_keywords')->updateOrInsert(['keyword' => $keyword], ['language' => $language, 'updated_at' => now(), 'created_at' => now()]);
        }

        $resident = User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Demo Resident', 'password' => Str::random(32), 'contact_number' => '09XX-XXX-XXXX', 'address' => 'Purok 1 (demo)'],
        );
        DB::table('user_roles')->insertOrIgnore([
            'user_id' => $resident->id, 'role' => 'resident', 'created_at' => now(), 'updated_at' => now(),
        ]);

        if (app()->environment(['local', 'testing'])) {
            foreach ([
                [
                    'name' => 'Barangay Demo Admin',
                    'email' => 'admin@example.test',
                    'password' => env('BARANGAY_DEMO_ADMIN_PASSWORD', 'BarangayAdmin123!'),
                    'role' => 'admin',
                ],
                [
                    'name' => 'Barangay Demo Staff',
                    'email' => 'staff@example.test',
                    'password' => env('BARANGAY_DEMO_STAFF_PASSWORD', 'BarangayStaff123!'),
                    'role' => 'staff',
                ],
            ] as $demoAccount) {
                $user = User::firstOrCreate(
                    ['email' => $demoAccount['email']],
                    ['name' => $demoAccount['name'], 'password' => $demoAccount['password']],
                );

                DB::table('user_roles')->insertOrIgnore([
                    'user_id' => $user->id,
                    'role' => $demoAccount['role'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (DB::table('reports')->exists()) {
            return;
        }

        $examples = [
            ['Streetlight near the covered court is out', 'Road/Infrastructure', 'The streetlight by the covered court has not been working this week.'],
            ['Missed waste collection on our street', 'Sanitation', 'Our scheduled collection was missed. Several households have set their bins aside.'],
            ['Drain needs clearing before rainy season', 'Flooding', 'Leaves have built up in the roadside drain near the community garden.'],
            ['Loud music late in the evening', 'Noise', 'The music at the open lot has continued past the usual quiet hours several nights.'],
            ['Uneven pavement near the health center', 'Road/Infrastructure', 'There is a raised section of pavement by the health center entrance.'],
            ['Overflowing public bin at the plaza', 'Sanitation', 'The bin beside the plaza seating area is full and needs collection.'],
            ['Request for safer crossing markings', 'Road/Infrastructure', 'The crossing by the school gate could use clearer road markings.'],
            ['Water collects along the footpath', 'Flooding', 'Rainwater pools along the footpath near the market after heavy showers.'],
            ['Community park gate needs repair', 'Other', 'The park gate latch is loose and the gate does not close properly.'],
            ['Request for neighborhood clean-up', 'Sanitation', 'Several households would like to coordinate a clean-up along the lane.'],
            ['Noisy construction during rest hours', 'Noise', 'Construction activity has been loud during the neighborhood rest period.'],
            ['Damaged bench at the waiting shed', 'Road/Infrastructure', 'One of the benches at the waiting shed is damaged and may need attention.'],
            ['Blocked sidewalk near the market', 'Peace & Order', 'Materials have been left on part of the sidewalk and make the path difficult to use.'],
            ['Community garden water tap is leaking', 'Other', 'The garden tap continues to drip and the handle is difficult to turn.'],
            ['Smoke alarm check requested at hall', 'Fire/Emergency', 'Requesting a routine check of the community hall smoke alarm.'],
        ];
        $statuses = ['pending', 'under_review', 'assigned', 'action_ongoing', 'resolved', 'closed', 'pending', 'under_review', 'assigned', 'action_ongoing', 'resolved', 'closed', 'pending', 'resolved', 'closed'];
        $reportIds = [];
        foreach ($examples as $index => [$title, $category, $description]) {
            $createdAt = now()->subDays(($index * 2) % 29)->subHours($index);
            $id = DB::table('reports')->insertGetId([
                'reference_no' => sprintf('CR-%d-%06d', now()->year, $index + 1),
                'user_id' => $resident->id,
                'title' => $title,
                'category' => $category,
                'description' => $description . ' (Fake demo report.)',
                'address' => 'Purok ' . (($index % 5) + 1) . ' — demo location',
                'status' => $statuses[$index],
                'priority' => 'normal',
                'is_urgent' => false,
                'is_anonymous' => $index === 12,
                'consent_given' => true,
                'confidentiality' => in_array($index, [3, 10], true) ? 'restricted' : 'standard',
                'created_at' => $createdAt,
                'updated_at' => $createdAt->copy()->addHours($index % 12),
            ]);
            $reportIds[] = $id;
            DB::table('report_status_history')->insert([
                'report_id' => $id,
                'status' => 'pending',
                'changed_by' => $resident->id,
                'note' => 'Fake demo report submitted.',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            if ($statuses[$index] !== 'pending') {
                DB::table('report_status_history')->insert([
                    'report_id' => $id,
                    'status' => $statuses[$index],
                    'changed_by' => null,
                    'note' => 'Fake demo status update.',
                    'created_at' => $createdAt->copy()->addHours(4),
                    'updated_at' => $createdAt->copy()->addHours(4),
                ]);
            }
        }
        DB::table('report_sequences')->updateOrInsert(['year' => now()->year], ['next_number' => count($examples) + 1]);

        foreach ([2, 5, 8] as $index) {
            $referralId = DB::table('referrals')->insertGetId([
                'report_id' => $reportIds[$index],
                'receiving_office' => ['Public Works Desk', 'Community Environment Office', 'Neighborhood Support Desk'][$index === 2 ? 0 : ($index === 5 ? 1 : 2)],
                'contact_person' => 'Demo contact',
                'reason' => 'Fake example referral for coordination.',
                'shared_data' => 'Only the concern summary and general location are shared. (Fake demo data.)',
                'status' => 'referred',
                'created_by' => null,
                'created_at' => now()->subDays($index),
                'updated_at' => now()->subDays($index),
            ]);
            DB::table('referral_status_history')->insert([
                'referral_id' => $referralId,
                'status' => 'referred',
                'note' => 'Fake demo referral created.',
                'created_at' => now()->subDays($index),
                'updated_at' => now()->subDays($index),
            ]);
            DB::table('partner_links')->insert([
                'referral_id' => $referralId,
                'token_hash' => hash('sha256', Str::random(64)),
                'expires_at' => now()->addDays(14),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
