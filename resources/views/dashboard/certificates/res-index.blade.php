@extends('layouts.dashboard')

@section('content')
    @php
        $dialogApplicationId = (int) old('application_id');
        $backgroundDialogOpen = $errors->certificateBackground->any() || request()->has('background_page');
        $hasFilters = filled($filters['q'] ?? null) || filled($filters['state'] ?? null);
    @endphp

    <div class="dashboard-page res-certification-page">
        <header class="dashboard-page-heading certificate-page-heading">
            <div>
                <h1>Certificate Processing</h1>
                <p>Release Applicant-visible decisions, generate official certificates, and manage future certificate backgrounds.</p>
            </div>
            <div class="certificate-page-actions">
                <button class="dashboard-outline-action" type="button" data-certificate-background-open>
                    <x-dashboard.icon name="image" size="17" />
                    <span>Manage Certificate Background</span>
                </button>
                <button class="dashboard-primary-action" type="button" data-certificate-bulk-open>
                    <x-dashboard.icon name="award" size="17" />
                    <span>Release All Eligible</span>
                </button>
            </div>
        </header>

        @if (session('status'))
            <div class="application-success-banner" role="status"><x-dashboard.icon name="check" size="19" /><span>{{ session('status') }}</span></div>
        @endif

        @if ($summary = session('bulk_certificate_summary'))
            <section class="application-panel certificate-bulk-summary" aria-labelledby="bulk-summary-title">
                <header class="application-panel-heading">
                    <div><h2 id="bulk-summary-title">Bulk Release Result</h2><p>Each eligible application was processed independently.</p></div>
                </header>
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

        <section class="application-panel certificate-metric-strip" aria-label="Certificate queue summary">
            <article>
                <span class="certificate-metric-icon is-green"><x-dashboard.icon name="file-text" size="25" /></span>
                <div><strong>{{ $queueMetrics['relevant'] }}</strong><span>Relevant Applications</span></div>
            </article>
            <article>
                <span class="certificate-metric-icon is-green"><x-dashboard.icon name="check" size="25" /></span>
                <div><strong>{{ $queueMetrics['released'] }}</strong><span>Certificates Released</span></div>
            </article>
            <article>
                <span class="certificate-metric-icon is-orange"><x-dashboard.icon name="clock" size="25" /></span>
                <div><strong>{{ $queueMetrics['pending_final_approval'] }}</strong><span>Pending Final Approval</span></div>
            </article>
            <article>
                <span class="certificate-metric-icon is-blue"><x-dashboard.icon name="clipboard" size="25" /></span>
                <div><strong>{{ $queueMetrics['survey_required'] }}</strong><span>Survey Required</span></div>
            </article>
        </section>

        <form class="application-panel certificate-queue-filters" method="GET" action="{{ route('res.certificates.index') }}">
            <div class="application-field application-search-field certificate-filter-search">
                <label for="certificate-q">Search</label>
                <span><x-dashboard.icon name="search" size="18" /></span>
                <input id="certificate-q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Application code, title, or Applicant">
            </div>
            <div class="application-field">
                <label for="certificate-state">Queue state</label>
                <select id="certificate-state" name="state">
                    <option value="">All relevant records</option>
                    <option value="decision" @selected(($filters['state'] ?? '') === 'decision')>Decision release</option>
                    <option value="eligible" @selected(($filters['state'] ?? '') === 'eligible')>Eligible</option>
                    <option value="released" @selected(($filters['state'] ?? '') === 'released')>Released</option>
                    <option value="failed" @selected(($filters['state'] ?? '') === 'failed')>Generation failed</option>
                    <option value="claimed" @selected(($filters['state'] ?? '') === 'claimed')>Claimed</option>
                </select>
            </div>
            <button class="dashboard-primary-action" type="submit">Apply Filters</button>
            <a class="dashboard-outline-action" href="{{ route('res.certificates.index') }}">Reset</a>
        </form>

        <section class="application-panel certificate-queue-panel" aria-labelledby="certificate-queue-title">
            <header class="application-panel-heading certificate-queue-heading">
                <div>
                    <h2 id="certificate-queue-title">Certification Queue</h2>
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
                        ? 'Adjust the search or queue state to see other certification records.'
                        : 'Applications appear here when Reviewer decisions reach RES release processing or become certificate eligible.'"
                />
            @else
                <x-dashboard.overflow class="certificate-queue-scroll" label="Certificate processing queue records" wide>
                    <table class="dashboard-table certificate-queue-table">
                        <thead>
                            <tr>
                                <th class="certificate-row-number">#</th>
                                <th>Application</th>
                                <th>Applicant</th>
                                <th>Final Review</th>
                                <th>Decision</th>
                                <th>Certificate</th>
                                <th>Survey</th>
                                <th>Claim</th>
                                <th>Last Updated</th>
                                <th class="dashboard-table-action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($applications as $application)
                                @php
                                    $rowNumber = ($applications->firstItem() ?? 1) + $loop->index;
                                    $state = $certificationStates[$application->id];
                                    $certificate = $application->certificate;
                                    $currentVersion = $certificate?->currentVersion;
                                    $latestRelease = $application->decisionReleases->first();
                                    $isReleased = $currentVersion !== null;
                                    $surveyComplete = $application->surveyResponse !== null;
                                    $isClaimed = $certificate?->claimed_at !== null;
                                    $certificateLabel = $currentVersion
                                        ? 'Version '.$currentVersion->certificate_version.' ready'
                                        : ($state === \App\Enums\CertificationState::GenerationFailed ? 'Generation failed' : 'Not generated');
                                    $certificateTone = $currentVersion ? 'success' : ($state === \App\Enums\CertificationState::GenerationFailed ? 'red' : 'neutral');
                                    $surveyLabel = $surveyComplete ? 'Completed' : ($isReleased ? 'Required' : 'Not available');
                                    $surveyTone = $surveyComplete ? 'success' : ($isReleased ? 'blue' : 'neutral');
                                    $claimLabel = $isClaimed ? 'Claimed' : ($surveyComplete && $isReleased ? 'Ready to claim' : 'Not claimed');
                                    $claimTone = $isClaimed ? 'success' : ($surveyComplete && $isReleased ? 'blue' : 'neutral');
                                @endphp
                                <tr class="{{ $isReleased ? 'is-certificate-ready' : '' }}">
                                    <td class="certificate-row-number" data-certificate-row-number="{{ $rowNumber }}">{{ $rowNumber }}</td>
                                    <td class="certificate-application-cell">
                                        <small>{{ $application->application_code }}</small>
                                        <strong>{{ $application->research_title }}</strong>
                                    </td>
                                    <td>{{ $application->applicant?->name ?? 'Applicant record unavailable' }}</td>
                                    <td><x-dashboard.status-badge :label="$application->application_status->label()" :tone="$application->application_status->tone()" /></td>
                                    <td><x-dashboard.status-badge :label="$latestRelease?->decision?->label() ?? 'Pending'" :tone="$latestRelease?->decision?->tone() ?? 'orange'" /></td>
                                    <td><x-dashboard.status-badge :label="$certificateLabel" :tone="$certificateTone" /></td>
                                    <td><x-dashboard.status-badge :label="$surveyLabel" :tone="$surveyTone" /></td>
                                    <td><x-dashboard.status-badge :label="$claimLabel" :tone="$claimTone" /></td>
                                    <td class="certificate-updated-cell"><span>{{ $application->status_updated_at?->format('M j, Y') }}</span><small>{{ $application->status_updated_at?->format('g:i A') }}</small></td>
                                    <td class="dashboard-table-action">
                                        <button class="dashboard-outline-action certificate-row-action" type="button" data-certificate-application-open="{{ $application->id }}">
                                            {{ $isReleased ? 'View Details' : 'Review & Release' }}
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

        <section class="application-panel certificate-background-callout">
            <div><x-dashboard.icon name="circle-help" size="19" /><span>Certificate backgrounds are managed separately and affect only future generations.</span></div>
            <button class="dashboard-outline-action" type="button" data-certificate-background-open><x-dashboard.icon name="image" size="17" /><span>Manage Background</span></button>
        </section>

        @foreach ($applications as $application)
            @php
                $state = $certificationStates[$application->id];
                $certificate = $application->certificate;
                $currentVersion = $certificate?->currentVersion;
                $cycle = max(0, ((int) $application->current_revision_cycle) - 1);
                $cycleAssignments = $application->reviewerAssignments->where('review_cycle', $cycle);
                $latestRelease = $application->decisionReleases->first();
                $surveyComplete = $application->surveyResponse !== null;
                $isClaimed = $certificate?->claimed_at !== null;
                $reopenApplicationDialog = $dialogApplicationId === $application->id
                    && ($errors->decisionRelease->any() || $errors->certificateRelease->any());
                $oldCommentIds = $dialogApplicationId === $application->id ? old('comment_ids', []) : [];
                $oldRevisionDocumentIds = $dialogApplicationId === $application->id ? old('revision_document_ids', []) : [];
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
                            <p>{{ $application->applicant?->name ?? 'Applicant record unavailable' }}</p>
                        </div>
                        <x-dashboard.status-badge :label="$state->label()" :tone="$state->tone()" />
                    </header>

                    <section class="certificate-modal-section" aria-labelledby="certificate-summary-{{ $application->id }}">
                        <h3 id="certificate-summary-{{ $application->id }}">Status Summary</h3>
                        <dl class="certificate-modal-status-grid">
                            <div><dt>Final Review</dt><dd><x-dashboard.status-badge :label="$application->application_status->label()" :tone="$application->application_status->tone()" /></dd></div>
                            <div><dt>Decision</dt><dd><x-dashboard.status-badge :label="$latestRelease?->decision?->label() ?? 'Pending'" :tone="$latestRelease?->decision?->tone() ?? 'orange'" /></dd></div>
                            <div><dt>Generation</dt><dd><x-dashboard.status-badge :label="$currentVersion ? 'Version '.$currentVersion->certificate_version.' ready' : 'Not generated'" :tone="$currentVersion ? 'success' : 'neutral'" /></dd></div>
                            <div><dt>Survey</dt><dd><x-dashboard.status-badge :label="$surveyComplete ? 'Completed' : 'Not completed'" :tone="$surveyComplete ? 'success' : 'neutral'" /></dd></div>
                            <div><dt>Claim</dt><dd><x-dashboard.status-badge :label="$isClaimed ? 'Claimed' : 'Not claimed'" :tone="$isClaimed ? 'success' : 'neutral'" /></dd></div>
                        </dl>
                    </section>

                    @if ($application->application_status === \App\Enums\ApplicationStatus::ReviewSubmittedPendingRelease)
                        <section class="certificate-modal-section certificate-decision-workspace" aria-labelledby="certificate-decision-{{ $application->id }}">
                            <div class="certificate-modal-section-heading">
                                <div><h3 id="certificate-decision-{{ $application->id }}">Review and Release Decision</h3><p>Select only the comments that Applicants are authorized to see.</p></div>
                            </div>
                            <form method="POST" action="{{ route('res.certificates.decisions.release', $application) }}" data-disable-on-submit>
                                @csrf
                                <input type="hidden" name="application_id" value="{{ $application->id }}">
                                <div class="certificate-reviewer-decisions">
                                    @foreach ($cycleAssignments as $assignment)
                                        <section>
                                            <header><strong>Reviewer {{ $loop->iteration }}</strong><x-dashboard.status-badge :label="$assignment->reviewSubmission?->decision?->label() ?? 'Pending'" :tone="$assignment->reviewSubmission?->decision?->tone() ?? 'neutral'" /></header>
                                            @forelse ($assignment->comments as $comment)
                                                <label>
                                                    <input type="checkbox" name="comment_ids[]" value="{{ $comment->id }}" @checked(in_array($comment->id, $oldCommentIds))>
                                                    <span>
                                                        <strong>{{ $comment->category->label() }} &middot; {{ $comment->scope->label() }}</strong>
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
                                <fieldset class="certificate-revision-document-picker">
                                    <legend>Documents requiring revision</legend>
                                    <p>For a minor or major revision, confirm the exact current files the Applicant must replace. Document-linked Required Revision comments are already matched; use this list to recover older submitted comments recorded as General or Overall.</p>
                                    @forelse ($application->documents as $document)
                                        <label>
                                            <input type="checkbox" name="revision_document_ids[]" value="{{ $document->id }}" @checked(in_array($document->id, $oldRevisionDocumentIds))>
                                            <span>
                                                <strong>{{ $document->requirement?->name ?? 'Supporting Document' }}</strong>
                                                <small>{{ $document->original_file_name }}</small>
                                            </span>
                                        </label>
                                    @empty
                                        <p>No current application documents are available for revision mapping.</p>
                                    @endforelse
                                </fieldset>
                                <div class="certificate-decision-controls">
                                    <label>
                                        <span>Official released decision</span>
                                        <select name="decision" required>
                                            <option value="">Select decision</option>
                                            @foreach ($decisions as $decision)
                                                <option value="{{ $decision->value }}" @selected($dialogApplicationId === $application->id && old('decision') === $decision->value)>{{ $decision->label() }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <button class="dashboard-primary-action" type="submit">Release Decision and Selected Comments</button>
                                </div>
                                <p class="certificate-release-note">A revision decision requires at least one selected Reviewer comment and one exact source document, supplied by a document-linked Required Revision comment or the recovery mapping above.</p>
                            </form>
                        </section>
                    @endif

                    <section class="certificate-modal-section" aria-labelledby="certificate-actions-{{ $application->id }}">
                        <div class="certificate-modal-section-heading">
                            <div><h3 id="certificate-actions-{{ $application->id }}">Certificate Actions</h3><p>Every action is revalidated against the current server state.</p></div>
                        </div>
                        <div class="certificate-modal-actions">
                            @if (in_array($state, [\App\Enums\CertificationState::Eligible, \App\Enums\CertificationState::GenerationFailed], true))
                                <form method="POST" action="{{ route('res.certificates.release', $application) }}" data-disable-on-submit>
                                    @csrf
                                    <input type="hidden" name="application_id" value="{{ $application->id }}">
                                    <button class="dashboard-primary-action" type="submit">
                                        <x-dashboard.icon name="award" size="17" />
                                        <span>{{ $state === \App\Enums\CertificationState::GenerationFailed ? 'Retry Secure Generation' : 'Generate and Release Certificate' }}</span>
                                    </button>
                                </form>
                            @endif
                            @if ($currentVersion)
                                <a class="dashboard-outline-action" href="{{ route('res.certificates.versions.preview', [$certificate, $currentVersion]) }}" target="_blank" rel="noopener"><x-dashboard.icon name="eye" size="17" /><span>Preview Current PDF</span></a>
                                <a class="dashboard-outline-action" href="{{ route('res.certificates.versions.download', [$certificate, $currentVersion]) }}"><x-dashboard.icon name="download" size="17" /><span>Download</span></a>
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
                            @elseif (! in_array($state, [\App\Enums\CertificationState::Eligible, \App\Enums\CertificationState::GenerationFailed], true))
                                <p class="certificate-modal-empty-action">Certificate actions become available after an authorized final approval or exemption.</p>
                            @endif
                        </div>
                    </section>

                    @if ($certificate?->versions->isNotEmpty())
                        <section class="certificate-modal-section" aria-labelledby="certificate-versions-{{ $application->id }}">
                            <div class="certificate-modal-section-heading"><div><h3 id="certificate-versions-{{ $application->id }}">Version History</h3><p>Issued files are immutable and retain their exact background provenance.</p></div></div>
                            <x-dashboard.overflow class="certificate-version-scroll" label="Certificate version history" wide>
                                <table class="dashboard-table certificate-version-table">
                                    <thead><tr><th>Version</th><th>Status</th><th>Generated</th><th>Background</th><th>File Hash</th><th>Action</th></tr></thead>
                                    <tbody>
                                        @foreach ($certificate->versions as $version)
                                            <tr>
                                                <td><strong>Version {{ $version->certificate_version }}</strong></td>
                                                <td><x-dashboard.status-badge :label="Str::headline($version->status->value)" :tone="$version->id === $certificate->current_certificate_version_id ? 'success' : 'neutral'" /></td>
                                                <td>{{ $version->generated_at?->format('M j, Y g:i A') }}</td>
                                                <td>Version {{ $version->background?->asset_version ?? 'n/a' }}</td>
                                                <td><code title="{{ $version->sha256 }}">{{ Str::limit($version->sha256, 18) }}</code></td>
                                                <td><a href="{{ route('res.certificates.versions.preview', [$certificate, $version]) }}" target="_blank" rel="noopener">Preview</a></td>
                                            </tr>
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

        <section class="application-modal-backdrop" data-certificate-background-dialog @if ($backgroundDialogOpen) data-open-on-load @else hidden @endif>
            <div class="application-modal certificate-background-modal" role="dialog" aria-modal="true" aria-labelledby="certificate-background-title" tabindex="-1">
                <button class="application-modal-close" type="button" aria-label="Close certificate background manager" data-certificate-background-close><x-dashboard.icon name="x" size="20" /></button>
                <header class="application-modal-heading">
                    <span class="application-modal-icon"><x-dashboard.icon name="image" size="23" /></span>
                    <div><h2 id="certificate-background-title">Manage Certificate Background</h2><p>Changes apply only to future generations; issued certificate versions are never rewritten.</p></div>
                </header>

                <section class="certificate-background-current">
                    <div>
                        <span>Current background</span>
                        <strong>{{ $activeBackground?->original_file_name ?? 'No active background' }}</strong>
                        <small>{{ $activeBackground?->source_kind === \App\Services\Certificates\CertificateBackgroundService::OFFICIAL_SOURCE_KIND ? 'Official default' : 'RES uploaded' }}@if ($activeBackground) &middot; SHA-256 {{ Str::limit($activeBackground->sha256, 24) }}@endif</small>
                    </div>
                    @if ($activeBackground)
                        <div class="certificate-background-current-actions">
                            <x-dashboard.status-badge :label="'Active v'.$activeBackground->asset_version" tone="success" />
                            <a class="dashboard-outline-action" href="{{ route('res.certificate-backgrounds.preview', $activeBackground) }}" target="_blank" rel="noopener"><x-dashboard.icon name="eye" size="17" /><span>Preview</span></a>
                        </div>
                    @endif
                </section>

                <section class="certificate-modal-section certificate-background-upload" aria-labelledby="certificate-background-upload-title">
                    <div class="certificate-modal-section-heading"><div><h3 id="certificate-background-upload-title">Upload and Activate</h3><p>Use a one-page portrait A4-compatible PDF, JPEG, or PNG.</p></div></div>
                    <form method="POST" action="{{ route('res.certificate-backgrounds.store') }}" enctype="multipart/form-data" data-disable-on-submit>
                        @csrf
                        <label><span>Background file</span><input type="file" name="background" accept=".pdf,.jpg,.jpeg,.png" required></label>
                        <button class="dashboard-primary-action" type="submit"><x-dashboard.icon name="upload" size="17" /><span>Validate and Activate</span></button>
                    </form>
                    <form method="POST" action="{{ route('res.certificate-backgrounds.reset') }}" data-disable-on-submit>
                        @csrf
                        <button class="dashboard-outline-action" type="submit"><x-dashboard.icon name="refresh" size="17" /><span>Reset to Official Default</span></button>
                    </form>
                </section>

                <section class="certificate-modal-section" aria-labelledby="certificate-background-history-title">
                    <div class="certificate-modal-section-heading"><div><h3 id="certificate-background-history-title">Background History</h3><p>{{ $backgrounds->total() }} stored {{ Str::plural('version', $backgrounds->total()) }}</p></div></div>
                    <x-dashboard.overflow class="certificate-background-scroll" label="Certificate background versions" wide>
                        <table class="dashboard-table certificate-background-table">
                            <thead><tr><th>Version</th><th>File</th><th>Source</th><th>Activated</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                @foreach ($backgrounds as $background)
                                    <tr>
                                        <td><strong>Version {{ $background->asset_version }}</strong></td>
                                        <td>{{ $background->original_file_name }}</td>
                                        <td>{{ $background->source_kind === \App\Services\Certificates\CertificateBackgroundService::OFFICIAL_SOURCE_KIND ? 'Official default' : 'RES uploaded' }}</td>
                                        <td>{{ $background->activated_at?->format('M j, Y g:i A') ?? 'Never' }}</td>
                                        <td><x-dashboard.status-badge :label="$background->is_active ? 'Active' : 'Available'" :tone="$background->is_active ? 'success' : 'neutral'" /></td>
                                        <td class="certificate-background-row-actions">
                                            <a href="{{ route('res.certificate-backgrounds.preview', $background) }}" target="_blank" rel="noopener">Preview</a>
                                            @unless ($background->is_active)
                                                <form method="POST" action="{{ route('res.certificate-backgrounds.activate', $background) }}" data-disable-on-submit>@csrf @method('PATCH')<button type="submit">Activate</button></form>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-dashboard.overflow>
                    <x-dashboard.pagination :paginator="$backgrounds" label="Certificate background pages" />
                </section>

                <div class="application-modal-actions certificate-modal-footer"><button class="dashboard-outline-action" type="button" data-certificate-background-close>Close</button></div>
            </div>
        </section>

        <section class="application-modal-backdrop" data-certificate-bulk-dialog hidden>
            <div class="application-modal certificate-bulk-modal" role="dialog" aria-modal="true" aria-labelledby="certificate-bulk-title" tabindex="-1">
                <button class="application-modal-close" type="button" aria-label="Cancel bulk certificate release" data-certificate-bulk-close><x-dashboard.icon name="x" size="20" /></button>
                <header class="application-modal-heading">
                    <span class="application-modal-icon"><x-dashboard.icon name="award" size="23" /></span>
                    <div><h2 id="certificate-bulk-title">Release All Eligible Certificates?</h2><p>Every application is revalidated and processed independently. Existing releases are skipped.</p></div>
                </header>
                <div class="certificate-bulk-confirmation-copy"><x-dashboard.icon name="circle-help" size="19" /><p>This may generate multiple private PDFs. A safe failure on one application does not roll back successful releases.</p></div>
                <form method="POST" action="{{ route('res.certificates.release-eligible') }}" data-disable-on-submit>
                    @csrf
                    <input type="hidden" name="confirmation" value="release_all_eligible">
                    <div class="application-modal-actions">
                        <button class="dashboard-outline-action" type="button" data-certificate-bulk-close>Cancel</button>
                        <button class="dashboard-primary-action" type="submit">Confirm Bulk Release</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection
