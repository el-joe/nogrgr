/**
 * Admin affiliate pages (ADM-060…063): detail / edit / pay out / program settings /
 * reverse / promo code modals ([data-remote-modal], remote-modal.js) and row confirms.
 */
import { initOrderPage } from '../orders-shared.js';

export default function init(root) {
    initOrderPage(root);
}
