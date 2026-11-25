/**
 * Page transitions with the View Transitions API (cross-document, CSS
 * `@view-transition { navigation: auto }` in motion.css). Shared-element morph: a link
 * with data-vt-image="product-42" names its image `vt-product-42` just before
 * navigation; the target page gives its hero/gallery image the same name
 * (data-vt-name="product-42"). Browsers without support simply navigate; reduced
 * motion turns transitions off in CSS.
 */
import { prefersReducedMotion } from './utils';

const name = (value) => `vt-${String(value).replace(/[^a-z0-9-]/gi, '-')}`;

export function initTransitions(root = document) {
    root.querySelectorAll('[data-vt-name]').forEach((element) => {
        element.style.viewTransitionName = name(element.dataset.vtName);
    });

    if (prefersReducedMotion() || root !== document) {
        return;
    }

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-vt-image]');

        if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
            return;
        }

        document.querySelectorAll('[style*="view-transition-name"]').forEach((element) => {
            if (!element.dataset.vtName) {
                element.style.viewTransitionName = '';
            }
        });

        const image = link.querySelector('img') ?? link;
        image.style.viewTransitionName = name(link.dataset.vtImage);
    });

    /* bfcache restore: clear names so the next navigation starts clean. */
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('a[data-vt-image] img').forEach((image) => (image.style.viewTransitionName = ''));
    });
}
