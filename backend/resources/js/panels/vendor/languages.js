/**
 * Vendor › Settings › Languages (VEN-073), loaded for [data-page="vendor-languages"]:
 *  - "Your languages" rows sort by drag and drop (mouse / touch-pad) or with the handle
 *    and the arrow keys; the hidden order[] inputs follow the DOM order and the form is
 *    marked dirty (saved with the Save button);
 *  - translation progress bars take their width from data-progress;
 *  - live paid-translation progress ([data-translation-live] JSON): every Reverb push on
 *    the store's translation-runs channel (and a poll while a run is active) re-reads
 *    the runs status URL and swaps each [data-run-slot] / [data-run-badge]; a finished
 *    run reloads the page so the language shows as active.
 */
import { http } from '../../core/http';
import { subscribePrivate } from '../realtime-channel';
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

function paintProgress(scope) {
    scope.querySelectorAll('[data-progress]').forEach((bar) => {
        bar.style.width = `${Math.min(100, Math.max(0, Number(bar.dataset.progress) || 0))}%`;
    });
}

function bindLiveRuns(root) {
    const island = root.querySelector('script[data-translation-live]');

    if (!island) {
        return;
    }

    let config;

    try {
        config = JSON.parse(island.textContent || '{}');
    } catch {
        return;
    }

    let loading = false;
    let timer = null;
    const hasActive = () => Boolean(root.querySelector('[data-run-slot][data-run-active="1"]'));

    const refresh = async () => {
        if (loading || document.hidden) {
            return;
        }

        loading = true;

        try {
            const { data } = await http.get(config.statusUrl, undefined, { silent: true });
            let finished = false;

            Object.entries(data?.runs ?? {}).forEach(([code, run]) => {
                const slot = root.querySelector(`[data-run-slot="${CSS.escape(code)}"]`);
                const badge = root.querySelector(`[data-run-badge="${CSS.escape(code)}"]`);

                if (slot) {
                    finished = finished || (slot.dataset.runActive === '1' && run.status === 'completed');
                    slot.innerHTML = run.html;
                    slot.dataset.runActive = ['queued', 'translating'].includes(run.status) ? '1' : '0';
                    paintProgress(slot);
                }

                if (badge) {
                    badge.innerHTML = run.badge;
                }
            });

            if (finished && !root.querySelector('form[data-dirty]')) {
                window.location.reload();
            }
        } catch {
            // Keep the last known state; the next push or poll tries again.
        } finally {
            loading = false;
        }
    };

    const schedule = () => {
        window.clearTimeout(timer);

        if (hasActive()) {
            timer = window.setTimeout(async () => {
                await refresh();
                schedule();
            }, config.pollMs || 10000);
        }
    };

    if (config.realtime) {
        subscribePrivate(config.realtime, () => refresh().then(schedule));
    }

    schedule();
}

export default function initVendorLanguages(root) {
    root.querySelectorAll('[data-language-list]').forEach(bindSorting);
    paintProgress(root);
    bindLiveRuns(root);
}
