/**
 * Admin › Role editor (ADM-111a): "Manage implies View" in the matrix — ticking
 * Manage ticks View, and View cannot be cleared while Manage is on. Row and global
 * select / clear helpers.
 */
function boxes(row) {
    return [...row.querySelectorAll('input[type="checkbox"][data-ability]:not(:disabled)')];
}

function enforceImplied(row) {
    const manage = row.querySelector('input[data-ability="manage"]');
    const view = row.querySelector('input[data-ability="view"]');

    if (!view) {
        return;
    }

    if (manage?.checked) {
        view.checked = true;
    }

    view.setAttribute('aria-disabled', manage?.checked ? 'true' : 'false');
}

export default function initRoles(root) {
    const form = root.querySelector('[data-role-matrix]');

    if (!form) {
        return;
    }

    const rows = [...form.querySelectorAll('[data-matrix-row]')];
    rows.forEach(enforceImplied);

    form.addEventListener('click', (event) => {
        const view = event.target.closest('input[data-ability="view"]');

        if (view && view.getAttribute('aria-disabled') === 'true' && !view.checked) {
            event.preventDefault();
            view.checked = true;
        }
    });

    form.addEventListener('change', (event) => {
        const row = event.target.closest('[data-matrix-row]');

        if (row && event.target.matches('input[data-ability]')) {
            enforceImplied(row);
            form.dataset.dirty = '';
        }
    });

    form.addEventListener('click', (event) => {
        const rowToggle = event.target.closest('[data-matrix-row-toggle]');
        const all = event.target.closest('[data-matrix-all]');

        if (rowToggle) {
            const row = rowToggle.closest('[data-matrix-row]');
            const list = boxes(row);
            const check = !list.every((box) => box.checked);
            list.forEach((box) => (box.checked = check));
            enforceImplied(row);
        }

        if (all) {
            const check = all.dataset.matrixAll === '1';
            rows.forEach((row) => {
                boxes(row).forEach((box) => (box.checked = check));
                enforceImplied(row);
            });
        }
    });
}
