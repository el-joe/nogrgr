/**
 * Cart page (x-sf::cart.body + cart.summary, SF-ECO-022): Store API mutations through
 * lines.js, then one `storefront.cart.fragment` request re-renders the body and the
 * summary (prices are never computed in the browser). Removing the last line swaps in
 * the empty state.
 */
import { bindCartLines, enhanceLines } from './lines';
import { fragmentLoader, setCartCount } from './api';
import { initReveal } from '../../core/reveal';
import { initMotion } from '../core/motion';

export function initCartPage(page) {
    if (page.__cartPage) {
        return;
    }

    page.__cartPage = true;
    const region = page.querySelector('[data-cart-region]');
    const summary = page.querySelector('[data-cart-summary-region]');
    const load = fragmentLoader(page.dataset.fragmentUrl);
    /* Optional (Elora): rails that follow the cart contents; they sit next to the page element. */
    const rails = document.querySelector('[data-cart-rails]');

    const refresh = async () => {
        region.setAttribute('aria-busy', 'true');

        try {
            const data = await load(rails ? { rails: rails.dataset.railsKey || '-' } : {});
            region.innerHTML = data.html ?? region.innerHTML;

            if (summary && data.summary) {
                summary.innerHTML = data.summary;
            }

            if (rails && typeof data.rails === 'string') {
                rails.innerHTML = data.rails;
                rails.dataset.railsKey = data.rails_key ?? '';
                initReveal(rails);
                initMotion(rails);
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
    /* Added from outside the cart lines (a rail card, its variant picker, the mini cart popup). */
    document.addEventListener('sf:cart-updated', refresh);
    enhanceLines(page);
}
