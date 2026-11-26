/**
 * Shared conversation ([data-thread]) of the admin Requests center, the vendor Requests
 * pages and the Support tickets (admin / vendor / owner): Enter-to-send composer with up to 5 attachments, live refresh over
 * Reverb (private-requests.<kind>.<id>, channel auth URL from the JSON island) with a
 * 15 s polling fallback, read receipts and the status badge refresh.
 */
import { http } from '../core/http';

const POLL_MS = 15000;

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

export function bindThread(root) {
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
        // Extra composer fields (e.g. the support "Internal note" switch): checked boxes / values.
        form.querySelectorAll('[data-thread-extra]').forEach((input) => {
            if (input.type === 'checkbox' ? input.checked : input.value !== '') {
                data.append(input.name, input.type === 'checkbox' ? input.value || '1' : input.value);
            }
        });
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
