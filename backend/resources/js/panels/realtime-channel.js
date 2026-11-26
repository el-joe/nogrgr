/**
 * Subscribe to one private Reverb channel over the Pusher protocol (no client
 * library): config {key, host, port, tls, channel, authUrl, event}. Calls onEvent(data)
 * for every matching event and onState(connected) on (re)connects; reconnects with
 * exponential backoff. Returns a stop() function.
 */
import { http } from '../core/http';

export function subscribePrivate(config, onEvent, onState = () => {}) {
    let attempt = 0;
    let socket = null;
    let stopped = false;

    const open = () => {
        const scheme = config.tls ? 'wss' : 'ws';
        socket = new WebSocket(`${scheme}://${config.host}:${config.port}/app/${encodeURIComponent(config.key)}?protocol=7&client=js&version=8.4.0&flash=false`);

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
                let data = frame.data;

                try {
                    data = typeof data === 'string' ? JSON.parse(data) : data;
                } catch {
                    data = {};
                }

                onEvent(data);
            }
        });

        socket.addEventListener('close', () => {
            onState(false);

            if (stopped) {
                return;
            }

            attempt += 1;
            window.setTimeout(open, Math.min(30000, 1000 * 2 ** attempt));
        });
    };

    open();

    return () => {
        stopped = true;
        socket?.close();
    };
}
