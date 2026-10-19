import assert from 'node:assert/strict';
import test from 'node:test';
import { bindLoginAutofillGuard, createLoginAutofillState } from '../../resources/js/login.js';

test('a valid username and its saved password remain available', () => {
    const state = createLoginAutofillState();
    assert.deepEqual(state.sanitize({ username: 'adminm', password: 'saved', usernameAutofill: true, passwordAutofill: true }), { username: 'adminm', password: 'saved' });
});

test('a legacy autofilled email and password are cleared together', () => {
    const state = createLoginAutofillState();
    assert.deepEqual(state.sanitize({ username: 'old@example.test', password: 'saved', usernameAutofill: true, passwordAutofill: true }), { username: '', password: '' });
});

test('a password arriving after the legacy email is also cleared', () => {
    const state = createLoginAutofillState();
    assert.deepEqual(state.sanitize({ username: 'old@example.test', password: '', usernameAutofill: true }), { username: '', password: '' });
    assert.deepEqual(state.sanitize({ username: '', password: 'later', passwordAutofill: true }), { username: '', password: '' });
});

test('a password arriving before the email is cleared once the legacy pair is known', () => {
    const state = createLoginAutofillState();
    assert.deepEqual(state.sanitize({ username: '', password: 'first', passwordAutofill: true }), { username: '', password: 'first' });
    assert.deepEqual(state.sanitize({ username: 'old@example.test', password: 'first', usernameAutofill: true }), { username: '', password: '' });
});

test('postback values and manually edited usernames are preserved, including invalid emails', () => {
    for (const preserveInput of [true, false]) {
        const state = createLoginAutofillState({ preserveInput });
        if (!preserveInput) state.markEdited('username');
        assert.deepEqual(state.sanitize({ username: 'typed@example.test', password: 'typed', usernameAutofill: true, passwordAutofill: true }), { username: 'typed@example.test', password: 'typed' });
    }
});

test('a manually entered password is preserved when an old email is autofilled', () => {
    const state = createLoginAutofillState();
    state.markEdited('password');
    assert.deepEqual(state.sanitize({ username: 'old@example.test', password: 'manual', usernameAutofill: true, passwordAutofill: true }), { username: '', password: 'manual' });
});

test('unknown values without autofill evidence are preserved', () => {
    const state = createLoginAutofillState();
    assert.deepEqual(state.sanitize({ username: 'typed@example.test', password: 'manual' }), { username: 'typed@example.test', password: 'manual' });
});

test('consumed autofill evidence cannot erase a later unconfirmed email replacement', () => {
    const state = createLoginAutofillState();
    state.observeAutofill('username');
    state.observeAutofill('password');
    assert.deepEqual(state.sanitize({ username: 'old@example.test', password: 'saved' }), { username: '', password: '' });
    assert.deepEqual(state.sanitize({ username: 'replacement@example.test', password: 'replacement' }), { username: 'replacement@example.test', password: 'replacement' });
});

test('consumed password evidence cannot erase a later unconfirmed password while username is blank', () => {
    const state = createLoginAutofillState();
    assert.deepEqual(state.sanitize({ username: 'old@example.test', password: 'saved', usernameAutofill: true, passwordAutofill: true }), { username: '', password: '' });
    assert.deepEqual(state.sanitize({ username: '', password: 'replacement' }), { username: '', password: 'replacement' });
});

test('a changed password loses its earlier autofill association before the legacy email arrives', () => {
    const state = createLoginAutofillState();
    state.sanitize({ username: '', password: 'saved first', passwordAutofill: true });
    assert.deepEqual(state.sanitize({ username: 'old@example.test', password: 'unconfirmed replacement', usernameAutofill: true }), { username: '', password: 'unconfirmed replacement' });
});

class FakeEvents {
    constructor() { this.listeners = new Map(); }
    addEventListener(type, listener) {
        const listeners = this.listeners.get(type) || [];
        listeners.push(listener);
        this.listeners.set(type, listeners);
    }
    emit(type, properties = {}) {
        for (const listener of this.listeners.get(type) || []) listener({ type, isTrusted: true, ...properties });
    }
}

function fakeLogin({ preserveInput = 'false', username = '', password = '', autofilled = false } = {}) {
    const user = { value: username, autofilled, matches() { return this.autofilled; } };
    const pass = { value: password, autofilled, matches() { return this.autofilled; } };
    const form = new FakeEvents();
    form.dataset = { preserveInput };
    form.querySelector = (selector) => selector.includes('username') ? user : pass;
    const page = new FakeEvents();
    bindLoginAutofillGuard(form, page);
    return { form, page, user, pass };
}

test('the initial scan and pageshow handle saved legacy values', () => {
    const { user, pass, page } = fakeLogin({ username: 'old@example.test', password: 'saved', autofilled: true });
    assert.equal(user.value, '');
    assert.equal(pass.value, '');
    user.value = 'old@example.test';
    pass.value = 'restored';
    page.emit('pageshow');
    assert.equal(user.value, '');
    assert.equal(pass.value, '');
});

test('autofill replacement beforeinput does not hide late CSS detection', () => {
    const { form, user, pass } = fakeLogin();
    form.emit('beforeinput', { target: user, inputType: 'insertReplacementText', data: 'old@example.test' });
    user.value = 'old@example.test';
    form.emit('animationstart', { target: user, animationName: 'sicovi-login-autofill' });
    assert.equal(user.value, '');
    pass.value = 'late password';
    form.emit('animationstart', { target: pass, animationName: 'sicovi-login-autofill' });
    assert.equal(pass.value, '');
});

test('an OS replacement after a completed legacy cleanup remains available without new autofill evidence', () => {
    const { form, user, pass } = fakeLogin({ username: 'old@example.test', password: 'saved', autofilled: true });
    user.autofilled = pass.autofilled = false;
    form.emit('beforeinput', { target: pass, inputType: 'insertReplacementText', data: 'new password' });
    pass.value = 'new password';
    form.emit('input', { target: pass, inputType: 'insertReplacementText', data: 'new password' });
    assert.equal(pass.value, 'new password');
    form.emit('beforeinput', { target: user, inputType: 'insertReplacementText', data: 'replacement@example.test' });
    user.value = 'replacement@example.test';
    form.emit('input', { target: user, inputType: 'insertReplacementText', data: 'replacement@example.test' });
    assert.equal(user.value, 'replacement@example.test');
    assert.equal(pass.value, 'new password');
});

test('typing, pasting, dropping and composing username input prevent later cleanup', () => {
    for (const event of [
        ['keydown', { key: '@' }], ['paste', {}], ['drop', {}], ['compositionstart', {}],
        ['beforeinput', { inputType: 'insertText', data: '@' }],
    ]) {
        const { form, user, pass, page } = fakeLogin();
        form.emit(event[0], { target: user, ...event[1] });
        user.value = 'manually@example.test';
        pass.value = 'manual';
        user.autofilled = pass.autofilled = true;
        form.emit('animationstart', { target: user, animationName: 'sicovi-login-autofill' });
        page.emit('pageshow');
        assert.equal(user.value, 'manually@example.test');
        assert.equal(pass.value, 'manual');
    }
});

test('valid replacement usernames stop pending legacy-password cleanup', () => {
    const { form, user, pass } = fakeLogin({ username: 'old@example.test', autofilled: true });
    user.value = 'adminm';
    form.emit('input', { target: user });
    pass.value = 'valid saved password';
    form.emit('animationstart', { target: pass, animationName: 'sicovi-login-autofill' });
    assert.equal(user.value, 'adminm');
    assert.equal(pass.value, 'valid saved password');
});

test('the browser binding respects postback preservation and installs only once', () => {
    const { form, page, user, pass } = fakeLogin({ preserveInput: 'true', username: 'old@example.test', password: 'saved', autofilled: true });
    bindLoginAutofillGuard(form, page);
    assert.equal(page.listeners.get('pageshow').length, 1);
    page.emit('pageshow');
    assert.equal(user.value, 'old@example.test');
    assert.equal(pass.value, 'saved');
});
