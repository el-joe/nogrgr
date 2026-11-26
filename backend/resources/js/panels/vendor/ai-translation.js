/**
 * Vendor › Settings › AI translation (VEN-074 / 074a), loaded for
 * [data-page="vendor-ai-translation"]:
 *  - progress bars take their width from data-progress (no inline styles in Blade);
 *  - live current run ([data-translation-live] JSON): every Reverb push on the store's
 *    translation-runs channel (and a poll while the run is active) re-reads the status
 *    URL and swaps [data-run-slot]; when the run changes or finishes, the KPI / languages
 *    cards are refreshed by a reload unless the purchase form is in use, otherwise the
 *    run history table reloads in place;
 *  - the language select updates the price note and the "Pay & translate" label;
 *  - the result banner can be dismissed.
 */
import { http } from '../../core/http';
import { subscribePrivate } from '../realtime-channel';

function paintProgress(scope) {
    scope.querySelectorAll('[data-progress]').forEach((bar) => {
        bar.style.width = `${Math.min(100, Math.max(0, Number(bar.dataset.progress) || 0))}%`;
    });
}

function readJson(root, selector) {
    const island = root.querySelector(selector);

    if (!island) {
        return null;
    }

    try {
        return JSON.parse(island.textContent || '{}');
    } catch {
        return null;
    }
}

function reloadHistory(root) {
    root.querySelector('[data-datatable-card]')?.dispatchEvent(new CustomEvent('datatable:reload', { detail: { keepPage: true, quiet: true } }));
}

function bindLiveRun(root) {
    const config = readJson(root, 'script[data-translation-live]');
    const slot = root.querySelector('[data-run-slot]');

    if (!config || !slot) {
        return;
    }

    let loading = false;
    let timer = null;
    const state = () => slot.querySelector('[data-run-current]');
    const isActive = () => state()?.dataset.runActive === '1';

    const refresh = async () => {
        if (loading || document.hidden) {
            return;
        }

        loading = true;

        try {
            const before = state();
            const previous = { run: before?.dataset.runCurrent || '', active: before?.dataset.runActive === '1' };
            const { data } = await http.get(config.statusUrl, undefined, { silent: true });

            slot.innerHTML = data?.html ?? slot.innerHTML;
            paintProgress(slot);

            const changed = String(data?.run ?? '') !== previous.run || Boolean(data?.active) !== previous.active;

            if (changed) {
                const purchase = root.querySelector('[data-ai-translation-purchase]');
                const busy = purchase && (purchase.contains(document.activeElement) || purchase.querySelector('[data-stripe-card]:not([hidden])'));

                if (busy) {
                    reloadHistory(root);
                } else {
                    window.location.reload();
                }
            }
        } catch {
            // Keep the last known state; the next push or poll tries again.
        } finally {
            loading = false;
        }
    };

    const schedule = () => {
        window.clearTimeout(timer);

        if (isActive()) {
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

function bindPurchase(root) {
    const form = root.querySelector('[data-ai-translation-purchase]');
    const config = readJson(root, 'script[data-language-prices]');

    if (!form || !config) {
        return;
    }

    const select = form.querySelector('select[name="language_id"]');
    const note = form.querySelector('[data-price-note] bdi');
    const button = form.querySelector('[data-pay-button]');
    const label = button?.querySelector('.ui-btn-label') ?? button;

    const sync = () => {
        const price = config.prices?.[select.value];

        if (!price) {
            return;
        }

        if (note) {
            note.textContent = config.note.replace(':price', price);
        }

        if (label) {
            label.textContent = config.pay.replace(':price', price);
        }
    };

    select?.addEventListener('change', sync);

    if (select && window.jQuery) {
        window.jQuery(select).on('change', sync);
    }
}

export default function initVendorAiTranslation(root) {
    paintProgress(root);
    bindLiveRun(root);
    bindPurchase(root);

    root.addEventListener('click', (event) => {
        const dismiss = event.target.closest('[data-dismiss-banner]');

        if (dismiss) {
            dismiss.closest('[data-result-banner]')?.remove();
        }
    });
}
