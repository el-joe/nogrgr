/**
 * Vendor catalog pages (VEN-010 … 016), loaded for [data-page="vendor-catalog"].
 *
 *  - remote modals: price list (VEN-010a), shipping details (010e), edit request (013a),
 *    new product preview (011a), select markets (015a / 015b), request detail (013)
 *  - price list: live preview (server-priced, Commerce\Pricing) + queued apply with
 *    progress polling; bulk markup for selected product cards
 *  - product edit pricing tab: live row preview + summary
 *  - new products: add one / selected / all
 *  - product sort: drag & drop (SortableJS) + keyboard moves, save order
 *  - own product form: variants repeater, countries picker; markets / country search
 *
 * No price maths happens here: every number comes from the server.
 */
import { http } from '../../core/http';
import { t } from '../../core/i18n';
import { notify } from '../../core/notify';
import { loadSortable, closeOverlay, confirmDialog } from '../core.js';
import { bindRemoteModals } from '../remote-modal.js';

const debounce = (callback, wait = 300) => {
    let timer;

    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => callback(...args), wait);
    };
};

function reloadTables(root = document) {
    root.querySelectorAll('[data-datatable-card]').forEach((card) => card.dispatchEvent(new CustomEvent('datatable:reload', { detail: { keepPage: true } })));
}

function selectedIds(tableId) {
    const card = document.getElementById(tableId)?.closest('[data-datatable-card]');

    return card ? [...card.querySelectorAll('input[data-dt-select]:checked')].map((input) => input.value) : [];
}

function escapeHtml(value) {
    const element = document.createElement('span');
    element.textContent = value ?? '';

    return element.innerHTML;
}

/* ---------- Price list (VEN-010a) ---------- */

function renderPreviewRows(form, rows) {
    const body = form.querySelector('[data-preview-rows]');

    if (!body) {
        return;
    }

    body.innerHTML = rows
        .map(
            (row) => `<tr>
                <td class="px-4 py-4 text-ink">${escapeHtml(row.country)}</td>
                <td class="px-4 py-4 text-ink">${escapeHtml(row.variant ?? t('catalog.all'))}</td>
                <td class="px-4 py-4 text-end font-semibold text-ink" dir="ltr">${escapeHtml(row.cost)}</td>
                <td class="px-4 py-4 text-end font-semibold text-ink" dir="ltr">${escapeHtml(row.price)}</td>
            </tr>`,
        )
        .join('');
}

const previewPriceList = debounce(async (form) => {
    if (!form.dataset.previewUrl) {
        return;
    }

    const data = new FormData(form);

    if (!data.get('profit_value')) {
        return;
    }

    try {
        const payload = await http.post(form.dataset.previewUrl, data, { silent: true, toast: false });
        renderPreviewRows(form, payload?.data?.rows ?? []);
    } catch {
        /* Validation messages are shown on submit. */
    }
}, 350);

async function pollRun(form, run) {
    const box = form.querySelector('[data-run-progress]');
    const update = (state) => {
        if (!box) {
            return;
        }

        box.hidden = false;
        box.querySelector('[data-run-label]').textContent = state.label;
        box.querySelector('[data-run-count]').textContent = `${state.processed} / ${state.total}`;
        box.querySelector('[data-run-bar]').style.width = `${state.percent}%`;
    };

    let state = run;
    update(state);

    while (!state.finished) {
        await new Promise((resolve) => setTimeout(resolve, 1000));

        try {
            state = (await http.get(state.url, { silent: true, toast: false }))?.data ?? state;
        } catch {
            break;
        }

        update(state);
    }

    if (state.status === 'completed') {
        notify.success(t('catalog.markup_applied'));
        const modal = form.closest('.ui-overlay');

        if (modal) {
            closeOverlay(modal, { force: true });
        }

        if (document.querySelector('[data-product-edit]')) {
            window.location.reload();
        } else {
            reloadTables();
        }
    }
}

async function applyPriceList(form) {
    const data = new FormData(form);

    if (form.dataset.bulkTarget) {
        const ids = selectedIds(form.dataset.bulkTarget);

        if (ids.length === 0) {
            notify.error(t('catalog.select_products'));
            return;
        }

        ids.forEach((id) => data.append('product_ids[]', id));
    }

    const submit = document.querySelector(`[form="${form.id}"][type="submit"]`);
    submit?.setAttribute('data-loading', '');

    try {
        const payload = await http.post(form.dataset.applyUrl, data, { form });
        await pollRun(form, payload.data);
    } catch {
        /* Field errors are rendered by the http client. */
    } finally {
        submit?.removeAttribute('data-loading');
    }
}

/* ---------- Product edit pricing tab (VEN-012a) ---------- */

const previewPricing = debounce(async (form) => {
    try {
        const payload = await http.post(form.dataset.previewUrl, new FormData(form), { silent: true, toast: false });
        const rows = payload?.data?.rows ?? [];

        form.querySelectorAll('[data-price-row]').forEach((row) => {
            const data = rows[Number(row.dataset.priceRow)];

            if (data) {
                row.querySelector('[data-cell="price"]').textContent = data.price;
                row.querySelector('[data-cell="profit"]').textContent = data.profit;
            }
        });

        const summary = payload?.data?.summary ?? {};
        ['average', 'profit'].forEach((key) => {
            const cell = form.querySelector(`[data-summary="${key}"]`);

            if (cell) {
                cell.textContent = summary[key] ?? '—';
            }
        });
    } catch {
        /* Errors show on save. */
    }
}, 400);

/* ---------- Edit request modal (VEN-013a) ---------- */

function syncEditRequestForm(form) {
    const select = form.querySelector('[data-request-field-select]');
    const key = select?.value ?? '';
    const images = key === 'images';
    const currentValues = JSON.parse(form.querySelector('[data-request-current-values]')?.textContent || '{}');

    form.querySelector('[data-request-images]').hidden = !images;
    form.querySelector('[data-request-value-wrap]').hidden = images;

    const diff = form.querySelector('[data-request-diff]');

    if (diff) {
        diff.hidden = images;
        diff.querySelector('[data-request-current]').textContent = currentValues[key] || '—';
        diff.querySelector('[data-request-preview]').textContent = form.querySelector('[data-request-value]')?.value || '—';
    }
}

/* ---------- Markets / countries pickers ---------- */

function filterCountries(container, term) {
    const query = term.trim().toLowerCase();
    let visible = 0;

    container.querySelectorAll('[data-market-option]').forEach((option) => {
        const match = query === '' || option.dataset.name.includes(query);
        option.hidden = !match;
        visible += match ? 1 : 0;
    });

    const none = container.querySelector('[data-markets-none]');

    if (none) {
        none.hidden = visible > 0;
    }
}

function syncMarketRows(form) {
    form.querySelectorAll('[data-market-country]').forEach((box) => {
        const row = form.querySelector(`[data-market-row="${box.value}"]`);

        if (row) {
            row.hidden = !box.checked;
            row.querySelectorAll('input').forEach((input) => (input.disabled = !box.checked));
        }
    });

    const all = form.querySelector('[data-markets-all]');
    const boxes = [...form.querySelectorAll('[data-market-country]')];

    if (all) {
        all.checked = boxes.length > 0 && boxes.every((box) => box.checked);
    }
}

/* ---------- New products import (VEN-011) ---------- */

async function importProducts(url, payload) {
    try {
        await http.post(url, payload);
        reloadTables();
        return true;
    } catch {
        return false;
    }
}

/* ---------- Product sort (VEN-014) ---------- */

function initSort(root) {
    const list = root.querySelector('[data-sort-list]');
    const dirty = root.querySelector('[data-sort-dirty]');
    const save = document.querySelector('[data-sort-save]');

    if (!list) {
        return;
    }

    const renumber = () => {
        list.querySelectorAll('[data-sort-item]').forEach((item, index) => {
            item.querySelector('[data-sort-position]').textContent = String(index + 1);
        });
        if (dirty) {
            dirty.hidden = false;
        }
    };

    list.addEventListener('click', (event) => {
        const move = event.target.closest('[data-sort-move]');

        if (!move) {
            return;
        }

        const item = move.closest('[data-sort-item]');
        const sibling = move.dataset.sortMove === 'up' ? item.previousElementSibling : item.nextElementSibling;

        if (sibling) {
            move.dataset.sortMove === 'up' ? sibling.before(item) : sibling.after(item);
            renumber();
            move.focus();
        }
    });

    save?.addEventListener('click', async () => {
        save.setAttribute('data-loading', '');

        try {
            await http.post(root.dataset.sortUrl, { ids: [...list.querySelectorAll('[data-sort-item]')].map((item) => item.dataset.id) });
            if (dirty) {
                dirty.hidden = true;
            }
        } catch {
            /* Toast already shown (stale list → reload advised by the message). */
        } finally {
            save.removeAttribute('data-loading');
        }
    });

    if (root.dataset.readonly === undefined) {
        loadSortable().then((Sortable) => {
            Sortable.create(list, {
                handle: '[data-drag-handle]',
                draggable: '[data-sort-item]',
                animation: 150,
                ghostClass: 'opacity-40',
                onEnd: (event) => {
                    if (event.oldIndex !== event.newIndex) {
                        renumber();
                    }
                },
            });
        });
    }
}

/* ---------- Own product form (VEN-016c) ---------- */

function initOwnForm(form) {
    const section = form.querySelector('[data-variants]');
    const list = section?.querySelector('[data-variant-list]');
    const template = section?.querySelector('[data-variant-template]');
    const stock = form.querySelector('[data-product-stock]');

    const sync = () => {
        const count = list.querySelectorAll('[data-variant-row]').length;
        section.querySelector('[data-variants-empty]').hidden = count > 0;

        if (stock) {
            stock.disabled = count > 0;
        }
    };

    section?.addEventListener('click', (event) => {
        if (event.target.closest('[data-variant-add]')) {
            const index = Number(section.dataset.nextIndex || 0);
            section.dataset.nextIndex = String(index + 1);
            list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
            list.lastElementChild?.querySelector('input')?.focus();
            sync();
        }

        const remove = event.target.closest('[data-variant-remove]');

        if (remove) {
            remove.closest('[data-variant-row]').remove();
            sync();
        }
    });

    const all = form.querySelector('[data-countries-all]');
    const syncCountries = () => {
        form.querySelectorAll('input[name="country_ids[]"]').forEach((box) => (box.disabled = all.checked));
    };

    all?.addEventListener('change', syncCountries);

    if (all) {
        syncCountries();
    }

    if (section) {
        sync();
    }
}

export default function initCatalog(page) {
    if (document.querySelector('[data-remote-body]')) {
        bindRemoteModals(page);
    }

    page.querySelectorAll('[data-product-sort]').forEach((root) => initSort(root));
    // Own product form (VEN-016c) and store category form (VEN-017b): countries picker.
    page.querySelectorAll('[data-own-product-form], [data-category-form]').forEach((form) => initOwnForm(form));

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (form instanceof HTMLFormElement && form.matches('[data-price-list-form]')) {
            event.preventDefault();
            applyPriceList(form);
        }
    });

    document.addEventListener('input', (event) => {
        const target = event.target;

        if (target.closest('[data-price-list-form]') && target.matches('[data-preview-field]')) {
            previewPriceList(target.closest('[data-price-list-form]'));
        }

        if (target.matches('[data-markup-input]')) {
            previewPricing(target.closest('[data-product-edit]'));
        }

        if (target.matches('[data-request-value]')) {
            syncEditRequestForm(target.closest('[data-edit-request-form]'));
        }

        if (target.matches('[data-markets-search]')) {
            filterCountries(target.closest('[data-markets], [data-country-picker]'), target.value);
        }
    });

    document.addEventListener('change', (event) => {
        const target = event.target;

        if (target.closest('[data-price-list-form]') && target.matches('[data-preview-field]')) {
            previewPriceList(target.closest('[data-price-list-form]'));
        }

        if (target.matches('[data-markup-input]')) {
            previewPricing(target.closest('[data-product-edit]'));
        }

        if (target.matches('[data-request-field-select]')) {
            syncEditRequestForm(target.closest('[data-edit-request-form]'));
        }

        if (target.matches('[data-market-country]')) {
            syncMarketRows(target.closest('[data-markets-form]'));
        }

        if (target.matches('[data-markets-all]')) {
            const form = target.closest('[data-markets-form]');
            form.querySelectorAll('[data-market-country]').forEach((box) => (box.checked = target.checked));
            syncMarketRows(form);
        }

        if (target.matches('[data-shipping-select]')) {
            target.closest('[data-shipping-details]').querySelectorAll('[data-shipping-country]').forEach((block) => {
                block.hidden = block.dataset.shippingCountry !== target.value;
            });
        }
    });

    document.addEventListener('click', async (event) => {
        const remove = event.target.closest('[data-market-remove]');

        if (remove) {
            const form = remove.closest('[data-markets-form]');
            const box = form.querySelector(`[data-market-country][value="${remove.dataset.marketRemove}"]`);

            if (box) {
                box.checked = false;
                syncMarketRows(form);
            }

            return;
        }

        const one = event.target.closest('[data-import-one]');

        if (one) {
            one.setAttribute('data-loading', '');
            await importProducts(one.dataset.url, { ids: [one.dataset.id] });
            one.removeAttribute('data-loading');
            return;
        }

        const bulk = event.target.closest('[data-bulk-import]');

        if (bulk) {
            const ids = selectedIds('new-products');

            if (ids.length > 0) {
                await importProducts(bulk.dataset.url, { ids });
            }

            return;
        }

        const all = event.target.closest('[data-import-all]');

        if (all) {
            const ok = await confirmDialog({ title: all.dataset.confirmTitle, message: all.dataset.confirmText, tone: 'primary' });

            if (ok && (await importProducts(all.dataset.importAll, { all: 1 }))) {
                window.location.reload();
            }

            return;
        }

        const previewImport = event.target.closest('[data-preview-import]');

        if (previewImport) {
            const modal = previewImport.closest('.ui-overlay');
            const id = modal?.querySelector('[data-preview-for]')?.dataset.previewFor;

            if (id && (await importProducts(previewImport.dataset.url, { ids: [id] }))) {
                closeOverlay(modal, { force: true });
            }

            return;
        }

        const tab = event.target.closest('#product-edit-tabs [data-tab-key]');

        if (tab) {
            const input = document.querySelector('[data-active-tab]');

            if (input) {
                input.value = tab.dataset.tabKey;
            }
        }
    });

    /* Edit request modal body arrives after fetch: sync it once it is in the DOM. */
    new MutationObserver(() => {
        document.querySelectorAll('[data-edit-request-form]:not([data-synced])').forEach((form) => {
            form.dataset.synced = '';
            syncEditRequestForm(form);
        });
        document.querySelectorAll('[data-markets-form]:not([data-synced])').forEach((form) => {
            form.dataset.synced = '';
            syncMarketRows(form);
        });
    }).observe(document.body, { childList: true, subtree: true });
}
