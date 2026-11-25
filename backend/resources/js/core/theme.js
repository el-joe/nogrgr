/**
 * Dark / light toggle for panels. Server renders <html class="dark"> from the
 * `theme` cookie (no inline script, no flash). Without a cookie the panel follows
 * prefers-color-scheme until the user picks a mode; the choice is then kept in the
 * cookie (and later on the admin's profile).
 *   <button data-theme-toggle>…</button>   <button data-theme-set="dark">…</button>
 */
const hasCookie = () => document.cookie.split('; ').some((part) => part.startsWith('theme='));

function apply(dark, persist) {
    document.documentElement.classList.toggle('dark', dark);
    document.querySelectorAll('[data-theme-toggle], [data-theme-set]').forEach((button) => {
        const pressed = button.dataset.themeSet ? (button.dataset.themeSet === 'dark') === dark : dark;
        button.setAttribute(button.getAttribute('role') === 'menuitemcheckbox' ? 'aria-checked' : 'aria-pressed', String(pressed));
    });

    if (persist) {
        document.cookie = `theme=${dark ? 'dark' : 'light'}; path=/; max-age=31536000; SameSite=Lax`;
    }

    document.dispatchEvent(new CustomEvent('theme:change', { detail: { dark } }));
}

export function initThemeToggle(root = document) {
    const media = window.matchMedia('(prefers-color-scheme: dark)');

    if (!hasCookie()) {
        apply(media.matches, false);
        media.addEventListener('change', (event) => !hasCookie() && apply(event.matches, false));
    } else {
        apply(document.documentElement.classList.contains('dark'), false);
    }

    root.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-theme-toggle], [data-theme-set]');

        if (!button) {
            return;
        }

        const dark = button.dataset.themeSet ? button.dataset.themeSet === 'dark' : !document.documentElement.classList.contains('dark');
        apply(dark, true);
    });
}
