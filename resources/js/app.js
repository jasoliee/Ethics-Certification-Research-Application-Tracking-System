import { initializeDashboard } from './dashboard';

const LOGIN_DESIGN_WIDTH = 1040;
const LOGIN_DESIGN_HEIGHT = 650;
const LOGIN_OUTER_GAP = 32;
const LOGIN_MOBILE_BREAKPOINT = 900;

function initializeLoginPage() {
    const shell = document.querySelector('.login-shell');

    if (shell) {
        const resizeLoginShell = () => {
            if (window.innerWidth <= LOGIN_MOBILE_BREAKPOINT) {
                shell.style.removeProperty('--login-scale');

                return;
            }

            const availableWidth = Math.max(0, window.innerWidth - LOGIN_OUTER_GAP);
            const availableHeight = Math.max(0, window.innerHeight - LOGIN_OUTER_GAP);
            const scale = Math.min(
                1,
                availableWidth / LOGIN_DESIGN_WIDTH,
                availableHeight / LOGIN_DESIGN_HEIGHT,
            );

            shell.style.setProperty('--login-scale', String(scale));
        };

        resizeLoginShell();
        window.addEventListener('resize', resizeLoginShell, { passive: true });
    }

    document.querySelectorAll('[data-password-toggle]').forEach((passwordToggle) => {
        const inputId = passwordToggle.getAttribute('aria-controls');
        const password = inputId ? document.getElementById(inputId) : null;

        if (! password) {
            return;
        }

        const setPasswordVisibility = (passwordIsVisible) => {
            const label = passwordIsVisible ? 'Hide password' : 'Show password';

            password.type = passwordIsVisible ? 'text' : 'password';
            passwordToggle.setAttribute('aria-label', label);
            passwordToggle.setAttribute('aria-pressed', String(passwordIsVisible));
            passwordToggle.title = label;
        };

        const syncPasswordToggle = () => {
            const passwordHasValue = password.value.length > 0;

            passwordToggle.hidden = ! passwordHasValue;

            if (! passwordHasValue) {
                setPasswordVisibility(false);
            }
        };

        passwordToggle.addEventListener('click', () => {
            setPasswordVisibility(password.type !== 'text');
        });

        password.addEventListener('input', syncPasswordToggle);
        password.addEventListener('change', syncPasswordToggle);

        syncPasswordToggle();
        window.requestAnimationFrame(syncPasswordToggle);
        window.setTimeout(syncPasswordToggle, 500);
    });

    const form = document.querySelector('[data-login-form]');

    if (! form) {
        return;
    }

    const username = form.querySelector('#username');
    const password = form.querySelector('#password');
    const messageArea = form.querySelector('#login-validation-messages');

    if (! username || ! password || ! messageArea) {
        return;
    }

    const fields = { username, password };

    const hideMessage = (message) => {
        message.hidden = true;
    };

    const clearMessagesFor = (fieldName) => {
        messageArea.querySelectorAll(`[data-error-for="${fieldName}"]`).forEach(hideMessage);
    };

    const clearCredentialError = () => {
        const credentialMessages = messageArea.querySelectorAll('[data-error-for="credentials"]:not([hidden])');

        if (credentialMessages.length === 0) {
            return false;
        }

        credentialMessages.forEach(hideMessage);
        username.setAttribute('aria-invalid', 'false');
        password.setAttribute('aria-invalid', 'false');

        return true;
    };

    const renderMessage = (fieldName, message) => {
        const element = document.createElement('span');

        element.className = 'login-validation-message';
        element.dataset.loginError = '';
        element.dataset.errorFor = fieldName;
        element.textContent = message;
        messageArea.append(element);
        fields[fieldName].setAttribute('aria-invalid', 'true');
    };

    Object.entries(fields).forEach(([fieldName, field]) => {
        field.addEventListener('input', () => {
            if (! clearCredentialError()) {
                clearMessagesFor(fieldName);
                field.setAttribute('aria-invalid', 'false');
            }
        });
    });

    form.addEventListener('submit', (event) => {
        username.value = username.value.trim();

        messageArea.querySelectorAll('[data-login-error]').forEach(hideMessage);
        username.setAttribute('aria-invalid', 'false');
        password.setAttribute('aria-invalid', 'false');

        const errors = [];
        const usernameLength = [...username.value].length;
        const passwordLength = [...password.value].length;

        if (usernameLength === 0) {
            errors.push(['username', 'Enter your username.']);
        } else if (usernameLength > 30) {
            errors.push(['username', 'Username must not exceed 30 characters.']);
        }

        if (passwordLength === 0) {
            errors.push(['password', 'Enter your password.']);
        } else if (passwordLength > 64) {
            errors.push(['password', 'Password must not exceed 64 characters.']);
        }

        if (errors.length === 0) {
            return;
        }

        event.preventDefault();
        errors.forEach(([fieldName, message]) => renderMessage(fieldName, message));
        fields[errors[0][0]].focus();
    });
}

function initializeApplication() {
    initializeLoginPage();
    initializeDashboard();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeApplication);
} else {
    initializeApplication();
}
