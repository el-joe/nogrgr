/**
 * "Impersonating" banner countdown (VEN-005): updates "Session ends in N min" every
 * 15 s from data-ends-at and reloads once the time is up, so the server ends the
 * session (EnsureVendorSessionTenant) and returns to the admin panel.
 */
export function initImpersonationBanner(root = document) {
    const banner = root.querySelector('[data-impersonation-banner]');
    const label = banner?.querySelector('[data-impersonation-remaining]');
    const endsAt = Date.parse(banner?.dataset.endsAt ?? '');

    if (!banner || !label || Number.isNaN(endsAt)) {
        return;
    }

    const render = () => {
        const seconds = Math.round((endsAt - Date.now()) / 1000);

        if (seconds <= 0) {
            window.clearInterval(timer);
            window.location.reload();
            return;
        }

        const minutes = Math.max(1, Math.ceil(seconds / 60));
        label.textContent = minutes === 1 ? banner.dataset.labelOne : banner.dataset.labelMany.replace('__COUNT__', String(minutes));
    };

    const timer = window.setInterval(render, 15000);
    render();
}
