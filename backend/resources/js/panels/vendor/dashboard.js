/**
 * Vendor dashboard widgets (VEN-009a / c / d / f): add to store (VEN-011 import), the
 * "Add to flash sale" remote modal (VEN-041b), join a hidden platform promotion, the
 * new-product preview modal, drag-to-scroll product rails and the live store preview
 * (scaled frame, loading / error states, country switcher).
 */
import { http } from '../../core/http';
import { closeOverlay } from '../core.js';
import { bindRemoteModals } from '../remote-modal.js';

const PREVIEW_WIDTH = 1280;
const PREVIEW_TIMEOUT = 15000;

function markAdded(card, label) {
    if (!card) {
        return;
    }

    card.classList.add('is-added');
    card.querySelector('[data-added-ribbon]')?.removeAttribute('hidden');
    card.querySelectorAll('[data-dashboard-import], [data-import-one]').forEach((button) => {
        button.setAttribute('disabled', '');
        button.setAttribute('aria-disabled', 'true');
        const text = button.querySelector('[data-label]') ?? [...button.childNodes].reverse().find((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '');

        if (text && label) {
            text.textContent = label;
        }
    });
}

async function importOne(button, label) {
    if (button.hasAttribute('disabled')) {
        return;
    }

    button.setAttribute('data-loading', '');

    try {
        await http.post(button.dataset.url, { ids: [button.dataset.id] });
        markAdded(button.closest('[data-dashboard-card]'), label);
    } catch {
        // http shows the error toast; the button stays usable for a retry.
    } finally {
        button.removeAttribute('data-loading');
    }
}

function addedLabel(element) {
    return element.closest('[data-added-label]')?.dataset.addedLabel ?? document.querySelector('[data-added-label]')?.dataset.addedLabel ?? '';
}

function bindActions(page) {
    page.addEventListener('click', async (event) => {
        const add = event.target.closest('[data-dashboard-import], [data-import-one]');

        if (add) {
            await importOne(add, addedLabel(add));
            return;
        }

        const join = event.target.closest('[data-dashboard-join]');

        if (join && !join.hasAttribute('disabled')) {
            join.setAttribute('data-loading', '');

            try {
                await http.post(join.dataset.url, { hidden: 0 });
                join.setAttribute('disabled', '');
                join.closest('[data-dashboard-offer]')?.classList.add('opacity-70');
                const text = [...join.childNodes].reverse().find((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '');

                if (text) {
                    text.textContent = ` ${join.dataset.joinedLabel}`;
                }
            } catch {
                // toast already shown
            } finally {
                join.removeAttribute('data-loading');
            }

            return;
        }

        const previewImport = event.target.closest('[data-preview-import]');

        if (previewImport) {
            const modal = previewImport.closest('.ui-overlay');
            const id = modal?.querySelector('[data-preview-for]')?.dataset.previewFor;

            if (!id) {
                return;
            }

            try {
                await http.post(previewImport.dataset.url, { ids: [id] });
                closeOverlay(modal, { force: true });
                const button = page.querySelector(`[data-import-one][data-id="${CSS.escape(id)}"]`);
                markAdded(button?.closest('[data-dashboard-card]'), addedLabel(button ?? previewImport));
            } catch {
                // toast already shown
            }
        }
    });
}

/* Mouse / trackpad drag on the product rails (touch scrolls natively). */
function bindRails(page) {
    page.querySelectorAll('.vnd-rail').forEach((rail) => {
        let startX = 0;
        let startScroll = 0;
        let moved = false;
        let pointer = null;

        rail.addEventListener('pointerdown', (event) => {
            if (event.pointerType !== 'mouse' || event.button !== 0 || event.target.closest('a, button, input, select')) {
                return;
            }

            pointer = event.pointerId;
            startX = event.clientX;
            startScroll = rail.scrollLeft;
            moved = false;
        });

        rail.addEventListener('pointermove', (event) => {
            if (pointer !== event.pointerId) {
                return;
            }

            const delta = event.clientX - startX;

            if (!moved && Math.abs(delta) > 5) {
                moved = true;
                rail.classList.add('is-dragging');
                rail.setPointerCapture(pointer);
            }

            if (moved) {
                rail.scrollLeft = startScroll - delta;
            }
        });

        const end = () => {
            if (pointer !== null && rail.hasPointerCapture(pointer)) {
                rail.releasePointerCapture(pointer);
            }

            pointer = null;
            rail.classList.remove('is-dragging');
        };

        rail.addEventListener('pointerup', end);
        rail.addEventListener('pointercancel', end);
    });
}

function bindPreview(page) {
    const widget = page.querySelector('[data-storefront-preview]');
    const viewport = widget?.querySelector('[data-preview-viewport]');
    const frame = widget?.querySelector('[data-preview-frame]');

    if (!viewport || !frame) {
        return;
    }

    const loading = viewport.querySelector('[data-preview-loading]');
    const error = viewport.querySelector('[data-preview-error]');
    let timer = null;

    const scale = () => viewport.style.setProperty('--vnd-preview-scale', String(Math.min(1, viewport.clientWidth / PREVIEW_WIDTH)));
    scale();
    new ResizeObserver(scale).observe(viewport);

    const waiting = () => {
        loading.hidden = false;
        error.hidden = true;
        clearTimeout(timer);
        timer = setTimeout(() => {
            loading.hidden = true;
            error.hidden = false;
        }, PREVIEW_TIMEOUT);
    };

    frame.addEventListener('load', () => {
        clearTimeout(timer);
        loading.hidden = true;
        error.hidden = true;
    });
    frame.addEventListener('error', () => {
        clearTimeout(timer);
        loading.hidden = true;
        error.hidden = false;
    });
    waiting();

    let urls = {};

    try {
        urls = JSON.parse(widget.querySelector('[data-preview-urls]')?.textContent ?? '{}');
    } catch {
        urls = {};
    }

    widget.querySelector('[data-preview-country]')?.addEventListener('change', (event) => {
        const url = urls[event.target.value];

        if (url) {
            waiting();
            frame.src = url;
        }
    });
}

export default function initDashboard(page) {
    bindRemoteModals(page);
    bindActions(page);
    bindRails(page);
    bindPreview(page);
}
