<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Report {{ $report->reference_no }} · Barangay Community Portal</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="print-report-page">
    <main class="print-report">
        <div class="print-actions">
            <a class="button button-outline" href="/staff/reports/{{ $report->id }}">Back to report</a>
            @if (! $isRestrictedWithoutAccess)
                <button class="button button-primary" type="button" data-print-report>Print report</button>
            @endif
        </div>
        <header class="print-report-header">
            <span class="eyebrow">Barangay Community Portal · Report record</span>
            <h1>{{ $report->reference_no }}</h1>
            <p>Generated {{ now()->format('M j, Y · g:i A') }}</p>
        </header>

        @if ($isRestrictedWithoutAccess)
            <section class="print-protected-notice" role="note">
                <h2>Restricted report</h2>
                <p>You are not authorized to print this report. Request emergency access from the report review page if required.</p>
            </section>
        @else
            <section class="print-report-section">
                <h2>Report details</h2>
                <dl class="print-report-fields">
                    <div><dt>Title</dt><dd>{{ $report->title }}</dd></div>
                    <div><dt>Category</dt><dd>{{ $report->category }}</dd></div>
                    <div><dt>Status</dt><dd>{{ str_replace('_', ' ', ucfirst($report->status)) }}</dd></div>
                    <div><dt>Priority</dt><dd>{{ ucfirst($report->priority) }}@if ($report->is_urgent) · Urgent @endif</dd></div>
                    <div><dt>Confidentiality</dt><dd>{{ ucfirst($report->confidentiality) }}</dd></div>
                    <div><dt>Received</dt><dd>{{ \Illuminate\Support\Carbon::parse($report->created_at)->format('M j, Y · g:i A') }}</dd></div>
                    <div><dt>Last updated</dt><dd>{{ \Illuminate\Support\Carbon::parse($report->updated_at)->format('M j, Y · g:i A') }}</dd></div>
                    @if (! $report->is_anonymous && $hasAccess && $report->resident)
                        <div><dt>Resident</dt><dd>{{ $report->resident->name }}@if ($report->resident->contact_number) · {{ $report->resident->contact_number }} @endif</dd></div>
                    @elseif ($report->is_anonymous)
                        <div><dt>Resident</dt><dd>Anonymous</dd></div>
                    @endif
                    @if ($report->address)
                        <div><dt>Location</dt><dd>{{ $report->address }}</dd></div>
                    @endif
                </dl>
                <div class="print-report-description">
                    <h3>Description</h3>
                    <p>{{ $report->description }}</p>
                </div>
            </section>

            <section class="print-report-section">
                <h2>Status history</h2>
                @if ($timeline->isNotEmpty())
                    <ol class="print-report-timeline">
                        @foreach ($timeline as $entry)
                            <li>
                                <strong>{{ str_replace('_', ' ', ucfirst($entry->status)) }}</strong>
                                <time>{{ \Illuminate\Support\Carbon::parse($entry->created_at)->format('M j, Y · g:i A') }}</time>
                                <p>{{ $entry->note }}</p>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="print-muted">No status updates have been recorded.</p>
                @endif
            </section>
            <footer class="print-report-footer">Internal report copy · Handle according to the report’s confidentiality classification.</footer>
        @endif
    </main>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/js/app.js'])
    @endif
</body>
</html>
