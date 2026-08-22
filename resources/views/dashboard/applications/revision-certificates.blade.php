@extends('layouts.dashboard')

@section('content')
    @php
        $application = $selectedApplication;
        $certificates = $application?->certificates ?? collect();
        $certificate = $certificates->first();
        $currentCertificateVersion = $certificate?->currentVersion;
        $revisionPending = $activeRevision?->status === \App\Enums\ApplicationRevisionStatus::PendingUploads;
        $revisionSubmitted = $activeRevision?->status === \App\Enums\ApplicationRevisionStatus::UnderReview;
        $surveyComplete = $application?->surveyResponse !== null;
        $finalApproved = $latestRelease?->decision === \App\Enums\ReviewDecision::Approved && ! $activeRevision;
        $showCertification = ! $activeRevision;
        $step = match (true) {
            ! $application => 1,
            $certificationState === \App\Enums\CertificationState::Claimed,
            $certificationState === \App\Enums\CertificationState::Claimable,
            $certificationState === \App\Enums\CertificationState::PendingResRelease => 3,
            $certificationState === \App\Enums\CertificationState::SurveyRequired,
            $revisionSubmitted => 2,
            $revisionPending => 1,
            default => 1,
        };
    @endphp

    <div class="dashboard-page revision-certificate-page">
        <header class="dashboard-page-heading">
            <div>
                <h1>Revision and Certificates</h1>
            </div>
        </header>

        @if (session('status'))
            <div class="application-success-banner" role="status">
                <x-dashboard.icon name="check" size="19" />
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @foreach (['revisionUpload', 'revisionSubmission'] as $bag)
            @if ($errors->{$bag}->any())
                <div class="res-form-error-summary" role="alert">
                    <x-dashboard.icon name="alert-triangle" size="19" />
                    <div><strong>The request was not completed.</strong><span>{{ $errors->{$bag}->first() }}</span></div>
                </div>
            @endif
        @endforeach

        @if ($applications->isEmpty())
            <section class="application-panel revision-certificate-empty">
                <x-dashboard.icon name="award" size="42" />
                <h2>No revision or certification records yet</h2>
                <p>Applications appear here after an authorized review result is ready or released.</p>
                <a class="dashboard-primary-action" href="{{ route('applicant.applications.index') }}">Return to Applications</a>
            </section>
        @else
            <nav class="application-panel revision-application-switcher" aria-label="Revision and certificate applications">
                <div>
                    <strong>Application</strong>
                </div>
                <div class="revision-application-tabs">
                    @foreach ($applications as $item)
                        <a
                            href="{{ route('applicant.revision-certificates.index', ['application' => $item->id]) }}"
                            @if ($application?->is($item)) aria-current="page" @endif
                        >
                            <strong>{{ $item->application_code }}</strong>
                            <span>{{ $item->research_title }}</span>
                        </a>
                    @endforeach
                </div>
                {{ $applications->links() }}
            </nav>

            <ol class="revision-progress" aria-label="Revision and certification progress">
                @foreach (['Revision Submission', 'Evaluation Form', 'Certification'] as $label)
                    <li @class(['is-active' => $loop->iteration === $step, 'is-complete' => $loop->iteration < $step || ($loop->iteration === 3 && $certificationState === \App\Enums\CertificationState::Claimed)])>
                        <span>{{ $loop->iteration < $step ? '✓' : $loop->iteration }}</span>
                        <strong>{{ $label }}</strong>
                    </li>
                @endforeach
            </ol>

            <details class="application-panel revision-status-overview revision-major-disclosure" open>
                <summary class="application-panel-heading">
                    <div><h2 id="revision-status-title">Application Status Overview</h2></div>
                    <span class="reviewer-workflow-accordion-chevron" aria-hidden="true"><x-dashboard.icon name="chevron-down" size="18" /></span>
                </summary>
                <dl>
                    <div><dt>Application Code</dt><dd>{{ $application->application_code }}</dd></div>
                    <div><dt>Research Title</dt><dd>{{ $application->research_title }}</dd></div>
                    <div><dt>Released Decision</dt><dd>{{ $latestRelease?->decision?->label() ?? 'Not released' }}</dd></div>
                    <div><dt>Current Status</dt><dd><x-dashboard.status-badge :label="$application->statusLabel()" :tone="$application->application_status->tone()" /></dd></div>
                    <div><dt>Date Released</dt><dd>{{ $latestRelease?->released_at?->format('M j, Y g:i A') ?? 'Not released' }}</dd></div>
                    <div><dt>Revision Due</dt><dd>{{ $activeRevision?->due_at?->format('M j, Y g:i A') ?? 'Not applicable' }}</dd></div>
                </dl>
            </details>

            <div @class([
                'revision-workspace-grid',
                'is-revision-active' => (bool) $activeRevision,
                'is-final-approved' => $finalApproved,
            ])>
                <main>
                    <details class="application-panel revision-feedback-panel revision-major-disclosure" open>
                        <summary class="application-panel-heading">
                            <div>
                                <h2 id="released-feedback-title">Released Feedback and Document History</h2>
                            </div>
                            @if ($latestRelease)
                                <x-dashboard.status-badge :label="$latestRelease->decision->label()" :tone="$latestRelease->decision->tone()" />
                            @endif
                            <span class="reviewer-workflow-accordion-chevron" aria-hidden="true"><x-dashboard.icon name="chevron-down" size="18" /></span>
                        </summary>

                        @if ($requirementFeedbackGroups->isEmpty())
                            <p class="revision-empty-state">No released requirement feedback or document versions are available yet.</p>
                        @else
                            <div class="revision-requirement-accordions">
                                @foreach ($requirementFeedbackGroups as $group)
                                    @php
                                        $versionSelectorId = 'requirement-version-'.$application->id.'-'.$loop->iteration;
                                    @endphp
                                    <details class="revision-requirement-disclosure" data-revision-requirement="{{ $group['key'] }}">
                                        <summary>
                                            <strong>{{ $group['name'] }}</strong>
                                            <span>
                                                {{ $group['comment_count'] }} released {{ Str::plural('comment', $group['comment_count']) }}
                                                @if ($group['versions']->isNotEmpty())
                                                    · {{ $group['versions']->count() }} {{ Str::plural('version', $group['versions']->count()) }}
                                                @endif
                                            </span>
                                        </summary>
                                        <div class="revision-requirement-disclosure-body">
                                            <section class="revision-requirement-feedback" aria-label="{{ $group['name'] }} released feedback">
                                                <h3>Released Reviewer Feedback</h3>
                                                @if ($group['reviewer_groups']->isEmpty())
                                                    <p class="revision-empty-state">No detailed comments were released for this requirement.</p>
                                                @else
                                                    <div class="revision-reviewer-feedback-grid">
                                                        @foreach ($group['reviewer_groups'] as $reviewerGroup)
                                                            <section class="revision-anonymous-reviewer-group">
                                                                <h4>{{ $reviewerGroup['label'] }}</h4>
                                                                @foreach ($reviewerGroup['comments'] as $comment)
                                                                    <article>
                                                                        <header>
                                                                            <x-dashboard.status-badge :label="$comment->category->label()" :tone="$comment->category->tone()" />
                                                                            <span>{{ $comment->scope->label() }}</span>
                                                                            <time datetime="{{ $comment->released_at?->toIso8601String() }}">Released {{ $comment->released_at?->format('M j, Y') }}</time>
                                                                        </header>
                                                                        <p>{{ $comment->body }}</p>
                                                                        @if ($comment->document || $comment->page_number)
                                                                            <small>
                                                                                @if ($comment->document)Document version {{ $comment->document->document_version }}@endif
                                                                                @if ($comment->document && $comment->page_number) · @endif
                                                                                @if ($comment->page_number)Page {{ $comment->page_number }}@endif
                                                                            </small>
                                                                        @endif
                                                                    </article>
                                                                @endforeach
                                                            </section>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </section>

                                            @if ($group['versions']->isNotEmpty())
                                                <div class="revision-version-control">
                                                    <div class="revision-version-selector-heading">
                                                        <label for="{{ $versionSelectorId }}">
                                                            <span>Select version</span>
                                                            <select id="{{ $versionSelectorId }}" data-revision-version-select data-table-tooltip="">
                                                                @foreach ($group['versions'] as $document)
                                                                    <option value="{{ $document->id }}">Version {{ $document->document_version }}: {{ $document->original_file_name }}{{ $document->is_current ? ' - Current' : '' }}</option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                        <div class="revision-version-panels">
                                                            @foreach ($group['versions'] as $document)
                                                                <article data-revision-version-panel="{{ $document->id }}" @if (! $loop->first) hidden @endif>
                                                                    <div class="revision-version-actions">
                                                                        <a href="{{ route('applicant.applications.documents.preview', [$application, $document]) }}" target="_blank" rel="noopener">Preview</a>
                                                                        <a href="{{ route('applicant.applications.documents.download', [$application, $document]) }}">Download</a>
                                                                    </div>
                                                                    <div class="revision-version-meta">
                                                                        @if ($document->is_current)<strong>Current</strong>@endif
                                                                        <time datetime="{{ $document->uploaded_at?->toIso8601String() }}">{{ $document->uploaded_at?->format('M j, Y g:i A') ?? 'Date not recorded' }}</time>
                                                                    </div>
                                                                </article>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </details>
                                    @if (Str::lower($group['name']) === 'research proposal' && $worksheetGroups->isNotEmpty())
                                        <details class="revision-requirement-disclosure applicant-review-worksheet" data-applicant-review-worksheet>
                                            <summary>
                                                <strong>Review Worksheet</strong>
                                                <span>{{ $worksheetGroups->count() }} official worksheet types</span>
                                            </summary>
                                            <div class="applicant-review-worksheet-body">
                                                <div class="applicant-review-worksheet-tabs" role="tablist" aria-label="Review worksheet type">
                                                    @foreach ($worksheetGroups as $worksheetGroup)
                                                        <button
                                                            type="button"
                                                            role="tab"
                                                            aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                                                            data-applicant-worksheet-tab="{{ $worksheetGroup['type']->value }}"
                                                        >{{ $worksheetGroup['type']->label() }}</button>
                                                    @endforeach
                                                </div>
                                                @foreach ($worksheetGroups as $worksheetGroup)
                                                    @php
                                                        $worksheetType = $worksheetGroup['type'];
                                                        $firstWorksheet = $worksheetGroup['artifacts']->first();
                                                        $firstWorksheetArtifact = $firstWorksheet['artifact'];
                                                        $firstWorksheetPreview = route('applicant.revision-certificates.worksheets.preview', [
                                                            $application,
                                                            $firstWorksheet['assignment'],
                                                            $firstWorksheetArtifact->formSubmission,
                                                            $firstWorksheetArtifact,
                                                        ]);
                                                    @endphp
                                                    <section data-applicant-worksheet-panel="{{ $worksheetType->value }}" @if (! $loop->first) hidden @endif>
                                                        <div class="applicant-review-worksheet-controls">
                                                            <label>
                                                                <span>Select version</span>
                                                                <select data-applicant-worksheet-version>
                                                                    @foreach ($worksheetGroup['artifacts'] as $worksheet)
                                                                        @php
                                                                            $worksheetArtifact = $worksheet['artifact'];
                                                                            $worksheetPreview = route('applicant.revision-certificates.worksheets.preview', [
                                                                                $application,
                                                                                $worksheet['assignment'],
                                                                                $worksheetArtifact->formSubmission,
                                                                                $worksheetArtifact,
                                                                            ]);
                                                                            $worksheetDownload = route('applicant.revision-certificates.worksheets.download', [
                                                                                $application,
                                                                                $worksheet['assignment'],
                                                                                $worksheetArtifact->formSubmission,
                                                                                $worksheetArtifact,
                                                                            ]);
                                                                        @endphp
                                                                        <option value="{{ $worksheetArtifact->id }}" data-preview-url="{{ $worksheetPreview }}" data-download-url="{{ $worksheetDownload }}">Version {{ $worksheet['version_number'] }} - {{ $worksheet['reviewer_label'] }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </label>
                                                            <a href="{{ $firstWorksheetPreview }}" target="_blank" rel="noopener" data-applicant-worksheet-preview-link>Open in New Tab</a>
                                                            <a href="{{ route('applicant.revision-certificates.worksheets.download', [$application, $firstWorksheet['assignment'], $firstWorksheetArtifact->formSubmission, $firstWorksheetArtifact]) }}" data-applicant-worksheet-download-link>Download</a>
                                                        </div>
                                                        <iframe src="{{ $firstWorksheetPreview }}" title="{{ $worksheetType->label() }} secure preview" loading="lazy" referrerpolicy="no-referrer" data-applicant-worksheet-frame></iframe>
                                                    </section>
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </details>

                    @unless ($finalApproved)
                    <details class="application-panel revision-documents-panel" open>
                        <summary class="application-panel-heading">
                            <div>
                                <h2 id="revision-documents-title">Revision Submission</h2>
                            </div>
                            @if ($activeRevision)
                                <x-dashboard.status-badge :label="$activeRevision->status->label()" :tone="$revisionPending ? 'orange' : 'blue'" />
                            @endif
                        </summary>

                        @if ($revisionPending)
                            <div class="revision-deadline-notice" role="status">
                                <x-dashboard.icon name="clock" size="19" />
                                <span>Revision {{ $activeRevision->revision_number }} is due {{ $activeRevision->due_at->format('M j, Y g:i A') }}.</span>
                            </div>
                            <div class="revision-requirement-list">
                                @foreach ($activeRevision->requirements as $revisionRequirement)
                                    @php
                                        $replacement = $revisionRequirement->replacementDocument;
                                    @endphp
                                    <article>
                                        <div class="revision-requirement-copy">
                                            <strong>{{ $revisionRequirement->requirement?->name ?? 'Required Document' }}</strong>
                                            <span>
                                                Source: Version {{ $revisionRequirement->sourceDocument?->document_version ?? 'not recorded' }} · {{ $revisionRequirement->sourceDocument?->original_file_name ?? 'file unavailable' }}
                                                @if ($revisionRequirement->sourceDocument)
                                                    <a href="{{ route('applicant.applications.documents.preview', [$application, $revisionRequirement->sourceDocument]) }}" target="_blank" rel="noopener">View Source</a>
                                                @endif
                                            </span>
                                            @if ($replacement)
                                                <button class="revision-upload-complete application-document-title" type="button" data-document-open data-document-name="{{ $replacement->original_file_name }}" data-document-type="{{ $replacement->fileTypeLabel() }}" data-document-meta="Version {{ $replacement->document_version }} uploaded {{ $replacement->uploaded_at?->format('M j, Y g:i A') }}" data-document-preview-kind="{{ $replacement->previewKind() }}" data-document-preview-url="{{ route('applicant.applications.documents.preview', [$application, $replacement]) }}" data-document-download-url="{{ route('applicant.applications.documents.download', [$application, $replacement]) }}" data-document-replace-input="revision_document_{{ $revisionRequirement->id }}">{{ $replacement->original_file_name }}</button>
                                            @else
                                                <small class="revision-upload-required">Replacement required before submission</small>
                                            @endif
                                        </div>
                                        <div class="revision-requirement-actions">
                                            <form
                                                method="POST"
                                                action="{{ route('applicant.revision-certificates.revisions.documents.store', [$application, $activeRevision, $revisionRequirement]) }}"
                                                enctype="multipart/form-data"
                                                data-revision-upload-form
                                            >
                                                @csrf
                                                <label class="dashboard-outline-action revision-file-control">
                                                    <x-dashboard.icon name="upload" size="16" />
                                                    <span data-revision-file-name>Choose File</span>
                                                    <input id="revision_document_{{ $revisionRequirement->id }}" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp" required data-revision-upload-input>
                                                </label>
                                                <span class="application-upload-feedback" role="status" aria-live="polite" data-revision-upload-feedback></span>
                                            </form>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                            @php
                                $revisionReady = $activeRevision->requirements->isNotEmpty()
                                    && $activeRevision->requirements->every(fn ($requirement) => $requirement->replacement_application_document_id !== null);
                            @endphp
                            <form method="POST" action="{{ route('applicant.revision-certificates.revisions.submit', [$application, $activeRevision]) }}" data-disable-on-submit data-notification-confirm data-confirm-title="Submit Revision for Re-review" data-confirm-message="Submit all selected Version {{ $activeRevision->revision_number + 1 }} documents to the authorized Reviewer set? Files cannot be replaced after submission." data-confirm-action="Submit Revision">
                                @csrf
                                <button class="dashboard-primary-action" type="submit" @disabled(! $revisionReady)>
                                    <x-dashboard.icon name="send" size="17" />
                                    <span>Submit Revision for Re-review</span>
                                </button>
                            </form>
                        @elseif ($revisionSubmitted)
                            <div class="revision-complete-state">
                                <x-dashboard.icon name="check" size="34" />
                                <h3>Revision submitted for direct re-review</h3>
                                <p>Submitted {{ $activeRevision->submitted_at?->format('M j, Y g:i A') }}. It did not return to Adviser endorsement or initial RES screening.</p>
                            </div>
                        @elseif ($latestRelease?->decision === \App\Enums\ReviewDecision::Approved)
                            <div class="revision-complete-state">
                                <x-dashboard.icon name="check" size="34" />
                                <h3>No revision required</h3>
                                <p>The released decision approved this application. All current documents remain preserved as the accepted version set.</p>
                            </div>
                        @else
                            <p class="revision-empty-state">No Applicant revision submission is currently open.</p>
                        @endif

                    </details>
                    @endunless
                </main>

                @if ($showCertification)
                <aside>
                    <details class="application-panel certification-panel revision-major-disclosure" open>
                        <summary class="application-panel-heading">
                            <div><h2 id="certification-state-title">Certification</h2></div>
                            <x-dashboard.status-badge :label="$certificationState->label()" :tone="$certificationState->tone()" />
                            <span class="reviewer-workflow-accordion-chevron" aria-hidden="true"><x-dashboard.icon name="chevron-down" size="18" /></span>
                        </summary>

                        @if ($errors->certificateSurvey->any())
                            <div class="res-form-error-summary certificate-action-error" role="alert">
                                <x-dashboard.icon name="alert-triangle" size="19" />
                                <div><strong>Evaluation was not submitted.</strong><span>{{ $errors->certificateSurvey->first() }}</span></div>
                            </div>
                        @endif
                        @if ($errors->certificateClaim->any())
                            <div class="res-form-error-summary certificate-action-error" role="alert">
                                <x-dashboard.icon name="alert-triangle" size="19" />
                                <div><strong>Certificate was not claimed.</strong><span>{{ $errors->certificateClaim->first() }}</span></div>
                            </div>
                        @endif

                        @if ($certificationState === \App\Enums\CertificationState::SurveyRequired)
                            <form method="POST" action="{{ route('applicant.revision-certificates.survey.store', $application) }}" class="certificate-survey-form" data-disable-on-submit>
                                @csrf
                                <div class="certificate-survey-intro">
                                    <strong>Required post-review feedback</strong>
                                    <span>Rate all 10 statements from 1 (Poor) to 5 (Excellent) before claiming your certificate.</span>
                                </div>
                                @foreach (\App\Support\ApplicantSurveyCatalog::sections() as $surveySection)
                                    <section class="certificate-survey-form certificate-survey-section" aria-labelledby="survey-section-{{ $loop->iteration }}">
                                        <h3 id="survey-section-{{ $loop->iteration }}">{{ $surveySection['title'] }}</h3>
                                        @foreach ($surveySection['questions'] as $ratingKey => $ratingLabel)
                                            <fieldset>
                                                <legend>{{ $ratingLabel }}</legend>
                                                <div class="certificate-rating-options">
                                                    @foreach (\App\Support\ApplicantSurveyCatalog::ratingScale() as $rating => $ratingText)
                                                        <label>
                                                            <input type="radio" name="ratings[{{ $ratingKey }}]" value="{{ $rating }}" @checked((int) old("ratings.{$ratingKey}") === (int) $rating) required>
                                                            <span>{{ $rating }} <small>{{ $ratingText }}</small></span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                                @error("ratings.{$ratingKey}", 'certificateSurvey')<small class="certificate-survey-field-error">{{ $message }}</small>@enderror
                                            </fieldset>
                                        @endforeach
                                    </section>
                                @endforeach
                                <label>
                                    <span>Section 3 – Additional Comments (optional)</span>
                                    <textarea name="suggestions_comments" maxlength="2000" placeholder="Share any suggestions or comments.">{{ old('suggestions_comments') }}</textarea>
                                    @error('suggestions_comments', 'certificateSurvey')<small class="certificate-survey-field-error">{{ $message }}</small>@enderror
                                </label>
                                <button class="dashboard-primary-action" type="submit"><x-dashboard.icon name="send" size="16" /><span>Submit Evaluation</span></button>
                            </form>
                        @elseif ($certificationState === \App\Enums\CertificationState::Claimable)
                            <div class="certificate-action-state">
                                <x-dashboard.icon name="award" size="42" />
                                <h3>Certificate ready to claim</h3>
                                <p>Your evaluation is complete and the current released certificate version is ready.</p>
                                <form method="POST" action="{{ route('applicant.revision-certificates.certificate.claim', $application) }}" data-disable-on-submit>
                                    @csrf
                                    <button class="dashboard-primary-action" type="submit">Claim Certificate</button>
                                </form>
                            </div>
                        @elseif ($certificationState === \App\Enums\CertificationState::Claimed && $certificate && $currentCertificateVersion)
                            <div class="certificate-action-state">
                                <x-dashboard.icon name="award" size="42" />
                                <h3>Personalized certificates claimed</h3>
                                <div class="applicant-certificate-list">
                                    @foreach ($certificates as $personalizedCertificate)
                                        @php($personalizedVersion = $personalizedCertificate->currentVersion)
                                        @if ($personalizedVersion)
                                            <article>
                                                <div>
                                                    <strong>{{ $personalizedCertificate->recipient_name ?: $application->applicant?->name }}</strong>
                                                    <span>{{ $personalizedCertificate->certificate_number }} · version {{ $personalizedVersion->certificate_version }}</span>
                                                </div>
                                                <div>
                                                    <a class="dashboard-outline-action" href="{{ route('applicant.revision-certificates.certificate.preview', [$application, $personalizedCertificate, $personalizedVersion]) }}" target="_blank" rel="noopener">View</a>
                                                    <a class="dashboard-primary-action" href="{{ route('applicant.revision-certificates.certificate.download', [$application, $personalizedCertificate, $personalizedVersion]) }}">Download</a>
                                                </div>
                                            </article>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @elseif ($certificationState === \App\Enums\CertificationState::GenerationFailed)
                            <div class="certificate-action-state is-error"><x-dashboard.icon name="alert-triangle" size="34" /><h3>Generation failed safely</h3><p>RES has been asked to retry. No incomplete certificate is available.</p></div>
                        @else
                            <div class="certificate-action-state">
                                <x-dashboard.icon name="clock" size="34" />
                                <h3>{{ $certificationState->label() }}</h3>
                                <p>{{ match ($certificationState) {
                                    \App\Enums\CertificationState::Eligible => 'Final approval is complete. RES must generate and release the official certificate.',
                                    \App\Enums\CertificationState::PendingResRelease => 'Certificate release is currently being processed by RES.',
                                    \App\Enums\CertificationState::PendingFinalApproval => 'The application must receive a final released approval first.',
                                    default => 'This application does not currently satisfy certification eligibility.',
                                } }}</p>
                            </div>
                        @endif
                    </details>
                </aside>
                @endif
            </div>
        @endif
        @include('dashboard.applications.partials.document-dialog')
        <section class="application-modal-backdrop" data-notification-confirm-dialog hidden><div class="application-modal notification-confirmation-modal" role="dialog" aria-modal="true" aria-labelledby="revision-confirm-title" aria-describedby="revision-confirm-message" tabindex="-1"><button class="application-modal-close" type="button" aria-label="Cancel revision submission" data-notification-confirm-close><x-dashboard.icon name="x" size="20" /></button><header class="application-modal-heading"><span class="application-modal-icon"><x-dashboard.icon name="send" size="24" /></span><div><h2 id="revision-confirm-title" data-notification-confirm-title>Submit Revision for Re-review</h2><p id="revision-confirm-message" data-notification-confirm-message>Confirm this revision submission.</p></div></header><div class="application-modal-actions"><button class="dashboard-outline-action" type="button" data-notification-confirm-close>Cancel</button><button class="dashboard-primary-action" type="button" data-notification-confirm-submit><span>Submit Revision</span></button></div></div></section>
    </div>
@endsection
