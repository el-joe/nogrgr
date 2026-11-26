/**
 * Live page regions (QA-P8-005): parts of a server-rendered page that depend on the
 * list below them (quota lines, tab counts, "N members · seats used", limit notices,
 * a create button disabled at the plan limit) refresh in place after a change made
 * without a reload.
 *
 *   <p data-live="banner-quota">…</p>          (unique key per page)
 *
 * Any code that changed data calls notifyChanged() (or dispatches `panel:changed` on
 * document). The shared switch handler, remote-modal saves and confirmed actions
 * already do. The page URL is fetched once (debounced) and every [data-live] element
 * is swapped for its counterpart with the same key; a key missing from the response
 * is left as it is. Delegated handlers keep working; injected markup is re-initialised.
 */
import { initUi } from './index.js';

const DEBOUNCE_MS = 150;
let timer = null;
let controller = null;

async function refresh() {
    const regions = [...document.querySelectorAll('[data-live]')];

    if (regions.length === 0) {
        return;
    }

    controller?.abort();
    controller = new AbortController();

    let html;

    try {
        const response = await fetch(window.location.href, {
            headers: { Accept: 'text/html' },
            credentials: 'same-origin',
            signal: controller.signal,
        });

        if (!response.ok) {
            return;
        }

        html = await response.text();
    } catch {
        return;
    }

    const fresh = new DOMParser().parseFromString(html, 'text/html');

    regions.forEach((region) => {
        if (!region.isConnected) {
            return;
        }

        const next = fresh.querySelector(`[data-live="${CSS.escape(region.dataset.live)}"]`);

        if (!next || next.outerHTML === region.outerHTML) {
            return;
        }

        const node = document.importNode(next, true);
        region.replaceWith(node);
        initUi(node.parentElement ?? document);
    });

    document.dispatchEvent(new CustomEvent('panel:refreshed'));
}

/** Ask the page to refresh its live regions (debounced). */
export function notifyChanged(detail = {}) {
    document.dispatchEvent(new CustomEvent('panel:changed', { detail }));
}

let bound = false;

export function initLiveRegions() {
    if (bound) {
        return;
    }

    bound = true;

    const schedule = () => {
        clearTimeout(timer);
        timer = setTimeout(refresh, DEBOUNCE_MS);
    };

    document.addEventListener('panel:changed', schedule);
    document.addEventListener('confirm:done', schedule);
}
