function initializeUserPermissions(section) {
    const form = section.closest('form');
    const roleSelect = form?.querySelector('[data-user-permission-role]');
    const resetButton = section.querySelector('[data-reset-user-permissions]');
    const status = section.querySelector('[data-permission-status]');
    const count = section.querySelector('[data-permission-count]');
    const checkboxes = [...section.querySelectorAll('[data-user-permission]')];
    const groups = [...section.querySelectorAll('[data-permission-group]')];
    let state;

    try {
        state = JSON.parse(section.querySelector('[data-user-permission-state]').textContent);
    } catch {
        resetButton.disabled = true;
        return;
    }

    const rolePermissions = state.rolePermissions ?? {};
    const hasSelectedRole = () => Boolean(roleSelect?.value)
        && Array.isArray(rolePermissions[roleSelect.value]);

    function updateGroupStates() {
        for (const group of groups) {
            const toggle = group.querySelector('[data-permission-group-toggle]');
            const actions = [...group.querySelectorAll('[data-user-permission]')];
            const selected = actions.filter((checkbox) => checkbox.checked).length;
            toggle.checked = actions.length > 0 && selected === actions.length;
            toggle.indeterminate = selected > 0 && selected < actions.length;
            toggle.setAttribute('aria-checked', toggle.indeterminate ? 'mixed' : String(toggle.checked));
        }
        const selectedCount = checkboxes.filter((checkbox) => checkbox.checked).length;
        count.textContent = `${selectedCount} ${selectedCount === 1 ? 'acción seleccionada' : 'acciones seleccionadas'}`;
        resetButton.disabled = !hasSelectedRole();
    }

    function loadRolePermissions(message) {
        const defaults = new Set(hasSelectedRole() ? rolePermissions[roleSelect.value] : []);
        for (const checkbox of checkboxes) {
            checkbox.checked = defaults.has(checkbox.value);
        }
        updateGroupStates();
        status.textContent = hasSelectedRole()
            ? message
            : 'Seleccione un cargo para cargar sus permisos predeterminados.';
    }

    roleSelect?.addEventListener('change', () => {
        loadRolePermissions('Se cargaron los permisos predeterminados del cargo seleccionado. Puede ajustarlos para esta cuenta.');
    });
    resetButton.addEventListener('click', () => {
        loadRolePermissions('Se restauraron los permisos predeterminados del cargo para esta cuenta.');
    });
    for (const group of groups) {
        group.querySelector('[data-permission-group-toggle]').addEventListener('change', (event) => {
            for (const checkbox of group.querySelectorAll('[data-user-permission]')) {
                checkbox.checked = event.target.checked;
            }
            updateGroupStates();
            status.textContent = 'La selección de permisos se aplicará solo a esta cuenta.';
        });
    }
    for (const checkbox of checkboxes) {
        checkbox.addEventListener('change', () => {
            updateGroupStates();
            status.textContent = 'La selección de permisos se aplicará solo a esta cuenta.';
        });
    }

    // Blade renders current/old selections. Initialization only updates group indicators.
    updateGroupStates();
}

function initializeAllUserPermissions() {
    document.querySelectorAll('[data-user-permissions]').forEach(initializeUserPermissions);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAllUserPermissions, { once: true });
} else {
    initializeAllUserPermissions();
}
