/**
 * Motion helpers shared by every storefront motion module.
 * Tokens come from CSS custom properties (tokens/storefront-<family>.css):
 * --motion-fast / --motion-base / --motion-slow (ms), --ease-out, --ease-in-out,
 * --ease-spring, --motion-stagger.
 */
const media = (query) => window.matchMedia?.(query);

export const prefersReducedMotion = () => Boolean(media('(prefers-reduced-motion: reduce)')?.matches);

export const isRtl = (element = document.documentElement) => (element.closest?.('[dir]')?.getAttribute('dir') ?? document.documentElement.dir) === 'rtl';

/** Low-end or data-saver devices skip decorative motion (parallax, Ken Burns). */
export const isLowEnd = () => {
    const connection = navigator.connection;

    return Boolean(connection?.saveData) || (navigator.deviceMemory ?? 8) <= 2 || (navigator.hardwareConcurrency ?? 8) <= 2;
};

/** Numeric motion token in ms, read from the nearest element. */
export function token(name, element = document.documentElement, fallback = 280) {
    const value = getComputedStyle(element).getPropertyValue(name).trim();
    const number = parseFloat(value);

    if (Number.isNaN(number)) {
        return fallback;
    }

    return value.endsWith('ms') ? number : value.endsWith('s') ? number * 1000 : number;
}

export const easing = (name = '--ease-out', element = document.documentElement) =>
    getComputedStyle(element).getPropertyValue(name).trim() || 'cubic-bezier(.22,1,.36,1)';

/** Run callback(visible) whenever the element enters / leaves the viewport. */
export function watchVisibility(element, callback, rootMargin = '0px') {
    if (!('IntersectionObserver' in window)) {
        callback(true);
        return () => {};
    }

    const observer = new IntersectionObserver((entries) => entries.forEach((entry) => callback(entry.isIntersecting)), { rootMargin });
    observer.observe(element);

    return () => observer.disconnect();
}

/** Parse a data-* JSON option bag safely. */
export function options(element, name, defaults = {}) {
    try {
        return { ...defaults, ...JSON.parse(element.dataset[name] || '{}') };
    } catch {
        return { ...defaults };
    }
}

/** rAF-throttled handler. */
export function frame(handler) {
    let queued = false;

    return (...args) => {
        if (queued) {
            return;
        }

        queued = true;
        requestAnimationFrame(() => {
            queued = false;
            handler(...args);
        });
    };
}

/**
 * Pause controller for anything that moves on its own: pauses on hover, focus
 * inside, touch, when off-screen, when the tab is hidden and when the user pressed
 * the pause button ([data-motion-pause] inside the element; WCAG 2.2.2).
 */
export function autoPause(element, { play, pause }) {
    const reasons = new Set();
    const button = element.querySelector('[data-motion-pause]');
    const sync = () => (reasons.size ? pause() : play());
    const set = (reason, on) => {
        on ? reasons.add(reason) : reasons.delete(reason);
        sync();
    };

    element.addEventListener('pointerenter', (event) => event.pointerType === 'mouse' && set('hover', true));
    element.addEventListener('pointerleave', (event) => event.pointerType === 'mouse' && set('hover', false));
    element.addEventListener('focusin', () => set('focus', true));
    element.addEventListener('focusout', (event) => !element.contains(event.relatedTarget) && set('focus', false));
    element.addEventListener('touchstart', () => set('touch', true), { passive: true });
    element.addEventListener('touchend', () => setTimeout(() => set('touch', false), 2500), { passive: true });
    document.addEventListener('visibilitychange', () => set('hidden', document.hidden));
    watchVisibility(element, (visible) => set('offscreen', !visible));

    if (prefersReducedMotion()) {
        reasons.add('reduced');
    }

    button?.addEventListener('click', () => {
        const paused = !reasons.has('user');

        /* An explicit "play" wins over the implicit hover / focus / touch pauses. */
        if (!paused) {
            ['hover', 'focus', 'touch'].forEach((reason) => reasons.delete(reason));
        }

        set('user', paused);
        button.setAttribute('aria-pressed', String(paused));
        button.dataset.state = paused ? 'paused' : 'playing';
    });

    sync();

    return { set };
}

/** Pointer capture that never throws (synthetic or already-released pointers). */
export function capture(element, pointerId) {
    try {
        element.setPointerCapture(pointerId);
    } catch {
        // pointer not active: dragging still works without capture
    }
}
