export function initializeRevisionDocumentHistory() {
    document.querySelectorAll('[data-revision-requirement]').forEach((requirement) => {
        const selector = requirement.querySelector('[data-revision-version-select]');
        const versionPanels = requirement.querySelectorAll('[data-revision-version-panel]');

        if (! selector || versionPanels.length === 0) {
            return;
        }

        const showSelectedVersion = () => {
            versionPanels.forEach((panel) => {
                panel.hidden = panel.dataset.revisionVersionPanel !== selector.value;
            });
        };

        selector.addEventListener('change', showSelectedVersion);
        showSelectedVersion();
    });
}
