/**
 * Vendor › Your store › Themes (VEN-030 / 030a / 030b) and Themes by country (VEN-031),
 * loaded for [data-page="vendor-themes"]: the "Use … in" countries modal and the
 * per-country theme modal are remote fragments; their data-ajax forms (and the card
 * "Activate as store default" forms, the reset confirms) answer with a JSON redirect
 * back to the page, which core/http follows.
 */
import { bindRemoteModals } from '../remote-modal.js';

export default function initVendorThemes(page) {
    bindRemoteModals(page);
}
