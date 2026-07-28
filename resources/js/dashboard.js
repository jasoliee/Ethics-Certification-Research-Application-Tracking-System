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
    ];

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
    });

    // Echo the local display filename while the server remains authoritative for type and size checks.
    shell.querySelectorAll('[data-application-file]').forEach((input) => {
        const form = input.closest('form');
        const filename = form?.querySelector('[data-application-file-name]');

        // Update only the local display label; no file data leaves the browser until form submission.
        input.addEventListener('change', () => {
            // A missing optional filename label does not prevent native upload behavior.
            if (filename) {
                filename.textContent = input.files?.[0]?.name ?? 'No file selected';
            }
        });
    });

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

    // Each View command opens the shared dialog using URLs already authorized by its role route.
    shell.querySelectorAll('[data-document-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const previewUrl = button.dataset.documentPreviewUrl ?? '';
            documentTrigger = button;

            // Use textContent so original display filenames cannot create markup.
            if (documentTitle) {
                documentTitle.textContent = button.dataset.documentName ?? 'Document';
            }

            // Use textContent for requirement and upload metadata for the same reason.
            if (documentMeta) {
                documentMeta.textContent = button.dataset.documentMeta ?? 'Selected requirement document';
            }

            // Point the download command only to the controller route rendered by Blade.
            if (documentDownload) {
                documentDownload.href = button.dataset.documentDownloadUrl;
            }

            // Resolve an applicant-only replacement input by ID and never accept an arbitrary selector.
            const replaceInputId = button.dataset.documentReplaceInput ?? '';
            const replacementCandidate = replaceInputId === '' ? null : document.getElementById(replaceInputId);
            documentReplaceInput = replacementCandidate instanceof HTMLInputElement && shell.contains(replacementCandidate)
                ? replacementCandidate
                : null;

            if (documentReplace) {
                documentReplace.hidden = documentReplaceInput === null;
            }

            // Supported types load in the sandboxed frame; unsupported types leave no retained source.
            if (documentFrame) {
                documentFrame.hidden = previewUrl === '';
                previewUrl === ''
                    ? documentFrame.removeAttribute('src')
                    : documentFrame.setAttribute('src', previewUrl);
            }

            // The fallback replaces the frame when no safe inline route was supplied.
            if (documentFallback) {
                documentFallback.hidden = previewUrl !== '';
            }

            // Reveal only after all display state and secure URLs have been populated.
            documentDialog.hidden = false;
            documentPanel?.focus();
        });
    });

    // The modal Replace command opens the requirement-scoped native file picker.
    documentReplace?.addEventListener('click', () => {
        documentReplaceInput?.click();
    });

    // Selecting a replacement submits its CSRF-protected requirement form immediately.
    shell.querySelectorAll('[data-document-replace-file]').forEach((input) => {
        input.addEventListener('change', () => {
            if (input.files?.length) {
                closeDocumentDialog();
                input.form?.requestSubmit();
            }
        });
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

    // Removing a current document makes required checklist items incomplete again.
    shell.querySelectorAll('[data-confirm-document-remove]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (! window.confirm('Remove this uploaded document? A replacement will be required before submission.')) {
                event.preventDefault();
            }
        });
    });

    // Draft discard archives only the current unsubmitted application after explicit confirmation.
    shell.querySelectorAll('[data-confirm-draft-discard]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (! window.confirm('Discard this draft application? It will be removed from your application list.')) {
                event.preventDefault();
            }
        });
    });

    // Disable write commands after native validation passes to prevent accidental duplicate requests.
    shell.querySelectorAll('[data-application-submit-once]').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelector('button[type="submit"]')?.setAttribute('disabled', 'disabled');
        });
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

    if (targets.length === 0) {
        return;
    }

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
