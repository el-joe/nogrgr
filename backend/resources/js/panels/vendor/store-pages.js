/**
 * Vendor › Your store › Pages (VEN-033 / 033a), loaded for [data-page="vendor-store-pages"]:
 * the list needs nothing beyond the shared datatable / switches / confirm dialog; the
 * create / edit page gets the rich-text editors and the title → slug helper shared with
 * Admin › Website pages (panels/admin/content.js).
 */
import initContent from '../admin/content.js';

export default function initStorePages(root) {
    initContent(root ?? document);
}
