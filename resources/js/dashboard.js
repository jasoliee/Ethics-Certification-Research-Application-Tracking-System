export function initializeDashboard() {
    const shell = document.querySelector('[data-dashboard-shell]');

    if (! shell) {
        return;
    }

    shell.querySelectorAll('[data-disable-on-submit]').forEach((form) => {
        form.addEventListener('submit', () => {
            if (form.getAttribute('aria-busy') === 'true') {
                return;
            }

            form.setAttribute('aria-busy', 'true');
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = true;
                button.setAttribute('aria-disabled', 'true');
                const label = button.querySelector('span');
                if (label) {
                    label.dataset.originalLabel = label.textContent;
                    label.textContent = 'Processing...';
                }
            });
        });
    });

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
    initializeNotificationTools(shell);
    initializeOnboardingGuide(shell);
}

function initializeNotificationTools(shell) {
    const dialog = shell.querySelector('[data-notification-confirm-dialog]');

    if (! dialog) {
        return;
    }

    const panel = dialog.querySelector('[role="dialog"]');
    const title = dialog.querySelector('[data-notification-confirm-title]');
    const message = dialog.querySelector('[data-notification-confirm-message]');
    const confirmButton = dialog.querySelector('[data-notification-confirm-submit]');
    const confirmLabel = confirmButton?.querySelector('span');
    const focusableSelector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(',');
    let pendingForm = null;
    let returnFocus = null;

    const selectedActionConfiguration = (form) => {
        const actionField = form.elements.namedItem('action');
        const action = actionField instanceof HTMLSelectElement ? actionField.value : '';

        if (form.dataset.notificationConfirmMode === 'inbox-selected') {
            return action === 'delete' ? {
                title: 'Move Selected Notifications to Bin',
                message: 'Move the selected notifications to the Bin? You can restore them for seven days.',
                action: 'Move Selected',
                danger: false,
            } : null;
        }

        if (form.dataset.notificationConfirmMode === 'bin-selected') {
            if (action === 'restore') {
                return {
                    title: 'Restore Selected Notifications',
                    message: 'Restore the selected notifications to the inbox?',
                    action: 'Restore Selected',
                    danger: false,
                };
            }

            if (action === 'force_delete') {
                return {
                    title: 'Permanently Delete Selected Notifications? This cannot be undone.',
                    message: '',
                    action: 'Delete Selected',
                    danger: true,
                };
            }
        }

        return null;
    };

    const configurationFor = (form) => {
        if (form.dataset.notificationConfirmMode) {
            return selectedActionConfiguration(form);
        }

        return {
            title: form.dataset.confirmTitle ?? 'Confirm Notification Action',
            message: form.dataset.confirmMessage ?? 'Confirm this notification action.',
            action: form.dataset.confirmAction ?? 'Confirm',
            danger: Object.hasOwn(form.dataset, 'confirmDanger'),
        };
    };

    const focusableElements = () => [...(panel?.querySelectorAll(focusableSelector) ?? [])]
        .filter((element) => ! element.closest('[hidden]') && element.getAttribute('aria-hidden') !== 'true');

    const closeDialog = () => {
        if (confirmButton?.disabled) {
            return;
        }

        dialog.hidden = true;
        document.body.classList.remove('has-application-modal-open');
        pendingForm = null;
        returnFocus?.focus();
    };

    shell.querySelectorAll('form[data-notification-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.notificationConfirmationApproved === 'true') {
                delete form.dataset.notificationConfirmationApproved;

                return;
            }

            const configuration = configurationFor(form);
            if (! configuration) {
                return;
            }

            event.preventDefault();
            if (! form.reportValidity()) {
                return;
            }

            pendingForm = form;
            returnFocus = event.submitter instanceof HTMLElement ? event.submitter : document.activeElement;
            title.textContent = configuration.title;
            message.textContent = configuration.message;
            message.hidden = configuration.message.trim() === '';
            confirmLabel.textContent = configuration.action;
            confirmButton.classList.toggle('is-danger', configuration.danger);
            dialog.hidden = false;
            document.body.classList.add('has-application-modal-open');
            panel?.focus();
        });
    });

    dialog.querySelectorAll('[data-notification-confirm-close]').forEach((button) => {
        button.addEventListener('click', closeDialog);
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            closeDialog();
        }
    });
    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeDialog();

            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusable = focusableElements();
        const first = focusable[0];
        const last = focusable.at(-1);
        if (! first || ! last) {
            event.preventDefault();
            panel?.focus();
        } else if (event.shiftKey && (document.activeElement === first || document.activeElement === panel)) {
            event.preventDefault();
            last.focus();
        } else if (! event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
    confirmButton?.addEventListener('click', () => {
        const form = pendingForm;
        if (! form) {
            return;
        }

        confirmButton.disabled = true;
        confirmButton.setAttribute('aria-disabled', 'true');
        confirmLabel.textContent = 'Processing...';
        form.dataset.notificationConfirmationApproved = 'true';
        form.requestSubmit();
    });
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
        {
            dialog: shell.querySelector('[data-reviewer-worksheet-dialog]'),
            openSelector: '[data-reviewer-worksheet-open]',
            closeSelector: '[data-reviewer-worksheet-close]',
        },
        {
            dialog: shell.querySelector('[data-certificate-background-dialog]'),
            openSelector: '[data-certificate-background-open]',
            closeSelector: '[data-certificate-background-close]',
        },
        {
            dialog: shell.querySelector('[data-certificate-bulk-dialog]'),
            openSelector: '[data-certificate-bulk-open]',
            closeSelector: '[data-certificate-bulk-close]',
        },
    ];
    const reviewerWorksheetDialog = shell.querySelector('[data-reviewer-worksheet-dialog]');
    const modalFocusSelector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(',');
    const modalInertState = new Map();

    const focusableModalElements = (panel) => [...(panel?.querySelectorAll(modalFocusSelector) ?? [])]
        .filter((element) => ! element.closest('[hidden]') && element.getAttribute('aria-hidden') !== 'true');

    // Keep keyboard and scroll interaction inside the topmost dashboard modal.
    const syncModalEnvironment = () => {
        modalInertState.forEach((wasInert, element) => {
            element.inert = wasInert;
        });
        modalInertState.clear();

        const visibleDialogs = [...shell.querySelectorAll('.application-modal-backdrop:not([hidden])')];
        const activeDialog = visibleDialogs.at(-1);
        document.body.classList.toggle('has-application-modal-open', Boolean(activeDialog));

        [...(activeDialog?.parentElement?.children ?? [])].forEach((sibling) => {
            if (! (sibling instanceof HTMLElement) || sibling === activeDialog) {
                return;
            }

            modalInertState.set(sibling, sibling.inert);
            sibling.inert = true;
        });
    };

    const reviewerWorksheetAccordion = shell.querySelector('[data-reviewer-worksheet-accordion]');
    shell.querySelectorAll('[data-reviewer-form-dialog]').forEach((dialog) => {
        const formType = dialog.dataset.reviewerFormDialog;

        modalConfigurations.push({
            dialog,
            openSelector: `[data-reviewer-form-open="${formType}"]`,
            closeSelector: '[data-reviewer-form-close]',
            reviewerWorksheet: true,
        });
    });

    // Certification queue rows keep their forms server-rendered while opening one focused record at a time.
    shell.querySelectorAll('[data-certificate-application-dialog]').forEach((dialog) => {
        const applicationId = dialog.dataset.certificateApplicationDialog;

        modalConfigurations.push({
            dialog,
            openSelector: `[data-certificate-application-open="${applicationId}"]`,
            closeSelector: '[data-certificate-application-close]',
        });
    });

    // Initialize only modal configurations whose Blade dialog exists on the current page.
    modalConfigurations.forEach(({ dialog, openSelector, closeSelector, reviewerWorksheet = false }) => {
        // Pages without this dialog require no listeners or temporary modal state.
        if (! dialog) {
            return;
        }

        const panel = dialog.querySelector('[role="dialog"]');
        const trackedForm = reviewerWorksheet
            ? dialog.querySelector('[data-reviewer-worksheet-form]')
            : null;
        let returnFocus = null;
        let pristineFormState = '';

        const formState = () => {
            if (! trackedForm) {
                return '';
            }

            return new URLSearchParams(new FormData(trackedForm)).toString();
        };

        const refreshPristineFormState = () => {
            pristineFormState = formState();
        };

        const hasUnsavedFormChanges = () => trackedForm && formState() !== pristineFormState;

        // Closing a dashboard application dialog always returns the user to its originating command.
        const closeDialog = () => {
            if (trackedForm?.getAttribute('aria-busy') === 'true') {
                const feedback = trackedForm.querySelector('[data-reviewer-form-feedback]');
                if (feedback) {
                    feedback.textContent = 'Wait for the worksheet draft to finish saving before closing.';
                }
                panel?.focus();

                return false;
            }

            if (hasUnsavedFormChanges() && ! window.confirm('Discard unsaved worksheet changes?')) {
                panel?.focus();

                return false;
            }

            if (hasUnsavedFormChanges()) {
                trackedForm.reset();
                trackedForm.dispatchEvent(new CustomEvent('reviewer:form-reset'));
                refreshPristineFormState();
            }

            dialog.hidden = true;
            if (reviewerWorksheet && reviewerWorksheetDialog && returnFocus && reviewerWorksheetDialog.contains(returnFocus)) {
                reviewerWorksheetDialog.hidden = false;
                syncModalEnvironment();
                returnFocus.focus();
            } else {
                syncModalEnvironment();
                returnFocus?.focus();
            }

            return true;
        };

        // Each open command reveals its matching dialog and moves keyboard focus into the panel.
        shell.querySelectorAll(openSelector).forEach((button) => {
            button.addEventListener('click', () => {
                returnFocus = button;
                if (reviewerWorksheet && reviewerWorksheetDialog?.contains(button)) {
                    reviewerWorksheetDialog.hidden = true;
                }
                dialog.hidden = false;
                refreshPristineFormState();
                syncModalEnvironment();
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
            if (event.key === 'Tab') {
                const focusable = focusableModalElements(panel);
                const first = focusable[0];
                const last = focusable.at(-1);

                if (! first || ! last) {
                    event.preventDefault();
                    panel?.focus();

                    return;
                }

                if (event.shiftKey && (document.activeElement === first || document.activeElement === panel)) {
                    event.preventDefault();
                    last.focus();
                } else if (! event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }

                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeDialog();
            }
        });

        trackedForm?.addEventListener('reviewer:form-saved', refreshPristineFormState);
        trackedForm?.addEventListener('reviewer:form-completed', () => {
            refreshPristineFormState();
            closeDialog();
        });

        // Server validation reopens the relevant decision form without losing entered remarks.
        if (dialog.hasAttribute('data-open-on-load')) {
            dialog.hidden = false;
            refreshPristineFormState();
            syncModalEnvironment();
            panel?.focus();
        }
    });

    shell.querySelectorAll('[data-reviewer-worksheet-form]').forEach((form) => {
        const formType = form.dataset.reviewerFormType;
        const draftButton = form.querySelector('[data-reviewer-form-save-draft]');
        const completeButton = form.querySelector('[data-reviewer-form-submit-final]');
        const feedback = form.querySelector('[data-reviewer-form-feedback]');
        const progressBar = form.querySelector('[data-reviewer-form-progress-bar]');
        const formDialog = form.closest('[data-reviewer-form-dialog]');
        const formCloseButtons = [...(formDialog?.querySelectorAll('[data-reviewer-form-close]') ?? [])];
        const consentGate = form.querySelector('[data-reviewer-consent-gate]');
        const consentExplanation = form.querySelector('[data-reviewer-consent-explanation]');
        const consentExplanationInput = form.querySelector('[data-reviewer-consent-explanation-input]');
        const consentDependent = form.querySelector('[data-reviewer-consent-dependent]');
        let draftSaveInFlight = false;
        let worksheetControlsLocked = false;
        const worksheetControlStates = new Map();
        const worksheetState = () => new URLSearchParams(new FormData(form)).toString();
        let savedWorksheetState = worksheetState();

        const setFormFeedback = (message = '', state = '') => {
            if (! feedback) {
                return;
            }

            feedback.textContent = message;
            feedback.classList.toggle('is-success', state === 'success');
            feedback.classList.toggle('is-error', state === 'error');
        };

        const formProgress = () => {
            const questions = [...form.querySelectorAll('[data-reviewer-form-question]')];
            const selectedConsent = consentGate?.querySelector('input[name="consent_required"]:checked')?.value;
            const consentQuestionsNotApplicable = selectedConsent === '0';

            return {
                answered: consentQuestionsNotApplicable
                    ? questions.length
                    : questions.filter((question) => question.querySelector('input[type="radio"]:checked')).length,
                total: questions.length,
            };
        };

        const renderFormProgress = ({ answered, total }, includeWorksheetChooser = false) => {
            const progressTargets = includeWorksheetChooser
                ? shell.querySelectorAll(`[data-reviewer-form-progress="${formType}"]`)
                : form.querySelectorAll(`[data-reviewer-form-progress="${formType}"]`);

            progressTargets.forEach((target) => {
                target.textContent = `${answered} of ${total} items completed`;
            });

            if (progressBar) {
                progressBar.max = total;
                progressBar.value = answered;
                progressBar.textContent = `${answered} / ${total}`;
            }
        };

        const preserveSavedFormDefaults = () => {
            [...form.elements].forEach((control) => {
                if (control instanceof HTMLInputElement && ['checkbox', 'radio'].includes(control.type)) {
                    control.defaultChecked = control.checked;
                } else if (control instanceof HTMLInputElement || control instanceof HTMLTextAreaElement) {
                    control.defaultValue = control.value;
                } else if (control instanceof HTMLSelectElement) {
                    [...control.options].forEach((option) => {
                        option.defaultSelected = option.selected;
                    });
                }
            });
        };

        const setWorksheetControlsLocked = (locked) => {
            const controls = new Set([...form.elements, ...formCloseButtons]);

            if (locked) {
                worksheetControlStates.clear();
                controls.forEach((control) => {
                    if (! (control instanceof HTMLElement) || ! ('disabled' in control)) {
                        return;
                    }

                    worksheetControlStates.set(control, control.disabled);
                    control.disabled = true;
                });

                return;
            }

            worksheetControlStates.forEach((wasDisabled, control) => {
                if (control.isConnected) {
                    control.disabled = wasDisabled;
                }
            });
            worksheetControlStates.clear();
        };

        const syncConsentExplanation = () => {
            if (! consentGate || ! consentExplanation || ! consentExplanationInput) {
                return;
            }

            const selectedConsent = consentGate.querySelector('input[name="consent_required"]:checked')?.value;
            const consentIsRequired = selectedConsent === '1';
            const consentIsNotRequired = selectedConsent === '0';
            const canWrite = consentGate.dataset.reviewerConsentWritable === 'true';
            consentExplanation.hidden = ! consentIsNotRequired;
            consentExplanationInput.disabled = ! consentIsNotRequired || ! canWrite || form.getAttribute('aria-busy') === 'true';
            consentExplanationInput.required = consentIsNotRequired && canWrite;
            if (consentDependent) {
                consentDependent.hidden = ! consentIsRequired;
                consentDependent.querySelectorAll('input').forEach((input) => {
                    if (consentIsNotRequired && input.type === 'radio') {
                        input.checked = false;
                    }
                    input.disabled = ! consentIsRequired || ! canWrite || form.getAttribute('aria-busy') === 'true';
                });
            }
        };

        form.addEventListener('change', (event) => {
            if (event.target.matches?.('input[name="consent_required"]')) {
                syncConsentExplanation();
            }
            renderFormProgress(formProgress());
        });
        form.addEventListener('reviewer:form-reset', () => {
            syncConsentExplanation();
            renderFormProgress(formProgress());
        });
        syncConsentExplanation();

        form.addEventListener('submit', async (event) => {
            if (draftSaveInFlight) {
                event.preventDefault();

                return;
            }

            const intent = event.submitter === draftButton
                ? 'draft'
                : (event.submitter === completeButton ? 'submit' : null);
            if (! intent) {
                return;
            }

            event.preventDefault();
            const formData = new FormData(form);
            formData.set('intent', intent);
            draftSaveInFlight = true;
            form.setAttribute('aria-busy', 'true');
            setWorksheetControlsLocked(true);
            worksheetControlsLocked = true;
            setFormFeedback(intent === 'submit' ? 'Completing worksheet...' : 'Saving draft...');

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    const validationMessage = Object.values(payload.errors ?? {}).flat()[0];
                    throw new Error(validationMessage ?? payload.message ?? 'The worksheet draft could not be saved.');
                }

                const savedProgress = {
                    answered: Number(payload.data?.answered_items ?? formProgress().answered),
                    total: Number(payload.data?.total_items ?? formProgress().total),
                };
                const completed = ['completed', 'final'].includes(payload.data?.status);
                form.dataset.savedRecommendation = String(formData.get('recommendation') ?? '');
                form.dataset.formCompleted = String(completed);
                shell.querySelectorAll(`[data-reviewer-form-status="${formType}"]`).forEach((status) => {
                    [...status.classList]
                        .filter((className) => className.startsWith('tone-'))
                        .forEach((className) => status.classList.remove(className));
                    status.classList.add(completed ? 'tone-success' : 'tone-blue');
                    status.textContent = completed ? 'Completed' : 'In Progress';
                });
                shell.querySelectorAll(`[data-reviewer-form-open-label="${formType}"]`).forEach((label) => {
                    label.textContent = completed ? 'Edit' : 'Continue';
                });
                renderFormProgress(savedProgress, true);
                const completedTypes = new Set(
                    [...shell.querySelectorAll('[data-reviewer-form-status]')]
                        .filter((status) => ['Completed', 'Final', 'Submitted'].includes(status.textContent.trim()))
                        .map((status) => status.dataset.reviewerFormStatus),
                );
                const completedCount = completedTypes.size;
                shell.querySelectorAll('[data-reviewer-forms-summary]').forEach((summary) => {
                    summary.textContent = `${completedCount} / 2 completed`;
                    [...summary.classList]
                        .filter((className) => className.startsWith('tone-'))
                        .forEach((className) => summary.classList.remove(className));
                    summary.classList.add(completedCount === 2 ? 'tone-success' : 'tone-orange');
                });
                shell.querySelectorAll('[data-reviewer-submit-form-summary]').forEach((summary) => {
                    summary.textContent = `${completedCount} of 2 completed`;
                });
                const decisionForm = shell.querySelector('[data-reviewer-decision-form]');
                if (decisionForm) {
                    decisionForm.dataset.completedReviewerForms = String(completedCount);
                }
                form.removeAttribute('aria-busy');
                setWorksheetControlsLocked(false);
                worksheetControlsLocked = false;
                syncConsentExplanation();
                preserveSavedFormDefaults();
                // FormData omits disabled controls. Capture the saved state only
                // after restoring the worksheet controls so pagehide does not
                // mistake a completed worksheet for an unsaved draft.
                savedWorksheetState = worksheetState();
                form.dispatchEvent(new CustomEvent('reviewer:form-saved'));
                if (intent === 'submit' && completed) {
                    form.dispatchEvent(new CustomEvent('reviewer:form-completed'));
                } else {
                    setFormFeedback('Progress saved.', 'success');
                }
            } catch (error) {
                setFormFeedback(error.message || 'The worksheet draft could not be saved. Check your connection and try again.', 'error');
            } finally {
                draftSaveInFlight = false;
                form.removeAttribute('aria-busy');
                if (worksheetControlsLocked) {
                    setWorksheetControlsLocked(false);
                    worksheetControlsLocked = false;
                }
                syncConsentExplanation();
            }
        });

        window.addEventListener('pagehide', () => {
            if (draftSaveInFlight || worksheetState() === savedWorksheetState) return;
            const formData = new FormData(form);
            formData.set('intent', 'draft');
            fetch(form.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                keepalive: true,
            }).catch(() => {});
        });
    });

    // A requirement keeps all prior files and the current reviewer's comments together by selected version.
    shell.querySelectorAll('[data-reviewer-history-group]').forEach((group) => {
        const selector = group.querySelector('[data-reviewer-history-version-select]');
        const panels = [...group.querySelectorAll('[data-reviewer-history-version-panel]')];
        if (! selector || panels.length === 0) {
            return;
        }
        const syncVersion = () => {
            panels.forEach((panel) => {
                const active = panel.dataset.reviewerHistoryVersionPanel === selector.value;
                panel.hidden = ! active;
                panel.setAttribute('aria-hidden', String(! active));
            });
        };

        selector.addEventListener('input', syncVersion);
        selector.addEventListener('change', syncVersion);
        syncVersion();
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
                row?.dataset.reviewerInstitute,
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
        const reviewerStudio = reviewerCommentForm.closest('[data-reviewer-review-studio]');
        const documentInput = reviewerCommentForm.querySelector('[data-reviewer-comment-document]');
        const categoryInput = reviewerCommentForm.elements.namedItem('category');
        const bodyInput = reviewerCommentForm.elements.namedItem('body');
        const methodInput = reviewerCommentForm.querySelector('[data-reviewer-comment-method]');
        const submitButton = reviewerCommentForm.querySelector('[data-reviewer-comment-submit]');
        const submitLabel = reviewerCommentForm.querySelector('[data-reviewer-comment-submit-label]');
        const cancelButton = reviewerCommentForm.querySelector('[data-reviewer-comment-cancel]');
        const feedback = reviewerCommentForm.querySelector('[data-reviewer-comment-feedback]');
        const commentList = reviewerStudio?.querySelector('[data-reviewer-comment-list]');
        const emptyState = reviewerStudio?.querySelector('[data-reviewer-comment-empty]');
        const commentCount = reviewerStudio?.querySelector('[data-reviewer-comment-count] .dashboard-status-badge');
        const loadOlderCommentsButton = reviewerStudio?.querySelector('[data-reviewer-comments-load]');
        const loadOlderCommentsLabel = loadOlderCommentsButton?.querySelector('[data-reviewer-comments-load-label]');
        const commentsHistoryFeedback = reviewerStudio?.querySelector('[data-reviewer-comments-history-feedback]');
        const storeUrl = reviewerCommentForm.dataset.commentStoreUrl ?? reviewerCommentForm.action;
        let authoritativeCommentCount = Number.parseInt(commentCount?.dataset.reviewerCommentTotal ?? '0', 10);
        let openCommentMenu = null;
        let commentRequestInFlight = false;
        let olderCommentsRequestInFlight = false;
        const commentActionsInFlight = new Set();

        const closeCommentMenu = (restoreFocus = false) => {
            if (! openCommentMenu) {
                return;
            }

            const toggle = openCommentMenu.querySelector('[data-reviewer-comment-menu-toggle]');
            const popover = openCommentMenu.querySelector('[data-reviewer-comment-menu-popover]');
            if (popover) {
                popover.hidden = true;
                popover.style.removeProperty('inset');
            }
            toggle?.setAttribute('aria-expanded', 'false');
            openCommentMenu = null;
            if (restoreFocus) {
                toggle?.focus();
            }
        };

        const openMenu = (menu, focusFirst = false) => {
            closeCommentMenu();
            const toggle = menu.querySelector('[data-reviewer-comment-menu-toggle]');
            const popover = menu.querySelector('[data-reviewer-comment-menu-popover]');
            if (! toggle || ! popover) {
                return;
            }

            popover.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            openCommentMenu = menu;
            const toggleBounds = toggle.getBoundingClientRect();
            const popoverBounds = popover.getBoundingClientRect();
            const left = Math.max(12, Math.min(
                toggleBounds.right - popoverBounds.width,
                window.innerWidth - popoverBounds.width - 12,
            ));
            const opensAbove = toggleBounds.bottom + popoverBounds.height + 8 > window.innerHeight;
            const top = opensAbove
                ? Math.max(12, toggleBounds.top - popoverBounds.height - 6)
                : toggleBounds.bottom + 6;
            popover.style.inset = `${top}px auto auto ${left}px`;
            if (focusFirst) {
                popover.querySelector('[role="menuitem"]')?.focus();
            }
        };

        const setCommentFeedback = (message = '', state = '') => {
            if (! feedback) {
                return;
            }

            feedback.textContent = message;
            feedback.classList.toggle('is-error', state === 'error');
            feedback.classList.toggle('is-success', state === 'success');
        };

        const setCommentsHistoryFeedback = (message = '', state = '') => {
            if (! commentsHistoryFeedback) {
                return;
            }

            commentsHistoryFeedback.textContent = message;
            commentsHistoryFeedback.classList.toggle('is-error', state === 'error');
            commentsHistoryFeedback.classList.toggle('is-success', state === 'success');
        };

        const syncCommentCount = (count = null) => {
            const parsedCount = Number.parseInt(count, 10);

            if (Number.isFinite(parsedCount)) {
                authoritativeCommentCount = parsedCount;
            }

            if (commentCount) {
                commentCount.textContent = `${authoritativeCommentCount} recorded`;
                commentCount.dataset.reviewerCommentTotal = String(authoritativeCommentCount);
            }
            if (emptyState) {
                emptyState.hidden = authoritativeCommentCount > 0;
            }
        };

        const commentElementFromHtml = (html) => {
            const template = document.createElement('template');
            template.innerHTML = html.trim();

            return template.content.firstElementChild;
        };

        const resetCommentComposer = () => {
            reviewerCommentForm.reset();
            reviewerCommentForm.action = storeUrl;
            delete reviewerCommentForm.dataset.editingCommentId;
            if (methodInput) {
                methodInput.value = 'POST';
                methodInput.disabled = true;
            }
            if (submitLabel) {
                submitLabel.textContent = 'Add Comment';
            }
            if (cancelButton) {
                cancelButton.hidden = true;
            }
        };

        // Server-rendered fragments keep asynchronous comments escaped and identical to the no-JS fallback.
        const submitCommentRequest = async () => {
            if (commentRequestInFlight || ! reviewerCommentForm.reportValidity()) {
                return;
            }

            commentRequestInFlight = true;
            submitButton?.setAttribute('disabled', 'disabled');
            reviewerCommentForm.setAttribute('aria-busy', 'true');
            setCommentFeedback(reviewerCommentForm.dataset.editingCommentId ? 'Saving changes...' : 'Adding comment...');

            try {
                const response = await fetch(reviewerCommentForm.action, {
                    method: 'POST',
                    body: new FormData(reviewerCommentForm),
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    const validationMessage = Object.values(payload.errors ?? {}).flat()[0];
                    throw new Error(validationMessage ?? payload.message ?? 'The comment could not be saved.');
                }

                const nextComment = commentElementFromHtml(payload.data?.html ?? '');
                if (! nextComment || ! commentList) {
                    throw new Error('The saved comment could not be displayed. Refresh to see the latest version.');
                }

                const editingId = reviewerCommentForm.dataset.editingCommentId;
                const existingComment = editingId
                    ? commentList.querySelector(`[data-reviewer-comment-item][data-comment-id="${editingId}"]`)
                    : null;
                if (existingComment) {
                    existingComment.replaceWith(nextComment);
                } else {
                    commentList.prepend(nextComment);
                }

                resetCommentComposer();
                syncCommentCount(payload.data?.count);
                setCommentFeedback(existingComment ? 'Comment updated.' : 'Comment added.', 'success');
            } catch (error) {
                setCommentFeedback(error.message || 'Comment failed. Check your connection and try again.', 'error');
            } finally {
                commentRequestInFlight = false;
                reviewerCommentForm.removeAttribute('aria-busy');
                submitButton?.removeAttribute('disabled');
            }
        };

        reviewerCommentForm.addEventListener('submit', (event) => {
            event.preventDefault();
            submitCommentRequest();
        });
        cancelButton?.addEventListener('click', () => {
            resetCommentComposer();
            setCommentFeedback('Edit cancelled.');
        });

        commentList?.addEventListener('click', (event) => {
            const menuToggle = event.target.closest?.('[data-reviewer-comment-menu-toggle]');
            if (menuToggle) {
                const menu = menuToggle.closest('[data-reviewer-comment-menu]');
                if (! menu) {
                    return;
                }

                if (openCommentMenu === menu) {
                    closeCommentMenu(true);
                } else {
                    openMenu(menu);
                }

                return;
            }

            const editButton = event.target.closest?.('[data-reviewer-comment-edit]');
            const comment = editButton?.closest('[data-reviewer-comment-item]');

            if (! comment) {
                return;
            }

            if (commentActionsInFlight.has(comment.dataset.commentId)) {
                return;
            }

            reviewerCommentForm.action = comment.dataset.commentUpdateUrl;
            reviewerCommentForm.dataset.editingCommentId = comment.dataset.commentId;
            if (methodInput) {
                methodInput.value = 'PUT';
                methodInput.disabled = false;
            }
            if (categoryInput) {
                categoryInput.value = comment.dataset.commentCategory;
            }
            if (documentInput) {
                documentInput.value = comment.dataset.commentDocumentId ?? '';
            }
            if (bodyInput) {
                bodyInput.value = comment.dataset.commentBody ?? '';
            }
            if (submitLabel) {
                submitLabel.textContent = 'Save Comment';
            }
            if (cancelButton) {
                cancelButton.hidden = false;
            }
            closeCommentMenu();
            setCommentFeedback('Editing comment.');
            bodyInput?.focus();
        });

        commentList?.addEventListener('submit', async (event) => {
            const actionForm = event.target.closest?.('[data-reviewer-comment-action-form]');
            const comment = actionForm?.closest('[data-reviewer-comment-item]');
            if (! actionForm || ! comment) {
                return;
            }

            event.preventDefault();
            const action = actionForm.dataset.reviewerCommentActionForm;
            const commentId = comment.dataset.commentId;

            if (commentActionsInFlight.has(commentId)) {
                return;
            }

            closeCommentMenu();
            if (action === 'delete' && ! window.confirm('Delete this review comment? This action cannot be undone.')) {
                return;
            }

            commentActionsInFlight.add(commentId);
            comment.setAttribute('aria-busy', 'true');
            const actionButtons = [...comment.querySelectorAll('button')];
            actionButtons.forEach((button) => button.setAttribute('disabled', 'disabled'));
            setCommentFeedback(action === 'delete' ? 'Removing comment...' : 'Updating comment...');

            try {
                const response = await fetch(actionForm.action, {
                    method: 'POST',
                    body: new FormData(actionForm),
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    throw new Error(payload.message ?? 'The comment action could not be completed.');
                }

                if (action === 'delete') {
                    if (reviewerCommentForm.dataset.editingCommentId === comment.dataset.commentId) {
                        resetCommentComposer();
                    }
                    comment.remove();
                    syncCommentCount(payload.data?.count);
                    setCommentFeedback('Comment removed.', 'success');
                } else {
                    const nextComment = commentElementFromHtml(payload.data?.html ?? '');
                    if (! nextComment) {
                        throw new Error('The updated comment could not be displayed.');
                    }
                    comment.replaceWith(nextComment);
                    syncCommentCount(payload.data?.count);
                    setCommentFeedback(payload.data?.status === 'resolved' ? 'Comment resolved.' : 'Comment reopened.', 'success');
                }
            } catch (error) {
                setCommentFeedback(error.message || 'The comment action failed. Try again.', 'error');
            } finally {
                commentActionsInFlight.delete(commentId);
                if (comment.isConnected) {
                    comment.removeAttribute('aria-busy');
                    actionButtons.forEach((button) => button.removeAttribute('disabled'));
                }
            }
        });

        const loadOlderComments = async () => {
            const beforeId = loadOlderCommentsButton?.dataset.beforeId;
            const commentsUrl = loadOlderCommentsButton?.dataset.commentsUrl;

            if (olderCommentsRequestInFlight || ! loadOlderCommentsButton || ! beforeId || ! commentsUrl) {
                return;
            }

            olderCommentsRequestInFlight = true;
            loadOlderCommentsButton.setAttribute('disabled', 'disabled');
            loadOlderCommentsButton.setAttribute('aria-busy', 'true');
            commentList?.setAttribute('aria-busy', 'true');
            if (loadOlderCommentsLabel) {
                loadOlderCommentsLabel.textContent = 'Loading Older Comments...';
            }
            setCommentsHistoryFeedback('Loading older comments...');

            try {
                const url = new URL(commentsUrl, window.location.href);
                url.searchParams.set('before_id', beforeId);
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    const validationMessage = Object.values(payload.errors ?? {}).flat()[0];
                    throw new Error(validationMessage ?? payload.message ?? 'Older comments could not be loaded.');
                }

                const items = Array.isArray(payload.data?.items) ? payload.data.items : [];
                let appendedCount = 0;

                items.forEach((item) => {
                    if (! commentList || commentList.querySelector(`[data-reviewer-comment-item][data-comment-id="${item.id}"]`)) {
                        return;
                    }

                    const nextComment = commentElementFromHtml(item.html ?? '');
                    if (! nextComment) {
                        return;
                    }

                    commentList.insertBefore(nextComment, emptyState ?? null);
                    appendedCount += 1;
                });

                const nextBeforeId = payload.data?.next_before_id;
                const hasMore = Boolean(payload.data?.has_more && nextBeforeId);
                loadOlderCommentsButton.dataset.beforeId = hasMore ? String(nextBeforeId) : '';
                loadOlderCommentsButton.hidden = ! hasMore;
                syncCommentCount(payload.data?.count);
                setCommentsHistoryFeedback(
                    appendedCount > 0
                        ? `${appendedCount} older ${appendedCount === 1 ? 'comment' : 'comments'} loaded.`
                        : 'No older comments remain.',
                    'success',
                );
            } catch (error) {
                setCommentsHistoryFeedback(error.message || 'Older comments could not be loaded. Try again.', 'error');
            } finally {
                olderCommentsRequestInFlight = false;
                loadOlderCommentsButton.removeAttribute('aria-busy');
                commentList?.setAttribute('aria-busy', 'false');
                if (! loadOlderCommentsButton.hidden) {
                    loadOlderCommentsButton.removeAttribute('disabled');
                }
                if (loadOlderCommentsLabel) {
                    loadOlderCommentsLabel.textContent = 'Load Older Comments';
                }
            }
        };

        loadOlderCommentsButton?.addEventListener('click', loadOlderComments);

        const documentChoices = [...(reviewerStudio?.querySelectorAll('[data-reviewer-document-choice]') ?? [])];
        const documentFrameShell = reviewerStudio?.querySelector('[data-reviewer-document-frame-shell]');
        let documentFrame = reviewerStudio?.querySelector('[data-reviewer-document-frame]');
        const documentLoading = reviewerStudio?.querySelector('[data-reviewer-document-loading]');
        const documentTitle = reviewerStudio?.querySelector('[data-reviewer-document-title]');
        const documentMeta = reviewerStudio?.querySelector('[data-reviewer-document-meta]');
        const documentOpenTab = reviewerStudio?.querySelector('[data-reviewer-document-open-tab]');
        const documentDownload = reviewerStudio?.querySelector('[data-reviewer-document-download]');
        let documentPreviewRequestId = 0;

        const settleDocumentPreview = (requestId, failed = false) => {
            if (requestId !== documentPreviewRequestId) {
                return;
            }

            documentFrameShell?.setAttribute('aria-busy', 'false');
            if (! documentLoading) {
                return;
            }

            if (failed) {
                documentLoading.textContent = 'Preview could not be loaded. Use Open or Download to continue.';
                documentLoading.hidden = false;
            } else {
                documentLoading.hidden = true;
            }
        };

        const bindDocumentFrameLoad = (frame, requestId) => {
            let settled = false;
            const settle = (failed) => {
                if (settled) {
                    return;
                }

                settled = true;
                settleDocumentPreview(requestId, failed);
            };

            frame.addEventListener('load', () => settle(false), { once: true });
            frame.addEventListener('error', () => settle(true), { once: true });
        };

        const replaceDocumentFrame = (previewUrl, title, requestId) => {
            if (! documentFrame) {
                return;
            }

            // Replacing also covers an initial iframe whose load completed before dashboard startup.
            const nextFrame = documentFrame.cloneNode(false);
            nextFrame.removeAttribute('src');
            nextFrame.loading = 'eager';
            nextFrame.title = title;
            nextFrame.dataset.reviewerDocumentFrame = '';
            bindDocumentFrameLoad(nextFrame, requestId);
            nextFrame.src = previewUrl;
            documentFrame.replaceWith(nextFrame);
            documentFrame = nextFrame;
        };

        if (documentFrame) {
            replaceDocumentFrame(documentFrame.src, documentFrame.title, documentPreviewRequestId);
        }

        const selectReviewDocument = (choice) => {
            documentChoices.forEach((candidate) => {
                const selected = candidate === choice;
                candidate.classList.toggle('is-active', selected);
                candidate.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
            if (documentTitle) {
                documentTitle.textContent = choice.dataset.documentRequirement;
            }
            if (documentMeta) {
                documentMeta.textContent = `${choice.dataset.documentName} - ${choice.dataset.documentKind} - ${choice.dataset.documentVersion}`;
            }
            if (documentOpenTab) {
                documentOpenTab.href = choice.dataset.documentPreviewUrl;
                documentOpenTab.hidden = false;
            }
            if (documentDownload) {
                documentDownload.href = choice.dataset.documentDownloadUrl;
                documentDownload.hidden = false;
            }
            if (documentFrame && documentFrameShell) {
                documentPreviewRequestId += 1;
                documentFrameShell.setAttribute('aria-busy', 'true');
                if (documentLoading) {
                    documentLoading.textContent = 'Loading secure preview...';
                    documentLoading.hidden = false;
                }

                replaceDocumentFrame(
                    choice.dataset.documentPreviewUrl,
                    `Preview of ${choice.dataset.documentName}`,
                    documentPreviewRequestId,
                );
            }
            if (documentInput && ! documentInput.disabled) {
                documentInput.value = choice.dataset.documentId;
                setCommentFeedback(`Comment reference set to ${choice.dataset.documentRequirement}.`);
            }
        };

        documentChoices.forEach((choice) => {
            choice.addEventListener('click', () => selectReviewDocument(choice));
        });

        syncCommentCount();

        commentList?.addEventListener('keydown', (event) => {
            const toggle = event.target.closest?.('[data-reviewer-comment-menu-toggle]');
            if (toggle && ['ArrowDown', 'Enter', ' '].includes(event.key)) {
                event.preventDefault();
                const menu = toggle.closest('[data-reviewer-comment-menu]');
                if (menu) {
                    openMenu(menu, true);
                }

                return;
            }

            if (! openCommentMenu) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeCommentMenu(true);

                return;
            }

            const menuItems = [...openCommentMenu.querySelectorAll('[role="menuitem"]:not(:disabled)')];
            const currentIndex = menuItems.indexOf(document.activeElement);
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                menuItems[(currentIndex + 1) % menuItems.length]?.focus();
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                menuItems[(currentIndex - 1 + menuItems.length) % menuItems.length]?.focus();
            } else if (event.key === 'Home') {
                event.preventDefault();
                menuItems[0]?.focus();
            } else if (event.key === 'End') {
                event.preventDefault();
                menuItems.at(-1)?.focus();
            }
        });

        document.addEventListener('click', (event) => {
            if (openCommentMenu && ! openCommentMenu.contains(event.target)) {
                closeCommentMenu();
            }
        });
        window.addEventListener('scroll', () => closeCommentMenu(), { capture: true, passive: true });
        window.addEventListener('resize', () => closeCommentMenu(), { passive: true });
    }

    // Populate the secure viewer only with controller URLs already rendered into the triggering row.
    const documentDialog = shell.querySelector('[data-document-dialog]');
    const documentPanel = documentDialog?.querySelector('[role="dialog"]');
    const documentTitle = documentDialog?.querySelector('[data-document-title]');
    const documentMeta = documentDialog?.querySelector('[data-document-meta]');
    const documentFrame = documentDialog?.querySelector('[data-document-frame]');
    const documentImage = documentDialog?.querySelector('[data-document-image]');
    const documentFallback = documentDialog?.querySelector('[data-document-fallback]');
    const documentPreview = documentDialog?.querySelector('[data-document-preview]');
    const documentToolbar = documentDialog?.querySelector('[data-document-toolbar]');
    const documentRenderControls = documentDialog?.querySelectorAll([
        '[data-document-zoom-out]',
        '[data-document-zoom]',
        '[data-document-zoom-in]',
        '[data-document-fit-width]',
        '[data-document-fit-page]',
        '[data-document-reset]',
        '[data-document-rotate]',
    ].join(',')) ?? [];
    const documentZoom = documentDialog?.querySelector('[data-document-zoom]');
    const documentOpenTab = documentDialog?.querySelector('[data-document-open-tab]');
    const documentRotate = documentDialog?.querySelector('[data-document-rotate]');
    const documentDownload = documentDialog?.querySelector('[data-document-download]');
    const documentReplace = documentDialog?.querySelector('[data-document-replace]');
    let documentTrigger = null;
    let documentReplaceInput = null;
    let documentPreviewUrl = '';
    let documentPreviewKind = 'download';
    let documentZoomPercent = 100;
    let documentRotation = 0;

    const renderDocumentView = (fitMode = null) => {
        if (documentZoom) {
            documentZoom.textContent = `${documentZoomPercent}%`;
        }

        if (documentPreviewKind === 'pdf' && documentFrame) {
            const fragment = fitMode === 'width'
                ? 'zoom=page-width'
                : fitMode === 'page' ? 'zoom=page-fit' : `zoom=${documentZoomPercent}`;
            documentFrame.src = `${documentPreviewUrl}#${fragment}`;
        }

        if (documentPreviewKind === 'image' && documentImage) {
            documentImage.style.width = `${documentZoomPercent}%`;
            documentImage.style.transform = `rotate(${documentRotation}deg)`;
        }
    };

    // Clearing the iframe on close stops private document rendering and avoids retaining an obsolete URL.
    const closeDocumentDialog = () => {
        if (! documentDialog) {
            return;
        }

        documentDialog.hidden = true;
        documentFrame?.removeAttribute('src');
        documentImage?.removeAttribute('src');
        documentReplaceInput = null;
        documentTrigger?.focus();
    };

    const openDocumentDialog = (button) => {
        const previewUrl = button.dataset.documentPreviewUrl ?? '';
        const declaredPreviewKind = button.dataset.documentPreviewKind ?? 'download';
        // Legacy rows report every non-browser-native format as download-only. If the
        // server supplied an authorized preview URL, keep that document inside the
        // private first-party Office/fallback frame instead of bypassing the viewer.
        const previewKind = ['pdf', 'image', 'office'].includes(declaredPreviewKind)
            ? declaredPreviewKind
            : (previewUrl === '' ? 'download' : 'office');
        documentTrigger = button;
        documentPreviewUrl = previewUrl;
        documentPreviewKind = previewUrl === '' ? 'download' : previewKind;
        documentZoomPercent = 100;
        documentRotation = 0;

        if (documentTitle) {
            const name = button.dataset.documentName ?? 'Document';
            documentTitle.textContent = name;
            documentTitle.dataset.tableTooltip = name;
        }

        if (documentMeta) {
            const documentType = button.dataset.documentType ?? '';
            const metadata = button.dataset.documentMeta ?? 'Selected requirement document';
            documentMeta.textContent = documentType === '' ? metadata : `${documentType} · ${metadata}`;
        }

        if (documentDownload) {
            documentDownload.href = button.dataset.documentDownloadUrl;
        }
        if (documentOpenTab) {
            documentOpenTab.href = previewUrl;
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
            const usesFrame = ['pdf', 'office'].includes(documentPreviewKind);
            documentFrame.hidden = ! usesFrame;
            ! usesFrame
                ? documentFrame.removeAttribute('src')
                : documentFrame.setAttribute(
                    'src',
                    documentPreviewKind === 'pdf' ? `${previewUrl}#zoom=100` : previewUrl,
                );
        }

        if (documentImage) {
            documentImage.hidden = documentPreviewKind !== 'image';
            documentPreviewKind !== 'image'
                ? documentImage.removeAttribute('src')
                : documentImage.setAttribute('src', previewUrl);
        }

        if (documentFallback) {
            documentFallback.hidden = documentPreviewKind !== 'download';
        }
        if (documentToolbar) {
            documentToolbar.hidden = documentPreviewKind === 'download';
        }
        documentRenderControls.forEach((control) => {
            control.hidden = documentPreviewKind === 'office';
        });
        if (documentRotate) {
            documentRotate.hidden = documentPreviewKind !== 'image';
        }

        documentDialog.hidden = false;
        documentPanel?.focus();
    };

    documentDialog?.querySelector('[data-document-zoom-out]')?.addEventListener('click', () => {
        documentZoomPercent = Math.max(25, documentZoomPercent - 25);
        renderDocumentView();
    });
    documentDialog?.querySelector('[data-document-zoom-in]')?.addEventListener('click', () => {
        documentZoomPercent = Math.min(200, documentZoomPercent + 25);
        renderDocumentView();
    });
    documentDialog?.querySelector('[data-document-reset]')?.addEventListener('click', () => {
        documentZoomPercent = 100;
        documentRotation = 0;
        renderDocumentView();
    });
    documentDialog?.querySelector('[data-document-fit-width]')?.addEventListener('click', () => {
        if (documentPreviewKind === 'image' && documentImage?.naturalWidth && documentPreview) {
            documentZoomPercent = Math.max(25, Math.min(200, Math.floor((documentPreview.clientWidth / documentImage.naturalWidth) * 100)));
            renderDocumentView();
            return;
        }
        renderDocumentView('width');
    });
    documentDialog?.querySelector('[data-document-fit-page]')?.addEventListener('click', () => {
        if (documentPreviewKind === 'image' && documentImage?.naturalWidth && documentImage?.naturalHeight && documentPreview) {
            documentZoomPercent = Math.max(25, Math.min(200, Math.floor(Math.min(
                documentPreview.clientWidth / documentImage.naturalWidth,
                documentPreview.clientHeight / documentImage.naturalHeight,
            ) * 100)));
            renderDocumentView();
            return;
        }
        renderDocumentView('page');
    });
    documentRotate?.addEventListener('click', () => {
        documentRotation = (documentRotation + 90) % 360;
        renderDocumentView();
    });
    documentDialog?.querySelector('[data-document-fullscreen]')?.addEventListener('click', () => {
        documentPreview?.requestFullscreen?.();
    });

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

    const reviewerDecisionForm = shell.querySelector('[data-reviewer-decision-form]');

    if (reviewerDecisionForm) {
        const submitDialog = shell.querySelector('[data-reviewer-submit-dialog]');
        const submitPanel = submitDialog?.querySelector('[data-reviewer-submit-panel]');
        const confirmationState = submitDialog?.querySelector('[data-reviewer-submit-confirmation]');
        const resultState = submitDialog?.querySelector('[data-reviewer-submit-result]');
        const cancelButtons = [...(submitDialog?.querySelectorAll('[data-reviewer-submit-cancel]') ?? [])];
        const closeButton = submitDialog?.querySelector('.application-modal-close[data-reviewer-submit-cancel]');
        const confirmButton = submitDialog?.querySelector('[data-reviewer-submit-confirm]');
        const confirmLabel = submitDialog?.querySelector('[data-reviewer-submit-confirm-label]');
        const dialogFeedback = submitDialog?.querySelector('[data-reviewer-submit-feedback]');
        const formFeedback = reviewerDecisionForm.querySelector('[data-reviewer-decision-feedback]');
        const decisionField = reviewerDecisionForm.querySelector('[name="decision"]');
        const commentField = reviewerDecisionForm.querySelector('[name="decision_comment"]');
        const confirmationDecision = submitDialog?.querySelector('[data-reviewer-submit-decision]');
        const resultDecision = submitDialog?.querySelector('[data-reviewer-submit-result-decision]');
        const resultTime = submitDialog?.querySelector('[data-reviewer-submit-result-time]');
        const resultMessage = submitDialog?.querySelector('[data-reviewer-submit-result-message]');
        const resultLink = submitDialog?.querySelector('[data-reviewer-submit-result-link]');
        const completedForms = () => Number.parseInt(reviewerDecisionForm.dataset.completedReviewerForms ?? '0', 10);
        const requiredForms = Number.parseInt(reviewerDecisionForm.dataset.requiredReviewerForms ?? '2', 10);
        let returnFocus = null;
        let finalSubmissionInFlight = false;
        let finalSubmissionSucceeded = false;
        let decisionDraftDirty = false;

        const selectedDecisionLabel = () => decisionField?.selectedOptions?.[0]?.textContent?.trim() ?? '';

        const setDecisionFeedback = (message = '') => {
            if (formFeedback) {
                if (message === 'Add at least one actionable Required Revision comment before submitting a revision decision.') {
                    formFeedback.replaceChildren(
                        document.createTextNode('Add at least one actionable '),
                        Object.assign(document.createElement('strong'), {textContent: 'Required Revision comment'}),
                        document.createTextNode(' before submitting a revision decision.'),
                    );
                } else {
                    formFeedback.textContent = message;
                }
                formFeedback.classList.toggle('is-error', Boolean(message));
            }
        };

        const setSubmitDialogFeedback = (message = '') => {
            if (dialogFeedback) {
                dialogFeedback.textContent = message;
                dialogFeedback.classList.toggle('is-error', Boolean(message));
            }
        };

        const validateFinalReview = () => {
            decisionField?.removeAttribute('aria-invalid');
            commentField?.removeAttribute('aria-invalid');
            setDecisionFeedback();

            const completedFormCount = completedForms();
            if (completedFormCount < requiredForms) {
                setDecisionFeedback(`Complete both required worksheets before submitting the final review (${completedFormCount} of ${requiredForms} completed).`);
                if (reviewerWorksheetAccordion) {
                    reviewerWorksheetAccordion.open = true;
                }
                shell.querySelector('[data-reviewer-form-open]')?.focus();

                return false;
            }

            if (! decisionField?.value) {
                decisionField?.setAttribute('aria-invalid', 'true');
                setDecisionFeedback('Select a final decision before continuing.');
                decisionField?.focus();

                return false;
            }

            const revisionWorksheetConflict = [...shell.querySelectorAll('[data-reviewer-worksheet-form]')]
                .some((form) => form.dataset.formCompleted === 'true'
                    && ['minor_revision', 'major_revision'].includes(form.dataset.savedRecommendation));
            if (decisionField.value === 'approved' && revisionWorksheetConflict) {
                decisionField.setAttribute('aria-invalid', 'true');
                setDecisionFeedback('Approval cannot be submitted while a completed worksheet recommends a revision. Align the final decision with the worksheets.');
                decisionField.focus();

                return false;
            }

            if ((commentField?.value.trim().length ?? 0) < 5) {
                commentField?.setAttribute('aria-invalid', 'true');
                setDecisionFeedback('Enter a decision comment of at least 5 characters before continuing.');
                commentField?.focus();

                return false;
            }

            if (! reviewerDecisionForm.reportValidity()) {
                return false;
            }

            return true;
        };

        const closeSubmitDialog = () => {
            if (! submitDialog || finalSubmissionInFlight) {
                if (finalSubmissionInFlight) {
                    setSubmitDialogFeedback('Wait for the final review submission to finish.');
                    submitPanel?.focus();
                }

                return;
            }

            if (finalSubmissionSucceeded) {
                window.location.assign(resultLink?.href ?? reviewerDecisionForm.dataset.reviewerResultUrl);

                return;
            }

            submitDialog.hidden = true;
            syncModalEnvironment();
            returnFocus?.focus();
        };

        const openSubmitDialog = (trigger) => {
            if (! submitDialog || ! validateFinalReview()) {
                return;
            }

            returnFocus = trigger;
            finalSubmissionSucceeded = false;
            closeButton?.setAttribute('aria-label', 'Cancel final review submission');
            if (confirmationDecision) {
                confirmationDecision.textContent = selectedDecisionLabel();
            }
            if (confirmationState) {
                confirmationState.hidden = false;
            }
            if (resultState) {
                resultState.hidden = true;
            }
            submitPanel?.setAttribute('aria-labelledby', 'reviewer-submit-title');
            submitPanel?.setAttribute('aria-describedby', 'reviewer-submit-description reviewer-submit-warning');
            setSubmitDialogFeedback();
            submitDialog.hidden = false;
            syncModalEnvironment();
            submitPanel?.focus();
        };

        reviewerDecisionForm.addEventListener('input', (event) => {
            decisionDraftDirty = true;
            if (event.target === decisionField || event.target === commentField) {
                event.target.removeAttribute('aria-invalid');
                setDecisionFeedback();
            }
        });

        reviewerDecisionForm.addEventListener('change', (event) => {
            decisionDraftDirty = true;
            if (event.target === decisionField) {
                decisionField.removeAttribute('aria-invalid');
                setDecisionFeedback();
            }
        });

        reviewerDecisionForm.addEventListener('submit', (event) => {
            if (event.submitter?.value === 'draft') {
                decisionDraftDirty = false;
            }
            if (event.submitter?.value !== 'submit') {
                return;
            }

            event.preventDefault();
            if (! finalSubmissionInFlight) {
                openSubmitDialog(event.submitter);
            }
        });

        window.addEventListener('pagehide', () => {
            if (! decisionDraftDirty || finalSubmissionInFlight || finalSubmissionSucceeded) return;
            const formData = new FormData(reviewerDecisionForm);
            formData.set('intent', 'draft');
            fetch(reviewerDecisionForm.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                keepalive: true,
            }).catch(() => {});
        });

        cancelButtons.forEach((button) => {
            button.addEventListener('click', closeSubmitDialog);
        });

        submitDialog?.addEventListener('click', (event) => {
            if (event.target === submitDialog) {
                closeSubmitDialog();
            }
        });

        submitDialog?.addEventListener('keydown', (event) => {
            if (event.key === 'Tab') {
                const focusable = focusableModalElements(submitPanel);
                const first = focusable[0];
                const last = focusable.at(-1);

                if (! first || ! last) {
                    event.preventDefault();
                    submitPanel?.focus();

                    return;
                }

                if (event.shiftKey && (document.activeElement === first || document.activeElement === submitPanel)) {
                    event.preventDefault();
                    last.focus();
                } else if (! event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }

                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeSubmitDialog();
            }
        });

        confirmButton?.addEventListener('click', async () => {
            if (finalSubmissionInFlight || ! validateFinalReview()) {
                return;
            }

            const formData = new FormData(reviewerDecisionForm);
            formData.set('intent', 'submit');
            finalSubmissionInFlight = true;
            submitPanel?.setAttribute('aria-busy', 'true');
            confirmButton.disabled = true;
            cancelButtons.forEach((button) => {
                button.disabled = true;
            });
            if (confirmLabel) {
                confirmLabel.textContent = 'Submitting Review...';
            }
            setSubmitDialogFeedback('Submitting your final review...');

            try {
                const response = await fetch(reviewerDecisionForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await parseJson(response);

                if (! response.ok) {
                    const validationMessage = Object.values(payload.errors ?? {}).flat()[0];
                    throw new Error(validationMessage ?? payload.message ?? 'The final review could not be submitted.');
                }

                finalSubmissionSucceeded = true;
                closeButton?.setAttribute('aria-label', 'Return to assignment');
                const decisionLabel = payload.data?.decision_label ?? selectedDecisionLabel();
                const submittedAt = payload.data?.submitted_at ? new Date(payload.data.submitted_at) : new Date();
                if (resultDecision) {
                    resultDecision.textContent = decisionLabel;
                }
                if (resultTime) {
                    resultTime.textContent = payload.data?.submitted_at_label ?? (Number.isNaN(submittedAt.getTime())
                        ? 'Recorded successfully'
                        : submittedAt.toLocaleString());
                }
                if (resultMessage && payload.data?.message) {
                    resultMessage.textContent = payload.data.message;
                }
                if (resultLink && payload.data?.redirect_url) {
                    const redirectUrl = new URL(payload.data.redirect_url, window.location.origin);
                    if (redirectUrl.origin === window.location.origin) {
                        resultLink.href = redirectUrl.href;
                    }
                }
                setSubmitDialogFeedback();
                if (confirmationState) {
                    confirmationState.hidden = true;
                }
                if (resultState) {
                    resultState.hidden = false;
                }
                submitPanel?.setAttribute('aria-labelledby', 'reviewer-submit-result-title');
                submitPanel?.setAttribute('aria-describedby', 'reviewer-submit-result-description');
                submitPanel?.removeAttribute('aria-busy');
                resultLink?.focus();
            } catch (error) {
                setSubmitDialogFeedback(error.message || 'The final review could not be submitted. Check your connection and try again.');
            } finally {
                finalSubmissionInFlight = false;
                submitPanel?.removeAttribute('aria-busy');
                cancelButtons.forEach((button) => {
                    button.disabled = false;
                });
                if (! finalSubmissionSucceeded) {
                    confirmButton.disabled = false;
                    if (confirmLabel) {
                        confirmLabel.textContent = 'Confirm Final Submission';
                    }
                }
            }
        });
    }

    const uploadProgress = (render) => {
        let dots = 0;
        render('Uploading');
        const timer = window.setInterval(() => {
            dots = (dots % 3) + 1;
            render(`Uploading${'.'.repeat(dots)}`);
        }, 360);

        return () => window.clearInterval(timer);
    };

    const uploadOne = async (form) => {
        const input = form.querySelector('[data-requirement-file]');
        const requirementId = form.dataset.requirementId;

        if (! input?.files?.length || ! requirementId) {
            return;
        }

        const button = form.querySelector('button[type="submit"]');
        button?.setAttribute('disabled', 'disabled');
        const stopProgress = uploadProgress((label) => setRequirementFeedback(requirementId, label));

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
            setRequirementFeedback(requirementId, '');
            refreshCompletedRequirements(payload.progress);
        } catch {
            setRequirementFeedback(requirementId, 'Upload failed. Check your connection and try again.', true);
        } finally {
            stopProgress();
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

        if (input.files?.length) {
            if (input.matches('[data-document-replace-file]')) {
                closeDocumentDialog();
            }
            uploadOne(form);
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
            setRequirementFeedback(input.dataset.requirementId, 'Uploading');
        });

        uploadAll.disabled = true;
        const stopBulkProgress = uploadProgress((label) => {
            if (uploadAllLabel) uploadAllLabel.textContent = label;
            selectedInputs.forEach((input) => setRequirementFeedback(input.dataset.requirementId, label));
        });
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
                setRequirementFeedback(requirementId, '');
            });
            Object.entries(payload.errors ?? {}).forEach(([requirementId, message]) => {
                setRequirementFeedback(requirementId, message, true);
            });
            updateRequirementProgress(payload.progress);
            const hasErrors = Object.keys(payload.errors ?? {}).length > 0;
            uploadAllSummary.classList.toggle('is-error', hasErrors);
            uploadAllSummary.textContent = hasErrors ? (payload.message ?? 'Some documents were not uploaded.') : '';
            refreshCompletedRequirements(payload.progress, hasErrors);
        } catch {
            uploadAllSummary.classList.add('is-error');
            uploadAllSummary.textContent = 'Upload failed. Check your connection and try again.';
        } finally {
            stopBulkProgress();
            if (uploadAllLabel) {
                uploadAllLabel.textContent = defaultUploadAllLabel;
            }
            syncUploadAll();
        }
    });
    syncUploadAll();

    shell.querySelectorAll('[data-revision-version-select]').forEach((selector) => {
        const container = selector.closest('[data-revision-requirement]');
        const panels = [...(container?.querySelectorAll('[data-revision-version-panel]') ?? [])];
        const syncVersion = () => {
            panels.forEach((panel) => {
                panel.hidden = panel.dataset.revisionVersionPanel !== selector.value;
            });
            selector.dataset.tableTooltip = selector.selectedOptions[0]?.textContent?.trim() ?? '';
        };
        selector.addEventListener('change', syncVersion);
        syncVersion();
    });

    shell.querySelectorAll('[data-applicant-review-worksheet]').forEach((worksheet) => {
        const tabs = [...worksheet.querySelectorAll('[data-applicant-worksheet-tab]')];
        const panels = [...worksheet.querySelectorAll('[data-applicant-worksheet-panel]')];
        const activate = (type) => {
            tabs.forEach((tab) => tab.setAttribute('aria-selected', String(tab.dataset.applicantWorksheetTab === type)));
            panels.forEach((panel) => {
                panel.hidden = panel.dataset.applicantWorksheetPanel !== type;
            });
        };

        tabs.forEach((tab) => tab.addEventListener('click', () => activate(tab.dataset.applicantWorksheetTab)));
        panels.forEach((panel) => {
            const selector = panel.querySelector('[data-applicant-worksheet-version]');
            const frame = panel.querySelector('[data-applicant-worksheet-frame]');
            const preview = panel.querySelector('[data-applicant-worksheet-preview-link]');
            const download = panel.querySelector('[data-applicant-worksheet-download-link]');
            const syncWorksheet = () => {
                const option = selector?.selectedOptions[0];
                if (! option) {
                    return;
                }
                const previewUrl = new URL(option.dataset.previewUrl, window.location.origin);
                const downloadUrl = new URL(option.dataset.downloadUrl, window.location.origin);
                if (previewUrl.origin !== window.location.origin || downloadUrl.origin !== window.location.origin) {
                    return;
                }
                frame?.setAttribute('src', previewUrl.href);
                preview?.setAttribute('href', previewUrl.href);
                download?.setAttribute('href', downloadUrl.href);
            };
            selector?.addEventListener('change', syncWorksheet);
        });
    });

    shell.querySelectorAll('.certification-panel').forEach((panel) => {
        const setCertificationBadge = (label) => {
            const badge = panel.querySelector('summary .dashboard-status-badge');

            if (badge) {
                badge.textContent = label;
                badge.className = 'dashboard-status-badge tone-success';
            }
        };
        const actionState = (title, message) => {
            const state = document.createElement('div');
            const heading = document.createElement('h3');
            const copy = document.createElement('p');

            state.className = 'certificate-action-state';
            heading.textContent = title;
            copy.textContent = message;
            state.append(heading, copy);

            return state;
        };
        const renderClaimable = (form, claimUrl) => {
            const state = actionState('Certificate ready to claim', 'Your evaluation is complete and all current released certificate versions are ready.');
            const claimForm = document.createElement('form');
            const token = document.createElement('input');
            const button = document.createElement('button');

            claimForm.method = 'POST';
            claimForm.action = claimUrl;
            claimForm.dataset.certificateAsyncForm = 'claim';
            token.type = 'hidden';
            token.name = '_token';
            token.value = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            button.type = 'submit';
            button.className = 'dashboard-primary-action';
            button.textContent = 'Claim Certificate';
            claimForm.append(token, button);
            state.append(claimForm);
            form.replaceWith(state);
            setCertificationBadge('Ready to Claim');
        };
        const renderClaimed = (form, payload) => {
            const state = actionState('Personalized certificates claimed', 'All personalized certificates are ready to view or download.');
            const list = document.createElement('div');

            list.className = 'applicant-certificate-list';
            (payload.certificates ?? []).forEach((certificate) => {
                const item = document.createElement('article');
                const identity = document.createElement('div');
                const name = document.createElement('strong');
                const metadata = document.createElement('span');
                const actions = document.createElement('div');
                const preview = document.createElement('a');
                const download = document.createElement('a');

                name.textContent = certificate.recipient_name;
                metadata.textContent = `${certificate.certificate_number} · version ${certificate.version}`;
                preview.className = 'dashboard-outline-action';
                preview.href = certificate.preview_url;
                preview.target = '_blank';
                preview.rel = 'noopener';
                preview.textContent = 'View';
                download.className = 'dashboard-primary-action';
                download.href = certificate.download_url;
                download.textContent = 'Download';
                identity.append(name, metadata);
                actions.append(preview, download);
                item.append(identity, actions);
                list.append(item);
            });
            state.append(list);

            const downloadAll = document.createElement('a');
            downloadAll.className = 'dashboard-primary-action applicant-certificate-download-all';
            downloadAll.href = payload.download_all_url;
            downloadAll.textContent = 'Download All';
            state.append(downloadAll);
            const currentState = form.closest('.certificate-action-state');
            if (currentState) {
                currentState.replaceWith(state);
            } else {
                form.replaceWith(state);
            }
            setCertificationBadge('Claimed');
        };

        panel.addEventListener('submit', async (event) => {
            const form = event.target.closest?.('[data-certificate-async-form]');

            if (! form || ! panel.contains(form)) {
                return;
            }

            event.preventDefault();
            if (! form.reportValidity()) {
                return;
            }

            const submit = event.submitter ?? form.querySelector('button[type="submit"]');
            const originalLabel = submit?.textContent;
            submit?.setAttribute('disabled', 'disabled');
            if (submit) {
                submit.textContent = form.dataset.certificateAsyncForm === 'survey' ? 'Submitting Evaluation...' : 'Claiming...';
            }

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json();

                if (! response.ok) {
                    const messages = Object.values(payload.errors ?? {}).flat();
                    let error = panel.querySelector('.certificate-action-error');

                    if (! error) {
                        error = document.createElement('div');
                        error.className = 'res-form-error-summary certificate-action-error';
                        form.before(error);
                    }
                    error.textContent = messages[0] ?? payload.message ?? 'The certificate action could not be completed.';

                    return;
                }

                panel.querySelector('.certificate-action-error')?.remove();
                if (form.dataset.certificateAsyncForm === 'survey') {
                    renderClaimable(form, payload.claim_url);
                } else {
                    renderClaimed(form, payload);
                }
            } catch {
                let error = panel.querySelector('.certificate-action-error');
                if (! error) {
                    error = document.createElement('div');
                    error.className = 'res-form-error-summary certificate-action-error';
                    form.before(error);
                }
                error.textContent = 'The certificate action could not be completed. Check your connection and try again.';
            } finally {
                submit?.removeAttribute('disabled');
                if (submit && submit.isConnected) {
                    submit.textContent = originalLabel;
                }
            }
        });
    });

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

    shell.querySelectorAll('.application-form-section').forEach((section) => {
        const heading = section.querySelector('.application-form-section-heading');
        const body = section.querySelector('.application-form-section-body');
        if (! heading || ! body) return;
        const expanded = Boolean(section.querySelector('.application-field-error, [role="alert"]'));
        body.hidden = ! expanded;
        section.classList.toggle('is-collapsed', ! expanded);
        heading.tabIndex = 0;
        heading.setAttribute('role', 'button');
        heading.setAttribute('aria-expanded', String(expanded));
        const toggle = () => {
            body.hidden = ! body.hidden;
            heading.setAttribute('aria-expanded', String(! body.hidden));
            section.classList.toggle('is-collapsed', body.hidden);
        };
        heading.addEventListener('click', toggle);
        heading.addEventListener('keydown', (event) => {
            if (! ['Enter', ' '].includes(event.key)) return;
            event.preventDefault();
            toggle();
        });
    });

    shell.querySelectorAll('[data-collapsible-panel]').forEach((panel) => {
        const heading = panel.firstElementChild;
        if (! heading || heading.dataset.collapsibleReady === 'true') return;
        const content = [...panel.children].slice(1);
        const expanded = Boolean(panel.querySelector('.application-field-error, .res-form-error-summary'));
        content.forEach((element) => { element.hidden = ! expanded; });
        panel.classList.toggle('is-collapsed', ! expanded);
        heading.dataset.collapsibleReady = 'true';
        heading.tabIndex = 0;
        heading.setAttribute('role', 'button');
        heading.setAttribute('aria-expanded', String(expanded));
        const toggle = () => {
            const expanded = heading.getAttribute('aria-expanded') === 'true';
            heading.setAttribute('aria-expanded', String(! expanded));
            content.forEach((element) => { element.hidden = expanded; });
            panel.classList.toggle('is-collapsed', expanded);
        };
        heading.addEventListener('click', toggle);
        heading.addEventListener('keydown', (event) => {
            if (! ['Enter', ' '].includes(event.key)) return;
            event.preventDefault();
            toggle();
        });
    });

    shell.querySelectorAll('form[data-auto-save-draft]').forEach((form) => {
        let dirty = false;
        let timer = null;
        const save = (keepalive = false) => {
            if (! dirty || ! form.checkValidity()) return;
            dirty = false;
            window.clearTimeout(timer);
            fetch(form.action, {
                method: form.method || 'POST',
                body: new FormData(form),
                headers: {'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest'},
                credentials: 'same-origin',
                keepalive,
            }).catch(() => { dirty = true; });
        };
        form.addEventListener('input', () => {
            dirty = true;
            window.clearTimeout(timer);
            timer = window.setTimeout(() => save(false), 900);
        });
        form.addEventListener('submit', () => {
            dirty = false;
            window.clearTimeout(timer);
        });
        window.addEventListener('pagehide', () => save(true));
    });

    shell.querySelectorAll('form[data-partial-auto-save-draft]').forEach((form) => {
        let dirty = false;
        let timer = null;
        const save = (keepalive = false) => {
            if (! dirty) return;
            dirty = false;
            window.clearTimeout(timer);
            fetch(form.dataset.partialAutoSaveDraft, {
                method: 'POST',
                body: new FormData(form),
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                credentials: 'same-origin',
                keepalive,
            }).then((response) => {
                if (! response.ok) dirty = true;
            }).catch(() => { dirty = true; });
        };
        form.addEventListener('input', () => {
            dirty = true;
            window.clearTimeout(timer);
            timer = window.setTimeout(() => save(false), 900);
        });
        form.addEventListener('submit', () => {
            dirty = false;
            window.clearTimeout(timer);
        });
        window.addEventListener('pagehide', () => save(true));
    });

    shell.querySelectorAll('[data-revision-upload-form]').forEach((form) => {
        const input = form.querySelector('[data-revision-upload-input]');
        const fileName = form.querySelector('[data-revision-file-name]');
        const feedback = form.querySelector('[data-revision-upload-feedback]');

        input?.addEventListener('change', async () => {
            const file = input.files?.[0];
            if (! file) {
                return;
            }

            if (fileName) {
                fileName.textContent = file.name;
            }
            const stopProgress = uploadProgress((label) => {
                if (feedback) feedback.textContent = label;
            });
            feedback?.classList.remove('is-error');
            const formData = new FormData(form);
            input.disabled = true;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json().catch(() => ({}));
                if (! response.ok) {
                    const validationMessage = Object.values(payload.errors ?? {}).flat()[0];
                    throw new Error(validationMessage ?? payload.message ?? 'The revised document could not be uploaded.');
                }

                if (feedback) feedback.textContent = '';
                window.location.reload();
            } catch (error) {
                input.disabled = false;
                input.value = '';
                if (fileName) {
                    fileName.textContent = 'Choose File';
                }
                if (feedback) {
                    feedback.textContent = error.message || 'Upload failed. Check your connection and try again.';
                    feedback.classList.add('is-error');
                }
            } finally {
                stopProgress();
            }
        });
    });

    const recipientBuilder = shell.querySelector('[data-certificate-recipients]');
    if (recipientBuilder) {
        const input = recipientBuilder.querySelector('[data-certificate-recipient-input]');
        const addButton = recipientBuilder.querySelector('[data-certificate-recipient-add]');
        const list = recipientBuilder.querySelector('[data-certificate-recipient-list]');
        const feedback = recipientBuilder.querySelector('[data-certificate-recipient-feedback]');
        const normalized = (value) => value.trim().replace(/\s+/g, ' ').toLocaleLowerCase();

        const removeRecipient = (button) => {
            button.closest('[data-certificate-recipient]')?.remove();
            if (feedback) {
                feedback.textContent = '';
            }
        };

        recipientBuilder.querySelectorAll('[data-certificate-recipient-remove]').forEach((button) => {
            button.addEventListener('click', () => removeRecipient(button));
        });

        const addRecipient = () => {
            const name = input?.value.trim().replace(/\s+/g, ' ');
            if (! name || ! list) {
                if (feedback) {
                    feedback.textContent = 'Enter a member name before selecting Add Name.';
                    feedback.classList.add('is-error');
                }
                input?.focus();
                return;
            }

            const duplicate = [...list.querySelectorAll('input[name="certificate_recipients[]"]')]
                .some((field) => normalized(field.value) === normalized(name));
            if (duplicate) {
                if (feedback) {
                    feedback.textContent = 'That name is already in the list.';
                    feedback.classList.add('is-error');
                }
                input?.focus();
                return;
            }

            const item = document.createElement('li');
            item.dataset.certificateRecipient = '';
            const label = document.createElement('span');
            label.textContent = name;
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'certificate_recipients[]';
            hidden.value = name;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.dataset.certificateRecipientRemove = '';
            remove.setAttribute('aria-label', `Remove ${name}`);
            remove.textContent = 'Remove';
            remove.addEventListener('click', () => removeRecipient(remove));
            item.append(label, hidden, remove);
            list.append(item);
            input.value = '';
            if (feedback) {
                feedback.textContent = `${name} added.`;
                feedback.classList.remove('is-error');
            }
            input.focus();
        };

        addButton?.addEventListener('click', addRecipient);
        input?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                addRecipient();
            }
        });
    }

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

    settings.querySelectorAll('[data-managed-background-file]').forEach((choice) => {
        const input = choice.querySelector('input[type="file"]');
        const label = choice.querySelector('[data-managed-background-file-label]');
        const syncFileName = () => {
            const fileName = input?.files?.[0]?.name ?? '';

            if (label) {
                label.textContent = fileName || 'Choose File';
                label.title = fileName;
            }
            choice.classList.toggle('is-selected', fileName !== '');
        };

        input?.addEventListener('change', syncFileName);
        syncFileName();
    });

    settings.querySelectorAll('[data-deadline-process]').forEach((process) => {
        const start = process.querySelector('[data-deadline-start]');
        const end = process.querySelector('[data-deadline-end]');
        const toggle = process.querySelector('[data-deadline-toggle]');
        const label = process.querySelector('[data-deadline-toggle-label]');
        const overrideChanged = process.querySelector('[data-deadline-override-changed]');
        const syncProcessState = () => {
            const manuallyOpen = Boolean(toggle?.checked);

            if (label) {
                label.textContent = manuallyOpen ? 'On' : 'Off';
            }

            if (end) {
                end.min = start?.value ?? '';
            }
        };

        start?.addEventListener('change', syncProcessState);
        toggle?.addEventListener('change', () => {
            if (overrideChanged) {
                overrideChanged.value = '1';
            }
            syncProcessState();
        });
        syncProcessState();
    });

    const termStart = settings.querySelector('#term_starts_on');
    const termEnd = settings.querySelector('#term_ends_on');
    termStart?.addEventListener('change', () => {
        if (termEnd) {
            termEnd.min = termStart.value || termStart.min || '';
        }
    });
    if (termEnd) {
        termEnd.min = termStart?.value || termStart?.min || '';
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

    settings.querySelectorAll('[data-settings-file-control]').forEach((control) => {
        const input = control.querySelector('input[type="file"]');
        const label = control.querySelector('[data-settings-file-label]');
        input?.addEventListener('change', () => {
            const selected = input.files?.[0];
            if (label) {
                label.textContent = selected?.name ?? label.dataset.settingsDefaultLabel ?? 'Choose File';
            }
            control.classList.toggle('has-file', Boolean(selected));
        });
    });

    settings.querySelectorAll('[data-settings-date-picker]').forEach((input) => {
        input.addEventListener('click', () => {
            if (typeof input.showPicker !== 'function') {
                return;
            }

            try {
                input.showPicker();
            } catch {
                // Native date controls still remain fully operable when showPicker is unavailable.
            }
        });
    });

    const inlineProfileForm = settings.querySelector('[data-inline-profile-form]');

    if (inlineProfileForm) {
        const profileFields = [...inlineProfileForm.querySelectorAll('[data-inline-profile-field]')];
        const fullNameTargets = settings.querySelectorAll('[data-inline-profile-name], [data-inline-profile-full-name]');

        const setFieldMode = (field, mode) => {
            const input = field.querySelector('[data-inline-profile-input]');
            const value = field.querySelector('[data-inline-profile-value]');
            const button = field.querySelector('[data-inline-profile-edit]');
            const editIcon = field.querySelector('[data-inline-edit-icon]');
            const saveIcon = field.querySelector('[data-inline-save-icon]');
            const label = field.querySelector('dt')?.textContent?.trim() ?? 'profile field';
            const editing = mode === 'save';

            input.hidden = ! editing;
            value.hidden = editing;
            button.dataset.mode = mode;
            button.setAttribute('aria-label', `${editing ? 'Save' : 'Edit'} ${label}`);
            editIcon.hidden = editing;
            saveIcon.hidden = ! editing;
        };

        const renderProfileData = (data) => {
            profileFields.forEach((field) => {
                const name = field.dataset.profileField;
                const input = field.querySelector('[data-inline-profile-input]');
                const value = field.querySelector('[data-inline-profile-value]');

                if (! name || ! Object.hasOwn(data, name)) {
                    return;
                }

                input.value = data[name] ?? '';
                input.dataset.savedValue = input.value;
                value.textContent = data[name] !== null && data[name] !== ''
                    ? String(data[name])
                    : value.dataset.emptyLabel || 'Not provided';
            });
            fullNameTargets.forEach((target) => {
                target.textContent = data.name;
            });
        };

        const saveProfile = async (field) => {
            const input = field.querySelector('[data-inline-profile-input]');
            const button = field.querySelector('[data-inline-profile-edit]');

            if (! input.reportValidity()) {
                return;
            }

            profileFields.forEach((candidate) => {
                candidate.querySelector('[data-inline-profile-error]').textContent = '';
                candidate.querySelector('[data-inline-profile-input]').setAttribute('aria-invalid', 'false');
            });
            button.disabled = true;

            try {
                const response = await fetch(inlineProfileForm.action, {
                    method: 'POST',
                    body: new FormData(inlineProfileForm),
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json();

                if (! response.ok) {
                    const errors = payload.errors ?? {};
                    let firstInvalid = null;

                    Object.entries(errors).forEach(([name, messages]) => {
                        const candidate = profileFields.find((item) => item.dataset.profileField === name);

                        if (! candidate) {
                            return;
                        }

                        const candidateInput = candidate.querySelector('[data-inline-profile-input]');
                        candidate.querySelector('[data-inline-profile-error]').textContent = Array.isArray(messages) ? messages[0] : String(messages);
                        candidateInput.setAttribute('aria-invalid', 'true');
                        setFieldMode(candidate, 'save');
                        firstInvalid ??= candidateInput;
                    });
                    firstInvalid?.focus();

                    return;
                }

                renderProfileData(payload.data ?? {});
                profileFields.forEach((candidate) => setFieldMode(candidate, 'edit'));
            } catch {
                field.querySelector('[data-inline-profile-error]').textContent = 'This value could not be saved. Check your connection and try again.';
            } finally {
                button.disabled = false;
            }
        };

        profileFields.forEach((field) => {
            const input = field.querySelector('[data-inline-profile-input]');
            const button = field.querySelector('[data-inline-profile-edit]');

            button.addEventListener('click', () => {
                if (button.dataset.mode === 'save') {
                    saveProfile(field);

                    return;
                }

                profileFields.forEach((candidate) => {
                    if (candidate !== field) {
                        const candidateInput = candidate.querySelector('[data-inline-profile-input]');
                        const candidateButton = candidate.querySelector('[data-inline-profile-edit]');

                        if (candidateButton.dataset.mode === 'save') {
                            candidateInput.value = candidateInput.dataset.savedValue ?? candidateInput.value;
                            candidate.querySelector('[data-inline-profile-error]').textContent = '';
                        }
                        setFieldMode(candidate, 'edit');
                    }
                });
                input.dataset.savedValue = input.value;
                setFieldMode(field, 'save');
                input.focus();
                input.select?.();
            });
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    saveProfile(field);
                } else if (event.key === 'Escape') {
                    input.value = input.dataset.savedValue ?? input.value;
                    field.querySelector('[data-inline-profile-error]').textContent = '';
                    setFieldMode(field, 'edit');
                    button.focus();
                }
            });
        });
    }

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
    shell.querySelectorAll('[data-profile-option-create]').forEach((form) => {
        const field = form.querySelector('[data-profile-option-field]');
        const acronymField = form.querySelector('[data-profile-option-acronym-field]');
        const acronym = form.querySelector('[data-profile-option-acronym]');

        if (! field || ! acronymField || ! acronym) {
            return;
        }

        const syncInstituteAcronym = () => {
            const isInstitute = field.value === 'institution';
            acronymField.hidden = ! isInstitute;
            acronym.disabled = ! isInstitute;
            acronym.required = isInstitute;

            if (! isInstitute) {
                acronym.value = '';
            }
        };

        field.addEventListener('change', syncInstituteAcronym);
        acronym.addEventListener('input', () => {
            acronym.value = acronym.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 12);
        });
        syncInstituteAcronym();
    });

    const reviewerToggle = shell.querySelector('[data-reviewer-capability-toggle]');
    const reviewerCapacity = shell.querySelector('[data-reviewer-capacity]');
    if (reviewerToggle && reviewerCapacity) {
        const syncReviewerCapacity = () => {
            const enabled = reviewerToggle.checked;
            reviewerCapacity.required = enabled;
            reviewerCapacity.disabled = ! enabled;
            reviewerCapacity.setAttribute('aria-disabled', String(! enabled));
        };

        reviewerToggle.addEventListener('change', syncReviewerCapacity);
        syncReviewerCapacity();
    }

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
    const reviewerAssignmentConfirmation = massForm?.querySelector('[data-reviewer-assignment-confirmation]');

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
                : selectedAction === 'hide_reviewer'
                    ? `Hide Reviewer access for the eligible selected Advisers? Any active assignments and review history will be preserved.`
                    : selectedAction === 'show_reviewer'
                        ? `Show Reviewer access for the eligible active Advisers?`
                : selectedAction === 'resend_all_pending'
                    ? 'Send a new setup link to every pending account in the current management scope?'
                    : `Apply this action to ${selectedCount} selected accounts?`;

            if (! window.confirm(message)) {
                event.preventDefault();
                return;
            }

            actionValue.value = selectedAction;
            if (reviewerAssignmentConfirmation) {
                reviewerAssignmentConfirmation.value = selectedAction === 'hide_reviewer' ? '1' : '0';
            }
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
