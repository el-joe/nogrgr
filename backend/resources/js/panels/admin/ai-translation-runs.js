/**
 * Admin AI translation runs (P10, ADM-091b catalogue / ADM-092a static keys):
 *  - [data-ai-catalog-start] (Admin › Languages row action): confirm → POST → progress modal;
 *  - #ai-keys-form (translations editor "Translate with AI" modal): the scope radios swap
 *    the estimate and the submit label; a successful submit opens the progress modal;
 *  - [data-ai-run-open] (page banner) opens the progress modal of a run;
 *  - progress modal #ai-run-modal: body = the run's status HTML, re-read on every Reverb
 *    push on "admin.translation-runs" and polled while the run is active (fallback);
 *    Retry now / Resume and Stop post to the run and repaint;
 *  - [data-ai-suggest] (edit key modal): one-key suggestion fills that language's textarea.
 */
import { http } from '../../core/http';
import { notify } from '../../core/notify';
import { openOverlay, confirmDialog, initUi, notifyChanged } from '../ui/index.js';
import { subscribePrivate } from '../realtime-channel';

let current = null;

function readLive() {
    const island = document.querySelector('script[data-ai-run-live]');

    try {
        return island ? JSON.parse(island.textContent || '{}') : null;
    } catch {
        return null;
    }
}

function paint(scope) {
    scope.querySelectorAll('[data-progress]').forEach((bar) => {
        bar.style.width = `${Math.min(100, Math.max(0, Number(bar.dataset.progress) || 0))}%`;
    });
}

function modal() {
    return document.getElementById('ai-run-modal');
}

function render(data) {
    const dialog = modal();

    if (!dialog || !data?.html) {
        return;
    }

    const body = dialog.querySelector('[data-ai-run-body]');
    const wasActive = current?.active;

    current = { url: data.status_url, active: Boolean(data.active), run: data.run };
    body.innerHTML = data.html;
    dialog.querySelector('[data-ai-run-loading]').hidden = true;
    dialog.querySelector('[data-ai-run-error]').hidden = true;
    body.hidden = false;
    paint(body);
    initUi(body);

    const title = dialog.querySelector(`#${CSS.escape(dialog.id)}-title`);
    const state = body.querySelector('[data-ai-run-state]');

    if (title && state?.dataset.modalTitle) {
        title.textContent = state.dataset.modalTitle;
    }

    if (wasActive && !current.active) {
        document.querySelectorAll('[data-datatable-card]').forEach((card) => card.dispatchEvent(new CustomEvent('datatable:reload', { detail: { keepPage: true, quiet: true } })));
        notifyChanged({ source: 'ai-translation' });
    }
}

async function refresh() {
    if (!current?.url) {
        return;
    }

    try {
        const { data } = await http.get(current.url, undefined, { silent: true });
        render(data);
    } catch {
        // Keep the last state; the next push or poll tries again.
    }
}

let timer = null;

function schedule(pollMs) {
    window.clearTimeout(timer);

    if (current?.active) {
        timer = window.setTimeout(async () => {
            await refresh();
            schedule(pollMs);
        }, pollMs);
    }
}

async function open(url, live) {
    const dialog = modal();

    if (!dialog) {
        return;
    }

    dialog.querySelector('[data-ai-run-loading]').hidden = false;
    dialog.querySelector('[data-ai-run-error]').hidden = true;
    dialog.querySelector('[data-ai-run-body]').hidden = true;
    openOverlay(dialog);

    try {
        const { data } = await http.get(url, undefined, { silent: true });
        render(data);
        schedule(live?.pollMs || 5000);
    } catch {
        dialog.querySelector('[data-ai-run-loading]').hidden = true;
        dialog.querySelector('[data-ai-run-error]').hidden = false;
    }
}

function show(data, live) {
    const dialog = modal();

    if (!dialog) {
        return;
    }

    openOverlay(dialog);
    render(data);
    schedule(live?.pollMs || 5000);
}

function busy(button, on) {
    button.toggleAttribute('data-loading', on);
    button.setAttribute('aria-busy', on ? 'true' : 'false');
    button.disabled = on;
}

function bindKeysForm(live) {
    document.addEventListener('change', (event) => {
        const radio = event.target.closest('[data-ai-keys-scope]');
        const form = radio?.closest('[data-ai-keys-form]');

        if (!form) {
            return;
        }

        form.querySelectorAll('[data-ai-keys-estimate]').forEach((block) => {
            block.hidden = block.dataset.aiKeysEstimate !== radio.value;

            if (!block.hidden) {
                const submit = form.closest('.ui-overlay')?.querySelector('[data-remote-submit]');
                const label = submit?.querySelector('.ui-btn-label') ?? submit;

                if (label) {
                    label.textContent = block.dataset.submitLabel;
                }

                submit?.toggleAttribute('disabled', Number(block.dataset.keys) === 0);
            }
        });
    });

    document.addEventListener('ajax:success', (event) => {
        if (event.target.matches?.('[data-ai-keys-form]') && event.detail?.data?.html) {
            window.setTimeout(() => show(event.detail.data, live), 50);
        }
    });
}

async function suggest(button) {
    const form = button.closest('form');
    const field = form?.querySelector(`textarea[name="values[${CSS.escape(button.dataset.locale)}]"]`);

    if (!field) {
        return;
    }

    busy(button, true);

    try {
        const { data } = await http.post(button.dataset.url, { file: button.dataset.file, key: button.dataset.key, locale: button.dataset.locale }, { toast: false });
        field.value = data?.text ?? field.value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        notify.success(button.dataset.suggested);
    } catch {
        // http shows the error toast.
    } finally {
        busy(button, false);
    }
}

export default function initAiTranslationRuns() {
    const live = readLive();

    if (live?.realtime) {
        subscribePrivate(live.realtime, (event) => {
            if (current && Number(event?.id) === Number(current.run)) {
                refresh().then(() => schedule(live.pollMs || 5000));
            }
        });
    }

    bindKeysForm(live);

    document.addEventListener('click', async (event) => {
        const start = event.target.closest('[data-ai-catalog-start]');

        if (start) {
            event.preventDefault();
            const ok = await confirmDialog({ title: start.dataset.aiTitle, message: start.dataset.aiMessage, confirmLabel: start.dataset.aiConfirm, tone: 'brand' });

            if (ok) {
                try {
                    const { data } = await http.post(start.dataset.url, { locale: start.dataset.locale });
                    show(data, live);
                } catch {
                    // http shows the error toast.
                }
            }

            return;
        }

        const opener = event.target.closest('[data-ai-run-open]');

        if (opener) {
            event.preventDefault();
            open(opener.dataset.url, live);

            return;
        }

        const suggestButton = event.target.closest('[data-ai-suggest]');

        if (suggestButton) {
            event.preventDefault();
            suggest(suggestButton);
        }
    });

    modal()?.addEventListener('click', async (event) => {
        const retry = event.target.closest('[data-ai-run-retry]');
        const stop = event.target.closest('[data-ai-run-stop]');
        const button = retry ?? stop;

        if (!button) {
            return;
        }

        event.preventDefault();

        if (stop && !(await confirmDialog({ title: stop.dataset.aiTitle, message: stop.dataset.aiMessage, confirmLabel: stop.dataset.aiConfirm }))) {
            return;
        }

        busy(button, true);

        try {
            const { data } = await http.post(button.dataset.url, {});
            render(data);
            schedule(live?.pollMs || 5000);
        } catch {
            busy(button, false);
        }
    });
}
