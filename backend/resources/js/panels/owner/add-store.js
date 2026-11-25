/**
 * Owner › Add another store (OWN-007): store address suggested from the store name
 * until edited, cleaned to a valid sub-domain, and checked live (debounced) against
 * GET owner/stores/availability; the helper shows the full address.
 */
import { http } from '../../core/http.js';

const DEBOUNCE_MS = 400;

const slugify = (value) => value
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9-]+/g, '-')
    .replace(/-{2,}/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 63);

export default function initAddStore(root = document) {
    const form = root.querySelector('[data-add-store-form]');
    const name = form?.querySelector('[data-store-name]');
    const slug = form?.querySelector('[data-store-slug]');
    const status = form?.querySelector('[data-slug-status]');

    if (!form || !name || !slug || !status) {
        return;
    }

    let edited = slug.value !== '';
    let timer = null;
    let sequence = 0;

    const check = () => {
        clearTimeout(timer);
        const value = slug.value.trim();

        if (value === '') {
            status.hidden = true;
            return;
        }

        timer = setTimeout(async () => {
            const current = ++sequence;
            status.hidden = false;
            status.className = 'text-xs text-ink-muted';
            status.textContent = form.dataset.checking ?? '…';

            try {
                const url = `${form.dataset.availabilityUrl}?${new URLSearchParams({ slug: value })}`;
                const result = await http.get(url, null, { toast: false, silent: true });

                if (current !== sequence) {
                    return;
                }

                status.className = result.available ? 'text-xs text-success' : 'text-xs text-danger';
                status.textContent = result.message ?? '';
            } catch {
                status.hidden = true;
            }
        }, DEBOUNCE_MS);
    };

    name.addEventListener('input', () => {
        if (!edited) {
            slug.value = slugify(name.value);
            check();
        }
    });

    slug.addEventListener('input', () => {
        edited = slug.value !== '';
        const cleaned = slugify(slug.value);

        if (cleaned !== slug.value && !slug.value.endsWith('-')) {
            slug.value = cleaned;
        }

        check();
    });
}
