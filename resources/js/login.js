const autofillAnimation = 'sicovi-login-autofill';

/** Only an obsolete email supplied by the browser can start a cleanup. */
export function createLoginAutofillState({ preserveInput = false } = {}) {
    let usernameEdited = false;
    let passwordEdited = false;
    let usernameAutofilled = false;
    let passwordAutofilled = false;
    let knownAutofilledPassword = null;
    let legacyPair = false;

    return {
        observeAutofill(field) {
            if (field === 'username') usernameAutofilled = true;
            if (field === 'password') passwordAutofilled = true;
        },
        markEdited(field) {
            if (field === 'username') {
                usernameEdited = true;
                legacyPair = false;
                knownAutofilledPassword = null;
            }
            if (field === 'password') {
                passwordEdited = true;
                knownAutofilledPassword = null;
            }
        },
        sanitize({ username, password, usernameAutofill = false, passwordAutofill = false }) {
            const currentUsernameAutofill = usernameAutofill || usernameAutofilled;
            const currentPasswordAutofill = passwordAutofill || passwordAutofilled;
            // Animation evidence belongs to this observation, never later replacements.
            usernameAutofilled = false;
            passwordAutofilled = false;
            if (preserveInput || usernameEdited) return { username, password };
            if (passwordEdited) knownAutofilledPassword = null;
            else if (currentPasswordAutofill) knownAutofilledPassword = password;
            else if (password !== knownAutofilledPassword) knownAutofilledPassword = null;

            if (username !== '' && !username.includes('@')) {
                legacyPair = false;
                return { username, password };
            }
            if (username.includes('@') && currentUsernameAutofill) {
                legacyPair = true;
                username = '';
            }
            if (legacyPair && username === '' && knownAutofilledPassword !== null && password === knownAutofilledPassword && !passwordEdited) {
                password = '';
                knownAutofilledPassword = null;
            }

            return { username, password };
        },
    };
}

function isAutofilled(input) {
    return [':autofill', ':-webkit-autofill'].some((selector) => {
        try { return input.matches(selector); } catch { return false; }
    });
}

function isManualTextEvent(event) {
    if (event.isTrusted === false) return false;
    const type = event.inputType || '';
    // Browser autofill can report insertReplacementText before its CSS state appears.
    return type.startsWith('delete')
        || ['insertFromPaste', 'insertFromDrop', 'insertCompositionText'].includes(type)
        || (type === 'insertText' && event.data != null);
}

export function bindLoginAutofillGuard(form, page) {
    if (form.dataset.loginAutofillGuard === 'true') return;
    const username = form.querySelector('input[name="username"]');
    const password = form.querySelector('input[name="password"]');
    if (!username || !password) return;
    form.dataset.loginAutofillGuard = 'true';
    const state = createLoginAutofillState({ preserveInput: ['true', '1'].includes(form.dataset.preserveInput) });
    const fieldName = (target) => target === username ? 'username' : target === password ? 'password' : null;
    const reconcile = () => {
        const clean = state.sanitize({
            username: username.value,
            password: password.value,
            usernameAutofill: isAutofilled(username),
            passwordAutofill: isAutofilled(password),
        });
        if (username.value !== clean.username) username.value = clean.username;
        if (password.value !== clean.password) password.value = clean.password;
    };

    form.addEventListener('keydown', (event) => {
        const field = fieldName(event.target);
        if (field && event.isTrusted !== false && (
            ['Backspace', 'Delete'].includes(event.key)
            || (event.key?.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey)
        )) state.markEdited(field);
    });
    for (const type of ['paste', 'cut', 'drop', 'compositionstart']) {
        form.addEventListener(type, (event) => {
            const field = fieldName(event.target);
            if (field && event.isTrusted !== false) state.markEdited(field);
        });
    }
    form.addEventListener('beforeinput', (event) => {
        const field = fieldName(event.target);
        if (field && isManualTextEvent(event)) state.markEdited(field);
    });
    for (const type of ['input', 'change']) {
        form.addEventListener(type, (event) => {
            const field = fieldName(event.target);
            if (!field) return;
            if (isManualTextEvent(event)) state.markEdited(field);
            reconcile();
        });
    }
    form.addEventListener('animationstart', (event) => {
        const field = fieldName(event.target);
        if (field && event.animationName === autofillAnimation) {
            state.observeAutofill(field);
            reconcile();
        }
    });
    page?.addEventListener('pageshow', reconcile);
    reconcile();
}

if (typeof document !== 'undefined' && typeof window !== 'undefined') {
    const install = () => document.querySelectorAll('form[data-login-form]').forEach((form) => bindLoginAutofillGuard(form, window));
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', install, { once: true });
    else install();
}
