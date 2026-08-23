@extends('layouts.dashboard')

@section('content')
    @php
        $dialogApplicationId = (int) old('application_id');
        $hasFilters = collect(['q', 'status', 'decision', 'claim', 'academic_term_id'])
            ->contains(fn (string $filter): bool => filled($filters[$filter] ?? null));
    @endphp

    <div class="dashboard-page res-certification-page">
        <header class="dashboard-page-heading certificate-page-heading">
            <div>
                <h1>Decision &amp; Certificates</h1>
            </div>
            <div class="certificate-page-actions">
                <button class="dashboard-primary-action" type="button" data-certificate-bulk-open>
                    <x-dashboard.icon name="award" size="17" />
                    <span>Release All</span>
                </button>
            </div>
        </header>

        @if (session('status'))
            <div class="application-success-banner" role="status"><x-dashboard.icon name="check" size="19" /><span>{{ session('status') }}</span></div>
        @endif

        @if ($summary = session('bulk_certificate_summary'))
            <section class="application-panel certificate-bulk-summary" aria-labelledby="bulk-summary-title">
                <header class="application-panel-heading">
                    <div><h2 id="bulk-summary-title">Bulk Release Result</h2></div>
                </header>
                <dl>
                    <div><dt>Eligible</dt><dd>{{ $summary['eligible'] }}</dd></div>
                    <div><dt>Successfully released</dt><dd>{{ $summary['successfully_released'] }}</dd></div>
                    <div><dt>Already released</dt><dd>{{ $summary['already_released'] }}</dd></div>
                    <div><dt>Conflicted (skipped)</dt><dd>{{ $summary['conflicted'] ?? 0 }}</dd></div>
                    <div><dt>Ineligible</dt><dd>{{ $summary['ineligible'] }}</dd></div>
                    <div><dt>Failed</dt><dd>{{ $summary['failed'] }}</dd></div>
                </dl>
                @if ($summary['failed_application_codes'])
                    <p role="alert">Failed application codes: {{ implode(', ', $summary['failed_application_codes']) }}</p>
                @endif
            </section>
        @endif

        @foreach (['decisionRelease', 'certificateRelease', 'bulkRelease'] as $bag)
            @if ($errors->{$bag}->any())
                <div class="res-form-error-summary" role="alert"><x-dashboard.icon name="alert-triangle" size="19" /><div><strong>The request was not completed.</strong><span>{{ $errors->{$bag}->first() }}</span></div></div>
            @endif
        @endforeach

        <section class="application-panel certificate-metric-strip" aria-label="Certificate queue summary">
            <a href="#certificate-queue-title">
                <span class="certificate-metric-icon is-orange"><x-dashboard.icon name="clock" size="25" /></span>
                <div><strong>{{ $queueMetrics['pending_decision_release'] }}</strong><span>Pending Decision Release</span></div>
            </a>
            <a href="#certificate-queue-title">
                <span class="certificate-metric-icon is-blue"><x-dashboard.icon name="award" size="25" /></span>
                <div><strong>{{ $queueMetrics['pending_certificate_release'] }}</strong><span>Pending Certificate Release</span></div>
            </a>
            <a href="#certificate-queue-title">
                <span class="certificate-metric-icon is-green"><x-dashboard.icon name="check" size="25" /></span>
                <div><strong>{{ $queueMetrics['certificates_released'] }}</strong><span>Certificates Released</span></div>
            </a>
        </section>

        <form class="application-panel certificate-queue-filters" method="GET" action="{{ route('res.certificates.index') }}">
            <div class="application-field">
                <label for="certificate-academic-term">Academic Term</label>
                <select id="certificate-academic-term" name="academic_term_id">
                    <option value="">All</option>
                    @foreach ($termOptions as $term)
                        <option value="{{ $term->id }}" @selected((string) ($filters['academic_term_id'] ?? '') === (string) $term->id)>{{ $term->filterLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="application-field application-search-field certificate-filter-search">
                <label for="certificate-q">Search</label>
                <span><x-dashboard.icon name="search" size="18" /></span>
                <input id="certificate-q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Application code or research title">
            </div>
            <div class="application-field">
                <label for="certificate-status">Status</label>
                <select id="certificate-status" name="status">
                    <option value="">All statuses</option>
                    @foreach (\App\Enums\ApplicationStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="application-field">
                <label for="certificate-decision">Decision</label>
                <select id="certificate-decision" name="decision">
                    <option value="">All decisions</option>
                    @foreach (\App\Enums\ReviewDecision::cases() as $decision)
                        <option value="{{ $decision->value }}" @selected(($filters['decision'] ?? '') === $decision->value)>{{ $decision->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="application-field">
                <label for="certificate-claim">Claim</label>
                <select id="certificate-claim" name="claim">
                    <option value="">All claim states</option>
                    <option value="claimed" @selected(($filters['claim'] ?? '') === 'claimed')>Claimed</option>
                    <option value="unclaimed" @selected(($filters['claim'] ?? '') === 'unclaimed')>Not claimed</option>
                    <option value="unavailable" @selected(($filters['claim'] ?? '') === 'unavailable')>Not available</option>
                </select>
            </div>
            <div class="certificate-filter-actions">
                <button class="dashboard-primary-action" type="submit">Apply Filters</button>
                <a class="dashboard-outline-action" href="{{ route('res.certificates.index') }}">Reset</a>
            </div>
        </form>

        <section class="application-panel certificate-queue-panel" aria-labelledby="certificate-queue-title">
            <header class="application-panel-heading certificate-queue-heading">
                <div>
                    <h2 id="certificate-queue-title">Decision &amp; Certificate Queue</h2>
                    <p>
                        Showing {{ $applications->firstItem() ?? 0 }} to {{ $applications->lastItem() ?? 0 }} of {{ $applications->total() }} relevant {{ Str::plural('application', $applications->total()) }}
                        @if ($hasFilters)<span>(filtered)</span>@endif
                    </p>
                </div>
            </header>

            @if ($applications->isEmpty())
                <x-dashboard.empty-state
                    image="no-applications"
                    alt="No certification records found"
                    title="No certification records match these filters"
                    :message="$hasFilters
                        ? 'Adjust the filters to see other certification records.'
                        : 'Applications appear here when Reviewer decisions reach RES release processing or become certificate eligible.'"
                />
            @else
                <x-dashboard.overflow class="certificate-queue-scroll" label="Certificate processing queue records" wide>
                    <table class="dashboard-table certificate-queue-table">
                        <thead>
                            <tr>
                                <th class="certificate-row-number">#</th>
                                <th>Application</th>
                                <th class="certificate-queue-centered">Status</th>
                                <th class="certificate-queue-centered">Decision</th>
                                <th class="certificate-queue-centered">Claim</th>
                                <th class="certificate-queue-centered">Last Updated</th>
                                <th class="dashboard-table-action certificate-queue-centered">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($applications as $application)
                                @php
                                    $rowNumber = ($applications->firstItem() ?? 1) + $loop->index;
                                    $state = $certificationStates[$application->id];
                                    $recipientCount = $application->certificateRecipients->count();
                                    $readyCertificates = $application->certificates->filter(
                                        fn ($item) => in_array($item->status, [\App\Enums\CertificateStatus::Released, \App\Enums\CertificateStatus::Claimed], true)
                                            && $item->currentVersion?->status === \App\Enums\CertificateVersionStatus::Ready,
                                    );
                                    $readyRecipientCount = $readyCertificates->pluck('application_certificate_recipient_id')->filter()->unique()->count();
                                    $isReleased = $recipientCount > 0 && $readyRecipientCount === $recipientCount;
                                    $latestRelease = $application->decisionReleases->first();
                                    $isConflicted = $application->review_consensus_status === \App\Enums\ReviewConsensusStatus::Conflicted;
                                    $queueLabel = match (true) {
                                        $isConflicted => 'Conflicted Decisions',
                                        $application->application_status === \App\Enums\ApplicationStatus::ReviewSubmittedPendingRelease => 'Pending Decision Release',
                                        $isReleased => 'Certificate Released',
                                        $state === \App\Enums\CertificationState::GenerationFailed => 'Certificate Generation Failed',
                                        default => 'Pending Certificate Release',
                                    };
                                    $queueTone = match (true) {
                                        $isConflicted, $state === \App\Enums\CertificationState::GenerationFailed => 'red',
                                        $isReleased => 'success',
                                        $application->application_status === \App\Enums\ApplicationStatus::ReviewSubmittedPendingRelease => 'orange',
                                        default => 'blue',
                                    };
                                    $decision = $latestRelease?->decision ?? $application->review_consensus_decision;
                                    $decisionLabel = $isConflicted ? 'Conflicted' : ($decision?->label() ?? 'Pending');
                                    $decisionTone = $isConflicted ? 'red' : ($decision?->tone() ?? 'orange');
                                    $claimLabel = match (true) {
                                        $isReleased && $readyCertificates->every(fn ($item) => $item->status === \App\Enums\CertificateStatus::Claimed) => 'Claimed',
                                        $isReleased => 'Not claimed',
                                        default => 'Not available',
                                    };
                                    $claimTone = $claimLabel === 'Claimed' ? 'success' : 'neutral';
                                @endphp
                                <tr class="{{ $isConflicted ? 'is-review-conflicted' : ($isReleased ? 'is-certificate-ready' : '') }}">
                                    <td class="certificate-row-number" data-certificate-row-number="{{ $rowNumber }}">{{ $rowNumber }}</td>
                                    <td class="certificate-application-cell">
                                        <small>{{ $application->application_code }}</small>
                                        <strong class="monitoring-title-truncate" data-research-title-tooltip>{{ $application->research_title }}</strong>
                                    </td>
                                    <td class="certificate-queue-centered"><x-dashboard.status-badge :label="$queueLabel" :tone="$queueTone" /></td>
                                    <td class="certificate-queue-centered"><x-dashboard.status-badge :label="$decisionLabel" :tone="$decisionTone" /></td>
                                    <td class="certificate-queue-centered"><x-dashboard.status-badge :label="$claimLabel" :tone="$claimTone" /></td>
                                    <td class="certificate-updated-cell certificate-queue-centered"><span>{{ $application->status_updated_at?->format('M j, Y') }}</span><small>{{ $application->status_updated_at?->format('g:i A') }}</small></td>
                                    <td class="dashboard-table-action certificate-queue-centered">
                                        <button class="dashboard-outline-action certificate-row-action" type="button" data-certificate-application-open="{{ $application->id }}">
                                            View
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-dashboard.overflow>
                <x-dashboard.pagination :paginator="$applications" label="Certificate queue pages" />
            @endif
        </section>

        @foreach ($applications as $application)
            @php
                $state = $certificationStates[$application->id];
                $certificates = $application->certificates->sortBy('id')->values();
                $recipientCount = $application->certificateRecipients->count();
                $previewableCertificates = $certificates->filter(
                    fn ($item) => in_array($item->status, [\App\Enums\CertificateStatus::PendingRelease, \App\Enums\CertificateStatus::Released, \App\Enums\CertificateStatus::Claimed], true)
                        && $item->currentVersion?->status === \App\Enums\CertificateVersionStatus::Ready,
                );
                $readyRecipientCount = $previewableCertificates->pluck('application_certificate_recipient_id')->filter()->unique()->count();
                $allRecipientCertificatesReady = $recipientCount > 0 && $readyRecipientCount === $recipientCount;
                $cycle = max(0, ((int) $application->current_revision_cycle) - 1);
                $cycleAssignments = $application->reviewerAssignments->where('review_cycle', $cycle);
                $latestRelease = $application->decisionReleases->first();
                $isConflicted = $application->review_consensus_status === \App\Enums\ReviewConsensusStatus::Conflicted;
                $decision = $latestRelease?->decision ?? $application->review_consensus_decision;
                $reopenApplicationDialog = $dialogApplicationId === $application->id
                    && ($errors->decisionRelease->any() || $errors->certificateRelease->any());
            @endphp
            <section
                class="application-modal-backdrop"
                data-certificate-application-dialog="{{ $application->id }}"
                @if ($reopenApplicationDialog) data-open-on-load @else hidden @endif
            >
                <div class="application-modal certificate-application-modal" role="dialog" aria-modal="true" aria-labelledby="certificate-application-{{ $application->id }}-title" tabindex="-1">
                    <button class="application-modal-close" type="button" aria-label="Close selected application" data-certificate-application-close><x-dashboard.icon name="x" size="20" /></button>
                    <header class="certificate-modal-heading">
                        <div>
                            <span>Selected Application</span>
                            <small>{{ $application->application_code }}</small>
                            <h2 id="certificate-application-{{ $application->id }}-title">{{ $application->research_title }}</h2>
                        </div>
                        <x-dashboard.status-badge :label="$state->label()" :tone="$state->tone()" />
                    </header>

                    <section class="certificate-modal-section" aria-labelledby="certificate-summary-{{ $application->id }}">
                        <h3 id="certificate-summary-{{ $application->id }}">Status Summary</h3>
                        <dl class="certificate-modal-status-grid">
                            <div><dt>Review State</dt><dd><x-dashboard.status-badge :label="$application->application_status->label()" :tone="$application->application_status->tone()" /></dd></div>
                            <div><dt>Consensus</dt><dd><x-dashboard.status-badge :label="$application->review_consensus_status?->label() ?? 'Not evaluated'" :tone="$application->review_consensus_status?->tone() ?? 'neutral'" /></dd></div>
                            <div><dt>Decision</dt><dd><x-dashboard.status-badge :label="$decision?->label() ?? 'Pending'" :tone="$decision?->tone() ?? 'orange'" /></dd></div>
                            <div><dt>Certificate</dt><dd><x-dashboard.status-badge :label="$allRecipientCertificatesReady ? $readyRecipientCount.' personalized '.Str::plural('certificate', $readyRecipientCount).' ready' : $readyRecipientCount.' of '.$recipientCount.' ready'" :tone="$allRecipientCertificatesReady ? 'success' : 'neutral'" /></dd></div>
                        </dl>
                    </section>

                    @if ($application->application_status === \App\Enums\ApplicationStatus::ReviewSubmittedPendingRelease)
                        <section class="certificate-modal-section certificate-decision-workspace" aria-labelledby="certificate-decision-{{ $application->id }}">
                            <div class="certificate-modal-section-heading">
                                <div><h3 id="certificate-decision-{{ $application->id }}">Submitted Reviewer Decisions</h3></div>
                            </div>
                            <div class="certificate-modal-actions">
                                <a class="dashboard-outline-action" href="{{ route('res.certificates.workspace', $application) }}"><x-dashboard.icon name="file-search" size="17" /><span>Open Workspace</span></a>
                                @if (! $isConflicted && $application->review_consensus_status === \App\Enums\ReviewConsensusStatus::Consensus && $decision !== \App\Enums\ReviewDecision::Approved)
                                    <form method="POST" action="{{ route('res.certificates.decisions.release', $application) }}" data-disable-on-submit>
                                        @csrf
                                        <input type="hidden" name="application_id" value="{{ $application->id }}">
                                        <button class="dashboard-primary-action" type="submit"><x-dashboard.icon name="send" size="17" /><span>Release Decision</span></button>
                                    </form>
                                @endif
                            </div>
                            @if ($isConflicted)
                                <div class="res-form-error-summary" role="alert"><x-dashboard.icon name="alert-triangle" size="19" /><div><strong>Decision release blocked.</strong><span>The three current Full Board submissions do not agree. A Reviewer must re-submit before RES can release a result.</span></div></div>
                            @endif
                        </section>
                    @endif

                    <section class="certificate-modal-section" aria-labelledby="certificate-actions-{{ $application->id }}">
                        <div class="certificate-modal-section-heading">
                            <div><h3 id="certificate-actions-{{ $application->id }}">Certificate Actions</h3></div>
                        </div>
                        <div class="certificate-modal-actions">
                            @if (in_array($state, [\App\Enums\CertificationState::Eligible, \App\Enums\CertificationState::PendingResRelease, \App\Enums\CertificationState::GenerationFailed], true))
                                <form method="POST" action="{{ route('res.certificates.release', $application) }}" data-disable-on-submit>
                                    @csrf
                                    <input type="hidden" name="application_id" value="{{ $application->id }}">
                                    <button class="dashboard-primary-action" type="submit">
                                        <x-dashboard.icon name="award" size="17" />
                                        <span>{{ match ($state) {
                                            \App\Enums\CertificationState::GenerationFailed => 'Retry Secure Generation',
                                            \App\Enums\CertificationState::PendingResRelease => 'Release Generated Certificate',
                                            default => 'Generate and Release Certificate',
                                        } }}</span>
                                    </button>
                                </form>
                            @endif
                            @if ($previewableCertificates->isNotEmpty())
                                <a class="dashboard-outline-action" href="{{ route('res.certificates.applications.preview-all', $application) }}" target="_blank" rel="noopener"><x-dashboard.icon name="eye" size="17" /><span>Preview All Certificate</span></a>
                                <a class="dashboard-outline-action" href="{{ route('res.certificates.applications.download-all', $application) }}"><x-dashboard.icon name="download" size="17" /><span>Download All Certificate</span></a>
                            @endif
                            @if ($previewableCertificates->isNotEmpty())
                                <details class="certificate-regenerate-confirmation">
                                    <summary class="dashboard-outline-action"><x-dashboard.icon name="refresh" size="17" /><span>Regenerate</span></summary>
                                    <div>
                                        <strong>Create a new certificate version?</strong>
                                        <p>Existing issued files and their provenance remain unchanged.</p>
                                        <form method="POST" action="{{ route('res.certificates.regenerate', $application) }}" data-disable-on-submit>
                                            @csrf
                                            <input type="hidden" name="application_id" value="{{ $application->id }}">
                                            <input type="hidden" name="confirmation" value="regenerate">
                                            <button class="dashboard-primary-action" type="submit">Confirm New Version</button>
                                        </form>
                                    </div>
                                </details>
                            @elseif (! in_array($state, [\App\Enums\CertificationState::Eligible, \App\Enums\CertificationState::PendingResRelease, \App\Enums\CertificationState::GenerationFailed], true))
                                <p class="certificate-modal-empty-action">Certificate actions become available after an authorized final approval or exemption.</p>
                            @endif
                        </div>
                    </section>

                    @if ($certificates->contains(fn ($item) => $item->versions->isNotEmpty()))
                        <section class="certificate-modal-section" aria-labelledby="certificate-versions-{{ $application->id }}">
                            <div class="certificate-modal-section-heading"><div><h3 id="certificate-versions-{{ $application->id }}">Version History</h3></div></div>
                            <x-dashboard.overflow class="certificate-version-scroll" label="Certificate version history" wide>
                                <table class="dashboard-table certificate-version-table">
                                    <thead><tr><th>Recipient</th><th>Version</th><th>Status</th><th>Issued</th><th>Valid Until</th><th>Background</th><th>File Hash</th><th>Action</th></tr></thead>
                                    <tbody>
                                        @foreach ($certificates as $recipientCertificate)
                                            @foreach ($recipientCertificate->versions as $version)
                                                <tr>
                                                    <td><strong>{{ $recipientCertificate->recipient_name ?? $recipientCertificate->recipient?->recipient_name ?? $application->applicant?->name }}</strong></td>
                                                    <td>Version {{ $version->certificate_version }}</td>
                                                    <td><x-dashboard.status-badge :label="Str::headline($version->status->value)" :tone="$version->id === $recipientCertificate->current_certificate_version_id ? 'success' : 'neutral'" /></td>
                                                    <td>{{ $version->issued_date?->format('M j, Y') ?? $version->generated_at?->format('M j, Y') }}</td>
                                                    <td>{{ $version->valid_until?->format('M j, Y') ?? 'Not recorded' }}</td>
                                                    <td>Version {{ $version->background?->asset_version ?? 'n/a' }}</td>
                                                    <td><code title="{{ $version->sha256 }}">{{ Str::limit($version->sha256, 18) }}</code></td>
                                                    <td>
                                                        <a href="{{ route('res.certificates.versions.preview', [$recipientCertificate, $version]) }}" target="_blank" rel="noopener">Preview</a>
                                                        <span aria-hidden="true"> · </span>
                                                        <a href="{{ route('res.certificates.versions.download', [$recipientCertificate, $version]) }}">Download</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </x-dashboard.overflow>
                        </section>
                    @endif

                    <div class="application-modal-actions certificate-modal-footer"><button class="dashboard-outline-action" type="button" data-certificate-application-close>Close</button></div>
                </div>
            </section>
        @endforeach

        <section class="application-modal-backdrop" data-certificate-bulk-dialog hidden>
            <div class="application-modal certificate-bulk-modal" role="dialog" aria-modal="true" aria-labelledby="certificate-bulk-title" tabindex="-1">
                <button class="application-modal-close" type="button" aria-label="Cancel bulk certificate release" data-certificate-bulk-close><x-dashboard.icon name="x" size="20" /></button>
                <header class="application-modal-heading">
                    <span class="application-modal-icon"><x-dashboard.icon name="award" size="23" /></span>
                    <div><h2 id="certificate-bulk-title">Release All</h2><p>Choose one release type. Only records that pass the corresponding backend checks will be processed.</p></div>
                </header>
                <form method="POST" action="{{ route('res.certificates.release-eligible') }}" data-disable-on-submit>
                    @csrf
                    <input type="hidden" name="confirmation" value="release_all_eligible">
                    <fieldset class="certificate-bulk-options">
                        <legend>Release type</legend>
                        <label>
                            <input type="radio" name="release_type" value="certificate" required>
                            <span><strong>Certificate</strong><small>{{ $bulkEligibleCounts['certificate'] }} eligible {{ Str::plural('record', $bulkEligibleCounts['certificate']) }}</small></span>
                        </label>
                        <label>
                            <input type="radio" name="release_type" value="decision" required>
                            <span><strong>Decision</strong><small>{{ $bulkEligibleCounts['decision'] }} eligible {{ Str::plural('record', $bulkEligibleCounts['decision']) }}</small></span>
                        </label>
                        <label>
                            <input type="radio" name="release_type" value="both" required>
                            <span><strong>Both Certificate and Decision</strong><small>{{ $bulkEligibleCounts['both'] }} eligible {{ Str::plural('record', $bulkEligibleCounts['both']) }}</small></span>
                        </label>
                    </fieldset>
                    <div class="application-modal-actions">
                        <button class="dashboard-outline-action" type="button" data-certificate-bulk-close>Cancel</button>
                        <button class="dashboard-primary-action" type="submit">Confirm Bulk Release</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection
