/**
 * Topbar bell (x-ui::notification-dropdown) for central panels:
 *  - [data-notification-realtime] JSON → subscribes to the user's private Reverb channel
 *    over the Pusher protocol (no extra client library) and prepends pushed rows,
 *    bumps the unread count and shows a toast. Reconnects with backoff.
 *  - "Mark all read" in the dropdown or on the inbox page clears the count.
 *  - `panel-notifications:changed` (sent by the inbox page after an action) re-reads
 *    the feed from data-latest-url so the bell stays in sync.
 * Every pushed row also fires `panel-notifications:received` on window.
 */
import { http } from '../core/http';
import { notify } from '../core/notify';

let bell = null;
let config = null;

const formatCount = (count) => (count > 99 ? '99+' : String(count));

function setUnread(count) {
    if (!bell) {
        return;
    }

    bell.dataset.unread = String(count);
    const badge = bell.querySelector('[data-notification-count]');
    const label = bell.querySelector('[data-notification-new-label]');

    if (badge) {
        badge.textContent = formatCount(count);
        badge.hidden = count === 0;
    }

    bell.querySelector('[data-notification-new]')?.toggleAttribute('hidden', count === 0);
    bell.querySelector('[data-notification-mark-all]')?.toggleAttribute('hidden', count === 0);
    document.querySelector('[data-notification-mark-all-page]')?.toggleAttribute('hidden', count === 0);

    if (label && config?.newLabel) {
        label.textContent = config.newLabel.replace(':count', String(count));
    }
}

function rowFor(item) {
    const template = bell.querySelector('template[data-notification-template]');
    const row = template.content.firstElementChild.cloneNode(true);
    const link = row.querySelector('a');
    const tone = row.querySelector('[data-tone]');

    row.dataset.notificationId = item.id;
    link.href = item.url || (config?.openUrl ? config.openUrl.replace('00000000-0000-0000-0000-000000000000', encodeURIComponent(item.id)) : '#');
    link.classList.toggle('bg-primary-subtle/60', Boolean(item.unread));
    row.querySelector('[data-title]').textContent = item.title;
    row.querySelector('[data-meta]').textContent = item.meta || '';
    row.querySelector('[data-time]').textContent = item.time || '';
    row.querySelector('[role="img"]')?.toggleAttribute('hidden', !item.unread);

    if (config?.tones?.[item.tone]) {
        tone.className = `grid size-9 shrink-0 place-items-center rounded-full ${config.tones[item.tone]}`;
    }

    if (config?.icons?.[item.icon]) {
        tone.innerHTML = config.icons[item.icon];
    }

    return row;
}

function render(items) {
    const list = bell.querySelector('[data-notification-list]');

    if (!list) {
        return;
    }

    list.replaceChildren(...items.map(rowFor));
    list.hidden = items.length === 0;
    bell.querySelector('[data-notification-empty]')?.toggleAttribute('hidden', items.length > 0);
}

function prepend(item) {
    const list = bell.querySelector('[data-notification-list]');

    if (!list || list.querySelector(`[data-notification-id="${CSS.escape(item.id)}"]`)) {
        return;
    }

    list.prepend(rowFor(item));
    [...list.children].slice(6).forEach((child) => child.remove());
    list.hidden = false;
    bell.querySelector('[data-notification-empty]')?.setAttribute('hidden', '');
    setUnread(Number(bell.dataset.unread || 0) + 1);
}

async function refresh() {
    const url = bell?.dataset.latestUrl;

    if (!url) {
        return;
    }

    try {
        const { data } = await http.get(url, null, { silent: true });
        render(data.items ?? []);
        setUnread(Number(data.unread ?? 0));
    } catch {
        // keep the current bell; the next push or page load resyncs it
    }
}

function connect(attempt = 0) {
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
                attempt = 0;
            } catch {
                socket.close();
            }

            return;
        }

        if (frame.event === 'pusher:ping') {
            socket.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
            return;
        }

        if (frame.channel === config.channel && frame.event === config.event) {
            const payload = typeof frame.data === 'string' ? JSON.parse(frame.data) : frame.data;
            const item = payload?.item;

            if (item) {
                prepend(item);
                notify.info(item.title, item.meta || '');
                window.dispatchEvent(new CustomEvent('panel-notifications:received', { detail: item }));
            }
        }
    });

    socket.addEventListener('close', () => {
        const delay = Math.min(30000, 1000 * 2 ** attempt);
        window.setTimeout(() => connect(attempt + 1), delay);
    });
}

export function initNotificationBell() {
    bell = document.querySelector('[data-notification-bell]');

    if (!bell) {
        return;
    }

    const island = bell.querySelector('script[data-notification-realtime]');
    config = island ? JSON.parse(island.textContent || '{}') : null;
    bell.dataset.unread = String(Number(bell.querySelector('[data-notification-count]')?.textContent?.replace('+', '') || 0));

    document.addEventListener('ajax:success', (event) => {
        if (event.target.matches?.('[data-notification-mark-all], [data-notification-mark-all-page]')) {
            setUnread(0);
            refresh();
            window.dispatchEvent(new CustomEvent('panel-notifications:changed', { detail: { source: 'bell' } }));
        }
    });

    window.addEventListener('panel-notifications:changed', (event) => {
        if (event.detail?.source !== 'bell') {
            refresh();
        }
    });

    if (config?.key && 'WebSocket' in window) {
        connect();
    }
}
