/**
 * Admin › Requests center (page key "admin-requests"): lists, detail pages and the shared
 * request conversation. Behaviour by data attributes:
 *  - [data-request-status]       opens #request-status-modal for data-url / data-current;
 *                                form[data-request-status-form] PATCHes the status.
 *  - [data-request-action]       sends data-method to data-url with data-payload (JSON),
 *                                after a confirm when data-confirm-text is set.
 *  - select[data-request-field]  PATCHes {name: value} to data-url on change (status / priority).
 *  - form[data-reload-form], form[data-payment-form], confirm:done inside
 *    [data-reload-on-change] → reload the page; on list pages the table reloads instead.
 *  - form[data-decision-form]    submit buttons with data-decision set the hidden decision.
 *  - [data-thread]               conversation: Enter-to-send composer with attachments,
 *                                live refresh over Reverb (private-requests.<kind>.<id>)
 *                                with a 15 s polling fallback, read receipts.
 */
import { http } from '../../core/http';
import { t } from '../../core/i18n';
import { $ } from '../../core/select2-lib.js';
import { confirmDialog } from '../ui/confirm';
import { openOverlay, closeOverlay } from '../ui/overlays';

const POLL_MS = 15000;

function refresh(trigger) {
    const card = trigger?.closest?.('[data-datatable-card]') ?? document.querySelector('[data-requests-list] [data-datatable-card]');

    if (card) {
        card.dispatchEvent(new CustomEvent('datatable:reload', { detail: { keepPage: true } }));
    } else {
        window.location.reload();
    }
}

function bindStatusModal() {
    const form = document.querySelector('form[data-request-status-form]');

    if (!form) {
        return;
    }

    const modal = form.closest('[data-overlay]');
    const select = form.querySelector('select[name="status"]');
    let source = null;

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest?.('[data-request-status]');

        if (!trigger) {
            return;
        }

        event.preventDefault();
        source = trigger;
        form.action = trigger.dataset.url;
        $(select).val(trigger.dataset.current).trigger('change');
        openOverlay(modal);
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = document.querySelector(`[form="${form.id}"][type="submit"]`);
        submit?.setAttribute('data-loading', '');

        try {
            await http.patch(form.action, new FormData(form), { form });
            await closeOverlay(modal, { force: true });
            refresh(source);
        } catch {
            // field errors / toast handled by http.js
        } finally {
            submit?.removeAttribute('data-loading');
        }
    });
}

function bindActions() {
    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest?.('[data-request-action]');

        if (!trigger || trigger.hasAttribute('data-loading')) {
            return;
        }

        event.preventDefault();

        if (trigger.dataset.confirmText) {
            const ok = await confirmDialog({
                title: trigger.dataset.confirmTitle,
                message: trigger.dataset.confirmText,
                tone: trigger.dataset.confirmTone || 'danger',
                confirmLabel: trigger.textContent.trim() || t('ui.confirm'),
            });

            if (!ok) {
                return;
            }
        }

        trigger.setAttribute('data-loading', '');
        trigger.setAttribute('aria-busy', 'true');

        try {
            let payload = null;

            try {
                payload = trigger.dataset.payload ? JSON.parse(trigger.dataset.payload) : null;
            } catch {
                payload = null;
            }

            await http.request(trigger.dataset.method || 'POST', trigger.dataset.url, { data: payload });
            refresh(trigger.closest('[data-datatable-card]') ? trigger : null);
        } catch {
            // http.js shows the error toast
        } finally {
            trigger.removeAttribute('data-loading');
            trigger.removeAttribute('aria-busy');
        }
    });
}

function bindFields() {
    document.querySelectorAll('select[data-request-field]').forEach((select) => {
        let previous = select.value;

        select.addEventListener('change', async () => {
            if (select.value === previous) {
                return;
            }

            try {
                await http.patch(select.dataset.url, { [select.name]: select.value });
                previous = select.value;
                window.location.reload();
            } catch {
                $(select).val(previous).trigger('change');
            }
        });
    });
}

function bindReloads() {
    document.addEventListener('ajax:success', (event) => {
        if (event.target.matches?.('form[data-reload-form], form[data-payment-form]')) {
            window.location.reload();
        }
    });

    document.addEventListener('confirm:done', (event) => {
        if (event.target.closest?.('[data-reload-on-change]') || event.target.closest?.('#request-payments')) {
            window.location.reload();
        }
    });

    document.querySelectorAll('form[data-decision-form]').forEach((form) => {
        const input = form.querySelector('[data-decision-input]');

        form.querySelectorAll('[data-decision]').forEach((button) => {
            button.addEventListener('click', () => {
                input.value = button.dataset.decision;
            });
        });
    });
}

/* ---------- conversation ---------- */

function connectRealtime(config, onUpdate, onState) {
    let attempt = 0;

    const open = () => {
        const scheme = config.tls ? 'wss' : 'ws';
        const socket = new WebSocket(`${scheme}://${config.host}:${config.port}/app/${encodeURIComponent(config.key)}?protocol=7&client=js&version=8.4.0&flash=false`);

        socket.addEventListener('message', async (message) => {
            let frame;

            try {
                frame = JSON.parse(message.data);
            } catch {
                return;
            }

            if (frame.event === 'pusher:connection_established') {
                const { socket_id: socketId } = JSON.parse(frame.data);

                try {
                    const auth = await http.post(config.authUrl, { socket_id: socketId, channel_name: config.channel }, { silent: true });
                    socket.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel: config.channel, auth: auth.auth } }));
                } catch {
                    socket.close();
                }

                return;
            }

            if (frame.event === 'pusher_internal:subscription_succeeded') {
                attempt = 0;
                onState(true);
                return;
            }

            if (frame.event === 'pusher:ping') {
                socket.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
                return;
            }

            if (frame.channel === config.channel && frame.event === config.event) {
                onUpdate();
            }
        });

        socket.addEventListener('close', () => {
            onState(false);
            attempt += 1;
            window.setTimeout(open, Math.min(30000, 1000 * 2 ** attempt));
        });
    };

    open();
}

function bindThread(root) {
    const list = root.querySelector('[data-thread-list]');
    const scroller = root.querySelector('[data-thread-scroll]');
    const empty = root.querySelector('[data-thread-empty]');
    const errorLine = root.querySelector('[data-thread-error]');
    const liveDot = root.querySelector('[data-live-dot]');
    const liveLabel = root.querySelector('[data-live-label]');
    const form = root.querySelector('form[data-thread-form]');
    let lastId = Number(root.dataset.lastId || 0);
    let loading = false;
    let live = false;

    const scrollToEnd = () => {
        scroller.scrollTop = scroller.scrollHeight;
    };

    const append = (html) => {
        if (!html) {
            return;
        }

        const template = document.createElement('template');
        template.innerHTML = html.trim();
        template.content.querySelectorAll('[data-message-id]').forEach((row) => {
            if (!list.querySelector(`[data-message-id="${CSS.escape(row.dataset.messageId)}"]`)) {
                list.append(row);
            }
        });
        empty?.classList.add('hidden');
        scrollToEnd();
    };

    const setLive = (state) => {
        live = state;
        liveDot?.classList.toggle('bg-success', state);
        liveDot?.classList.toggle('bg-line-strong', !state);

        if (liveLabel) {
            liveLabel.textContent = state ? liveLabel.dataset.liveText : liveLabel.dataset.offlineText;
        }
    };

    const fetchNew = async () => {
        if (loading) {
            return;
        }

        loading = true;

        try {
            const payload = await http.get(root.dataset.messagesUrl, { after: lastId }, { silent: true });
            append(payload.data?.html);
            lastId = Math.max(lastId, Number(payload.data?.last_id || 0));

            const badge = document.querySelector('[data-request-status-badge]');

            if (badge && payload.data?.status?.label) {
                badge.textContent = payload.data.status.label;
            }

            errorLine?.classList.add('hidden');
        } catch {
            errorLine?.classList.remove('hidden');
        } finally {
            loading = false;
        }
    };

    scrollToEnd();

    const island = root.querySelector('script[data-thread-realtime]');
    const config = island ? JSON.parse(island.textContent || '{}') : null;

    if (config?.key && 'WebSocket' in window) {
        connectRealtime(config, fetchNew, setLive);
    }

    window.setInterval(() => {
        if (!live && document.visibilityState === 'visible') {
            fetchNew();
        }
    }, POLL_MS);

    if (!form) {
        return;
    }

    const body = form.querySelector('[data-thread-body]');
    const fileInput = form.querySelector('[data-thread-file-input]');
    const chips = form.querySelector('[data-thread-files]');
    let files = [];

    const renderFiles = () => {
        chips.replaceChildren(
            ...files.map((file, index) => {
                const item = document.createElement('li');
                item.className = 'inline-flex items-center gap-1 rounded-control bg-subtle px-2 py-1 text-xs text-ink';
                const name = document.createElement('span');
                name.className = 'max-w-[180px] truncate';
                name.textContent = file.name;
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'grid size-5 place-items-center rounded-full hover:bg-surface';
                remove.textContent = '×';
                remove.setAttribute('aria-label', (chips.dataset.removeLabel || '').replace(':name', file.name));
                remove.addEventListener('click', () => {
                    files.splice(index, 1);
                    renderFiles();
                });
                item.append(name, remove);

                return item;
            }),
        );
    };

    const autosize = () => {
        body.style.height = 'auto';
        body.style.height = `${Math.min(body.scrollHeight, 160)}px`;
    };

    fileInput.addEventListener('change', () => {
        files = [...files, ...Array.from(fileInput.files || [])].slice(0, 5);
        fileInput.value = '';
        renderFiles();
    });

    body.addEventListener('input', autosize);
    body.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!body.value.trim() && files.length === 0) {
            body.focus();
            return;
        }

        const submit = form.querySelector('[type="submit"]');
        const data = new FormData();
        data.append('body', body.value);
        files.forEach((file) => data.append('files[]', file));
        submit?.setAttribute('data-loading', '');
        submit?.setAttribute('aria-busy', 'true');

        try {
            const payload = await http.post(form.action, data, { form, toast: false });
            append(payload.data?.html);
            lastId = Math.max(lastId, Number(payload.data?.last_id || 0));
            body.value = '';
            files = [];
            renderFiles();
            autosize();
        } catch {
            // 422 errors are shown under the composer by http.js
        } finally {
            submit?.removeAttribute('data-loading');
            submit?.removeAttribute('aria-busy');
            body.focus();
        }
    });
}

export default function initRequests() {
    bindStatusModal();
    bindActions();
    bindFields();
    bindReloads();
    document.querySelectorAll('[data-thread]').forEach(bindThread);
}
