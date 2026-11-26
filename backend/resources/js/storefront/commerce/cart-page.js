/**
 * Cart page (x-sf::cart.body + cart.summary, SF-ECO-022): Store API mutations through
 * lines.js, then one `storefront.cart.fragment` request re-renders the body and the
 * summary (prices are never computed in the browser). Removing the last line swaps in
 * the empty state.
 */
import { bindCartLines, enhanceLines } from './lines';
import { fragmentLoader, setCartCount } from './api';

export function initCartPage(page) {
    if (page.__cartPage) {
        return;
    }

    page.__cartPage = true;
    const region = page.querySelector('[data-cart-region]');
    const summary = page.querySelector('[data-cart-summary-region]');
    const load = fragmentLoader(page.dataset.fragmentUrl);

    const refresh = async () => {
        region.setAttribute('aria-busy', 'true');

        try {
            const data = await load();
            region.innerHTML = data.html ?? region.innerHTML;

            if (summary && data.summary) {
                summary.innerHTML = data.summary;
            }

            setCartCount(data.items_count);
            enhanceLines(page);
        } catch (error) {
            if (error?.name !== 'AbortError') {
                region.querySelectorAll('[aria-busy="true"]').forEach((row) => row.removeAttribute('aria-busy'));
            }
        } finally {
            region.setAttribute('aria-busy', 'false');
        }
    };

    bindCartLines(page, refresh);
    enhanceLines(page);
}
