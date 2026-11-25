/**
 * Add-to-cart micro-interactions (docs/plan/07-motion-guidelines.md §4):
 *  - button morph: idle → spinner → check (data-state on [data-add-to-cart])
 *  - fly-to-cart: the product image ([data-fly-image] inside the closest
 *    [data-product]) arcs to the cart icon ([data-cart-target])
 *  - cart count bump on [data-cart-count]
 *  - favourite heart burst on [data-favorite] (aria-pressed toggle)
 * The actual cart request belongs to the Commerce module: a page can listen to
 * `cart:add` (detail: {product, button}) and call event.detail.done(promise) to make
 * the morph wait for it.
 */
import { easing, prefersReducedMotion, token } from './utils';

function bumpCount(target) {
    const counter = document.querySelector('[data-cart-count]');

    if (!counter) {
        return;
    }

    counter.textContent = String((Number(counter.textContent) || 0) + 1);
    counter.hidden = false;
    counter.classList.remove('mo-bump');
    void counter.offsetWidth;
    counter.classList.add('mo-bump');
    target?.classList.remove('mo-bump');
    void target?.offsetWidth;
    target?.classList.add('mo-bump');
}

function fly(image, target) {
    if (!image || !target || prefersReducedMotion() || !image.animate) {
        return Promise.resolve();
    }

    const from = image.getBoundingClientRect();
    const to = target.getBoundingClientRect();
    const ghost = image.cloneNode(true);
    ghost.removeAttribute('id');
    ghost.className = 'mo-fly-ghost';
    ghost.style.width = `${from.width}px`;
    ghost.style.height = `${from.height}px`;
    ghost.style.left = `${from.left}px`;
    ghost.style.top = `${from.top}px`;
    document.body.appendChild(ghost);

    const dx = to.left + to.width / 2 - (from.left + from.width / 2);
    const dy = to.top + to.height / 2 - (from.top + from.height / 2);
    const lift = Math.min(160, Math.abs(dx) * 0.35 + 60);
    const scale = Math.max(0.08, 28 / Math.max(from.width, 1));
    const duration = token('--motion-slow', document.documentElement, 600) + 150;

    return ghost
        .animate(
            [
                { transform: 'translate3d(0,0,0) scale(1)', opacity: 1, offset: 0 },
                { transform: `translate3d(${dx * 0.5}px, ${dy * 0.5 - lift}px, 0) scale(${(1 + scale) / 2})`, opacity: 0.9, offset: 0.55 },
                { transform: `translate3d(${dx}px, ${dy}px, 0) scale(${scale})`, opacity: 0.2, offset: 1 },
            ],
            { duration, easing: easing('--ease-in-out') },
        )
        .finished.catch(() => {})
        .finally(() => ghost.remove());
}

let bound = false;

export function initCart() {
    if (bound) {
        return;
    }

    bound = true;

    document.addEventListener('click', async (event) => {
        const favorite = event.target.closest('[data-favorite]');

        if (favorite) {
            event.preventDefault();
            const pressed = favorite.getAttribute('aria-pressed') !== 'true';
            favorite.setAttribute('aria-pressed', String(pressed));
            favorite.classList.toggle('mo-burst', pressed);
            return;
        }

        const button = event.target.closest('[data-add-to-cart]');

        if (!button || button.dataset.state === 'loading') {
            return;
        }

        event.preventDefault();
        const product = button.closest('[data-product]');
        const image = product?.querySelector('[data-fly-image]');
        /* The cart icon may sit in a header hidden by scroll: show it so the arc has a target. */
        document.querySelector('[data-sticky-header][data-hidden]')?.removeAttribute('data-hidden');
        const target = document.querySelector('[data-cart-target]');
        let pending = Promise.resolve();

        button.dataset.state = 'loading';
        button.setAttribute('aria-busy', 'true');
        button.dispatchEvent(new CustomEvent('cart:add', { bubbles: true, detail: { product: product?.dataset.product, button, done: (promise) => (pending = promise) } }));

        try {
            await Promise.all([pending, fly(image, target)]);
            bumpCount(target);
            button.dataset.state = 'done';
            setTimeout(() => (button.dataset.state = 'idle'), token('--motion-slow') * 2.5);
        } catch {
            button.dataset.state = 'idle';
        } finally {
            button.removeAttribute('aria-busy');
        }
    });
}
