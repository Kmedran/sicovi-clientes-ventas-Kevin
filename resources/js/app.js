import 'bootstrap';
import './user-permissions.js';
import './login.js';
document.addEventListener('focusout', (event) => {
    if (event.target.matches('input[type="email"], input[name="username"]')) {
        event.target.value = event.target.value.trim().toLowerCase();
    }
});
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
    if (form.checkValidity()) {
        const button = form.querySelector('[type=submit]');
        if (button) { button.disabled = true; button.dataset.originalText = button.textContent; button.textContent = 'Procesando…'; }
    }
});
window.addEventListener('pageshow', () => document.querySelectorAll('button[data-original-text]').forEach(button => { button.disabled = false; button.textContent = button.dataset.originalText; }));
