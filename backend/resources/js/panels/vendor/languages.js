/**
 * Vendor › Settings › Languages (VEN-073), loaded for [data-page="vendor-languages"]:
 *  - "Your languages" rows sort by drag and drop (mouse / touch-pad) or with the handle
 *    and the arrow keys; the hidden order[] inputs follow the DOM order and the form is
 *    marked dirty (saved with the Save button);
 *  - translation progress bars take their width from data-progress.
 */
function renumber(list) {
    list.querySelectorAll('[data-language-position]').forEach((cell, index) => {
        cell.textContent = String(index + 1);
    });
    const form = list.closest('form');

    if (form) {
        form.dataset.dirty = '';
        form.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

function bindSorting(list) {
    let dragged = null;

    list.addEventListener('dragstart', (event) => {
        dragged = event.target.closest('[data-language-row][draggable="true"]');

        if (dragged) {
            dragged.classList.add('opacity-60');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', dragged.dataset.languageRow);
        }
    });

    list.addEventListener('dragover', (event) => {
        const target = event.target.closest('[data-language-row][draggable="true"]');

        if (!dragged || !target || target === dragged) {
            return;
        }

        event.preventDefault();
        const box = target.getBoundingClientRect();
        target[event.clientY > box.top + box.height / 2 ? 'after' : 'before'](dragged);
    });

    list.addEventListener('dragend', () => {
        if (dragged) {
            dragged.classList.remove('opacity-60');
            dragged = null;
            renumber(list);
        }
    });

    list.addEventListener('keydown', (event) => {
        const handle = event.target.closest('[data-language-handle]');

        if (!handle || !['ArrowUp', 'ArrowDown'].includes(event.key)) {
            return;
        }

        event.preventDefault();
        const row = handle.closest('[data-language-row]');
        const sibling = event.key === 'ArrowUp' ? row.previousElementSibling : row.nextElementSibling;

        if (sibling?.matches('[draggable="true"]')) {
            sibling[event.key === 'ArrowUp' ? 'before' : 'after'](row);
            handle.focus();
            renumber(list);
        }
    });
}

export default function initVendorLanguages(root) {
    root.querySelectorAll('[data-language-list]').forEach(bindSorting);
    root.querySelectorAll('[data-progress]').forEach((bar) => {
        bar.style.width = `${Math.min(100, Math.max(0, Number(bar.dataset.progress) || 0))}%`;
    });
}
