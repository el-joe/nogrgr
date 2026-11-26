/**
 * Admin › Sales › Promotions: coupons (ADM-025) and flash sales (ADM-026). The create /
 * edit / view modals are loaded from the server (remote-modal.js); the product picker
 * inside the flash-sale form is a remote Select2 wired by remote-modal on load.
 */
import { bindRemoteModals } from '../remote-modal.js';

export default function initPromotions(root) {
    bindRemoteModals(root);
}
