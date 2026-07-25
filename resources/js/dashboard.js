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
    initializeOnboardingGuide(shell);
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

    // Status changes require a final acknowledgement because deactivation immediately blocks sign-in.
    shell.querySelectorAll('[data-confirm-status]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (! window.confirm(form.dataset.confirmStatus)) {
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
