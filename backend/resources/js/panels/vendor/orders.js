/**
 * Vendor orders / returns / refunds / payment gateways pages (VEN-020…023, VEN-078):
 * print, carrier estimate and selection helpers shared with the admin (orders-shared.js).
 */
import { initOrderPage } from '../orders-shared.js';

export default function init(root) {
    initOrderPage(root);
}
