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
}

function initializeResearchTitleTooltips(shell) {
    const targets = [...shell.querySelectorAll('[data-research-title-tooltip]')];

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
            tooltip.textContent = target.dataset.fullTitle ?? target.textContent.trim();
            tooltip.hidden = false;
            target.setAttribute('aria-describedby', tooltipId);
            positionTooltip();
        }, 1000);
    };

    targets.forEach((target) => {
        target.addEventListener('pointerenter', (event) => {
            scheduleTooltip(target, { x: event.clientX, y: event.clientY });
        });
        target.addEventListener('pointermove', (event) => {
            if (activeTarget !== target) {
                return;
            }

            pointerPosition = { x: event.clientX, y: event.clientY };
            positionTooltip();
        });
        target.addEventListener('pointerleave', hideTooltip);
        target.addEventListener('focus', () => scheduleTooltip(target));
        target.addEventListener('blur', hideTooltip);
        target.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                hideTooltip();
            }
        });
    });
}
