/**
 * Vendor › Your store › Appearance (VEN-034), loaded for [data-page="vendor-appearance"]:
 *
 *   - link / social add & edit modals (remote fragments)
 *   - colour swatches: each [data-swatch] gets its colour as --swatch (no inline style in
 *     the markup); the custom colour input adds / selects the custom swatch; the preview
 *     button follows the chosen colour (--preview-brand / --preview-ink) and font
 *   - promo preview follows the text / background inputs
 *   - header / footer links: drag & drop order ([data-link-sort]), saved on drop
 */
import { http } from '../../core/http.js';
import { loadSortable } from '../core.js';
import { bindRemoteModals } from '../remote-modal.js';
import initAiLogo from './ai-logo.js';

const FONT_FAMILIES = {
    outfit: 'Outfit',
    inter: 'Inter',
    manrope: 'Manrope',
    'noto-sans-arabic': 'Noto Sans Arabic',
    'ibm-plex-sans-arabic': 'IBM Plex Sans Arabic',
    tajawal: 'Tajawal',
};

function readableInk(hex) {
    const value = /^#([0-9a-f]{6})$/i.exec(hex ?? '')?.[1];

    if (!value) {
        return '#ffffff';
    }

    const channel = (offset) => {
        const c = parseInt(value.slice(offset, offset + 2), 16) / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };
    const luminance = 0.2126 * channel(0) + 0.7152 * channel(2) + 0.0722 * channel(4);

    return 1.05 / (luminance + 0.05) >= (luminance + 0.05) / 0.0637 ? '#ffffff' : '#121212';
}

function paintSwatches(root) {
    root.querySelectorAll('[data-swatch]').forEach((swatch) => swatch.style.setProperty('--swatch', swatch.dataset.swatch));
}

function bindColors(root) {
    const form = root.querySelector('[data-colors-form]');

    if (!form) {
        return;
    }

    const preview = form.querySelector('[data-color-preview]');
    const picker = form.querySelector('[data-custom-color]');
    const hexLabel = form.querySelector('[data-custom-hex]');
    const customSwatch = form.querySelector('[data-custom-swatch]');
    const customChoice = form.querySelector('[data-custom-choice]');
    const fallback = preview ? getComputedStyle(document.documentElement).getPropertyValue('--nog-primary').trim() || '#ff4d00' : '#ff4d00';

    const render = () => {
        const chosen = form.querySelector('[data-color-choice]:checked')?.value || fallback;
        preview?.style.setProperty('--preview-brand', chosen);
        preview?.style.setProperty('--preview-ink', readableInk(chosen));

        const dir = document.documentElement.dir === 'rtl' ? 'rtl' : 'ltr';
        const font = form.querySelector(`[data-font-select="${dir}"]`)?.value;
        preview?.style.setProperty('font-family', FONT_FAMILIES[font] ? `'${FONT_FAMILIES[font]}', var(--nog-font-sans)` : '');
    };

    form.addEventListener('change', render);
    picker?.addEventListener('input', () => {
        const hex = picker.value.toLowerCase();

        if (customSwatch && customChoice) {
            customSwatch.hidden = false;
            customSwatch.dataset.swatch = hex;
            customSwatch.style.setProperty('--swatch', hex);
            customChoice.value = hex;
            customChoice.checked = true;
        }

        if (hexLabel) {
            hexLabel.textContent = hex;
        }

        render();
    });

    /* Select2 changes arrive as jQuery events; listen on the native select too. */
    form.querySelectorAll('[data-font-select]').forEach((select) => select.addEventListener('change', render));
    render();
}

function bindPromo(root) {
    const form = root.querySelector('[data-promo-form]');
    const preview = form?.querySelector('[data-promo-preview]');

    if (!form || !preview) {
        return;
    }

    const background = form.querySelector('[data-promo-background]');
    const hex = form.querySelector('[data-promo-background-hex]');
    const locale = document.documentElement.lang?.slice(0, 2) || 'en';

    const render = () => {
        const color = background?.value || preview.dataset.bg || '#0b0b0b';
        preview.style.setProperty('--promo-bg', color);
        preview.style.setProperty('--promo-ink', readableInk(color));

        if (hex) {
            hex.textContent = color;
        }

        const text = form.querySelector(`[data-promo-text="${locale}"]`)?.value || form.querySelector('[data-promo-text="en"]')?.value;

        if (text) {
            preview.textContent = text;
        }
    };

    form.addEventListener('input', render);
    render();
}

function bindLinkSorting(root) {
    root.querySelectorAll('[data-link-sort]').forEach((list) => {
        loadSortable().then((Sortable) => {
            Sortable.create(list, {
                handle: '[data-drag-handle]',
                animation: 150,
                ghostClass: 'opacity-40',
                onEnd: async (event) => {
                    if (event.oldIndex === event.newIndex) {
                        return;
                    }

                    list.querySelectorAll('[data-sort-position]').forEach((position, index) => {
                        position.textContent = String(index + 1);
                    });

                    try {
                        await http.post(list.dataset.linkSort, {
                            placement: list.dataset.placement,
                            ids: [...list.querySelectorAll('li[data-id]')].map((item) => Number(item.dataset.id)),
                        });
                    } catch {
                        /* http.js already showed the error toast. */
                    }
                },
            });
        });
    });
}

export default function initAppearance(root) {
    const scope = root ?? document;

    bindRemoteModals(scope);
    paintSwatches(scope);
    bindColors(scope);
    bindPromo(scope);
    bindLinkSorting(scope);
    initAiLogo(scope);
}
