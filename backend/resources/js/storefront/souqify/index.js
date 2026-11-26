/**
 * "souqify" family: home variants + shared inner pages (catalog modules in
 * resources/js/storefront/catalog/*; motion from core/motion via data-* attributes).
 */
import { initCart } from '../catalog/cart';
import { initFavorites } from '../catalog/favorites';
import { initSearch } from '../catalog/search';
import { initImageSearch } from '../catalog/image-search';
import { initListing } from '../catalog/listing';
import { initUi } from '../catalog/ui';
import { initCommerce } from '../commerce/index.js';
import { initSouqifyHome } from './home.js';
import { initSouqifyPages } from './pages.js';

export function initSouqify() {
    initCart();
    initFavorites();
    initSearch(document);
    initImageSearch(document);
    initListing(document);
    initUi(document);
    initCommerce(document);
    initSouqifyHome(document);
    initSouqifyPages(document);

    if (document.querySelector('[data-product-page]')) {
        import('../catalog/product').then(({ initProduct }) => initProduct(document));
    }
}
