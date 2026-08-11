@extends('layouts.dashboard')

@section('content')
    @php
        $application = $selectedApplication;
        $certificate = $application?->certificate;
        $currentCertificateVersion = $certificate?->currentVersion;
        $revisionPending = $activeRevision?->status === \App\Enums\ApplicationRevisionStatus::PendingUploads;
        $revisionSubmitted = $activeRevision?->status === \App\Enums\ApplicationRevisionStatus::UnderReview;
        $surveyComplete = $application?->surveyResponse !== null;
        $step = match (true) {
            ! $application => 1,
            $certificationState === \App\Enums\CertificationState::Claimed => 5,
            $certificationState === \App\Enums\CertificationState::Claimable,
            $certificationState === \App\Enums\CertificationState::SurveyRequired => 4,
            $revisionSubmitted => 3,
            $revisionPending => 1,
            default => 2,
        };
    @endphp

    <div class="dashboard-page revision-certificate-page">
        <header class="dashboard-page-heading">
            <div>
                <h1>Revision and Certificates</h1>
                <p>Review released feedback, submit required document versions, complete the evaluation, and claim your certificate.</p>
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
                    <span>Select an owned application record.</span>
                </div>
                <div class="revision-application-tabs">
                    @foreach ($applications as $item)
                        <a
                            href="{{ route('applicant.revision-certificates.index', ['application' => $item->id]) }}"
                            @if ($application?->is($item)) aria-current="page" @endif
                        >
                            <strong>{{ $item->application_code }}</strong>
                            <span>{{ Str::limit($item->research_title, 46) }}</span>
                        </a>
                    @endforeach
                </div>
                {{ $applications->links() }}
            </nav>

            <ol class="revision-progress" aria-label="Revision and certification progress">
                @foreach (['Revision Requirements', 'Released Comments', 'Revision Submission', 'Evaluation Form', 'Certification'] as $label)
                    <li @class(['is-active' => $loop->iteration === $step, 'is-complete' => $loop->iteration < $step || ($loop->iteration === 5 && $certificationState === \App\Enums\CertificationState::Claimed)])>
                        <span>{{ $loop->iteration < $step ? '✓' : $loop->iteration }}</span>
                        <strong>{{ $label }}</strong>
                    </li>
                @endforeach
            </ol>

            <section class="application-panel revision-status-overview" aria-labelledby="revision-status-title">
                <header class="application-panel-heading">
                    <div><h2 id="revision-status-title">Application Status Overview</h2></div>
                </header>
                <dl>
                    <div><dt>Application Code</dt><dd>{{ $application->application_code }}</dd></div>
                    <div><dt>Research Title</dt><dd>{{ $application->research_title }}</dd></div>
                    <div><dt>Released Decision</dt><dd>{{ $latestRelease?->decision?->label() ?? 'Not released' }}</dd></div>
                    <div><dt>Current Status</dt><dd><x-dashboard.status-badge :label="$application->application_status->label()" :tone="$application->application_status->tone()" /></dd></div>
                    <div><dt>Date Released</dt><dd>{{ $latestRelease?->released_at?->format('M j, Y g:i A') ?? 'Not released' }}</dd></div>
                    <div><dt>Revision Due</dt><dd>{{ $activeRevision?->due_at?->format('M j, Y g:i A') ?? 'Not applicable' }}</dd></div>
                </dl>
            </section>

            <div class="revision-workspace-grid">
                <main>
                    <section class="application-panel revision-feedback-panel" aria-labelledby="released-feedback-title">
                        <header class="application-panel-heading">
                            <div>
                                <h2 id="released-feedback-title">Applicant-visible Review Feedback</h2>
                                <p>Only comments explicitly released by RES are shown. Reviewer identities and internal notes remain private.</p>
                            </div>
                            @if ($latestRelease)
                                <x-dashboard.status-badge :label="$latestRelease->decision->label()" :tone="$latestRelease->decision->tone()" />
                            @endif
                        </header>

                        @if (! $latestRelease)
                            <p class="revision-empty-state">The Reviewer decision is still pending authorized RES release.</p>
                        @elseif ($releasedReviewerGroups->isEmpty())
                            <p class="revision-empty-state">The decision was released without Applicant-visible detailed comments.</p>
                        @else
                            <div class="released-reviewer-groups">
                                @foreach ($releasedReviewerGroups as $group)
                                    <details @if ($loop->first) open @endif>
                                        <summary><strong>{{ $group['label'] }}</strong><span>{{ $group['comments']->count() }} released {{ Str::plural('comment', $group['comments']->count()) }}</span></summary>
                                        <div>
                                            @foreach ($group['comments'] as $comment)
                                                <article>
                                                    <header>
                                                        <x-dashboard.status-badge :label="$comment->category->label()" :tone="$comment->category->tone()" />
                                                        <span>{{ $comment->scope->label() }}</span>
                                                        <time datetime="{{ $comment->released_at?->toIso8601String() }}">Released {{ $comment->released_at?->format('M j, Y') }}</time>
                                                    </header>
                                                    <p>{{ $comment->body }}</p>
                                                    <small>
                                                        {{ $comment->document?->requirement?->name ?? 'Overall application' }}
                                                        @if ($comment->document)
                                                            · document version {{ $comment->document->document_version }}
                                                        @endif
                                                        @if ($comment->page_number)
                                                            · page {{ $comment->page_number }}
                                                        @endif
                                                    </small>
                                                </article>
                                            @endforeach
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    <section class="application-panel revision-documents-panel" aria-labelledby="revision-documents-title">
                        <header class="application-panel-heading">
                            <div>
                                <h2 id="revision-documents-title">Revision Submission</h2>
                                <p>Every replacement is stored privately as a new version; original files remain available in history.</p>
                            </div>
                            @if ($activeRevision)
                                <x-dashboard.status-badge :label="$activeRevision->status->label()" :tone="$revisionPending ? 'orange' : 'blue'" />
                            @endif
                        </header>

                        @if ($revisionPending)
                            <div class="revision-deadline-notice" role="status">
                                <x-dashboard.icon name="clock" size="19" />
                                <span>Revision {{ $activeRevision->revision_number }} is due {{ $activeRevision->due_at->format('M j, Y g:i A') }}.</span>
                            </div>
                            <div class="revision-requirement-list">
                                @foreach ($activeRevision->requirements as $revisionRequirement)
                                    @php($replacement = $revisionRequirement->replacementDocument)
                                    <article>
                                        <div class="revision-requirement-copy">
                                            <strong>{{ $revisionRequirement->requirement?->name ?? 'Required Document' }}</strong>
                                            <span>Source: version {{ $revisionRequirement->sourceDocument?->document_version ?? 'not recorded' }} · {{ $revisionRequirement->sourceDocument?->original_file_name ?? 'file unavailable' }}</span>
                                            @if ($replacement)
                                                <small class="revision-upload-complete">Ready: version {{ $replacement->document_version }} uploaded {{ $replacement->uploaded_at?->format('M j, Y g:i A') }}</small>
                                            @else
                                                <small class="revision-upload-required">Replacement required before submission</small>
                                            @endif
                                        </div>
                                        <div class="revision-requirement-actions">
                                            @if ($revisionRequirement->sourceDocument)
                                                <a href="{{ route('applicant.applications.documents.preview', [$application, $revisionRequirement->sourceDocument]) }}" target="_blank" rel="noopener">View source</a>
                                            @endif
                                            @if ($replacement)
                                                <a href="{{ route('applicant.applications.documents.preview', [$application, $replacement]) }}" target="_blank" rel="noopener">View replacement</a>
                                            @endif
                                        </div>
                                        <form
                                            method="POST"
                                            action="{{ route('applicant.revision-certificates.revisions.documents.store', [$application, $activeRevision, $revisionRequirement]) }}"
                                            enctype="multipart/form-data"
                                            data-disable-on-submit
                                        >
                                            @csrf
                                            <label>
                                                <span>{{ $replacement ? 'Replace revised file' : 'Upload revised file' }}</span>
                                                <input type="file" name="document" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" required>
                                            </label>
                                            <button class="dashboard-outline-action" type="submit"><x-dashboard.icon name="upload" size="16" /><span>Upload Version {{ $activeRevision->revision_number + 1 }}</span></button>
                                        </form>
                                    </article>
                                @endforeach
                            </div>
                            @php($revisionReady = $activeRevision->requirements->isNotEmpty() && $activeRevision->requirements->every(fn ($requirement) => $requirement->replacement_application_document_id !== null))
                            <form method="POST" action="{{ route('applicant.revision-certificates.revisions.submit', [$application, $activeRevision]) }}" data-disable-on-submit>
                                @csrf
                                <button class="dashboard-primary-action" type="submit" @disabled(! $revisionReady)>
                                    <x-dashboard.icon name="send" size="17" />
                                    <span>Submit Revision for Re-review</span>
                                </button>
                                @unless ($revisionReady)<small>Upload every required replacement before submitting.</small>@endunless
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

                        @if ($documentVersions->isNotEmpty())
                            <div class="revision-version-history">
                                <h3>Current and previous document versions</h3>
                                @foreach ($documentVersions as $versions)
                                    <details>
                                        <summary><strong>{{ $versions->first()?->requirement?->name ?? 'Supporting Document' }}</strong><span>{{ $versions->count() }} {{ Str::plural('version', $versions->count()) }}</span></summary>
                                        <div>
                                            @foreach ($versions as $document)
                                                <article>
                                                    <div>
                                                        <strong>Version {{ $document->document_version }} @if ($document->is_current) · Current @endif</strong>
                                                        <span>{{ $document->original_file_name }}</span>
                                                        <small>{{ $document->uploaded_at?->format('M j, Y g:i A') ?? 'Date not recorded' }}</small>
                                                    </div>
                                                    <div>
                                                        <a href="{{ route('applicant.applications.documents.preview', [$application, $document]) }}" target="_blank" rel="noopener">Preview</a>
                                                        <a href="{{ route('applicant.applications.documents.download', [$application, $document]) }}">Download</a>
                                                    </div>
                                                </article>
                                            @endforeach
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        @endif
                    </section>
                </main>

                <aside>
                    <section class="application-panel certification-panel" aria-labelledby="certification-state-title">
                        <header class="application-panel-heading">
                            <div><h2 id="certification-state-title">Certification</h2><p>Every condition is checked by the server.</p></div>
                            <x-dashboard.status-badge :label="$certificationState->label()" :tone="$certificationState->tone()" />
                        </header>

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
                                <div class="certificate-survey-intro"><strong>Post-review evaluation</strong><span>Complete every rating and both required feedback fields before claiming.</span></div>
                                @foreach ([
                                    'overall_process' => 'Overall ethics review process',
                                    'communication' => 'Communication and status clarity',
                                    'comments_helpfulness' => 'Helpfulness of released comments',
                                    'timeliness' => 'Reasonableness of completion time',
                                ] as $ratingKey => $ratingLabel)
                                    <fieldset>
                                        <legend>{{ $ratingLabel }}</legend>
                                        <div class="certificate-rating-options">
                                            @foreach (range(1, 5) as $rating)
                                                <label><input type="radio" name="ratings[{{ $ratingKey }}]" value="{{ $rating }}" @checked((int) old("ratings.{$ratingKey}") === $rating) required> {{ $rating }}</label>
                                            @endforeach
                                        </div>
                                        @error("ratings.{$ratingKey}", 'certificateSurvey')<small class="certificate-survey-field-error">{{ $message }}</small>@enderror
                                    </fieldset>
                                @endforeach
                                <label>
                                    <span>What worked well?</span>
                                    <textarea name="positive_feedback" minlength="5" maxlength="500" required>{{ old('positive_feedback') }}</textarea>
                                    @error('positive_feedback', 'certificateSurvey')<small class="certificate-survey-field-error">{{ $message }}</small>@enderror
                                </label>
                                <label>
                                    <span>What can be improved?</span>
                                    <textarea name="improvement_feedback" minlength="5" maxlength="500" required>{{ old('improvement_feedback') }}</textarea>
                                    @error('improvement_feedback', 'certificateSurvey')<small class="certificate-survey-field-error">{{ $message }}</small>@enderror
                                </label>
                                <label><span>Additional comments (optional)</span><textarea name="additional_comments" maxlength="500">{{ old('additional_comments') }}</textarea></label>
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
                                <h3>Certificate claimed</h3>
                                <p>{{ $certificate->certificate_number }} · version {{ $currentCertificateVersion->certificate_version }} · claimed {{ $certificate->claimed_at?->format('M j, Y g:i A') }}</p>
                                <a class="dashboard-outline-action" href="{{ route('applicant.revision-certificates.certificate.preview', [$application, $certificate, $currentCertificateVersion]) }}" target="_blank" rel="noopener">View Certificate</a>
                                <a class="dashboard-primary-action" href="{{ route('applicant.revision-certificates.certificate.download', [$application, $certificate, $currentCertificateVersion]) }}">Download Certificate (PDF)</a>
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
                    </section>
                </aside>
            </div>
        @endif
    </div>
@endsection
