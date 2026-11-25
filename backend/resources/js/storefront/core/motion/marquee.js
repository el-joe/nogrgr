/**
 * Infinite marquee strip (Featured / trust strip, Figma 1:29865):
 *   <div class="mo-marquee" data-marquee='{"speed":40}'>
 *     <div class="mo-marquee-track" data-marquee-track>…items…</div>
 *     <button data-motion-pause aria-pressed="false">…</button>
 *   </div>
 * The track is cloned once for a seamless loop and moved with transform only. It can
 * be dragged (mouse / touch, vertical scroll untouched), pauses on hover, focus,
 * touch, when off-screen and via the pause button; direction mirrors in RTL; static
 * with reduced motion.
 */
import { autoPause, isRtl, options, prefersReducedMotion, watchVisibility, capture } from './utils';

export function initMarquee(element) {
    const track = element.querySelector('[data-marquee-track]');

    if (!track) {
        return null;
    }

    const config = options(element, 'marquee', { speed: 40 });
    const clone = track.cloneNode(true);
    clone.setAttribute('aria-hidden', 'true');
    clone.removeAttribute('data-marquee-track');
    clone.querySelectorAll('a, button').forEach((node) => node.setAttribute('tabindex', '-1'));
    track.after(clone);

    const lane = track.parentElement;
    const direction = isRtl(element) ? 1 : -1;
    let offset = 0;
    let running = !prefersReducedMotion();
    let last = performance.now();
    let width = track.getBoundingClientRect().width;
    let dragging = null;

    const apply = () => {
        if (width > 0) {
            offset = ((offset % width) + width) % width;
        }

        lane.style.transform = `translate3d(${direction * offset}px, 0, 0)`;
    };

    const tick = (now) => {
        const elapsed = Math.min(64, now - last);
        last = now;

        if (running && !dragging) {
            offset += (config.speed * elapsed) / 1000;
            apply();
        }

        if (element.isConnected) {
            requestAnimationFrame(tick);
        }
    };

    new ResizeObserver(() => (width = track.getBoundingClientRect().width)).observe(track);

    element.addEventListener('pointerdown', (event) => {
        if (event.button !== 0 || event.target.closest('[data-motion-pause]')) {
            return;
        }

        dragging = { x: event.clientX, y: event.clientY, start: offset, axis: null, id: event.pointerId };
    });

    element.addEventListener('pointermove', (event) => {
        if (!dragging) {
            return;
        }

        const dx = event.clientX - dragging.x;
        const dy = event.clientY - dragging.y;
        dragging.axis ??= Math.abs(dx) > 6 ? 'x' : Math.abs(dy) > 6 ? 'y' : null;

        if (dragging.axis === 'x') {
            capture(element, dragging.id);
            element.dataset.dragging = '';
            offset = dragging.start - direction * dx;
            apply();
        } else if (dragging.axis === 'y') {
            dragging = null;
        }
    });

    const end = (event) => {
        if (dragging?.axis === 'x') {
            event.preventDefault();
            /* A drag is not a click on the item underneath. */
            element.addEventListener('click', (click) => click.preventDefault(), { once: true, capture: true });
        }

        dragging = null;
        delete element.dataset.dragging;
    };

    element.addEventListener('pointerup', end);
    element.addEventListener('pointercancel', end);

    autoPause(element, {
        play: () => {
            running = true;
            last = performance.now();
        },
        pause: () => (running = false),
    });

    watchVisibility(element, () => {}, '0px');
    requestAnimationFrame(tick);

    return { pause: () => (running = false) };
}
