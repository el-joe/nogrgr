/**
 * Owner › Profile (OWN-006): submit buttons show their loading state while the
 * profile / password forms post (full page submit, errors come back inline).
 */
export default function initOwnerProfile(root = document) {
    root.querySelectorAll('[data-owner-profile-form], [data-owner-password-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const submit = form.querySelector('[type="submit"]');
            submit?.setAttribute('data-loading', '');
            submit?.setAttribute('aria-busy', 'true');
        });
    });
}
