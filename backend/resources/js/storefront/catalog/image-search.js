/**
 * Search by image (SF-ECO-012 / SF-ELO-013 / SF-SQF-013): header camera buttons
 * [data-image-search-open] open the #sf-image-search sheet; a photo (browse, camera,
 * drag & drop, paste) or an https image URL is posted to storefront.search.image and the
 * similar products are rendered with their match percentage. States: idle, searching
 * (scan line over the photo), results, no match, error (quota / provider / invalid).
 */
import { request } from '../../core/http';
import { openSheet } from '../core/motion/sheet';

const MAX_BYTES = 10 * 1024 * 1024;
const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

export function initImageSearch(root = document) {
    const sheet = document.getElementById('sf-image-search');

    if (!sheet || sheet.dataset.bound) {
        return;
    }

    sheet.dataset.bound = '1';

    const $ = (selector) => sheet.querySelector(selector);
    const texts = sheet.dataset;
    const idle = $('[data-image-search-idle]');
    const result = $('[data-image-search-result]');
    const drop = $('[data-image-search-drop]');
    const preview = $('[data-image-search-preview]');
    const status = $('[data-image-search-status]');
    const hint = $('[data-image-search-hint]');
    const empty = $('[data-image-search-empty]');
    const error = $('[data-image-search-error]');
    const grid = $('[data-image-search-grid]');
    const template = $('[data-image-search-card]');
    const urlInput = $('[data-image-search-url]');
    const searchingText = status?.textContent ?? '';
    let controller = null;
    let objectUrl = null;

    const reset = () => {
        controller?.abort();
        result.hidden = true;
        idle.hidden = false;
        grid.replaceChildren();
        sheet.querySelectorAll('[data-image-search-file]').forEach((input) => {
            input.value = '';
        });

        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
    };

    const showError = (message) => {
        result.removeAttribute('data-busy');
        status.textContent = '';
        error.textContent = message || texts.textGeneric;
        error.hidden = false;
    };

    const card = (product) => {
        const node = template.content.firstElementChild.cloneNode(true);
        node.href = product.url;
        node.querySelector('[data-card-name]').textContent = product.name;
        node.querySelector('[data-card-price]').textContent = product.price;
        node.querySelector('[data-card-match]').textContent = (texts.textMatch || '').replace('__PERCENT__', String(product.match));
        const badge = node.querySelector('[data-card-badge]');

        if (badge) {
            badge.textContent = product.badge || '';
            badge.dataset.badge = product.badge_type || '';
            badge.hidden = !product.badge;
        }

        const image = node.querySelector('[data-card-image]');

        if (product.image) {
            image.src = product.image;
            image.alt = product.name;
        } else {
            image.remove();
        }

        return node;
    };

    const search = async (data, previewSrc) => {
        controller?.abort();
        controller = new AbortController();
        idle.hidden = true;
        result.hidden = false;
        result.setAttribute('data-busy', '');
        grid.replaceChildren();
        [hint, empty, error].forEach((node) => {
            node.hidden = true;
        });
        status.textContent = searchingText;
        preview.hidden = !previewSrc;

        if (previewSrc) {
            preview.src = previewSrc;
        }

        try {
            const payload = await request('POST', texts.action, { data, signal: controller.signal, silent: true });
            const found = payload?.data ?? {};
            result.removeAttribute('data-busy');
            status.textContent = found.title ?? '';

            if (!found.products?.length) {
                empty.hidden = false;

                return;
            }

            hint.hidden = false;
            grid.replaceChildren(...found.products.map(card));
        } catch (exception) {
            if (exception?.name === 'AbortError') {
                return;
            }

            const message = exception?.payload?.errors ? Object.values(exception.payload.errors).flat()[0] : exception?.payload?.message;
            showError(message);
        }
    };

    const fromFile = (file) => {
        if (!file) {
            return;
        }

        if (!TYPES.includes(file.type) || file.size > MAX_BYTES) {
            idle.hidden = true;
            result.hidden = false;
            preview.hidden = true;
            [hint, empty].forEach((node) => {
                node.hidden = true;
            });
            showError(TYPES.includes(file.type) ? texts.textSize : texts.textType);

            return;
        }

        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
        }

        objectUrl = URL.createObjectURL(file);
        const data = new FormData();
        data.append('image', file);
        search(data, objectUrl);
    };

    root.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-image-search-open]');

        if (!opener) {
            return;
        }

        event.preventDefault();
        reset();
        openSheet('sf-image-search');
    });

    sheet.querySelectorAll('[data-image-search-file]').forEach((input) => {
        input.addEventListener('change', () => fromFile(input.files?.[0]));
    });

    $('[data-image-search-reset]')?.addEventListener('click', reset);

    $('[data-image-search-url-form]')?.addEventListener('submit', (event) => {
        event.preventDefault();
        const url = urlInput.value.trim();

        if (!url) {
            urlInput.focus();

            return;
        }

        const data = new FormData();
        data.append('url', url);
        search(data, url);
    });

    ['dragenter', 'dragover'].forEach((type) => drop?.addEventListener(type, (event) => {
        event.preventDefault();
        drop.setAttribute('data-dragover', '');
    }));
    ['dragleave', 'drop'].forEach((type) => drop?.addEventListener(type, (event) => {
        event.preventDefault();
        drop.removeAttribute('data-dragover');
    }));
    drop?.addEventListener('drop', (event) => fromFile(event.dataTransfer?.files?.[0]));

    sheet.addEventListener('paste', (event) => {
        const file = [...(event.clipboardData?.files ?? [])].find((item) => item.type.startsWith('image/'));

        if (file && !idle.hidden) {
            event.preventDefault();
            fromFile(file);
        }
    });
}
