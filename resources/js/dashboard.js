export function initializeDashboard() {
    const shell = document.querySelector('[data-dashboard-shell]');

    if (! shell) {
        return;
    }

    const menuToggles = [...shell.querySelectorAll('[data-menu-toggle]')];
    const menus = [...shell.querySelectorAll('[data-menu]')];

    // Only one header menu remains open, which prevents notification and profile panels from colliding.
    const closeMenus = (exceptName = null) => {
        menus.forEach((menu) => {
            const name = menu.dataset.menu;

            if (name === exceptName) {
                return;
            }

            menu.hidden = true;
            const toggle = menuToggles.find((candidate) => candidate.dataset.menuToggle === name);
            toggle?.setAttribute('aria-expanded', 'false');
        });
    };

    menuToggles.forEach((toggle) => {
        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const name = toggle.dataset.menuToggle;
            const menu = menus.find((candidate) => candidate.dataset.menu === name);

            if (! menu) {
                return;
            }

            const shouldOpen = menu.hidden;
            closeMenus(shouldOpen ? name : null);
            menu.hidden = ! shouldOpen;
            toggle.setAttribute('aria-expanded', String(shouldOpen));
        });
    });

    menus.forEach((menu) => {
        menu.addEventListener('click', (event) => event.stopPropagation());
    });

    document.addEventListener('click', () => closeMenus());
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenus();
            closeSidebar();
        }
    });

    const sidebarOpen = shell.querySelector('[data-sidebar-open]');
    const sidebarClose = shell.querySelector('[data-sidebar-close]');
    const sidebarBackdrop = shell.querySelector('[data-sidebar-backdrop]');

    const setSidebarState = (isOpen) => {
        shell.classList.toggle('sidebar-open', isOpen);
        sidebarOpen?.setAttribute('aria-expanded', String(isOpen));

        if (sidebarBackdrop) {
            sidebarBackdrop.hidden = ! isOpen;
        }

        document.body.style.overflow = isOpen ? 'hidden' : '';
    };

    function closeSidebar() {
        setSidebarState(false);
    }

    sidebarOpen?.addEventListener('click', () => setSidebarState(true));
    sidebarClose?.addEventListener('click', closeSidebar);
    sidebarBackdrop?.addEventListener('click', closeSidebar);

    shell.querySelectorAll('.dashboard-nav-link, .dashboard-sidebar-profile').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 1120px)').matches) {
                closeSidebar();
            }
        });
    });

    window.addEventListener('resize', () => {
        if (! window.matchMedia('(max-width: 1120px)').matches) {
            closeSidebar();
        }
    }, { passive: true });

    initializeResearchTitleTooltips(shell);
    initializeManagedAccountTools(shell);
    initializeApplicationTools(shell);
    initializeSettingsTools(shell);
    initializeOnboardingGuide(shell);
}

function initializeApplicationTools(shell) {
    // Share one focus-restoring modal behavior between dashboard details and requirements dialogs.
    const modalConfigurations = [
        {
            dialog: shell.querySelector('[data-application-details-dialog]'),
            openSelector: '[data-application-details-open]',
            closeSelector: '[data-application-details-close]',
        },
        {
            dialog: shell.querySelector('[data-requirements-details-dialog]'),
            openSelector: '[data-requirements-details-open]',
            closeSelector: '[data-requirements-details-close]',
        },
        {
            dialog: shell.querySelector('[data-adviser-endorse-dialog]'),
            openSelector: '[data-adviser-endorse-open]',
            closeSelector: '[data-adviser-endorse-close]',
        },
        {
            dialog: shell.querySelector('[data-adviser-return-dialog]'),
            openSelector: '[data-adviser-return-open]',
            closeSelector: '[data-adviser-return-close]',
        },
        {
            dialog: shell.querySelector('[data-final-submit-dialog]'),
            openSelector: '[data-final-submit-open]',
            closeSelector: '[data-final-submit-close]',
        },
        {
            dialog: shell.querySelector('[data-reviewer-assignment-dialog]'),
            openSelector: '[data-reviewer-assignment-confirm-open]',
            closeSelector: '[data-reviewer-assignment-confirm-close]',
        },
    ];

    // Each official Reviewer form owns a separate accessible dialog while sharing focus restoration.
    shell.querySelectorAll('[data-reviewer-form-dialog]').forEach((dialog) => {
        const type = dialog.dataset.reviewerFormDialog;

        modalConfigurations.push({
            dialog,
            openSelector: `[data-reviewer-form-open="${type}"]`,
            closeSelector: '[data-reviewer-form-close]',
        });
    });

    // Initialize only modal configurations whose Blade dialog exists on the current page.
    modalConfigurations.forEach(({ dialog, openSelector, closeSelector }) => {
        // Pages without this dialog require no listeners or temporary modal state.
        if (! dialog) {
            return;
        }

        const panel = dialog.querySelector('[role="dialog"]');
        let returnFocus = null;

        // Closing a dashboard application dialog always returns the user to its originating command.
        const closeDialog = () => {
            dialog.hidden = true;
            returnFocus?.focus();
        };

        // Each open command reveals its matching dialog and moves keyboard focus into the panel.
        shell.querySelectorAll(openSelector).forEach((button) => {
            button.addEventListener('click', () => {
                returnFocus = button;
                dialog.hidden = false;
                panel?.focus();
            });
        });

        // Every explicit close control shares the focus-restoring cleanup.
        dialog.querySelectorAll(closeSelector).forEach((button) => {
            button.addEventListener('click', closeDialog);
        });

        // A pointer click on the shaded backdrop closes without triggering a navigation or write.
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                closeDialog();
            }
        });

        // Escape provides the keyboard-equivalent dialog close action.
        dialog.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeDialog();
            }
        });

        // Server validation reopens the relevant decision form without losing entered remarks.
        if (dialog.hasAttribute('data-open-on-load')) {
            dialog.hidden = false;
            panel?.focus();
        }
    });

    const reviewerAssignmentForm = shell.querySelector('[data-reviewer-assignment-form]');

    if (reviewerAssignmentForm) {
        const requiredReviewers = Number.parseInt(reviewerAssignmentForm.dataset.requiredReviewers ?? '0', 10);
        const reviewerInputs = [...reviewerAssignmentForm.querySelectorAll('[data-reviewer-select]')];
        const selectionCount = shell.querySelector('[data-reviewer-selection-count]');
        const selectedList = shell.querySelector('[data-selected-reviewer-list]');
        const confirmationList = shell.querySelector('[data-confirmation-reviewer-list]');
        const confirmationButton = shell.querySelector('[data-reviewer-assignment-confirm-open]');
        const removeIconTemplate = shell.querySelector('[data-reviewer-remove-icon]');

        // Build reviewer summaries with text nodes so account data is never interpreted as markup.
        const reviewerSummary = (input, removable) => {
            const row = input.closest('[data-reviewer-row]');
            const item = document.createElement('li');
            const identity = document.createElement('div');
            const name = document.createElement('strong');
            const details = document.createElement('small');
            const load = document.createElement('span');

            name.textContent = row?.dataset.reviewerName ?? 'Reviewer';
            details.textContent = [
                row?.dataset.reviewerPosition,
                row?.dataset.reviewerDepartment,
            ].filter(Boolean).join(' - ');
            load.textContent = row?.dataset.reviewerLoad ?? '';
            identity.append(name, details);
            item.append(identity, load);

            if (removable) {
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'res-selected-reviewer-remove';
                remove.title = `Remove ${name.textContent}`;
                remove.setAttribute('aria-label', `Remove ${name.textContent}`);
                if (removeIconTemplate) {
                    remove.append(removeIconTemplate.content.cloneNode(true));
                } else {
                    remove.textContent = 'X';
                }
                remove.addEventListener('click', () => {
                    input.checked = false;
                    syncReviewerSelection();
                    input.focus();
                });
                item.append(remove);
            }

            return item;
        };

        // Keep the selected panel, confirmation modal, row state, and exact-count command synchronized.
        const syncReviewerSelection = () => {
            const selected = reviewerInputs.filter((input) => input.checked && ! input.disabled);

            reviewerInputs.forEach((input) => {
                input.closest('[data-reviewer-row]')?.classList.toggle('is-selected', input.checked);
            });

            if (selectionCount) {
                selectionCount.textContent = `${selected.length} / ${requiredReviewers} Selected`;
                selectionCount.classList.toggle('is-complete', selected.length === requiredReviewers);
            }

            selectedList?.replaceChildren(...selected.map((input) => reviewerSummary(input, true)));
            confirmationList?.replaceChildren(...selected.map((input) => reviewerSummary(input, false)));

            if (confirmationButton) {
                confirmationButton.disabled = selected.length !== requiredReviewers;
                confirmationButton.setAttribute('aria-disabled', String(confirmationButton.disabled));
            }
        };

        reviewerInputs.forEach((input) => {
            input.addEventListener('change', () => {
                const selectedCount = reviewerInputs.filter((candidate) => candidate.checked && ! candidate.disabled).length;

                // Reject an extra selection immediately while preserving the previously valid reviewer set.
                if (selectedCount > requiredReviewers) {
                    input.checked = false;
                }

                syncReviewerSelection();
            });
        });

        syncReviewerSelection();
    }

    const reviewerCommentForm = shell.querySelector('[data-reviewer-comment-form]');

    if (reviewerCommentForm) {
        const scope = reviewerCommentForm.querySelector('[data-reviewer-comment-scope]');
        const documentField = reviewerCommentForm.querySelector('[data-reviewer-comment-document-field]');
        const documentInput = reviewerCommentForm.querySelector('[data-reviewer-comment-document]');
        const pageField = reviewerCommentForm.querySelector('[data-reviewer-comment-page-field]');
        const pageInput = reviewerCommentForm.querySelector('[data-reviewer-comment-page]');

        // Scope-dependent controls remain absent from validation until the Reviewer selects them.
        const syncReviewerCommentScope = () => {
            const referencesDocument = ['document', 'page'].includes(scope?.value);
            const referencesPage = scope?.value === 'page';

            if (documentField && documentInput) {
                documentField.hidden = ! referencesDocument;
                documentInput.disabled = ! referencesDocument || scope.disabled;
                documentInput.required = referencesDocument;
            }
            if (pageField && pageInput) {
                pageField.hidden = ! referencesPage;
                pageInput.disabled = ! referencesPage || scope.disabled;
                pageInput.required = referencesPage;
            }
        };

        scope?.addEventListener('change', syncReviewerCommentScope);
        syncReviewerCommentScope();
    }

    // Populate the secure viewer only with controller URLs already rendered into the triggering row.
    const documentDialog = shell.querySelector('[data-document-dialog]');
    const documentPanel = documentDialog?.querySelector('[role="dialog"]');
    const documentTitle = documentDialog?.querySelector('[data-document-title]');
    const documentMeta = documentDialog?.querySelector('[data-document-meta]');
    const documentFrame = documentDialog?.querySelector('[data-document-frame]');
    const documentFallback = documentDialog?.querySelector('[data-document-fallback]');
    const documentDownload = documentDialog?.querySelector('[data-document-download]');
    const documentReplace = documentDialog?.querySelector('[data-document-replace]');
    let documentTrigger = null;
    let documentReplaceInput = null;

    // Clearing the iframe on close stops private document rendering and avoids retaining an obsolete URL.
    const closeDocumentDialog = () => {
        if (! documentDialog) {
            return;
        }

        documentDialog.hidden = true;
        documentFrame?.removeAttribute('src');
        documentReplaceInput = null;
        documentTrigger?.focus();
    };

    const openDocumentDialog = (button) => {
        const previewUrl = button.dataset.documentPreviewUrl ?? '';
        documentTrigger = button;

        if (documentTitle) {
            const name = button.dataset.documentName ?? 'Document';
            documentTitle.textContent = name;
            documentTitle.dataset.tableTooltip = name;
        }

        if (documentMeta) {
            documentMeta.textContent = button.dataset.documentMeta ?? 'Selected requirement document';
        }

        if (documentDownload) {
            documentDownload.href = button.dataset.documentDownloadUrl;
        }

        const replaceInputId = button.dataset.documentReplaceInput ?? '';
        const replacementCandidate = replaceInputId === '' ? null : document.getElementById(replaceInputId);
        documentReplaceInput = replacementCandidate instanceof HTMLInputElement && shell.contains(replacementCandidate)
            ? replacementCandidate
            : null;

        if (documentReplace) {
            documentReplace.hidden = documentReplaceInput === null;
        }

        if (documentFrame) {
            documentFrame.hidden = previewUrl === '';
            previewUrl === ''
                ? documentFrame.removeAttribute('src')
                : documentFrame.setAttribute('src', previewUrl);
        }

        if (documentFallback) {
            documentFallback.hidden = previewUrl !== '';
        }

        documentDialog.hidden = false;
        documentPanel?.focus();
    };

    // The modal Replace command opens the requirement-scoped native file picker.
    documentReplace?.addEventListener('click', () => {
        documentReplaceInput?.click();
    });

    // Explicit document close controls clear the frame and restore trigger focus.
    documentDialog?.querySelectorAll('[data-document-close]').forEach((button) => {
        button.addEventListener('click', closeDocumentDialog);
    });

    // Backdrop clicks close the viewer without navigating to the private document.
    documentDialog?.addEventListener('click', (event) => {
        if (event.target === documentDialog) {
            closeDocumentDialog();
        }
    });

    // Escape closes the viewer for keyboard users.
    documentDialog?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeDocumentDialog();
        }
    });

    const uploadAll = shell.querySelector('[data-upload-all]');
    const uploadAllLabel = uploadAll?.querySelector('[data-upload-all-label]');
    const uploadAllSummary = shell.querySelector('[data-upload-all-summary]');
    const defaultUploadAllLabel = uploadAllLabel?.textContent ?? 'Upload All';
    let requirementsRefreshTimer = null;

    const selectedRequirementFiles = () => [...shell.querySelectorAll('[data-requirement-file]')]
        .filter((input) => input.files?.length);

    const syncUploadAll = () => {
        if (uploadAll) {
            uploadAll.disabled = selectedRequirementFiles().length === 0;
        }
    };

    const setRequirementFeedback = (requirementId, message, isError = false) => {
        const row = shell.querySelector(`[data-requirement-row][data-requirement-id="${requirementId}"]`);
        const feedback = row?.querySelector('[data-upload-feedback]');

        if (feedback) {
            feedback.textContent = message;
            feedback.classList.toggle('is-error', isError);
        }
    };

    const replaceRequirementRow = (requirementId, html) => {
        const currentRow = shell.querySelector(`[data-requirement-row][data-requirement-id="${requirementId}"]`);
        const template = document.createElement('template');
        template.innerHTML = html.trim();
        const replacement = template.content.firstElementChild;

        if (! currentRow || ! replacement?.matches('[data-requirement-row]')) {
            return;
        }

        currentRow.replaceWith(replacement);
        replacement.querySelectorAll('[data-table-tooltip]').forEach((target) => {
            if (! target.matches('a, button, input, select, textarea, [tabindex]')) {
                target.tabIndex = 0;
            }
        });
    };

    const updateRequirementProgress = (progress) => {
        if (! progress) {
            return;
        }

        const progressElement = shell.querySelector('[data-requirement-progress]');
        const progressCopy = shell.querySelector('[data-requirement-progress-copy]');
        const progressPercent = shell.querySelector('[data-requirement-progress-percent]');
        const finalButton = shell.querySelector('[data-final-application-button]');
        const readinessItem = shell.querySelector('[data-requirement-readiness]');

        if (progressElement) {
            progressElement.value = progress.completed_count;
            progressElement.max = Math.max(1, progress.mandatory_total);
            progressElement.textContent = `${progress.percentage}%`;
        }
        if (progressCopy) {
            progressCopy.textContent = `${progress.completed_count} of ${progress.mandatory_total} mandatory requirements completed`;
        }
        if (progressPercent) {
            progressPercent.textContent = `${progress.percentage}%`;
        }
        Object.entries(progress).forEach(([key, value]) => {
            const count = shell.querySelector(`[data-requirement-count="${key}"]`);

            if (count) {
                count.textContent = value;
            }
        });
        if (finalButton) {
            const otherChecksPass = finalButton.dataset.otherChecksPass === 'true';
            finalButton.disabled = ! (progress.ready && otherChecksPass);
        }
        if (readinessItem) {
            readinessItem.classList.toggle('is-complete', progress.ready);
            const readyIcon = readinessItem.querySelector('[data-requirement-ready-icon]');
            const pendingIcon = readinessItem.querySelector('[data-requirement-pending-icon]');
            readyIcon?.toggleAttribute('hidden', ! progress.ready);
            pendingIcon?.toggleAttribute('hidden', progress.ready);
        }
    };

    // Refresh only after every requirement succeeds and no browser-selected file would be discarded.
    const refreshCompletedRequirements = (progress, hasErrors = false) => {
        if (! progress?.ready || hasErrors || selectedRequirementFiles().length > 0) {
            return;
        }

        window.clearTimeout(requirementsRefreshTimer);
        requirementsRefreshTimer = window.setTimeout(() => {
            if (selectedRequirementFiles().length === 0) {
                window.location.reload();
            }
        }, 700);
    };

    const parseJson = async (response) => {
        try {
            return await response.json();
        } catch {
            return {};
        }
    };

    const uploadOne = async (form) => {
        const input = form.querySelector('[data-requirement-file]');
        const requirementId = form.dataset.requirementId;

        if (! input?.files?.length || ! requirementId) {
            return;
        }

        const button = form.querySelector('button[type="submit"]');
        button?.setAttribute('disabled', 'disabled');
        setRequirementFeedback(requirementId, 'Uploading...');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await parseJson(response);

            if (! response.ok) {
                const message = payload.errors?.document?.[0]
                    ?? payload.message
                    ?? 'This document could not be uploaded.';
                setRequirementFeedback(requirementId, message, true);

                return;
            }

            replaceRequirementRow(requirementId, payload.row_html);
            updateRequirementProgress(payload.progress);
            setRequirementFeedback(requirementId, payload.message ?? 'Document uploaded.');
            refreshCompletedRequirements(payload.progress);
        } catch {
            setRequirementFeedback(requirementId, 'Upload failed. Check your connection and try again.', true);
        } finally {
            button?.removeAttribute('disabled');
            syncUploadAll();
        }
    };

    shell.addEventListener('click', (event) => {
        const documentButton = event.target.closest?.('[data-document-open]');

        if (documentButton && shell.contains(documentButton)) {
            openDocumentDialog(documentButton);
        }
    });

    shell.addEventListener('change', (event) => {
        const input = event.target;

        if (! input.matches?.('[data-requirement-file]')) {
            if (input.matches?.('[data-document-replace-file]') && input.files?.length) {
                closeDocumentDialog();
                input.form?.requestSubmit();
            }

            return;
        }

        const form = input.closest('form');
        const filename = form?.querySelector('[data-application-file-name]');
        const requirementId = input.dataset.requirementId;

        if (filename) {
            filename.textContent = input.files?.[0]?.name ?? 'No file selected';
        }
        if (requirementId) {
            setRequirementFeedback(requirementId, '');
        }
        syncUploadAll();

        if (input.matches('[data-document-replace-file]') && input.files?.length) {
            closeDocumentDialog();
            form?.requestSubmit();
        }
    });

    shell.addEventListener('submit', (event) => {
        const form = event.target;

        // Screening corrections require a deliberate confirmation before the locked workflow update runs.
        if (form.matches?.('[data-confirm-screening-update]')
            && ! window.confirm('Update this screening decision? Incompatible unstarted reviewer assignments may be removed.')) {
            event.preventDefault();

            return;
        }

        if (form.matches?.('[data-confirm-review-submit]')
            && event.submitter?.value === 'submit'
            && ! window.confirm('Submit this final review? Submitted forms, comments, and the decision can no longer be changed.')) {
            event.preventDefault();

            return;
        }

        if (form.matches?.('[data-application-upload-form]')) {
            event.preventDefault();
            uploadOne(form);

            return;
        }

        if (form.matches?.('[data-confirm-document-remove]')
            && ! window.confirm('Remove this uploaded document? A replacement will be required before submission.')) {
            event.preventDefault();
        }

        if (form.matches?.('[data-confirm-draft-discard]')
            && ! window.confirm('Permanently discard this draft application and its uploaded files? This cannot be undone.')) {
            event.preventDefault();
        }
    });

    uploadAll?.addEventListener('click', async () => {
        const selectedInputs = selectedRequirementFiles();

        if (selectedInputs.length === 0) {
            return;
        }

        const formData = new FormData();
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrfToken) {
            formData.append('_token', csrfToken);
        }
        selectedInputs.forEach((input) => {
            formData.append(`documents[${input.dataset.requirementId}]`, input.files[0]);
            setRequirementFeedback(input.dataset.requirementId, 'Uploading...');
        });

        uploadAll.disabled = true;
        if (uploadAllLabel) {
            uploadAllLabel.textContent = 'Uploading...';
        }
        uploadAllSummary.textContent = '';

        try {
            const response = await fetch(uploadAll.dataset.uploadAllUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await parseJson(response);

            if (! response.ok) {
                uploadAllSummary.textContent = payload.message ?? 'The selected documents could not be uploaded.';
                uploadAllSummary.classList.add('is-error');

                return;
            }

            Object.entries(payload.successes ?? {}).forEach(([requirementId, result]) => {
                replaceRequirementRow(requirementId, result.row_html);
                setRequirementFeedback(requirementId, result.message ?? 'Document uploaded.');
            });
            Object.entries(payload.errors ?? {}).forEach(([requirementId, message]) => {
                setRequirementFeedback(requirementId, message, true);
            });
            updateRequirementProgress(payload.progress);
            const hasErrors = Object.keys(payload.errors ?? {}).length > 0;
            uploadAllSummary.classList.toggle('is-error', hasErrors);
            uploadAllSummary.textContent = payload.message ?? 'Selected documents processed.';
            refreshCompletedRequirements(payload.progress, hasErrors);
        } catch {
            uploadAllSummary.classList.add('is-error');
            uploadAllSummary.textContent = 'Upload failed. Check your connection and try again.';
        } finally {
            if (uploadAllLabel) {
                uploadAllLabel.textContent = defaultUploadAllLabel;
            }
            syncUploadAll();
        }
    });
    syncUploadAll();

    // Keep the ending-date boundary synchronized without replacing server-side validation.
    const expectedStartDate = shell.querySelector('#expected_start_date');
    const expectedEndDate = shell.querySelector('[data-expected-end-date]');
    const syncExpectedDuration = () => {
        if (expectedEndDate) {
            expectedEndDate.min = expectedStartDate?.value ?? '';
        }
    };
    expectedStartDate?.addEventListener('change', syncExpectedDuration);
    syncExpectedDuration();

    // Disable write commands after native validation passes to prevent accidental duplicate requests.
    shell.querySelectorAll('[data-application-submit-once]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            // Earlier validation or confirmation handlers may intentionally cancel this submission.
            if (event.defaultPrevented) {
                return;
            }

            const submitter = event.submitter ?? form.querySelector('button[type="submit"]');
            submitter?.setAttribute('disabled', 'disabled');
        });
    });
}

function initializeSettingsTools(shell) {
    const settings = shell.querySelector('[data-settings-tabs]');

    if (! settings) {
        return;
    }

    const tabs = [...settings.querySelectorAll('[data-settings-tab]')];
    const panels = [...settings.querySelectorAll('[data-settings-panel]')];
    const hashTab = window.location.hash.startsWith('#settings-')
        ? window.location.hash.replace('#settings-', '')
        : '';
    let activeTab = tabs.some((tab) => tab.dataset.settingsTab === hashTab)
        ? hashTab
        : settings.dataset.settingsActiveTab;

    if (! tabs.some((tab) => tab.dataset.settingsTab === activeTab)) {
        activeTab = 'profile';
    }

    const activateTab = (name, updateLocation = false) => {
        activeTab = name;

        tabs.forEach((tab) => {
            const selected = tab.dataset.settingsTab === name;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        });
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.settingsPanel !== name;
        });

        if (updateLocation) {
            window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}#settings-${name}`);
        }
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activateTab(tab.dataset.settingsTab, true));
        tab.addEventListener('keydown', (event) => {
            if (! ['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                return;
            }

            event.preventDefault();
            const nextIndex = event.key === 'Home'
                ? 0
                : event.key === 'End'
                    ? tabs.length - 1
                    : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
            tabs[nextIndex].focus();
            activateTab(tabs[nextIndex].dataset.settingsTab, true);
        });
    });
    activateTab(activeTab);

    settings.querySelectorAll('[data-deadline-process]').forEach((process) => {
        const start = process.querySelector('[data-deadline-start]');
        const end = process.querySelector('[data-deadline-end]');
        const toggle = process.querySelector('[data-deadline-toggle]');
        const label = process.querySelector('[data-deadline-toggle-label]');
        const syncProcessState = () => {
            const manuallyOpen = Boolean(toggle?.checked);

            if (label) {
                label.textContent = manuallyOpen ? 'On' : 'Auto';
            }

            // Manual availability never relaxes the authoritative no-past-date input boundary.
            if (start) {
                start.min = start.dataset.minimumDeadline ?? '';
            }
            if (end) {
                const absoluteMinimum = end.dataset.minimumDeadline ?? '';
                end.min = start?.value && start.value > absoluteMinimum
                    ? start.value
                    : absoluteMinimum;
            }
        };

        start?.addEventListener('change', syncProcessState);
        toggle?.addEventListener('change', syncProcessState);
        syncProcessState();
    });

    const termStart = settings.querySelector('#term_starts_on');
    const termEnd = settings.querySelector('#term_ends_on');
    termStart?.addEventListener('change', () => {
        if (termEnd) {
            const absoluteMinimum = termEnd.dataset.minimumDate ?? '';
            termEnd.min = termStart.value && termStart.value > absoluteMinimum
                ? termStart.value
                : absoluteMinimum;
        }
    });
    if (termEnd) {
        const absoluteMinimum = termEnd.dataset.minimumDate ?? '';
        termEnd.min = termStart?.value && termStart.value > absoluteMinimum
            ? termStart.value
            : absoluteMinimum;
    }

    const confirmationDialog = settings.querySelector('[data-settings-confirm-dialog]');
    const confirmationPanel = confirmationDialog?.querySelector('[role="dialog"]');
    const confirmationTitle = confirmationDialog?.querySelector('[data-settings-confirm-title]');
    const confirmationMessage = confirmationDialog?.querySelector('[data-settings-confirm-message]');
    const confirmationSubmit = confirmationDialog?.querySelector('[data-settings-confirm-submit]');
    let pendingConfirmationForm = null;
    let confirmationTrigger = null;

    const passwordFieldsMatch = (form) => {
        if (! form.matches('[data-settings-password-form]')) {
            return true;
        }

        const password = form.elements.namedItem('password');
        const confirmation = form.elements.namedItem('password_confirmation');

        if (! (password instanceof HTMLInputElement)
            || ! (confirmation instanceof HTMLInputElement)
            || password.value === confirmation.value) {
            return true;
        }

        [password, confirmation].forEach((input) => {
            input.setAttribute('aria-invalid', 'true');
            const error = form.querySelector(`[data-settings-error-for="${input.name}"]`);

            if (error) {
                error.textContent = 'The new password and confirmation do not match.';
            }
        });
        password.focus();

        return false;
    };

    const closeConfirmation = () => {
        if (! confirmationDialog) {
            return;
        }

        confirmationDialog.hidden = true;
        pendingConfirmationForm = null;
        confirmationTrigger?.focus();
    };

    settings.querySelectorAll('[data-settings-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.settingsConfirmationApproved === 'true') {
                delete form.dataset.settingsConfirmationApproved;

                return;
            }

            event.preventDefault();

            if (! form.reportValidity() || ! passwordFieldsMatch(form) || ! confirmationDialog) {
                return;
            }

            pendingConfirmationForm = form;
            confirmationTrigger = event.submitter;
            confirmationTitle.textContent = form.dataset.confirmTitle ?? 'Confirm Account Change';
            confirmationMessage.textContent = form.dataset.confirmMessage ?? 'Confirm this account change.';
            confirmationSubmit.textContent = form.dataset.confirmAction ?? 'Confirm';
            confirmationDialog.hidden = false;
            confirmationPanel?.focus();
        });
    });

    confirmationDialog?.querySelectorAll('[data-settings-confirm-close]').forEach((button) => {
        button.addEventListener('click', closeConfirmation);
    });
    confirmationDialog?.addEventListener('click', (event) => {
        if (event.target === confirmationDialog) {
            closeConfirmation();
        }
    });
    confirmationDialog?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeConfirmation();
        }
    });
    confirmationSubmit?.addEventListener('click', () => {
        const form = pendingConfirmationForm;

        if (! form) {
            return;
        }

        confirmationDialog.hidden = true;
        pendingConfirmationForm = null;
        form.dataset.settingsConfirmationApproved = 'true';
        form.requestSubmit();
    });

    const passwordForm = settings.querySelector('[data-settings-password-form]');

    if (! passwordForm) {
        return;
    }

    const passwordStatus = passwordForm.querySelector('[data-settings-password-status]');
    const passwordInputs = [...passwordForm.querySelectorAll('input[type="password"]')];
    const submitButton = passwordForm.querySelector('button[type="submit"]');
    const submitLabel = submitButton?.querySelector('span');
    const defaultSubmitLabel = submitLabel?.textContent ?? 'Change Password';

    const clearFieldError = (input) => {
        input.setAttribute('aria-invalid', 'false');
        const error = passwordForm.querySelector(`[data-settings-error-for="${input.name}"]`);

        if (error) {
            error.textContent = '';
        }
    };

    passwordInputs.forEach((input) => input.addEventListener('input', () => clearFieldError(input)));

    passwordForm.addEventListener('submit', async (event) => {
        if (event.defaultPrevented) {
            return;
        }

        event.preventDefault();

        if (! passwordForm.reportValidity()) {
            return;
        }

        passwordInputs.forEach(clearFieldError);
        passwordStatus.textContent = '';
        submitButton.disabled = true;
        if (submitLabel) {
            submitLabel.textContent = 'Changing...';
        }

        try {
            const response = await fetch(passwordForm.action, {
                method: 'POST',
                body: new FormData(passwordForm),
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await response.json();

            if (! response.ok) {
                const errors = payload.errors ?? {};
                let firstInvalid = null;

                Object.entries(errors).forEach(([field, messages]) => {
                    const input = passwordForm.elements.namedItem(field);
                    const error = passwordForm.querySelector(`[data-settings-error-for="${field}"]`);

                    if (input instanceof HTMLInputElement) {
                        input.setAttribute('aria-invalid', 'true');
                        firstInvalid ??= input;
                    }

                    if (error) {
                        error.textContent = Array.isArray(messages) ? messages[0] : String(messages);
                    }
                });

                passwordStatus.textContent = payload.message ?? 'Correct the highlighted password fields.';
                passwordStatus.classList.add('is-error');
                firstInvalid?.focus();

                return;
            }

            passwordInputs.forEach((input) => {
                input.value = '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
            passwordStatus.classList.remove('is-error');
            passwordStatus.textContent = payload.message ?? 'Your password was changed securely.';
        } catch {
            passwordStatus.classList.add('is-error');
            passwordStatus.textContent = 'The password could not be changed. Check your connection and try again.';
        } finally {
            submitButton.disabled = false;
            if (submitLabel) {
                submitLabel.textContent = defaultSubmitLabel;
            }
        }
    });
}

function initializeOnboardingGuide(shell) {
    const guide = shell.querySelector('[data-onboarding-guide]');

    if (! guide) {
        return;
    }

    const dialog = guide.querySelector('[role="dialog"]');
    const openButtons = shell.querySelectorAll('[data-guide-open]');
    const closeButtons = guide.querySelectorAll('[data-guide-close], [data-guide-finish]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    let returnFocus = null;

    const openGuide = (trigger = null) => {
        returnFocus = trigger;
        guide.hidden = false;
        dialog?.focus();
    };

    const recordCompletion = async () => {
        if (guide.dataset.requiresCompletion !== 'true') {
            return true;
        }

        try {
            const response = await fetch(guide.dataset.completeUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (! response.ok) {
                return false;
            }

            guide.dataset.requiresCompletion = 'false';
            openButtons.forEach((button) => {
                button.hidden = false;
            });

            return true;
        } catch {
            return false;
        }
    };

    const closeGuide = async () => {
        if (! await recordCompletion()) {
            return;
        }

        guide.hidden = true;
        returnFocus?.focus();
    };

    openButtons.forEach((button) => button.addEventListener('click', () => openGuide(button)));
    closeButtons.forEach((button) => button.addEventListener('click', closeGuide));

    shell.querySelectorAll('.dashboard-nav-link').forEach((link) => {
        link.addEventListener('click', async (event) => {
            if (guide.hidden || guide.dataset.requiresCompletion !== 'true') {
                return;
            }

            event.preventDefault();

            if (await recordCompletion()) {
                window.location.assign(link.href);
            }
        });
    });

    guide.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeGuide();
        }
    });

    if (guide.dataset.requiresCompletion === 'true') {
        openGuide();
    }
}

function initializeManagedAccountTools(shell) {
    // Account password controls stay hidden until input exists and clearly expose their current state.
    shell.querySelectorAll('[data-managed-password-toggle]').forEach((toggle) => {
        const wrapper = toggle.closest('.identity-password-wrap');
        const input = wrapper?.querySelector('[data-managed-password]');

        if (! input) {
            return;
        }

        const setVisibility = (isVisible) => {
            input.type = isVisible ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(isVisible));
            toggle.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
        };
        const syncToggle = () => {
            toggle.hidden = input.value.length === 0;

            if (toggle.hidden) {
                setVisibility(false);
            }
        };

        toggle.addEventListener('click', () => setVisibility(input.type !== 'text'));
        input.addEventListener('input', syncToggle);
        syncToggle();
    });

    // Locate the import controls and result regions once so every interaction updates one visible state.
    const importInput = shell.querySelector('[data-account-import-file]');
    const importName = shell.querySelector('[data-account-import-name]');
    const importGeneralError = shell.querySelector('[data-import-general-error]');
    const importPreview = shell.querySelector('[data-import-preview]');
    const importErrorsButton = shell.querySelector('[data-import-errors-open]');
    const importErrorsDialog = shell.querySelector('[data-import-errors-dialog]');
    const importErrorsPanel = importErrorsDialog?.querySelector('[role="dialog"]');
    const importResultCategories = importErrorsDialog?.querySelectorAll('[data-import-result-category]') ?? [];
    const importErrorsEmpty = importErrorsDialog?.querySelector('[data-import-errors-empty]');
    let importErrorsTrigger = null;

    // Stop the short attention pulse while retaining the unresolved error badge and accessible label.
    const stopImportAttention = () => {
        importErrorsButton?.classList.remove('is-attention');
    };

    // Clear stale client-visible results when the user chooses a different workbook for validation.
    const clearImportResults = () => {
        importErrorsButton?.classList.remove('has-errors', 'is-attention');
        importErrorsButton?.setAttribute('aria-label', 'Show Errors');

        if (importGeneralError) {
            importGeneralError.hidden = true;
        }

        if (importPreview) {
            importPreview.hidden = true;
        }

        importResultCategories.forEach((category) => {
            category.hidden = true;
        });

        if (importErrorsEmpty) {
            importErrorsEmpty.hidden = false;
        }
    };

    // Echo the selected Excel filename and discard result indicators tied to the previous selection.
    importInput?.addEventListener('change', () => {
        if (importName) {
            importName.textContent = importInput.files?.[0]?.name ?? 'No file selected';
        }

        clearImportResults();
    });

    // End the temporary animation after a short interval and immediately for reduced-motion users.
    if (importErrorsButton?.classList.contains('is-attention')) {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.setTimeout(stopImportAttention, prefersReducedMotion ? 0 : 2400);
    }

    // Close the validation modal and restore keyboard focus to the control that opened it.
    const closeImportErrors = () => {
        if (importErrorsDialog) {
            importErrorsDialog.hidden = true;
            importErrorsTrigger?.focus();
        }
    };

    // Open the modal, stop only the temporary pulse, and preserve unresolved error details and badge state.
    shell.querySelectorAll('[data-import-errors-open]').forEach((button) => {
        button.addEventListener('click', () => {
            importErrorsTrigger = button;
            stopImportAttention();
            importErrorsDialog.hidden = false;
            importErrorsPanel?.focus();
        });
    });

    // Register every visible modal close command against the shared focus-restoring close function.
    importErrorsDialog?.querySelectorAll('[data-import-errors-close]').forEach((button) => {
        button.addEventListener('click', closeImportErrors);
    });

    // Treat a click on the shaded backdrop as a modal close without clearing server results.
    importErrorsDialog?.addEventListener('click', (event) => {
        if (event.target === importErrorsDialog) {
            closeImportErrors();
        }
    });

    // Locate the shared restoration confirmation controls once for individual and bulk actions.
    const restoreDialog = shell.querySelector('[data-restore-dialog]');
    const restorePanel = restoreDialog?.querySelector('[role="dialog"]');
    const restoreForm = restoreDialog?.querySelector('[data-restore-form]');
    const restoreUserInput = restoreDialog?.querySelector('[data-restore-user-input]');
    const restoreTitle = restoreDialog?.querySelector('[data-restore-title]');
    const restoreMessage = restoreDialog?.querySelector('[data-restore-message]');
    const restoreSubmit = restoreDialog?.querySelector('[data-restore-submit]');
    let restoreTrigger = null;

    // Close the restoration dialog and return focus to the exact triggering account or bulk button.
    const closeRestoreDialog = () => {
        if (! restoreDialog) {
            return;
        }

        restoreDialog.hidden = true;
        restoreTrigger?.focus();
    };

    // Populate only display text and the preview-verified target ID before opening the confirmation.
    shell.querySelectorAll('[data-restore-confirm]').forEach((button) => {
        button.addEventListener('click', () => {
            const count = Number.parseInt(button.dataset.restoreCount ?? '1', 10);
            const isBulk = button.hasAttribute('data-restore-all');
            const userId = button.dataset.restoreUserId ?? '';
            const accountName = button.dataset.restoreAccountName ?? '';
            const accountLabel = count === 1 ? 'account' : 'accounts';
            restoreTrigger = button;
            restoreForm.action = button.dataset.restoreAction;
            restoreUserInput.value = userId;
            restoreUserInput.disabled = userId === '';
            restoreTitle.textContent = isBulk
                ? 'Restore Flagged Archived Accounts'
                : 'Restore Archived Account';
            restoreMessage.textContent = isBulk
                ? `You are about to restore ${count} archived ${accountLabel} identified in this import preview. The original accounts and their existing records will be preserved.`
                : `You are about to reactivate ${accountName || 'this archived account'}. The original account will be restored and no duplicate will be created.`;
            restoreSubmit.textContent = isBulk ? 'Restore Accounts' : 'Restore Account';
            restoreSubmit.disabled = false;
            restoreDialog.hidden = false;
            restorePanel?.focus();
        });
    });

    // Every cancel control shares the same focus-safe close behavior.
    restoreDialog?.querySelectorAll('[data-restore-cancel]').forEach((button) => {
        button.addEventListener('click', closeRestoreDialog);
    });

    // Clicking the shaded backdrop dismisses restoration without mutating the preview.
    restoreDialog?.addEventListener('click', (event) => {
        if (event.target === restoreDialog) {
            closeRestoreDialog();
        }
    });

    // Disable the final restoration command immediately to prevent rapid duplicate submissions.
    restoreForm?.addEventListener('submit', () => {
        restoreSubmit.disabled = true;
        restoreSubmit.textContent = 'Restoring...';
    });

    // Status changes require a final acknowledgement because deactivation immediately blocks sign-in.
    shell.querySelectorAll('[data-confirm-status]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (! window.confirm(form.dataset.confirmStatus)) {
                event.preventDefault();
            }
        });
    });

    // Require explicit confirmation before one managed account moves into soft-deleted archived records.
    shell.querySelectorAll('[data-confirm-account-archive]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (! window.confirm('Delete this account from active records and move it to the archive?')) {
                event.preventDefault();
            }
        });
    });
    shell.querySelectorAll('[data-confirm-option-status]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (! window.confirm(form.dataset.confirmOptionStatus)) {
                event.preventDefault();
            }
        });
    });

    const modeDialog = shell.querySelector('[data-account-mode-dialog]');
    const modePanel = modeDialog?.querySelector('[role="dialog"]');
    const modeLabel = modeDialog?.querySelector('[data-account-mode-label]');
    const individualLink = modeDialog?.querySelector('[data-account-individual-link]');
    const bulkLink = modeDialog?.querySelector('[data-account-bulk-link]');
    let modeTrigger = null;

    const closeModeDialog = () => {
        if (! modeDialog) {
            return;
        }

        modeDialog.hidden = true;
        modeTrigger?.focus();
    };

    shell.querySelectorAll('[data-account-mode-open]').forEach((button) => {
        button.addEventListener('click', () => {
            modeTrigger = button;
            modeLabel.textContent = button.dataset.accountLabel;
            individualLink.href = button.dataset.individualUrl;
            bulkLink.href = button.dataset.bulkUrl;
            modeDialog.hidden = false;
            modePanel?.focus();
        });
    });
    modeDialog?.querySelector('[data-account-mode-close]')?.addEventListener('click', closeModeDialog);
    modeDialog?.addEventListener('click', (event) => {
        if (event.target === modeDialog) {
            closeModeDialog();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modeDialog && ! modeDialog.hidden) {
            closeModeDialog();
        }
        if (event.key === 'Escape' && importErrorsDialog && ! importErrorsDialog.hidden) {
            closeImportErrors();
        }
        if (event.key === 'Escape' && restoreDialog && ! restoreDialog.hidden) {
            closeRestoreDialog();
        }
    });

    const massForm = shell.querySelector('[data-managed-mass-action]');
    const selectAll = massForm?.querySelector('[data-select-all-users]');
    const userCheckboxes = [...(massForm?.querySelectorAll('[data-select-user]') ?? [])];
    const actionSelect = massForm?.querySelector('[data-mass-action-select]');
    const actionValue = massForm?.querySelector('[data-mass-action-value]');

    selectAll?.addEventListener('change', () => {
        userCheckboxes.forEach((checkbox) => {
            checkbox.checked = selectAll.checked;
        });
    });

    massForm?.querySelectorAll('[data-mass-submit]').forEach((button) => {
        button.addEventListener('click', (event) => {
            const selectedAction = button.dataset.massSubmit === 'selected'
                ? actionSelect.value
                : button.dataset.massSubmit;
            const selectedCount = userCheckboxes.filter((checkbox) => checkbox.checked).length;

            if (! selectedAction) {
                event.preventDefault();
                actionSelect.focus();
                return;
            }

            if (selectedAction !== 'resend_all_pending' && selectedCount === 0) {
                event.preventDefault();
                window.alert('Select at least one account.');
                return;
            }

            const message = selectedAction === 'archive'
                ? `Remove ${selectedCount} selected accounts from active records?`
                : selectedAction === 'resend_all_pending'
                    ? 'Send a new setup link to every pending account in the current management scope?'
                    : `Apply this action to ${selectedCount} selected accounts?`;

            if (! window.confirm(message)) {
                event.preventDefault();
                return;
            }

            actionValue.value = selectedAction;
            actionSelect.required = false;
        });
    });

    shell.querySelectorAll('[data-confirm-import]').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelector('button[type="submit"]')?.setAttribute('disabled', 'disabled');
        });
    });

    shell.querySelectorAll('[data-confirm-username-change]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (! window.confirm('Generate a new username from the corrected identity and notify this user?')) {
                event.preventDefault();
            }
        });
    });
}

function initializeResearchTitleTooltips(shell) {
    const selector = '[data-research-title-tooltip], [data-table-tooltip]';
    const targets = [...shell.querySelectorAll(selector)];

    const tooltip = document.createElement('div');
    const tooltipId = 'dashboard-research-title-tooltip';
    let activeTarget = null;
    let pointerPosition = null;
    let showTimer = null;

    tooltip.id = tooltipId;
    tooltip.className = 'dashboard-title-tooltip';
    tooltip.setAttribute('role', 'tooltip');
    tooltip.hidden = true;
    document.body.append(tooltip);

    targets.forEach((target) => {
        if (! target.matches('a, button, input, select, textarea, [tabindex]')) {
            target.tabIndex = 0;
        }
    });

    const isTruncated = (target) => (
        target.scrollWidth > target.clientWidth + 1
        || target.scrollHeight > target.clientHeight + 1
    );

    // Positioning is clamped after measurement so long titles never escape the viewport.
    const positionTooltip = () => {
        if (! activeTarget || tooltip.hidden) {
            return;
        }

        const targetRect = activeTarget.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        const gap = 12;
        const preferredLeft = pointerPosition?.x ?? targetRect.left;
        const preferredTop = pointerPosition?.y ?? targetRect.bottom;
        const left = Math.min(
            Math.max(gap, preferredLeft + (pointerPosition ? gap : 0)),
            window.innerWidth - tooltipRect.width - gap,
        );
        let top = preferredTop + gap;

        if (top + tooltipRect.height > window.innerHeight - gap) {
            top = Math.max(gap, targetRect.top - tooltipRect.height - gap);
        }

        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${top}px`;
    };

    const hideTooltip = () => {
        window.clearTimeout(showTimer);
        showTimer = null;
        tooltip.hidden = true;

        if (activeTarget?.getAttribute('aria-describedby') === tooltipId) {
            activeTarget.removeAttribute('aria-describedby');
        }

        activeTarget = null;
        pointerPosition = null;
    };

    const scheduleTooltip = (target, position = null) => {
        hideTooltip();

        if (! isTruncated(target)) {
            return;
        }

        activeTarget = target;
        pointerPosition = position;
        showTimer = window.setTimeout(() => {
            tooltip.textContent = target.dataset.tableTooltip ?? target.dataset.fullTitle ?? target.textContent.trim();
            tooltip.hidden = false;
            target.setAttribute('aria-describedby', tooltipId);
            positionTooltip();
        }, 500);
    };

    // Delegation keeps the listener count fixed even when a paginated table contains many truncated cells.
    shell.addEventListener('pointerover', (event) => {
        const target = event.target.closest?.(selector);

        if (target && ! target.contains(event.relatedTarget)) {
            scheduleTooltip(target, { x: event.clientX, y: event.clientY });
        }
    });
    shell.addEventListener('pointermove', (event) => {
        if (! activeTarget) {
            return;
        }

        pointerPosition = { x: event.clientX, y: event.clientY };
        positionTooltip();
    });
    shell.addEventListener('pointerout', (event) => {
        const target = event.target.closest?.(selector);

        if (target && ! target.contains(event.relatedTarget)) {
            hideTooltip();
        }
    });
    shell.addEventListener('focusin', (event) => {
        const target = event.target.closest?.(selector);

        if (target) {
            scheduleTooltip(target);
        }
    });
    shell.addEventListener('focusout', (event) => {
        if (activeTarget?.contains(event.target)) {
            hideTooltip();
        }
    });
    shell.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hideTooltip();
        }
    });
    window.addEventListener('resize', positionTooltip, { passive: true });
    window.addEventListener('scroll', positionTooltip, { passive: true, capture: true });
}
