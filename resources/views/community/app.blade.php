<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $settings->barangay_name ?? 'Barangay Community Portal' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,800&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="{{ $isAdmin && in_array($page, ['admin', 'audit'], true) || $isStaff && $page === 'staff' ? 'admin-body' : '' }}">
    @php($adminArea = $isAdmin && in_array($page, ['admin', 'audit'], true))
    @php($staffDashboard = $isStaff && $page === 'staff')
    @php($dashboardShell = $adminArea || $staffDashboard)
    @if ($page !== 'home')
        <div class="emergency-bar">
            <span class="emergency-dot"></span>
            <span>Emergency? Call <a href="tel:911">911</a> or <a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings->hotline_number ?? '911') }}">{{ $settings->hotline_label ?? 'barangay hotline' }}</a> · {{ $settings->hotline_number ?? '911' }}</span>
        </div>
    @endif
    @if ($dashboardShell)
        <div class="admin-dashboard-shell">
            <aside class="admin-sidebar" aria-label="{{ $adminArea ? 'Admin navigation' : 'Staff navigation' }}">
                <a class="admin-brand" href="{{ $adminArea ? '/admin' : '/staff' }}">
                    <span class="brand-mark">b<span>.</span></span>
                    <span>{{ $settings->barangay_name ?? 'Barangay Portal' }}<small>{{ $adminArea ? 'Administration' : 'Staff workspace' }}</small></span>
                </a>
                <span class="admin-nav-label">WORKSPACE</span>
                @if ($adminArea || $isAdmin)
                    <a class="admin-nav-link {{ $page === 'admin' ? 'active' : '' }}" href="/admin"><span>▦</span> Overview</a>
                    <a class="admin-nav-link" href="/admin#users"><span>♙</span> User &amp; roles</a>
                    <a class="admin-nav-link" href="/admin#configuration"><span>⚙</span> Configuration</a>
                    <a class="admin-nav-link {{ $page === 'audit' ? 'active' : '' }}" href="/admin/audit"><span>◷</span> Audit &amp; security</a>
                    <span class="admin-nav-label admin-nav-label-spaced">OPERATIONS</span>
                @endif
                <a class="admin-nav-link {{ $page === 'staff' ? 'active' : '' }}" href="/staff"><span>▤</span> Reports inbox</a>
                <a class="admin-nav-link {{ $page === 'tasks' ? 'active' : '' }}" href="/staff/tasks"><span>✓</span> Call tasks</a>
                <a class="admin-nav-link {{ $page === 'referrals' ? 'active' : '' }}" href="/staff/referrals"><span>↗</span> Referrals</a>
                <a class="admin-nav-link {{ $page === 'analytics' ? 'active' : '' }}" href="/staff/analytics"><span>▥</span> Analytics</a>
                <div class="admin-sidebar-footer">
                    <span class="admin-user-avatar">{{ mb_substr(auth()->user()->name ?? 'A', 0, 1) }}</span>
                    <span class="admin-user-summary"><b>{{ auth()->user()->name ?? 'Staff member' }}</b><small>{{ $isAdmin ? 'Administrator' : 'Staff' }}</small></span>
                    <form method="post" action="/logout">@csrf<button class="admin-signout" type="submit" aria-label="Sign out">↗</button></form>
                </div>
            </aside>
            <div class="admin-dashboard-content">
                <header class="admin-topbar">
                    <div class="admin-breadcrumb">{{ $adminArea || $isAdmin ? 'Administration' : 'Staff workspace' }} <span>/</span> {{ $staffDashboard ? 'Reports inbox' : ($page === 'audit' ? 'Audit & security' : 'Overview') }}</div>
                    <div class="admin-topbar-actions">
                        <span class="admin-secure-label"><i></i> Secure workspace</span>
                        @if ($adminArea || $isAdmin)
                            <a class="admin-public-link" href="/" target="_blank" rel="noopener">View public site ↗</a>
                        @endif
                    </div>
                </header>
    @else
    <header class="site-header">
        <a class="brand" href="/">
            <span class="brand-mark">b<span>.</span></span>
            <span>{{ $settings->barangay_name ?? 'Barangay Community Portal' }}<small>Community care, together</small></span>
        </a>
        <nav class="main-nav" aria-label="Main navigation">
            <a class="{{ $page === 'home' ? 'active' : '' }}" href="/" @if ($page === 'home') aria-current="page" @endif>Home</a>
            <a class="{{ $page === 'track' ? 'active' : '' }}" href="/track" @if ($page === 'track') aria-current="page" @endif>Track</a>
            <a class="{{ $page === 'privacy' ? 'active' : '' }}" href="/privacy" @if ($page === 'privacy') aria-current="page" @endif>Privacy</a>
            @auth
                @if ($isStaff)
                    <a class="{{ in_array($page, ['staff', 'staff-detail', 'tasks', 'referrals', 'analytics'], true) ? 'active' : '' }}" href="/staff">Staff Inbox</a>
                    @if ($isAdmin)
                        <a class="{{ in_array($page, ['admin', 'audit'], true) ? 'active' : '' }}" href="/admin">Admin</a>
                    @endif
                @else
                    <a class="{{ in_array($page, ['reports', 'report-detail', 'new-report', 'notice'], true) ? 'active' : '' }}" href="/reports">My Reports</a>
                    <a class="{{ $page === 'profile' ? 'active' : '' }}" href="/profile">Profile</a>
                @endif
                <form method="post" action="/logout" class="inline-form">@csrf<button class="nav-logout" type="submit">Sign out</button></form>
            @else
                <a href="/auth">Sign in</a>
            @endauth
        </nav>
    </header>
    @endif

    @if ($page === 'home' && ! $adminArea)
        <section class="home-notice" role="note" aria-label="Office hours and urgent support information">
            <span class="home-notice-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.7"/>
                    <path d="M12 11v5m0-8h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </span>
            <p>Need help right now? Go to your barangay hall or call <a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings->hotline_number ?? '') }}">your barangay hotline</a>. This portal is for everyday concerns and is checked during office hours.</p>
        </section>
    @endif

    @if (session('success'))
        <div class="toast success-toast" role="status">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="toast error-toast" role="alert">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="toast error-toast" role="alert">{{ $errors->first() }}</div>
    @endif

    <main class="{{ $dashboardShell ? 'admin-main' : 'page-shell' }}">
        @switch($page)
            @case('home')
                <div class="home-content">
                <section class="home-hero">
                    <div class="hero-copy">
                        <span class="eyebrow">A better way to be heard</span>
                        <h1>Speak up.<br><span>See it through.</span></h1>
                        <p>Report a barangay concern in a few minutes, keep your details private, and follow every update until it’s resolved.</p>
                        <div class="hero-actions">
                            <a class="button button-primary" href="{{ auth()->check() ? ($isStaff ? '/staff' : '/reports/new') : '/auth' }}">Share a concern</a>
                            <a class="button button-outline" href="/track">Check a report</a>
                        </div>
                        <ul class="trust-points" aria-label="Portal benefits">
                            <li><span aria-hidden="true">✓</span>Private by default</li>
                            <li><span aria-hidden="true">✓</span>No account fuss</li>
                            <li><span aria-hidden="true">✓</span>Live status updates</li>
                        </ul>
                    </div>
                    <section class="sample-report" aria-labelledby="sample-report-title">
                        <div class="sample-report-kicker">Sample report</div>
                        <h2 id="sample-report-title">BRGY-2041 · Broken streetlight</h2>
                        <div class="status-trail" data-status-trail>
                            <div class="status-step is-complete">
                                <span class="status-step-marker" aria-hidden="true">✓</span>
                                <div class="status-step-content">
                                    <button type="button" class="status-step-toggle" aria-expanded="false" aria-controls="status-description-received" data-status-step>Received</button>
                                    <p class="status-step-description" id="status-description-received" hidden>Your concern is safely in the barangay queue.</p>
                                </div>
                            </div>
                            <div class="status-step is-complete">
                                <span class="status-step-marker" aria-hidden="true">✓</span>
                                <div class="status-step-content">
                                    <button type="button" class="status-step-toggle" aria-expanded="false" aria-controls="status-description-reviewed" data-status-step>Reviewed by barangay staff</button>
                                    <p class="status-step-description" id="status-description-reviewed" hidden>Staff checked the details and selected a response team.</p>
                                </div>
                            </div>
                            <div class="status-step is-current is-open">
                                <span class="status-step-marker" aria-hidden="true">3</span>
                                <div class="status-step-content">
                                    <button type="button" class="status-step-toggle" aria-expanded="true" aria-controls="status-description-assigned" data-status-step>Assigned to a team</button>
                                    <p class="status-step-description" id="status-description-assigned">The right team is on it. Expect a visit or an update soon.</p>
                                </div>
                            </div>
                            <div class="status-step">
                                <span class="status-step-marker" aria-hidden="true">4</span>
                                <div class="status-step-content">
                                    <button type="button" class="status-step-toggle" aria-expanded="false" aria-controls="status-description-resolved" data-status-step>Resolved</button>
                                    <p class="status-step-description" id="status-description-resolved" hidden>The barangay records the outcome and shares the final update.</p>
                                </div>
                            </div>
                        </div>
                        <p class="sample-report-hint">Tap a step to see what happens there.</p>
                    </div>
                </section>

                <section class="category-section" aria-labelledby="category-heading">
                    <div class="category-heading">
                        <div>
                            <h2 id="category-heading">What’s the concern about?</h2>
                            <p>Pick the closest match. You can add details and photos on the next screen.</p>
                        </div>
                    </div>
                    <div class="category-grid">
                        <a class="category-card" href="/reports/new?category={{ urlencode('Road/Infrastructure') }}">
                            <span class="category-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M9 21h6m-5-3h4m-5-3a6 6 0 1 1 6 0c-.8.6-1 1.2-1 2h-4c0-.8-.2-1.4-1-2Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <b>Streetlights</b><small>Dark or broken lamps</small>
                        </a>
                        <a class="category-card" href="/reports/new?category={{ urlencode('Sanitation') }}">
                            <span class="category-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16m-10-3h4m-7 3 1 13h8l1-13m-7 3v7m4-7v7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <b>Garbage</b><small>Missed pickups, dumping</small>
                        </a>
                        <a class="category-card" href="/reports/new?category={{ urlencode('Flooding') }}">
                            <span class="category-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M3 8c2.2 0 2.2-2 4.4-2s2.2 2 4.4 2 2.2-2 4.4-2 2.2 2 4.4 2M3 13c2.2 0 2.2-2 4.4-2s2.2 2 4.4 2 2.2-2 4.4-2 2.2 2 4.4 2m-17 5c2.2 0 2.2-2 4.4-2s2.2 2 4.4 2 2.2-2 4.4-2 2.2 2 4.4 2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
                            <b>Drainage &amp; flooding</b><small>Clogged canals, standing water</small>
                        </a>
                        <a class="category-card" href="/reports/new?category={{ urlencode('Road/Infrastructure') }}">
                            <span class="category-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M8 3 5 21m11-18 3 18M12 5v3m0 4v3m0 4v2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
                            <b>Roads &amp; sidewalks</b><small>Potholes, obstructions</small>
                        </a>
                        <a class="category-card" href="/reports/new?category={{ urlencode('Noise') }}">
                            <span class="category-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M11 5 6 9H3v6h3l5 4V5Zm4 4a4 4 0 0 1 0 6m3-9a8 8 0 0 1 0 12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <b>Noise &amp; disturbance</b><small>Neighbor and event noise</small>
                        </a>
                        <a class="category-card" href="/reports/new?category={{ urlencode('Other') }}">
                            <span class="category-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 3v3m0 12v3M3 12h3m12 0h3m-2.6-6.4-2.1 2.1m-8.6 8.6-2.1 2.1m0-12.8 2.1 2.1m8.6 8.6 2.1 2.1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
                            <b>Something else</b><small>Tell us in your own words</small>
                        </a>
                    </div>
                </section>

                <section class="privacy-callout">
                    <span class="privacy-callout-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" width="19" height="19">
                            <rect x="5" y="10" width="14" height="11" rx="2" stroke="currentColor" stroke-width="1.7"/>
                            <path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <div class="privacy-callout-copy">
                        <h2>Your name stays with you</h2>
                        <p>Choose to report anonymously or share your contact. Only authorized barangay staff can see your details.</p>
                    </div>
                    <a class="button privacy-callout-button" href="/privacy">Read our privacy notice</a>
                </section>
                </div>
            @break

            @case('auth')
                <section class="narrow-section auth-section">
                    <div class="form-card">
                        <span class="eyebrow">Welcome</span>
                        <h1>Sign in or create your resident account.</h1>
                        <div class="auth-tabs">
                            <button type="button" class="auth-tab active" data-auth-tab="signin">Sign in</button>
                            <button type="button" class="auth-tab" data-auth-tab="signup">Create account</button>
                        </div>

                        <form method="post" action="/auth/login" class="auth-form" data-auth-form="signin">@csrf
                            <div class="field">
                                <label for="login-email">Email address</label>
                                <input type="email" id="login-email" name="email" autocomplete="email" required>
                            </div>
                            <div class="field">
                                <label for="login-password">Password</label>
                                <input type="password" id="login-password" name="password" autocomplete="current-password" required>
                            </div>
                            <button type="submit" class="button button-primary button-wide">Sign in</button>
                        </form>

                        <form method="post" action="/auth/register" class="auth-form hidden" data-auth-form="signup">@csrf
                            <div class="field">
                                <label for="signup-name">Full name</label>
                                <input type="text" id="signup-name" name="name" autocomplete="name" required>
                            </div>
                            <div class="field">
                                <label for="signup-email">Email address</label>
                                <input type="email" id="signup-email" name="email" autocomplete="email" required>
                            </div>
                            <div class="field">
                                <label for="signup-phone">Contact number</label>
                                <input type="tel" id="signup-phone" name="contact_number" autocomplete="tel" required>
                            </div>
                            <div class="field">
                                <label for="signup-address">Address / purok</label>
                                <input type="text" id="signup-address" name="address" autocomplete="street-address" required>
                            </div>
                            <div class="field">
                                <label for="signup-password">Password</label>
                                <input type="password" id="signup-password" name="password" minlength="8" autocomplete="new-password" required>
                            </div>
                            <div class="field">
                                <label for="signup-confirm">Confirm password</label>
                                <input type="password" id="signup-confirm" name="password_confirmation" minlength="8" autocomplete="new-password" required>
                            </div>
                            <button type="submit" class="button button-primary button-wide">Create resident account</button>
                        </form>
                    </div>
                </section>
            @break

            @case('staff-auth')
                <section class="narrow-section auth-section">
                    <div class="form-card">
                        <span class="eyebrow">Authorized personnel</span>
                        <h1>Staff portal sign in.</h1>
                        <p>This sign-in is separate from resident accounts. Staff access is restricted to authorized staff, handlers, officers, and administrators.</p>
                        <form method="post" action="/staff/login">@csrf
                            <div class="field">
                                <label for="staff-login-email">Work email address</label>
                                <input type="email" id="staff-login-email" name="email" autocomplete="username" required>
                            </div>
                            <div class="field">
                                <label for="staff-login-password">Password</label>
                                <input type="password" id="staff-login-password" name="password" autocomplete="current-password" required>
                            </div>
                            <button type="submit" class="button button-primary button-wide">Sign in to staff portal</button>
                        </form>
                        <p><a class="text-link" href="/">Return to public website</a></p>
                    </div>
                </section>
            @break

            @case('reports')
                <section class="content-section">
                    <div class="page-heading">
                        <div>
                            <span class="eyebrow">Your account</span>
                            <h1>My Reports</h1>
                        </div>
                        <a href="/reports/new" class="button button-primary">New report</a>
                    </div>

                    @if (isset($reports) && $reports->isNotEmpty())
                        <div class="report-grid">
                            @foreach ($reports as $report)
                                <a href="/reports/{{ $report->id }}" class="report-card">
                                    <div class="report-card-top">
                                        <span class="status-badge status-{{ $report->status }}"><i></i>{{ str_replace('_', ' ', ucfirst($report->status)) }}</span>
                                        <span class="report-date">{{ \Illuminate\Support\Carbon::parse($report->created_at)->format('M d, Y') }}</span>
                                    </div>
                                    <h3>{{ $report->title }}</h3>
                                    <p>{{ $report->category }} <span>·</span> {{ $report->reference_no }}</p>
                                    <span class="card-link">View details</span>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="empty-state">
                            <div class="empty-icon">✳</div>
                            <h2>No reports yet.</h2>
                            <p>Your dashboard is ready for your first concern.</p>
                            <a href="/reports/new" class="button button-primary">Submit a report</a>
                        </div>
                    @endif
                </section>
            @break

            @case('new-report')
                <section class="narrow-section report-form-section">
                    <a href="/reports" class="back-link">← back to my reports</a>
                    <div class="form-card">
                        <span class="eyebrow">Before we begin</span>
                        <h1>Share a concern.</h1>
                        <p>This portal is for non-emergency concerns only.</p>
                        <form method="post" action="/reports" enctype="multipart/form-data" id="report-form">@csrf
                            <div class="field">
                                <label for="title">Report title</label>
                                <input id="title" name="title" value="{{ old('title') }}" required>
                            </div>
                            <div class="field">
                                <label for="category">Category</label>
                                <select id="category" name="category" required>
                                    <option value="">Choose a category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}" @selected(old('category', $selectedCategory ?? '') === $category)>{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="5" required>{{ old('description') }}</textarea>
                            </div>
                            <div class="field">
                                <label for="address">Address or purok</label>
                                <input id="address" name="address" value="{{ old('address') }}">
                            </div>
                            <div class="field">
                                <label for="photo">Attach a photo</label>
                                <input id="photo" name="photo" type="file" accept="image/*">
                            </div>
                            <label class="check-row">
                                <input type="checkbox" name="is_anonymous" value="1">
                                <span>Submit anonymously</span>
                            </label>
                            <label class="check-row consent-row">
                                <input type="checkbox" name="consent_given" value="1" required>
                                <span>I confirm this is a non-emergency concern and I consent to the barangay reviewing this report.</span>
                            </label>
                            <button type="submit" class="button button-primary button-wide">Submit report</button>
                        </form>
                    </div>
                </section>
            @break

            @case('notice')
                <section class="narrow-section">
                    <div class="form-card">
                        <span class="eyebrow">Before you report</span>
                        <h1>Read the non-emergency notice.</h1>
                        <p>This portal is for non-emergency community concerns only and should not be used for urgent danger, medical emergencies, or crimes in progress.</p>
                        <ul>
                            <li>Use 911 or your barangay hotline for emergencies.</li>
                            <li>Reports here are not monitored in real time.</li>
                            <li>Office hours and acknowledgment times are shown on the confirmation screen.</li>
                        </ul>
                        <form method="post" action="/reports/notice">@csrf
                            @if (! empty($selectedCategory))
                                <input type="hidden" name="category" value="{{ $selectedCategory }}">
                            @endif
                            <label class="check-row consent-row">
                                <input type="checkbox" name="non_emergency_acknowledged" value="1" required>
                                <span>I understand this portal is only for non-emergency concerns.</span>
                            </label>
                            <button class="button button-primary button-wide" type="submit">Continue to report form</button>
                        </form>
                    </div>
                </section>
            @break

            @case('report-detail')
                <section class="content-section">
                    <div class="page-heading">
                        <div>
                            <span class="eyebrow">Resident report</span>
                            <h1>{{ $report->reference_no ?? 'Report details' }}</h1>
                        </div>
                        <a href="/reports" class="button button-outline">Back to my reports</a>
                    </div>
                    <div class="form-card">
                        <div class="report-card-top">
                            <span class="status-badge status-{{ $report->status }}"><i></i>{{ str_replace('_', ' ', ucfirst($report->status)) }}</span>
                            <span class="report-date">{{ \Illuminate\Support\Carbon::parse($report->created_at)->format('M d, Y') }}</span>
                        </div>
                        <h2>{{ $report->title }}</h2>
                        <p><strong>Category:</strong> {{ $report->category }}<br>
                        <strong>Reference:</strong> {{ $report->reference_no }}<br>
                        <strong>Location:</strong> {{ $report->address ?: 'Not provided' }}</p>
                        <p>{{ $report->description }}</p>
                    </div>

                    <div class="form-card">
                        <span class="eyebrow">Status timeline</span>
                        <h2>Updates</h2>
                        @forelse ($timeline ?? [] as $item)
                            <div class="timeline-item">
                                <strong>{{ str_replace('_', ' ', ucfirst($item->status)) }}</strong>
                                <small>{{ \Illuminate\Support\Carbon::parse($item->created_at)->format('M d, Y · h:i A') }}</small>
                                <p>{{ $item->note }}</p>
                            </div>
                        @empty
                            <p>No updates yet.</p>
                        @endforelse
                    </div>
                </section>
            @break

            @case('profile')
                <section class="narrow-section">
                    <div class="form-card">
                        <span class="eyebrow">Your profile</span>
                        <h1>Resident details</h1>
                        <form method="post" action="/profile">@csrf
                            <div class="field">
                                <label for="profile-name">Full name</label>
                                <input id="profile-name" name="name" value="{{ old('name', auth()->user()->name ?? '') }}" required>
                            </div>
                            <div class="field">
                                <label for="profile-phone">Contact number</label>
                                <input id="profile-phone" name="contact_number" value="{{ old('contact_number', auth()->user()->contact_number ?? '') }}" required>
                            </div>
                            <div class="field">
                                <label for="profile-address">Address / purok</label>
                                <input id="profile-address" name="address" value="{{ old('address', auth()->user()->address ?? '') }}" required>
                            </div>
                            <button type="submit" class="button button-primary button-wide">Save profile</button>
                        </form>
                    </div>
                </section>
            @break

            @case('track')
                <section class="narrow-section track-page">
                    <div class="form-card">
                        <span class="eyebrow">Track a report</span>
                        <h1>Check its status.</h1>
                        <form method="get" action="/track">
                            <div class="field">
                                <label for="reference-no">Reference number</label>
                                <input id="reference-no" name="reference_no" value="{{ request('reference_no') }}" required>
                            </div>
                            <button type="submit" class="button button-primary button-wide">Check status</button>
                        </form>

                        @if (request()->filled('reference_no'))
                            @if ($report)
                                <div class="track-result">
                                    <span class="eyebrow">{{ $report->reference_no }}</span>
                                    <h2>{{ $report->category }}</h2>
                                    <span class="status-badge status-{{ $report->status }}"><i></i>{{ str_replace('_', ' ', ucfirst($report->status)) }}</span>
                                    <p>Last updated {{ \Illuminate\Support\Carbon::parse($report->updated_at)->format('M d, Y · h:i A') }}</p>
                                </div>
                            @else
                                <div class="not-found">
                                    <b>We couldn’t find that report.</b>
                                    <p>Check the number and try again. We use a generic not-found message for privacy.</p>
                                </div>
                            @endif
                        @endif
                    </div>
                </section>
            @break

            @case('staff')
                <section class="admin-content">
                    <div class="admin-page-heading">
                        <div>
                            <span class="eyebrow">STAFF WORKSPACE</span>
                            <h1>Good day, {{ auth()->user()->name ?? 'Staff member' }}</h1>
                            <p>Review incoming concerns and keep community reports moving.</p>
                        </div>
                        <span class="admin-date">{{ now()->format('l, F j, Y') }}</span>
                    </div>
                    <div class="admin-metric-grid">
                        <article class="admin-metric-card">
                            <span class="admin-metric-icon">▤</span>
                            <span class="admin-metric-label">Total reports</span>
                            <strong>{{ number_format($dashboardMetrics['reports'] ?? 0) }}</strong>
                            <small>All community submissions</small>
                        </article>
                        <article class="admin-metric-card">
                            <span class="admin-metric-icon is-blue">◷</span>
                            <span class="admin-metric-label">Open cases</span>
                            <strong>{{ number_format($dashboardMetrics['open'] ?? 0) }}</strong>
                            <small>Awaiting action or in progress</small>
                        </article>
                        <article class="admin-metric-card">
                            <span class="admin-metric-icon is-green">✓</span>
                            <span class="admin-metric-label">Resolved</span>
                            <strong>{{ number_format($dashboardMetrics['resolved'] ?? 0) }}</strong>
                            <small>Resolved or closed reports</small>
                        </article>
                        <article class="admin-metric-card">
                            <span class="admin-metric-icon is-amber">!</span>
                            <span class="admin-metric-label">Urgent flags</span>
                            <strong>{{ number_format($dashboardMetrics['urgent'] ?? 0) }}</strong>
                            <small>Requires staff attention</small>
                        </article>
                    </div>
                    <div class="admin-dashboard-grid">
                        <section class="admin-panel admin-recent-panel">
                            <div class="admin-panel-heading">
                                <div><h2>Reports inbox</h2><p>Search and filter incoming community submissions</p></div>
                                <span class="admin-count-pill">{{ $reports->total() }} reports</span>
                            </div>
                            <form method="get" action="/staff" class="staff-dashboard-filters">
                                <label class="sr-only" for="staff-search">Search reports</label>
                                <input id="staff-search" type="search" name="q" value="{{ request('q') }}" placeholder="Reference or title">
                                <label class="sr-only" for="staff-status-filter">Filter by status</label>
                                <select id="staff-status-filter" name="status">
                                    <option value="">All statuses</option>
                                    @foreach (['pending','under_review','assigned','action_ongoing','resolved','closed'] as $status)
                                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                                    @endforeach
                                </select>
                                <label class="sr-only" for="staff-category-filter">Filter by category</label>
                                <select id="staff-category-filter" name="category">
                                    <option value="">All categories</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                                    @endforeach
                                </select>
                                <button class="button button-primary" type="submit">Apply</button>
                            </form>
                            @if ($reports->isNotEmpty())
                                <div class="admin-table-wrap">
                                    <table class="admin-table staff-dashboard-table">
                                        <thead><tr><th>Report</th><th>Category</th><th>Status</th><th>Received</th></tr></thead>
                                        <tbody>
                                            @foreach ($reports as $report)
                                                <tr>
                                                    <td><a href="/staff/reports/{{ $report->id }}">{{ $report->reference_no }} · {{ $report->title }}</a>@if ($report->is_urgent)<span class="admin-urgent-tag">Urgent</span>@endif</td>
                                                    <td>{{ $report->category }}</td>
                                                    <td><span class="status-badge status-{{ $report->status }}"><i></i>{{ str_replace('_', ' ', ucfirst($report->status)) }}</span></td>
                                                    <td>{{ \Illuminate\Support\Carbon::parse($report->created_at)->format('M j, Y') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                {{ $reports->links('pagination::simple-tailwind') }}
                            @else
                                <div class="admin-empty">No reports match the selected filters.</div>
                            @endif
                        </section>
                        <section class="admin-panel admin-quick-panel">
                            <div class="admin-panel-heading"><div><h2>Staff tools</h2><p>Quick access to your work</p></div></div>
                            <a class="admin-quick-link" href="/staff/tasks"><span>✓</span><span><b>Call-resident tasks</b><small>Follow up on urgent reports</small></span><i>→</i></a>
                            <a class="admin-quick-link" href="/staff/referrals"><span>↗</span><span><b>Partner referrals</b><small>Coordinate open referrals</small></span><i>→</i></a>
                            <a class="admin-quick-link" href="/staff/analytics"><span>▥</span><span><b>Report analytics</b><small>View de-identified summaries</small></span><i>→</i></a>
                            <div class="admin-security-note"><b>Privacy-first access</b><p>Report details remain masked unless you are authorized. Restricted access requests are time-limited and audited.</p></div>
                        </section>
                    </div>
                </section>
            @break

            @case('staff-detail')
                <section class="content-section">
                    <div class="page-heading">
                        <div>
                            <span class="eyebrow">Staff review</span>
                            <h1>{{ $report->reference_no ?? 'Report review' }}</h1>
                        </div>
                        <div class="page-heading-actions">
                            @if ($report->confidentiality !== 'restricted' || $canViewDetails)
                                <a href="/staff/reports/{{ $report->id }}/print" class="button button-outline">Print report</a>
                            @endif
                            <a href="/staff" class="button button-outline">Back to inbox</a>
                        </div>
                    </div>
                    <div class="form-card">
                        <h2>{{ $report->title }}</h2>
                        <p><strong>Resident:</strong> {{ $report->is_anonymous ? 'Anonymous resident' : ($report->resident->name ?? 'Resident') }}</p>
                        <p><strong>Category:</strong> {{ $report->category }}<br>
                        <strong>Status:</strong> {{ str_replace('_', ' ', ucfirst($report->status)) }}<br>
                        <strong>Location:</strong> {{ $report->address ?: 'Not provided' }}</p>
                        <p>{{ $report->description }}</p>
                        @if ($report->photo_path && $canViewDetails)
                            <figure class="report-attachment">
                                <img src="/staff/reports/{{ $report->id }}/photo" alt="Photo attached to report {{ $report->reference_no }}" loading="lazy">
                                <figcaption>Photo attached to this report</figcaption>
                            </figure>
                        @endif
                        @if ($report->confidentiality === 'restricted' && ! $canViewDetails)
                            <div class="not-found" role="note">
                                <b>Restricted report</b>
                                <p>Only the assigned handler and officers may access this report. Other staff must provide a written reason of at least 20 characters for 30-minute emergency access. This access is audited and flagged for admin review.</p>
                            </div>
                            <form method="post" action="/staff/reports/{{ $report->id }}/break-glass">@csrf
                                <div class="field">
                                    <label for="break-glass-reason">Reason for emergency access</label>
                                    <textarea id="break-glass-reason" name="reason" rows="3" minlength="20" maxlength="1000" required>{{ old('reason') }}</textarea>
                                </div>
                                <button type="submit" class="button button-urgent button-wide">Request 30-minute emergency access</button>
                            </form>
                        @elseif ($report->confidentiality === 'restricted' && ! $hasAccess)
                            <div class="not-found" role="note">
                                <b>Administrator read-only access</b>
                                <p>Report details are visible for review. Only the assigned handler or an officer can make changes to this restricted report.</p>
                            </div>
                        @else
                            <form method="post" action="/staff/reports/{{ $report->id }}">@csrf
                                <div class="field">
                                    <label for="staff-status">Status</label>
                                    <select id="staff-status" name="status">
                                        @foreach (['pending','under_review','assigned','action_ongoing','resolved','closed'] as $status)
                                            <option value="{{ $status }}" @selected($report->status === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="staff-note">Update note</label>
                                    <textarea id="staff-note" name="note" rows="3" required>{{ old('note') }}</textarea>
                                </div>
                                <div class="field">
                                    <label for="staff-category">Category</label>
                                    <select id="staff-category" name="category" required>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category }}" @selected($report->category === $category)>{{ $category }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="priority">Priority</label>
                                    <select id="priority" name="priority">
                                        @foreach (['normal','high','urgent'] as $priority)
                                            <option value="{{ $priority }}" @selected(($report->priority ?? 'normal') === $priority)>{{ ucfirst($priority) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="confidentiality">Confidentiality</label>
                                    <select id="confidentiality" name="confidentiality">
                                        @foreach (['standard','confidential','restricted'] as $level)
                                            <option value="{{ $level }}" @selected(($report->confidentiality ?? 'standard') === $level)>{{ ucfirst($level) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="assigned-to">Assigned handler</label>
                                    <select id="assigned-to" name="assigned_to">
                                        <option value="">Unassigned</option>
                                        @foreach ($staffUsers as $staffUser)
                                            <option value="{{ $staffUser->id }}" @selected((int) $report->assigned_to === (int) $staffUser->id)>{{ $staffUser->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <label class="check-row">
                                    <input type="checkbox" name="is_urgent" value="1" @checked($report->is_urgent)>
                                    <span>Flag as urgent and create a call-resident task</span>
                                </label>
                                <button type="submit" class="button button-primary button-wide">Update report</button>
                            </form>
                        @endif
                    </div>
                </section>
            @break

            @case('tasks')
                <section class="content-section">
                    <div class="page-heading">
                        <div>
                            <span class="eyebrow">Tasks</span>
                            <h1>Call-resident follow-ups</h1>
                        </div>
                    </div>
                    @if (isset($tasks) && $tasks->isNotEmpty())
                        @foreach ($tasks as $task)
                            <div class="form-card">
                                <h3>{{ $task->reference_no ?? 'Task' }}</h3>
                                <p>{{ $task->note ?? 'Resident follow-up required.' }}</p>
                            </div>
                        @endforeach
                    @else
                        <div class="empty-state"><div class="empty-icon">•</div><h2>No active tasks.</h2></div>
                    @endif
                </section>
            @break

            @case('referrals')
                <section class="content-section">
                    <div class="page-heading">
                        <div>
                            <span class="eyebrow">Referrals</span>
                            <h1>Partner office coordination</h1>
                        </div>
                    </div>
                    @if (isset($referrals) && $referrals->isNotEmpty())
                        @foreach ($referrals as $referral)
                            <div class="form-card">
                                <h3>{{ $referral->receiving_office }}</h3>
                                <p><strong>Report:</strong> {{ $referral->reference_no ?? '—' }}<br>
                                <strong>Status:</strong> {{ str_replace('_', ' ', ucfirst($referral->status)) }}</p>
                            </div>
                        @endforeach
                    @else
                        <div class="empty-state"><div class="empty-icon">↗</div><h2>No referrals.</h2></div>
                    @endif
                </section>
            @break

            @case('analytics')
                <section class="content-section">
                    <div class="page-heading">
                        <div>
                            <span class="eyebrow">Analytics</span>
                            <h1>Aggregate reporting</h1>
                        </div>
                        <a href="/staff/analytics/export" class="button button-primary">Export CSV</a>
                    </div>
                    @if (isset($metrics))
                        <div class="report-grid">
                            <div class="report-card"><h3>{{ $metrics->reports ?? 0 }}</h3><p>Total reports</p></div>
                            <div class="report-card"><h3>{{ $metrics->resolved ?? 0 }}</h3><p>Resolved</p></div>
                            <div class="report-card"><h3>{{ $metrics->referrals ?? 0 }}</h3><p>Referrals</p></div>
                        </div>
                    @endif
                    @if (isset($aggregates) && $aggregates->isNotEmpty())
                        <div class="form-card">
                            <table>
                                <thead><tr><th>Category</th><th>Status</th><th>Month</th><th>Count</th></tr></thead>
                                <tbody>
                                    @foreach ($aggregates as $row)
                                        <tr><td>{{ $row->category }}</td><td>{{ str_replace('_', ' ', ucfirst($row->status)) }}</td><td>{{ $row->month }}</td><td>{{ $row->display_count ?? $row->total }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @break

            @case('admin')
                <section class="admin-content">
                    <div class="admin-page-heading">
                        <div>
                            <span class="eyebrow">ADMINISTRATION</span>
                            <h1>Good day, {{ auth()->user()->name ?? 'Administrator' }}</h1>
                            <p>Monitor community reports, manage access, and keep portal settings current.</p>
                        </div>
                        <span class="admin-date">{{ now()->format('l, F j, Y') }}</span>
                    </div>

                    <div class="admin-metric-grid">
                        <article class="admin-metric-card">
                            <span class="admin-metric-icon">▤</span>
                            <span class="admin-metric-label">Total reports</span>
                            <strong>{{ number_format($dashboardMetrics['reports'] ?? 0) }}</strong>
                            <small>All submitted concerns</small>
                        </article>
                        <article class="admin-metric-card">
                            <span class="admin-metric-icon is-blue">◷</span>
                            <span class="admin-metric-label">Open cases</span>
                            <strong>{{ number_format($dashboardMetrics['open'] ?? 0) }}</strong>
                            <small>Awaiting follow-up or in progress</small>
                        </article>
                        <article class="admin-metric-card">
                            <span class="admin-metric-icon is-green">✓</span>
                            <span class="admin-metric-label">Resolved</span>
                            <strong>{{ number_format($dashboardMetrics['resolved'] ?? 0) }}</strong>
                            <small>Resolved or closed reports</small>
                        </article>
                        <article class="admin-metric-card">
                            <span class="admin-metric-icon is-amber">!</span>
                            <span class="admin-metric-label">Urgent flags</span>
                            <strong>{{ number_format($dashboardMetrics['urgent'] ?? 0) }}</strong>
                            <small>Requires staff attention</small>
                        </article>
                    </div>

                    <div class="admin-dashboard-grid">
                        <section class="admin-panel admin-recent-panel">
                            <div class="admin-panel-heading">
                                <div><h2>Recent reports</h2><p>Latest community submissions</p></div>
                                <a href="/staff" class="admin-text-link">Open staff inbox →</a>
                            </div>
                            @if (isset($recentReports) && $recentReports->isNotEmpty())
                                <div class="admin-table-wrap">
                                    <table class="admin-table">
                                        <thead><tr><th>Reference</th><th>Category</th><th>Status</th><th>Received</th></tr></thead>
                                        <tbody>
                                            @foreach ($recentReports as $recentReport)
                                                <tr>
                                                    <td><a href="/staff/reports/{{ $recentReport->id }}">{{ $recentReport->reference_no }}</a>@if ($recentReport->is_urgent)<span class="admin-urgent-tag">Urgent</span>@endif</td>
                                                    <td>{{ $recentReport->category }}</td>
                                                    <td><span class="status-badge status-{{ $recentReport->status }}"><i></i>{{ str_replace('_', ' ', ucfirst($recentReport->status)) }}</span></td>
                                                    <td>{{ \Illuminate\Support\Carbon::parse($recentReport->created_at)->format('M j, Y') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="admin-empty">No reports have been submitted yet.</div>
                            @endif
                        </section>

                        <section class="admin-panel admin-quick-panel">
                            <div class="admin-panel-heading"><div><h2>Workspace</h2><p>Administration shortcuts</p></div></div>
                            <a class="admin-quick-link" href="#users"><span>♙</span><span><b>Manage users &amp; roles</b><small>{{ number_format($dashboardMetrics['staff'] ?? 0) }} staff accounts</small></span><i>→</i></a>
                            <a class="admin-quick-link" href="#configuration"><span>⚙</span><span><b>Portal configuration</b><small>Hotline, office hours, categories</small></span><i>→</i></a>
                            <a class="admin-quick-link" href="/admin/audit"><span>◷</span><span><b>Audit &amp; security</b><small>Review access and changes</small></span><i>→</i></a>
                            <div class="admin-security-note"><b>Privacy-first access</b><p>Restricted report details remain masked unless you have an authorized role or active emergency access.</p></div>
                        </section>
                    </div>

                    <section class="admin-panel admin-user-panel" id="users">
                        <div class="admin-panel-heading">
                            <div><h2>User access</h2><p>Assign a single role to each account. Role changes are recorded in the audit log.</p></div>
                            <span class="admin-count-pill">{{ count($users) }} accounts</span>
                        </div>
                        <div class="admin-config-card admin-create-staff">
                            <h3>Add a staff account</h3>
                            <p>Create a staff-only sign-in. New accounts cannot choose their own role.</p>
                            <form method="post" action="/admin/staff" class="admin-create-staff-form">@csrf
                                <div class="field">
                                    <label for="staff-name">Full name</label>
                                    <input id="staff-name" name="name" value="{{ old('name') }}" autocomplete="name" required>
                                </div>
                                <div class="field">
                                    <label for="staff-email">Email address</label>
                                    <input id="staff-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                                </div>
                                <div class="field">
                                    <label for="staff-password">Initial password</label>
                                    <input id="staff-password" type="password" name="password" autocomplete="new-password" minlength="8" required>
                                </div>
                                <div class="field">
                                    <label for="staff-password-confirmation">Confirm temporary password</label>
                                    <input id="staff-password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
                                </div>
                                <button type="submit" class="button button-primary">Create staff account</button>
                            </form>
                        </div>
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead><tr><th>Name</th><th>Email</th><th>Current role</th><th>Access</th><th>Change role</th><th>Account controls</th></tr></thead>
                                <tbody>
                                    @foreach ($users as $user)
                                        <tr>
                                            <td>{{ $user->name }}</td>
                                            <td>{{ $user->email }}</td>
                                            <td><span class="admin-role-pill">{{ ucfirst($user->role) }}</span></td>
                                            <td><span class="admin-access-pill {{ $user->is_active ? 'is-active' : 'is-removed' }}">{{ $user->is_active ? 'Active' : 'Deactivated' }}</span></td>
                                            <td>
                                                <form method="post" action="/admin/users/{{ $user->id }}/role" class="admin-role-form">@csrf
                                                    <select name="role" aria-label="Role for {{ $user->name }}">
                                                        @foreach (['resident', 'staff', 'handler', 'officer', 'admin'] as $role)
                                                            <option value="{{ $role }}" @selected($user->role === $role)>{{ ucfirst($role) }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="button button-outline">Save role</button>
                                                </form>
                                            </td>
                                            <td>
                                                @if (in_array($user->role, ['resident', 'staff', 'handler', 'officer'], true))
                                                    @if ($user->is_active)
                                                        <form method="post" action="/admin/users/{{ $user->id }}" onsubmit="return confirm('Deactivate this user account? They will lose access, but reports and audit history will be preserved.')" class="admin-staff-access-form">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="button button-outline">Deactivate</button>
                                                        </form>
                                                    @else
                                                        <form method="post" action="/admin/users/{{ $user->id }}/restore" class="admin-staff-access-form">
                                                            @csrf
                                                            <button type="submit" class="button button-outline">Reactivate</button>
                                                        </form>
                                                    @endif
                                                @else
                                                    <span class="admin-muted-action">Admin protected</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="admin-panel admin-configuration-panel" id="configuration">
                        <div class="admin-panel-heading">
                            <div><h2>Portal configuration</h2><p>Keep public safety information and report classifications up to date.</p></div>
                        </div>
                        <div class="admin-config-grid">
                            <div class="admin-config-card">
                                <h3>Site &amp; safety settings</h3>
                                <form method="post" action="/admin/settings">@csrf
                                    <div class="field"><label for="barangay_name">Barangay name</label><input id="barangay_name" name="barangay_name" value="{{ $settings->barangay_name ?? '' }}" required></div>
                                    <div class="field"><label for="hotline_label">Hotline label</label><input id="hotline_label" name="hotline_label" value="{{ $settings->hotline_label ?? '' }}" required></div>
                                    <div class="field"><label for="hotline_number">Hotline number</label><input id="hotline_number" name="hotline_number" value="{{ $settings->hotline_number ?? '' }}" required></div>
                                    <div class="field"><label for="office_hours">Office hours</label><input id="office_hours" name="office_hours" value="{{ $settings->office_hours ?? '' }}" required></div>
                                    <div class="field"><label for="acknowledgment_target">Acknowledgment target</label><input id="acknowledgment_target" name="acknowledgment_target" value="{{ $settings->acknowledgment_target ?? '' }}" required></div>
                                    <div class="field"><label for="overdue_days">Referral overdue reminder (days)</label><input id="overdue_days" type="number" min="1" max="60" name="overdue_days" value="{{ $settings->overdue_days ?? 3 }}" required></div>
                                    <button class="button button-primary" type="submit">Save safety settings</button>
                                </form>
                            </div>
                            <div class="admin-config-card">
                                <h3>Report categories</h3>
                                <div class="admin-chip-list">
                                    @foreach ($categories as $category)
                                        <span class="admin-config-chip">{{ $category->name }} <i>{{ $category->is_active ? 'Active' : 'Inactive' }}</i></span>
                                    @endforeach
                                </div>
                                <form method="post" action="/admin/categories" class="admin-inline-add">@csrf
                                    <div class="field"><label for="category-name">Add a category</label><input id="category-name" name="name" placeholder="Community clean-up" required></div>
                                    <button class="button button-outline" type="submit">Add category</button>
                                </form>
                                <h3 class="admin-subsection-title">Urgent keywords</h3>
                                <div class="admin-chip-list">
                                    @foreach ($keywords as $keyword)
                                        <span class="admin-config-chip">{{ $keyword->keyword }} <i>{{ strtoupper($keyword->language) }}</i></span>
                                    @endforeach
                                </div>
                                <form method="post" action="/admin/keywords" class="admin-inline-add">@csrf
                                    <div class="field"><label for="keyword">Add a triage keyword</label><input id="keyword" name="keyword" placeholder="fire" required></div>
                                    <div class="field"><label for="keyword-language">Language</label><select id="keyword-language" name="language"><option value="en">English</option><option value="fil">Filipino</option><option value="ceb">Cebuano</option></select></div>
                                    <button class="button button-outline" type="submit">Add keyword</button>
                                </form>
                            </div>
                        </div>
                    </section>
                </section>
            @break

            @case('audit')
                <section class="content-section">
                    <div class="page-heading">
                        <div>
                            <span class="eyebrow">Audit log</span>
                            <h1>Security review</h1>
                        </div>
                        <form method="post" action="/admin/audit/verify">@csrf<button class="button button-primary" type="submit">Verify integrity</button></form>
                    </div>
                    @if (isset($logs) && $logs->isNotEmpty())
                        @foreach ($logs as $log)
                            <div class="form-card">
                                <p><strong>{{ $log->action }}</strong> · {{ $log->entity }} · {{ $log->entity_id }}</p>
                                <small>{{ \Illuminate\Support\Carbon::parse($log->created_at)->format('M d, Y · h:i A') }}</small>
                            </div>
                        @endforeach
                    @else
                        <div class="empty-state"><div class="empty-icon">◎</div><h2>No audit entries.</h2></div>
                    @endif
                </section>
            @break

            @case('partner')
                <section class="narrow-section">
                    <div class="form-card">
                        <span class="eyebrow">Partner access</span>
                        <h1>Referral update</h1>
                        <p><strong>Receiving office:</strong> {{ $referral->receiving_office ?? 'Partner office' }}</p>
                        <p><strong>Shared data:</strong> {{ $referral->shared_data ?? 'No data shared' }}</p>
                        <form method="post" action="/partner/{{ $token }}">@csrf
                            <div class="field">
                                <label for="partner-status">Status</label>
                                <select id="partner-status" name="status">
                                    @foreach (['referred','received_by_agency','action_ongoing','resolved','returned'] as $status)
                                        <option value="{{ $status }}">{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="partner-note">Status note</label>
                                <textarea id="partner-note" name="note" rows="3" required></textarea>
                            </div>
                            <button class="button button-primary button-wide" type="submit">Submit update</button>
                        </form>
                    </div>
                </section>
            @break

            @case('privacy')
                <section class="narrow-section">
                    <div class="form-card privacy-page">
                        <span class="eyebrow">Privacy notice</span>
                        <h1>RA 10173 data privacy notice.</h1>
                        <p>This placeholder notice should be reviewed and finalized by the barangay before public launch.</p>
                        <h2>What information is collected</h2>
                        <p>We collect only the information required to receive and act on a non-emergency concern, such as contact details, address, concern summary, and optional photos or location.</p>
                        <h2>How it is used</h2>
                        <p>We use the information to review a report, assign it to a responsible party, keep the status timeline, and support a proper response within office hours.</p>
                        <h2>Who can access it</h2>
                        <p>Only authorized barangay staff, assigned handlers, and any official partner access permitted for a referral can view relevant details. Anonymous reports are masked unless there is a legitimate need.</p>
                        <h2>Emergency reporting</h2>
                        <p>This portal is not an emergency dispatch channel and does not replace 911, police procedures, or legally mandated reporting.</p>
                    </div>
                </section>
            @break

            @default
                <section class="narrow-section">
                    <div class="form-card">
                        <span class="eyebrow">Page not found</span>
                        <h1>That page is not available.</h1>
                        <p>Return to the home page and continue from there.</p>
                        <a href="/" class="button button-primary">Go home</a>
                    </div>
                </section>
            @break
        @endswitch
    </main>

    @if ($dashboardShell)
            </div>
        </div>
    @else
    <footer class="site-footer">
        <a class="brand footer-brand" href="/">
            <span class="brand-mark">b<span>.</span></span>
            <span>{{ $settings->barangay_name ?? 'Barangay Community Portal' }}</span>
        </a>
        <span>Need urgent help? Visit your barangay hall or call <a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings->hotline_number ?? '') }}">your barangay hotline</a>.</span>
        <div>
            <a href="/privacy">Privacy</a>
            <a href="/track">Track</a>
        </div>
    </footer>

    <nav class="mobile-tabs" aria-label="Resident navigation">
        <a href="/">⌂<small>Home</small></a>
        <a href="/reports">▤<small>Reports</small></a>
        <a href="/reports/new" class="mobile-new"><span>＋</span><small>New</small></a>
        <a href="/profile">◉<small>Profile</small></a>
    </nav>
    @endif

    <script>
        window.urgentKeywords = @json(\Illuminate\Support\Facades\DB::getSchemaBuilder()->hasTable('urgent_keywords')
            ? \Illuminate\Support\Facades\DB::table('urgent_keywords')->pluck('keyword')->toArray()
            : []);
    </script>
</body>
</html>
