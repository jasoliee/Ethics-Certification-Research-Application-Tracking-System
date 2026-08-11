@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page res-certification-page">
        <header class="dashboard-page-heading">
            <div>
                <h1>Certificate Processing</h1>
                <p>Release Applicant-visible decisions, generate official certificates, and manage future certificate backgrounds.</p>
            </div>
            <details class="res-bulk-release-confirmation">
                <summary class="dashboard-primary-action"><x-dashboard.icon name="award" size="17" /><span>Release All Eligible</span></summary>
                <div role="group" aria-label="Confirm bulk certificate release">
                    <strong>Release all currently eligible certificates?</strong>
                    <p>Every application is revalidated and processed independently. Existing releases are skipped.</p>
                    <form method="POST" action="{{ route('res.certificates.release-eligible') }}" data-disable-on-submit>
                        @csrf
                        <input type="hidden" name="confirmation" value="release_all_eligible">
                        <button class="dashboard-primary-action" type="submit">Confirm Bulk Release</button>
                    </form>
                </div>
            </details>
        </header>

        @if (session('status'))
            <div class="application-success-banner" role="status"><x-dashboard.icon name="check" size="19" /><span>{{ session('status') }}</span></div>
        @endif
        @if ($summary = session('bulk_certificate_summary'))
            <section class="application-panel certificate-bulk-summary" aria-labelledby="bulk-summary-title">
                <header class="application-panel-heading"><div><h2 id="bulk-summary-title">Bulk Release Result</h2></div></header>
                <dl>
                    <div><dt>Eligible</dt><dd>{{ $summary['eligible'] }}</dd></div>
                    <div><dt>Released</dt><dd>{{ $summary['released'] }}</dd></div>
                    <div><dt>Skipped</dt><dd>{{ $summary['skipped'] }}</dd></div>
                    <div><dt>Failed</dt><dd>{{ $summary['failed'] }}</dd></div>
                </dl>
                @if ($summary['failures'])
                    <p role="alert">Failed application codes: {{ implode(', ', $summary['failures']) }}</p>
                @endif
            </section>
        @endif
        @foreach (['decisionRelease', 'certificateRelease', 'certificateBackground'] as $bag)
            @if ($errors->{$bag}->any())
                <div class="res-form-error-summary" role="alert"><x-dashboard.icon name="alert-triangle" size="19" /><div><strong>The request was not completed.</strong><span>{{ $errors->{$bag}->first() }}</span></div></div>
            @endif
        @endforeach

        <form class="application-panel certificate-queue-filters" method="GET" action="{{ route('res.certificates.index') }}">
            <label><span>Search</span><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Application code, title, or Applicant"></label>
            <label>
                <span>Queue state</span>
                <select name="state">
                    <option value="">All relevant records</option>
                    <option value="decision" @selected(($filters['state'] ?? '') === 'decision')>Decision release</option>
                    <option value="eligible" @selected(($filters['state'] ?? '') === 'eligible')>Eligible</option>
                    <option value="released" @selected(($filters['state'] ?? '') === 'released')>Released</option>
                    <option value="failed" @selected(($filters['state'] ?? '') === 'failed')>Generation failed</option>
                    <option value="claimed" @selected(($filters['state'] ?? '') === 'claimed')>Claimed</option>
                </select>
            </label>
            <button class="dashboard-primary-action" type="submit">Apply Filters</button>
            <a class="dashboard-outline-action" href="{{ route('res.certificates.index') }}">Reset</a>
        </form>

        <section class="certificate-queue" aria-labelledby="certificate-queue-title">
            <header class="application-panel-heading"><div><h2 id="certificate-queue-title">Certification Queue</h2><p>{{ $applications->total() }} relevant {{ Str::plural('application', $applications->total()) }}</p></div></header>
            @forelse ($applications as $application)
                @php
                    $state = $certificationStates[$application->id];
                    $certificate = $application->certificate;
                    $cycle = max(0, ((int) $application->current_revision_cycle) - 1);
                    $cycleAssignments = $application->reviewerAssignments->where('review_cycle', $cycle);
                    $latestRelease = $application->decisionReleases->first();
                @endphp
                <article class="application-panel certificate-queue-card">
                    <header>
                        <div>
                            <span>{{ $application->application_code }}</span>
                            <h3>{{ $application->research_title }}</h3>
                            <p>{{ $application->applicant?->name ?? 'Applicant record unavailable' }}</p>
                        </div>
                        <x-dashboard.status-badge :label="$state->label()" :tone="$state->tone()" />
                    </header>
                    <dl class="certificate-queue-metadata">
                        <div><dt>Final/review state</dt><dd>{{ $application->application_status->label() }}</dd></div>
                        <div><dt>Released decision</dt><dd>{{ $latestRelease?->decision?->label() ?? 'Pending' }}</dd></div>
                        <div><dt>Generation</dt><dd>{{ $certificate?->currentVersion ? 'Version '.$certificate->currentVersion->certificate_version.' ready' : ($certificate?->status?->label() ?? 'Not generated') }}</dd></div>
                        <div><dt>Survey</dt><dd>{{ $application->surveyResponse ? 'Completed '.$application->surveyResponse->completed_at?->format('M j, Y') : 'Not completed' }}</dd></div>
                        <div><dt>Claim</dt><dd>{{ $certificate?->claimed_at ? 'Claimed '.$certificate->claimed_at->format('M j, Y') : 'Not claimed' }}</dd></div>
                    </dl>

                    @if ($application->application_status === \App\Enums\ApplicationStatus::ReviewSubmittedPendingRelease)
                        <details class="certificate-decision-release" @if ($errors->decisionRelease->any() && (int) old('application_id') === $application->id) open @endif>
                            <summary>Review submitted decisions and release Applicant-visible result</summary>
                            <form method="POST" action="{{ route('res.certificates.decisions.release', $application) }}" data-disable-on-submit>
                                @csrf
                                <input type="hidden" name="application_id" value="{{ $application->id }}">
                                <div class="certificate-reviewer-decisions">
                                    @foreach ($cycleAssignments as $assignment)
                                        <section>
                                            <header><strong>Reviewer {{ $loop->iteration }}</strong><x-dashboard.status-badge :label="$assignment->reviewSubmission?->decision?->label() ?? 'Pending'" :tone="$assignment->reviewSubmission?->decision?->tone() ?? 'neutral'" /></header>
                                            @forelse ($assignment->comments as $comment)
                                                <label>
                                                    <input type="checkbox" name="comment_ids[]" value="{{ $comment->id }}" @checked(in_array($comment->id, old('comment_ids', [])))>
                                                    <span>
                                                        <strong>{{ $comment->category->label() }} · {{ $comment->scope->label() }}</strong>
                                                        <small>{{ $comment->document?->requirement?->name ?? 'Overall application' }}</small>
                                                        <span>{{ $comment->body }}</span>
                                                    </span>
                                                </label>
                                            @empty
                                                <p>No comments were submitted by this Reviewer.</p>
                                            @endforelse
                                        </section>
                                    @endforeach
                                </div>
                                <label>
                                    <span>Official released decision</span>
                                    <select name="decision" required>
                                        <option value="">Select decision</option>
                                        @foreach ($decisions as $decision)
                                            <option value="{{ $decision->value }}" @selected(old('decision') === $decision->value)>{{ $decision->label() }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <p class="certificate-release-note">Only selected comments become Applicant-visible. A revision decision requires at least one selected document-linked Required Revision comment.</p>
                                <button class="dashboard-primary-action" type="submit">Release Decision and Selected Comments</button>
                            </form>
                        </details>
                    @endif

                    <div class="certificate-queue-actions">
                        @if (in_array($state, [\App\Enums\CertificationState::Eligible, \App\Enums\CertificationState::GenerationFailed], true))
                            <form method="POST" action="{{ route('res.certificates.release', $application) }}" data-disable-on-submit>
                                @csrf
                                <button class="dashboard-primary-action" type="submit">{{ $state === \App\Enums\CertificationState::GenerationFailed ? 'Retry Secure Generation' : 'Generate and Release Certificate' }}</button>
                            </form>
                        @endif
                        @if ($certificate?->currentVersion)
                            <a class="dashboard-outline-action" href="{{ route('res.certificates.versions.preview', [$certificate, $certificate->currentVersion]) }}" target="_blank" rel="noopener">Preview Current PDF</a>
                            <a class="dashboard-outline-action" href="{{ route('res.certificates.versions.download', [$certificate, $certificate->currentVersion]) }}">Download</a>
                            <details class="certificate-regenerate-confirmation">
                                <summary class="dashboard-outline-action">Regenerate</summary>
                                <div>
                                    <p>This creates a new version. Existing issued files remain unchanged.</p>
                                    <form method="POST" action="{{ route('res.certificates.regenerate', $application) }}" data-disable-on-submit>
                                        @csrf
                                        <input type="hidden" name="confirmation" value="regenerate">
                                        <button class="dashboard-primary-action" type="submit">Confirm New Version</button>
                                    </form>
                                </div>
                            </details>
                            <details class="certificate-version-audit">
                                <summary>Version history</summary>
                                <div>
                                    @foreach ($certificate->versions as $version)
                                        <article>
                                            <span>Version {{ $version->certificate_version }} · {{ Str::headline($version->status->value) }} · background {{ $version->background?->asset_version ?? 'n/a' }}</span>
                                            <small>{{ $version->generated_at?->format('M j, Y g:i A') }} · SHA-256 {{ Str::limit($version->sha256, 18) }}</small>
                                            <a href="{{ route('res.certificates.versions.preview', [$certificate, $version]) }}" target="_blank" rel="noopener">Preview</a>
                                        </article>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    </div>
                </article>
            @empty
                <section class="application-panel revision-certificate-empty"><h2>No certification records match these filters</h2><p>Adjust the queue filters or wait for Reviewer decisions to reach release processing.</p></section>
            @endforelse
            {{ $applications->links() }}
        </section>

        <section class="application-panel certificate-background-manager" aria-labelledby="certificate-background-title">
            <header class="application-panel-heading">
                <div><h2 id="certificate-background-title">Certificate Background / Watermark</h2><p>Changes apply only to newly generated versions. Issued files are never rewritten.</p></div>
                @if ($activeBackground)<x-dashboard.status-badge :label="'Active v'.$activeBackground->asset_version" tone="success" />@endif
            </header>
            <div class="certificate-background-current">
                <div>
                    <strong>{{ $activeBackground?->original_file_name ?? 'No active background' }}</strong>
                    <span>{{ $activeBackground?->source_kind === \App\Services\Certificates\CertificateBackgroundService::OFFICIAL_SOURCE_KIND ? 'Official default' : 'RES uploaded' }}</span>
                    @if ($activeBackground)<small>{{ $activeBackground->mime_type }} · SHA-256 {{ Str::limit($activeBackground->sha256, 24) }}</small>@endif
                </div>
                @if ($activeBackground)<a class="dashboard-outline-action" href="{{ route('res.certificate-backgrounds.preview', $activeBackground) }}" target="_blank" rel="noopener">Secure Preview</a>@endif
            </div>
            <div class="certificate-background-actions">
                <form method="POST" action="{{ route('res.certificate-backgrounds.store') }}" enctype="multipart/form-data" data-disable-on-submit>
                    @csrf
                    <label><span>Upload portrait A4-compatible background</span><input type="file" name="background" accept=".pdf,.jpg,.jpeg,.png" required></label>
                    <button class="dashboard-primary-action" type="submit">Validate and Activate</button>
                </form>
                <form method="POST" action="{{ route('res.certificate-backgrounds.reset') }}" data-disable-on-submit>
                    @csrf
                    <button class="dashboard-outline-action" type="submit">Reset to Official Default</button>
                </form>
            </div>
            <div class="certificate-background-history">
                @foreach ($backgrounds as $background)
                    <article>
                        <div><strong>Version {{ $background->asset_version }} · {{ $background->is_active ? 'Active' : 'Available' }}</strong><span>{{ $background->original_file_name }}</span><small>{{ $background->mime_type }} · {{ $background->activated_at?->format('M j, Y g:i A') ?? 'Never activated' }}</small></div>
                        <div>
                            <a href="{{ route('res.certificate-backgrounds.preview', $background) }}" target="_blank" rel="noopener">Preview</a>
                            @unless ($background->is_active)
                                <form method="POST" action="{{ route('res.certificate-backgrounds.activate', $background) }}" data-disable-on-submit>@csrf @method('PATCH')<button type="submit">Activate</button></form>
                            @endunless
                        </div>
                    </article>
                @endforeach
            </div>
            {{ $backgrounds->links() }}
        </section>
    </div>
@endsection
