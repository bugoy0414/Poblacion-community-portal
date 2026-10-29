<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunityController extends Controller
{
    private const STATUSES = ['pending', 'under_review', 'assigned', 'action_ongoing', 'resolved', 'closed'];
    private const REFERRAL_STATUSES = ['referred', 'received_by_agency', 'action_ongoing', 'resolved', 'returned'];
    private const ROLES = ['resident', 'staff', 'handler', 'officer', 'admin'];

    public function home(): View
    {
        return $this->page('home');
    }

    public function authPage(): View|RedirectResponse
    {
        return Auth::check() ? redirect($this->authenticatedLandingPath()) : $this->page('auth');
    }

    public function staffLoginPage(): View|RedirectResponse
    {
        return Auth::check() ? redirect($this->authenticatedLandingPath()) : $this->page('staff-auth');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'contact_number' => ['required', 'string', 'max:40'],
            'address' => ['required', 'string', 'max:255'],
        ]);
        $userId = DB::table('users')->insertGetId([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'contact_number' => $validated['contact_number'],
            'address' => $validated['address'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_roles')->insert(['user_id' => $userId, 'role' => 'resident', 'created_at' => now(), 'updated_at' => now()]);
        Auth::loginUsingId($userId);
        $request->session()->regenerate();
        app(AuditLogger::class)->record($userId, 'signed_up', 'user', $userId);

        return redirect('/reports')->with('success', 'Your resident account is ready.');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Those sign-in details did not match our records.'])->onlyInput('email');
        }
        if (! DB::table('users')->where('id', Auth::id())->where('is_active', true)->exists()) {
            Auth::logout();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'This account has been deactivated. Contact an administrator for help.'])->onlyInput('email');
        }
        if (! $this->hasRole(Auth::id(), 'resident')) {
            Auth::logout();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'Staff accounts must sign in through the staff portal.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        app(AuditLogger::class)->record(Auth::id(), 'signed_in', 'user', Auth::id());

        return redirect('/reports');
    }

    public function staffLogin(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Those staff sign-in details did not match our records.'])->onlyInput('email');
        }
        if (! DB::table('users')->where('id', Auth::id())->where('is_active', true)->exists()) {
            Auth::logout();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'This account has been deactivated. Contact an administrator for help.'])->onlyInput('email');
        }

        $isStaff = collect(['staff', 'handler', 'officer', 'admin'])
            ->contains(fn ($role) => $this->hasRole(Auth::id(), $role));
        if (! $isStaff) {
            Auth::logout();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'This account does not have staff access.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        app(AuditLogger::class)->record(Auth::id(), 'staff_signed_in', 'user', Auth::id());

        return redirect($this->authenticatedLandingPath());
    }

    public function logout(Request $request): RedirectResponse
    {
        app(AuditLogger::class)->record(Auth::id(), 'signed_out', 'user', Auth::id());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function notice(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'non_emergency_acknowledged' => ['accepted'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);
        $request->session()->put('non_emergency_acknowledged', true);

        $category = $validated['category'] ?? null;
        if ($category && DB::table('categories')->where('name', $category)->where('is_active', true)->exists()) {
            return redirect('/reports/new?category='.urlencode($category));
        }

        return redirect('/reports/new');
    }

    public function reports(): View
    {
        $this->requireResident();
        $reports = DB::table('reports')->where('user_id', Auth::id())->latest()->get();
        app(AuditLogger::class)->record(Auth::id(), 'viewed', 'resident_reports');

        return $this->page('reports', compact('reports'));
    }

    public function newReport(Request $request): View|RedirectResponse
    {
        $this->requireResident();
        $selectedCategory = $request->query('category');
        if (! is_string($selectedCategory)
            || ! DB::table('categories')->where('name', $selectedCategory)->where('is_active', true)->exists()) {
            $selectedCategory = null;
        }

        if (! $request->session()->get('non_emergency_acknowledged', false)) {
            return $this->page('notice', compact('selectedCategory'));
        }

        return $this->page('new-report', compact('selectedCategory'));
    }

    public function storeReport(Request $request): RedirectResponse
    {
        $this->requireResident();
        abort_unless($request->session()->get('non_emergency_acknowledged', false), 403);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'category' => ['required', 'string', 'exists:categories,name'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'consent_given' => ['accepted'],
            'is_anonymous' => ['nullable', 'boolean'],
            'confirm_emergency' => ['nullable', 'boolean'],
        ]);
        $text = mb_strtolower($validated['title'] . ' ' . $validated['description']);
        $keywords = DB::table('urgent_keywords')->pluck('keyword')->all();
        $keywordMatch = collect($keywords)->contains(fn (string $word) => str_contains($text, mb_strtolower($word)));
        $urgent = $keywordMatch || $validated['category'] === 'Fire/Emergency';
        if ($urgent && ! $request->boolean('confirm_emergency')) {
            return back()->withInput()->with('emergency_prompt', true);
        }
        $year = now()->year;
        $sequence = DB::transaction(function () use ($year): int {
            DB::table('report_sequences')->insertOrIgnore(['year' => $year, 'next_number' => 1]);
            $sequence = DB::table('report_sequences')->where('year', $year)->lockForUpdate()->first();
            DB::table('report_sequences')->where('year', $year)->update(['next_number' => $sequence->next_number + 1]);

            return (int) $sequence->next_number;
        });
        $photoPath = $request->file('photo')?->store(
            (string) Auth::id(),
            config('filesystems.report_photos_disk', 'local'),
        );
        $id = DB::table('reports')->insertGetId([
            'reference_no' => sprintf('CR-%d-%06d', $year, $sequence),
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'category' => $validated['category'],
            'description' => $validated['description'],
            'photo_path' => $photoPath,
            'address' => $validated['address'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'status' => 'pending',
            'priority' => 'normal',
            'is_urgent' => $urgent,
            'is_anonymous' => $request->boolean('is_anonymous'),
            'consent_given' => true,
            'confidentiality' => 'standard',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('report_status_history')->insert([
            'report_id' => $id,
            'status' => 'pending',
            'changed_by' => Auth::id(),
            'note' => 'Report submitted by resident.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(AuditLogger::class)->record(Auth::id(), 'created', 'report', $id, ['reference_no' => sprintf('CR-%d-%06d', $year, $sequence)]);

        return redirect('/reports/' . $id)->with('submitted', true);
    }

    public function reportDetail(int $id): View
    {
        $report = DB::table('reports')->where('id', $id)->where('user_id', Auth::id())->first();
        abort_unless($report, 404);
        $timeline = DB::table('report_status_history')->where('report_id', $id)->orderBy('created_at')->get();
        $referrals = DB::table('referrals')->where('report_id', $id)->select('receiving_office', 'status', 'created_at', 'updated_at')->get();
        app(AuditLogger::class)->record(Auth::id(), 'viewed', 'report', $id);

        return $this->page('report-detail', compact('report', 'timeline', 'referrals'));
    }

    public function track(Request $request): View
    {
        $report = null;
        if ($request->filled('reference_no')) {
            $reference = strtoupper(trim((string) $request->query('reference_no')));
            if (preg_match('/^CR-\d{4}-\d{6}$/', $reference)) {
                $report = DB::table('reports')->where('reference_no', $reference)
                    ->select('reference_no', 'category', 'status', 'created_at', 'updated_at')->first();
            }
            app(AuditLogger::class)->record(Auth::id(), 'tracked', 'report', $report?->reference_no, ['found' => (bool) $report]);
        }

        return $this->page('track', compact('report'));
    }

    public function profile(): View
    {
        $this->requireResident();
        app(AuditLogger::class)->record(Auth::id(), 'viewed', 'profile', Auth::id());

        return $this->page('profile');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $this->requireResident();
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'contact_number' => ['required', 'string', 'max:40'], 'address' => ['required', 'string', 'max:255']]);
        DB::table('users')->where('id', Auth::id())->update([...$data, 'updated_at' => now()]);
        app(AuditLogger::class)->record(Auth::id(), 'updated', 'profile', Auth::id());

        return back()->with('success', 'Profile updated.');
    }

    public function staffInbox(Request $request): View
    {
        $this->requireStaff();
        $query = DB::table('reports')->orderByDesc('created_at');
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }
        if ($request->filled('q')) {
            $term = '%' . addcslashes($request->query('q'), '%_\\') . '%';
            $query->where(fn ($builder) => $builder->where('reference_no', 'like', $term)->orWhere('title', 'like', $term));
        }
        $reports = $query->paginate(20)->withQueryString();
        foreach ($reports as $report) {
            $report->full_access = $this->canViewFull($report);
            if (! $report->full_access && ($report->is_anonymous || $report->confidentiality !== 'standard')) {
                $report->title = 'Protected report';
                $report->description = 'Narrative is protected. Open this report if you are authorized, or request emergency access.';
            }
            $report->identity = $report->is_anonymous ? 'Anonymous resident' : 'Identity protected';
        }
        $dashboardMetrics = [
            'reports' => DB::table('reports')->count(),
            'open' => DB::table('reports')->whereIn('status', ['pending', 'under_review', 'assigned', 'action_ongoing'])->count(),
            'resolved' => DB::table('reports')->whereIn('status', ['resolved', 'closed'])->count(),
            'urgent' => DB::table('reports')->where('is_urgent', true)->count(),
        ];
        app(AuditLogger::class)->record(Auth::id(), 'viewed', 'staff_inbox', null, ['filters' => $request->only(['status', 'category', 'q'])]);

        return $this->page('staff', compact('reports', 'dashboardMetrics'));
    }

    public function staffReport(int $id): View
    {
        $this->requireStaff();
        $report = DB::table('reports')->where('id', $id)->firstOrFail();
        $hasAccess = $this->canViewFull($report);
        $canViewDetails = $this->canViewReportDetails($report, $hasAccess);
        $this->maskStaffReport($report, $canViewDetails);
        $report->resident = $report->is_anonymous || ! $hasAccess
            ? null
            : DB::table('users')->where('id', $report->user_id)->first(['name', 'contact_number', 'email']);
        $timeline = DB::table('report_status_history')->where('report_id', $id)->orderBy('created_at')->get();
        $staffUsers = DB::table('user_roles')->join('users', 'users.id', '=', 'user_roles.user_id')
            ->where('users.is_active', true)->whereIn('role', ['staff', 'handler', 'officer', 'admin'])
            ->select('users.id', 'users.name')->distinct()->get();
        app(AuditLogger::class)->record(Auth::id(), 'viewed', 'report', $id);

        return $this->page('staff-detail', compact('report', 'timeline', 'staffUsers', 'hasAccess', 'canViewDetails'));
    }

    public function printStaffReport(int $id): View
    {
        $this->requireStaff();
        $report = DB::table('reports')->where('id', $id)->firstOrFail();
        $hasAccess = $this->canViewFull($report);
        $canViewDetails = $this->canViewReportDetails($report, $hasAccess);
        $isRestrictedWithoutAccess = $report->confidentiality === 'restricted' && ! $canViewDetails;
        $this->maskStaffReport($report, $canViewDetails);
        $report->resident = $report->is_anonymous || ! $hasAccess
            ? null
            : DB::table('users')->where('id', $report->user_id)->first(['name', 'contact_number', 'email']);
        $timeline = $isRestrictedWithoutAccess
            ? collect()
            : DB::table('report_status_history')->where('report_id', $id)->orderBy('created_at')->get();
        app(AuditLogger::class)->record(Auth::id(), 'printed', 'report', $id, [
            'masked' => ! $canViewDetails,
            'restricted' => $report->confidentiality === 'restricted',
        ]);

        return view('community.print-report', compact('report', 'timeline', 'hasAccess', 'isRestrictedWithoutAccess'));
    }

    public function staffReportPhoto(int $id): StreamedResponse
    {
        $this->requireStaff();
        $report = DB::table('reports')->where('id', $id)->firstOrFail();
        abort_unless($this->canViewReportDetails($report, $this->canViewFull($report)), 403);
        abort_unless($report->photo_path, 404);

        $disk = Storage::disk(config('filesystems.report_photos_disk', 'local'));
        abort_unless($disk->exists($report->photo_path), 404);
        app(AuditLogger::class)->record(Auth::id(), 'viewed', 'report_photo', $id);

        return $disk->response($report->photo_path);
    }

    public function updateStaffReport(Request $request, int $id): RedirectResponse
    {
        $this->requireStaff();
        $report = DB::table('reports')->where('id', $id)->firstOrFail();
        if ($report->confidentiality === 'restricted' && ! $this->canViewFull($report)) {
            abort(403, 'You are not authorized to update this restricted report.');
        }
        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', self::STATUSES)],
            'note' => ['required', 'string', 'min:5', 'max:2000'],
            'category' => ['required', 'exists:categories,name'],
            'priority' => ['required', 'in:normal,high,urgent'],
            'confidentiality' => ['required', 'in:standard,confidential,restricted'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'is_urgent' => ['nullable', 'boolean'],
        ]);
        DB::transaction(function () use ($data, $report, $id): void {
            DB::table('reports')->where('id', $id)->update([
                'status' => $data['status'],
                'category' => $data['category'],
                'priority' => $data['priority'],
                'confidentiality' => $data['confidentiality'],
                'assigned_to' => $data['assigned_to'] ?? null,
                'is_urgent' => (bool) ($data['is_urgent'] ?? false),
                'updated_at' => now(),
            ]);
            if ($report->status !== $data['status']) {
                DB::table('report_status_history')->insert([
                    'report_id' => $id, 'status' => $data['status'], 'changed_by' => Auth::id(),
                    'note' => $data['note'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            if ($data['is_urgent'] ?? false) {
                DB::table('tasks')->insert([
                    'report_id' => $id, 'assigned_to' => $data['assigned_to'] ?? null,
                    'type' => 'call_resident', 'status' => 'open', 'note' => 'Urgent report — call resident.',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
        app(AuditLogger::class)->record(Auth::id(), 'updated', 'report', $id, ['status' => $data['status'], 'assigned_to' => $data['assigned_to'] ?? null]);

        return back()->with('success', 'Report changes saved and recorded.');
    }

    public function breakGlass(Request $request, int $id): RedirectResponse
    {
        $this->requireStaff();
        $report = DB::table('reports')->where('id', $id)->firstOrFail();
        abort_unless($report->confidentiality === 'restricted' && ! $this->canViewFull($report), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:20', 'max:1000']]);
        DB::table('break_glass_access')->insert([
            'user_id' => Auth::id(), 'report_id' => $id, 'reason' => $data['reason'],
            'expires_at' => now()->addMinutes(30), 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(AuditLogger::class)->record(Auth::id(), 'break_glass_granted', 'report', $id, ['reason' => $data['reason'], 'expires_at' => now()->addMinutes(30)->toIso8601String()]);

        return redirect('/staff/reports/' . $id)->with('success', 'Emergency access granted for 30 minutes. This use was flagged for admin review.');
    }

    public function tasks(): View
    {
        $this->requireStaff();
        $tasks = DB::table('tasks')->join('reports', 'reports.id', '=', 'tasks.report_id')
            ->select('tasks.*', 'reports.reference_no', 'reports.title', 'reports.user_id')->orderByDesc('tasks.created_at')->get();
        app(AuditLogger::class)->record(Auth::id(), 'viewed', 'tasks');

        return $this->page('tasks', compact('tasks'));
    }

    public function referrals(): View
    {
        $this->requireStaff();
        $referrals = DB::table('referrals')->join('reports', 'reports.id', '=', 'referrals.report_id')
            ->select('referrals.*', 'reports.reference_no')->orderByDesc('referrals.created_at')->get();
        app(AuditLogger::class)->record(Auth::id(), 'viewed', 'referrals');

        return $this->page('referrals', compact('referrals'));
    }

    public function createReferral(Request $request, int $id): RedirectResponse
    {
        $this->requireStaff();
        $report = DB::table('reports')->where('id', $id)->firstOrFail();
        abort_unless($this->canViewFull($report), 403);
        $data = $request->validate([
            'receiving_office' => ['required', 'string', 'max:160'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'shared_data' => ['required', 'string', 'min:5', 'max:3000'],
        ]);
        $referralId = DB::table('referrals')->insertGetId([...$data, 'report_id' => $id, 'created_by' => Auth::id(), 'status' => 'referred', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('referral_status_history')->insert(['referral_id' => $referralId, 'status' => 'referred', 'note' => 'Referral created.', 'created_at' => now(), 'updated_at' => now()]);
        $token = Str::random(64);
        DB::table('partner_links')->insert([
            'referral_id' => $referralId, 'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(14), 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(AuditLogger::class)->record(Auth::id(), 'created', 'referral', $referralId, ['shared_data' => $data['shared_data']]);

        return back()->with('partner_link', url('/partner/' . $token))->with('success', 'Referral link created. Share it securely; it expires in 14 days.');
    }

    public function partner(string $token): View
    {
        $link = DB::table('partner_links')->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        abort_unless($link, 404, 'This partner link is unavailable.');
        $referral = DB::table('referrals')->where('id', $link->referral_id)->firstOrFail();
        app(AuditLogger::class)->record(null, 'viewed', 'partner_referral', $referral->id);

        return $this->page('partner', compact('referral', 'token'));
    }

    public function partnerUpdate(Request $request, string $token): RedirectResponse
    {
        $link = DB::table('partner_links')->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        abort_unless($link, 404);
        $data = $request->validate(['status' => ['required', 'in:' . implode(',', self::REFERRAL_STATUSES)], 'note' => ['required', 'string', 'min:5', 'max:1000']]);
        DB::table('referrals')->where('id', $link->referral_id)->update(['status' => $data['status'], 'updated_at' => now()]);
        DB::table('referral_status_history')->insert(['referral_id' => $link->referral_id, 'status' => $data['status'], 'note' => $data['note'], 'created_at' => now(), 'updated_at' => now()]);
        app(AuditLogger::class)->record(null, 'updated', 'partner_referral', $link->referral_id, ['status' => $data['status']]);

        return back()->with('success', 'Referral status updated.');
    }

    public function analytics(): View
    {
        $this->requireStaff();
        $rows = DB::table('reports')->select('category', 'status', DB::raw("strftime('%Y-%m', created_at) as month"), DB::raw('COUNT(*) as total'))->groupBy('category', 'status', 'month')->get();
        $aggregates = $rows->map(fn ($row) => (object) [...(array) $row, 'display_count' => $row->total < 5 ? '<5' : $row->total]);
        $metrics = (object) [
            'reports' => DB::table('reports')->count(),
            'acknowledgment' => DB::table('report_status_history')->where('status', '!=', 'pending')->avg(DB::raw("julianday(created_at) - julianday((select created_at from reports where reports.id = report_status_history.report_id))")),
            'resolved' => DB::table('reports')->whereIn('status', ['resolved', 'closed'])->count(),
            'referrals' => DB::table('referrals')->count(),
        ];
        app(AuditLogger::class)->record(Auth::id(), 'viewed', 'analytics');

        return $this->page('analytics', compact('aggregates', 'metrics'));
    }

    public function exportAnalytics(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->requireStaff();
        app(AuditLogger::class)->record(Auth::id(), 'exported', 'analytics');
        $rows = DB::table('reports')->select('category', 'status', DB::raw("strftime('%Y-%m', created_at) as month"), DB::raw('COUNT(*) as total'))->groupBy('category', 'status', 'month')->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Category', 'Status', 'Month', 'Count']);
            foreach ($rows as $row) {
                fputcsv($out, [$row->category, $row->status, $row->month, $row->total < 5 ? '<5' : $row->total]);
            }
            fclose($out);
        }, 'community-report-aggregates.csv', ['Content-Type' => 'text/csv']);
    }

    public function admin(): View
    {
        $this->requireAdmin();
        $users = DB::table('users')->join('user_roles', 'users.id', '=', 'user_roles.user_id')->select('users.id', 'users.name', 'users.email', 'users.is_active', 'user_roles.role')->orderBy('users.id')->get();
        $categories = DB::table('categories')->orderBy('name')->get();
        $keywords = DB::table('urgent_keywords')->orderBy('language')->orderBy('keyword')->get();
        $settings = DB::table('site_settings')->first();
        $dashboardMetrics = [
            'reports' => DB::table('reports')->count(),
            'open' => DB::table('reports')->whereIn('status', ['pending', 'under_review', 'assigned', 'action_ongoing'])->count(),
            'resolved' => DB::table('reports')->whereIn('status', ['resolved', 'closed'])->count(),
            'urgent' => DB::table('reports')->where('is_urgent', true)->count(),
            'staff' => DB::table('user_roles')->join('users', 'users.id', '=', 'user_roles.user_id')
                ->where('users.is_active', true)->whereIn('user_roles.role', ['staff', 'handler', 'officer', 'admin'])
                ->distinct('user_roles.user_id')->count('user_roles.user_id'),
        ];
        $recentReports = DB::table('reports')
            ->select('id', 'reference_no', 'category', 'status', 'confidentiality', 'is_urgent', 'created_at')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();
        app(AuditLogger::class)->record(Auth::id(), 'viewed', 'admin');

        return $this->page('admin', compact('users', 'categories', 'keywords', 'settings', 'dashboardMetrics', 'recentReports'));
    }

    public function updateRole(Request $request, int $id): RedirectResponse
    {
        $this->requireAdmin();
        $data = $request->validate(['role' => ['required', 'in:' . implode(',', self::ROLES)]]);
        DB::table('user_roles')->where('user_id', $id)->delete();
        DB::table('user_roles')->insert(['user_id' => $id, 'role' => $data['role'], 'created_at' => now(), 'updated_at' => now()]);
        app(AuditLogger::class)->record(Auth::id(), 'role_changed', 'user', $id, ['role' => $data['role']]);

        return back()->with('success', 'Role updated and audited.');
    }

    public function createStaff(Request $request): RedirectResponse
    {
        $this->requireAdmin();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($data): void {
            $now = now();
            $userId = DB::table('users')->insertGetId([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('user_roles')->insert([
                'user_id' => $userId,
                'role' => 'staff',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            app(AuditLogger::class)->record(Auth::id(), 'staff_created', 'user', $userId, [
                'email' => $data['email'],
                'role' => 'staff',
            ]);
        });

        return back()->with('success', 'Staff account created. Share the sign-in details securely with the new staff member.');
    }

    public function deactivateUser(int $id): RedirectResponse
    {
        $this->requireAdmin();
        abort_if($id === Auth::id(), 403, 'You cannot deactivate your own account.');

        $user = DB::table('users')
            ->join('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->where('users.id', $id)
            ->whereIn('user_roles.role', ['resident', 'staff', 'handler', 'officer'])
            ->select('users.id', 'users.name', 'users.email', 'users.is_active', 'user_roles.role')
            ->first();

        abort_unless($user, 404);
        if (! $user->is_active) {
            return back()->with('success', 'This account is already deactivated.');
        }

        DB::transaction(function () use ($user): void {
            DB::table('users')->where('id', $user->id)->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
            app(AuditLogger::class)->record(Auth::id(), 'user_deactivated', 'user', $user->id, [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]);
        });

        return back()->with('success', 'User account deactivated. Reports and audit history have been preserved.');
    }

    public function reactivateUser(int $id): RedirectResponse
    {
        $this->requireAdmin();
        abort_if($id === Auth::id(), 403, 'Your account is already active.');

        $user = DB::table('users')
            ->join('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->where('users.id', $id)
            ->whereIn('user_roles.role', ['resident', 'staff', 'handler', 'officer'])
            ->select('users.id', 'users.name', 'users.email', 'users.is_active', 'user_roles.role')
            ->first();

        abort_unless($user, 404);
        if ($user->is_active) {
            return back()->with('success', 'This account is already active.');
        }

        DB::transaction(function () use ($user): void {
            DB::table('users')->where('id', $user->id)->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
            app(AuditLogger::class)->record(Auth::id(), 'user_reactivated', 'user', $user->id, [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]);
        });

        return back()->with('success', 'User account reactivated.');
    }

    public function saveAdminSettings(Request $request): RedirectResponse
    {
        $this->requireAdmin();
        $data = $request->validate([
            'barangay_name' => ['required', 'string', 'max:120'],
            'hotline_label' => ['required', 'string', 'max:80'],
            'hotline_number' => ['required', 'string', 'max:40'],
            'office_hours' => ['required', 'string', 'max:120'],
            'acknowledgment_target' => ['required', 'string', 'max:120'],
            'overdue_days' => ['required', 'integer', 'min:1', 'max:60'],
        ]);
        DB::table('site_settings')->updateOrInsert(['id' => 1], [...$data, 'updated_at' => now(), 'created_at' => now()]);
        app(AuditLogger::class)->record(Auth::id(), 'updated', 'site_settings', 1);

        return back()->with('success', 'Site settings saved.');
    }

    public function saveCategory(Request $request): RedirectResponse
    {
        $this->requireAdmin();
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        DB::table('categories')->updateOrInsert(['name' => $data['name']], ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]);
        app(AuditLogger::class)->record(Auth::id(), 'updated', 'category', $data['name']);

        return back()->with('success', 'Category saved.');
    }

    public function saveKeyword(Request $request): RedirectResponse
    {
        $this->requireAdmin();
        $data = $request->validate(['keyword' => ['required', 'string', 'max:80'], 'language' => ['required', 'string', 'max:20']]);
        DB::table('urgent_keywords')->updateOrInsert(['keyword' => mb_strtolower($data['keyword'])], ['language' => $data['language'], 'updated_at' => now(), 'created_at' => now()]);
        app(AuditLogger::class)->record(Auth::id(), 'updated', 'urgent_keyword', $data['keyword']);

        return back()->with('success', 'Urgent keyword saved.');
    }

    public function audit(Request $request): View
    {
        $this->requireAdmin();
        $query = DB::table('audit_logs')->orderByDesc('id');
        if ($request->boolean('break_glass')) {
            $query->where('action', 'break_glass_granted');
        }
        if ($request->filled('q')) {
            $query->where(fn ($builder) => $builder->where('action', 'like', '%' . $request->query('q') . '%')->orWhere('entity', 'like', '%' . $request->query('q') . '%')->orWhere('entity_id', 'like', '%' . $request->query('q') . '%'));
        }
        $logs = $query->paginate(50)->withQueryString();
        app(AuditLogger::class)->record(Auth::id(), 'viewed', 'audit_logs');

        return $this->page('audit', compact('logs'));
    }

    public function verifyAudit(): RedirectResponse
    {
        $this->requireAdmin();
        $logs = DB::table('audit_logs')->orderBy('id')->get();
        $previous = null;
        $valid = true;
        foreach ($logs as $log) {
            $row = [
                'actor_id' => $log->actor_id, 'action' => $log->action, 'entity' => $log->entity,
                'entity_id' => $log->entity_id, 'details' => is_string($log->details) ? $log->details : json_encode($log->details),
                'created_at' => $log->created_at, 'prev_hash' => $log->prev_hash,
            ];
            $expected = hash('sha256', ($previous ?? '') . json_encode($row, JSON_THROW_ON_ERROR));
            if ($log->prev_hash !== $previous || ! hash_equals($log->row_hash, $expected)) {
                $valid = false;
                break;
            }
            $previous = $log->row_hash;
        }
        app(AuditLogger::class)->record(Auth::id(), 'integrity_verified', 'audit_logs', null, ['valid' => $valid]);

        return back()->with($valid ? 'success' : 'error', $valid ? 'Audit chain integrity verified.' : 'Audit chain integrity check failed.');
    }

    public function privacy(): View
    {
        return $this->page('privacy');
    }

    private function page(string $page, array $data = []): View
    {
        $settings = DB::getSchemaBuilder()->hasTable('site_settings')
            ? DB::table('site_settings')->first()
            : (object) [
                'barangay_name' => 'Barangay Community Portal',
                'hotline_label' => 'Barangay hotline',
                'hotline_number' => '911',
                'office_hours' => 'Mon-Fri, 8:00 AM-5:00 PM',
                'acknowledgment_target' => 'Within 1 business day',
                'overdue_days' => 3,
            ];

        $categories = DB::getSchemaBuilder()->hasTable('categories')
            ? DB::table('categories')->where('is_active', true)->orderBy('name')->pluck('name')
            : collect(['Peace & Order', 'Sanitation', 'Road/Infrastructure', 'Flooding', 'Noise', 'Fire/Emergency', 'Other']);

        $isStaff = Auth::check() && $this->isUserActive(Auth::id()) && collect(['staff', 'handler', 'officer', 'admin'])
            ->contains(fn ($role) => $this->hasRole(Auth::id(), $role));

        return view('community.app', [
            ...$data,
            'page' => $page,
            'settings' => $settings,
            'categories' => $data['categories'] ?? $categories,
            'isStaff' => $isStaff,
            'isAdmin' => Auth::check() && $this->isUserActive(Auth::id()) && $this->hasRole(Auth::id(), 'admin'),
        ]);
    }

    private function requireResident(): void
    {
        abort_unless(Auth::check() && $this->isUserActive(Auth::id()) && $this->hasRole(Auth::id(), 'resident'), 403);
    }

    private function requireStaff(): void
    {
        abort_unless(Auth::check() && $this->isUserActive(Auth::id()) && collect(['staff', 'handler', 'officer', 'admin'])->contains(fn ($role) => $this->hasRole(Auth::id(), $role)), 403);
    }

    private function requireAdmin(): void
    {
        abort_unless(Auth::check() && $this->isUserActive(Auth::id()) && $this->hasRole(Auth::id(), 'admin'), 403);
    }

    private function isUserActive(int $userId): bool
    {
        return DB::table('users')->where('id', $userId)->where('is_active', true)->exists();
    }

    private function authenticatedLandingPath(): string
    {
        if (! $this->isUserActive(Auth::id())) {
            return '/';
        }

        if ($this->hasRole(Auth::id(), 'admin')) {
            return '/admin';
        }

        if (collect(['staff', 'handler', 'officer'])->contains(fn ($role) => $this->hasRole(Auth::id(), $role))) {
            return '/staff';
        }

        return '/reports';
    }

    private function hasRole(int $userId, string $role): bool
    {
        if (! DB::getSchemaBuilder()->hasTable('user_roles')) {
            return false;
        }

        return DB::table('user_roles')->where('user_id', $userId)->where('role', $role)->exists();
    }

    private function canViewFull(object $report): bool
    {
        if (! DB::getSchemaBuilder()->hasTable('break_glass_access')) {
            return false;
        }

        return (int) $report->user_id === (int) Auth::id()
            || ($report->assigned_to !== null && (int) $report->assigned_to === (int) Auth::id())
            || $this->hasRole(Auth::id(), 'officer')
            || DB::table('break_glass_access')->where('user_id', Auth::id())->where('report_id', $report->id)->where('expires_at', '>', now())->exists();
    }

    private function canViewReportDetails(object $report, bool $hasFullAccess): bool
    {
        return $hasFullAccess
            || $this->hasRole(Auth::id(), 'admin')
            || (! $report->is_anonymous && $report->confidentiality === 'standard');
    }

    private function maskStaffReport(object $report, bool $canViewDetails): void
    {
        if ($canViewDetails) {
            return;
        }

        $report->title = $report->confidentiality === 'restricted' ? 'Restricted report' : 'Protected report';
        $report->description = 'Report details are masked for this account.';
        $report->address = null;
        $report->latitude = null;
        $report->longitude = null;
        $report->photo_path = null;
    }
}
