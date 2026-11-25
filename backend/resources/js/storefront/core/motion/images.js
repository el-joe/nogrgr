/**
 * Image loading polish: <img data-blur-up> sits on a tiny blurred placeholder
 * (background on its .mo-media wrapper) and fades in once decoded; skeleton
 * shimmer (.mo-skeleton) stays until then. Sizes are reserved by width/height /
 * aspect-ratio, so nothing shifts (CLS 0).
 */
function done(image) {
    image.closest('.mo-media')?.setAttribute('data-loaded', '');
    image.setAttribute('data-loaded', '');
}

export function initImages(root = document) {
    root.querySelectorAll('img[data-blur-up]:not([data-loaded])').forEach((image) => {
        if (image.complete && image.naturalWidth > 0) {
            done(image);
        } else {
            image.addEventListener('load', () => done(image), { once: true });
            image.addEventListener('error', () => done(image), { once: true });
        }
    });
}

/**
 * Number count-up for stats: <span data-count-to="1248" data-count-suffix="+">0</span>,
 * once, when visible; Latin digits in every locale.
 */
export function initCounters(root = document, { prefersReducedMotion, watchVisibility, numberLocale }) {
    root.querySelectorAll('[data-count-to]:not([data-count-ready])').forEach((element) => {
        element.dataset.countReady = '';
        const target = Number(element.dataset.countTo) || 0;
        const suffix = element.dataset.countSuffix ?? '';
        const format = new Intl.NumberFormat(numberLocale());
        const show = (value) => (element.textContent = `${format.format(Math.round(value))}${suffix}`);

        if (prefersReducedMotion()) {
            show(target);
            return;
        }

        const stop = watchVisibility(element, (visible) => {
            if (!visible) {
                return;
            }

            stop();
            const started = performance.now();
            const step = (now) => {
                const progress = Math.min(1, (now - started) / 900);
                show(target * (1 - (1 - progress) ** 3));

                if (progress < 1) {
                    requestAnimationFrame(step);
                }
            };
            requestAnimationFrame(step);
        });
    });
}
