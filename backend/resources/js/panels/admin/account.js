/**
 * Admin › My profile (ADM-005): clears the password form after a successful change
 * and refreshes the avatar shown in the top bar after the profile is saved.
 */
export default function initAccount(root) {
    root.querySelectorAll('form[data-reset-on-success]').forEach((form) => {
        form.addEventListener('ajax:success', () => form.reset());
    });

    root.querySelector('#profile-form')?.addEventListener('ajax:success', (event) => {
        const url = event.detail?.data?.avatar_url;

        document.querySelectorAll('[data-user-avatar]').forEach((avatar) => {
            const image = avatar.querySelector('img');

            if (url && image) {
                image.src = url;
            } else if (url || image) {
                window.location.reload();
            }
        });
    });
}
