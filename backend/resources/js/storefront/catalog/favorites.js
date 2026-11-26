/**
 * Favourite heart ([data-favorite][data-favorite-url]): the motion module toggles
 * aria-pressed + burst instantly; this persists it (storefront.favorites.toggle) and
 * reverts on failure. Guests are sent to sign in (401 → http.js redirect).
 */
import { http } from '../../core/http';

let bound = false;

export function initFavorites() {
    if (bound) {
        return;
    }

    bound = true;
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-favorite][data-favorite-url]');

        if (!button) {
            return;
        }

        /* The motion handler already flipped aria-pressed in this click. */
        queueMicrotask(async () => {
            const wanted = button.getAttribute('aria-pressed') === 'true';

            try {
                const response = await http.post(button.dataset.favoriteUrl, { store_product_id: Number(button.dataset.productId) }, { toast: false });
                const favorited = Boolean(response?.data?.favorited);
                button.setAttribute('aria-pressed', String(favorited));
                document.dispatchEvent(new CustomEvent('sf:favorites-updated', { detail: response?.data ?? {} }));
            } catch {
                button.setAttribute('aria-pressed', String(!wanted));
                button.classList.remove('mo-burst');
            }
        });
    });
}
