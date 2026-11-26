/**
 * Vendor › Your store › Themes (VEN-030 / 030a / 030b) and Home variants (VEN-031),
 * loaded for [data-page="vendor-themes"]: the "Use … in" countries modal and the
 * home-variant modal are remote fragments; their data-ajax forms (and the card
 * Activate / Select forms, the "Use store default" confirm) answer with a JSON
 * redirect back to the page, which core/http follows.
 */
import { bindRemoteModals } from '../remote-modal.js';

export default function initVendorThemes(page) {
    bindRemoteModals(page);
}
