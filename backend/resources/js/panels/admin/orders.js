/**
 * Admin orders, shipping queue and returns pages (ADM-020…024):
 *  - [data-print]                  opens the print dialog (receipt / label / page);
 *  - [data-estimate-url] select    reassign-carrier modal: asks the server for the new
 *                                  delivery estimate and shows it in [data-estimate];
 *  - [data-select-all] / [data-row-select]  shipping queue bulk selection, enables the
 *                                  "Mark shipped" button and reloads the table after it.
 */
import { initOrderPage } from '../orders-shared.js';

export default function init(root) {
    initOrderPage(root);
}
