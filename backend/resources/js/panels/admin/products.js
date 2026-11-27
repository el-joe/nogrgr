/**
 * Admin › Catalog › Products pages.
 *
 * List (ADM-010): price-list remote modal, export link carrying the table filters.
 * Form (ADM-011):
 *  - unsaved-changes bar + leave-page warning while the form is dirty;
 *  - validate-on-blur: [data-validate] fields POST the form to data-validate-url with
 *    `field` and show / clear only that field's error;
 *  - slug follows the English name until edited; live margin (promotional price, else base);
 *  - tenant picker shown for "selected";
 *  - category drill-down: one Select2 per level, the deepest level is added as a tag (#115);
 *  - variant builder: attribute groups (Select2 multi) → one row per combination,
 *    keyed by the sorted option ids; edits survive regeneration;
 *  - stock table: one row per variant (or the product) with its default-branch quantity
 *    (`variants[key][stock]` / `stock`, #115) and the total across all branches.
 * Sort (ADM-012): pages of products by drag and drop / arrow keys, Save order.
 */
import { loadSortable } from '../core.js';
import { initSelect2 } from '../select2';
import { bindRemoteModals } from '../remote-modal.js';
import { bindUnsavedForms } from './unsaved-form.js';
import { collectFilters } from '../ui/filters';
import { fieldName } from '../../core/form-errors';
import { http } from '../../core/http';

const keyOf = (ids) => [...ids].map(Number).sort((a, b) => a - b).join('-');

/* ------------------------------------------------------------------ list */

function bindExportLinks(root) {
    root.querySelectorAll('a[data-export-for]').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const url = new URL(link.href, window.location.origin);

            Object.entries(collectFilters(link.dataset.exportFor)).forEach(([key, value]) => {
                (Array.isArray(value) ? value : [value]).forEach((item) => {
                    if (item !== '' && item !== null && item !== undefined) {
                        url.searchParams.append(Array.isArray(value) ? `${key}[]` : key, item);
                    }
                });
            });

            window.location.assign(url.toString());
        });
    });
}

/* ----------------------------------------------------------- field errors */

function setFieldError(form, key, message) {
    const name = fieldName(key);
    const input = form.querySelector(`[name="${CSS.escape(name)}"], [name="${CSS.escape(name)}[]"]`);
    let target = form.querySelector(`[data-error-for="${CSS.escape(key)}"]`);

    if (!target && input && message) {
        target = document.createElement('p');
        target.dataset.errorFor = key;
        target.className = 'ui-error mt-1.5 text-xs text-danger';
        target.setAttribute('role', 'alert');
        (input.closest('[data-field]') ?? input).insertAdjacentElement('afterend', target);
    }

    if (target) {
        target.textContent = message ?? '';
        target.hidden = !message;
    }

    if (input) {
        if (message) {
            input.setAttribute('aria-invalid', 'true');
        } else {
            input.removeAttribute('aria-invalid');
        }
    }
}

function bindValidateOnBlur(form) {
    const url = form.dataset.validateUrl;

    if (!url) {
        return;
    }

    form.addEventListener('focusout', async (event) => {
        const input = event.target.closest?.('[data-validate]');

        if (!input?.name || input.type === 'file') {
            return;
        }

        const key = input.name.replace(/\]/g, '').replace(/\[/g, '.');
        const data = new FormData(form);
        data.delete('_method');
        data.set('field', key);
        [...data.keys()].filter((name) => data.get(name) instanceof File).forEach((name) => data.delete(name));

        try {
            await http.post(url, data, { silent: true, toast: false, followRedirect: false });
            setFieldError(form, key, null);
        } catch (error) {
            if (error?.status === 422) {
                const messages = error.errors?.[key];
                setFieldError(form, key, Array.isArray(messages) ? messages[0] : null);
            }
        }
    });
}

function bindLeaveGuard(form) {
    window.addEventListener('beforeunload', (event) => {
        // A save in flight redirects (http.js follows payload.redirect) before
        // ajax:success runs, so the guard must stand down while submitting.
        if (form.dataset.dirty && !form.dataset.submitting) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    form.addEventListener('submit', () => {
        form.dataset.submitting = '1';
    });
    form.addEventListener('ajax:success', () => {
        delete form.dataset.dirty;
        delete form.dataset.submitting;
    });
    form.addEventListener('ajax:error', () => delete form.dataset.submitting);
}

/* ----------------------------------------------------------- form helpers */

function slugify(value) {
    return value
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 180);
}

function bindSlug(form) {
    const source = form.querySelector('[data-slug-source]');
    const target = form.querySelector('[data-slug-target]');

    if (!source || !target) {
        return;
    }

    let locked = target.dataset.slugLocked !== undefined || target.value !== '';
    target.addEventListener('input', () => (locked = target.value !== ''));
    source.addEventListener('input', () => {
        if (!locked) {
            target.value = slugify(source.value);
        }
    });
}

function bindMargin(form) {
    const price = form.querySelector('[data-price]');
    const sale = form.querySelector('[data-sale-price]');
    const cost = form.querySelector('[data-cost]');
    const margin = form.querySelector('[data-margin]');

    if (!price || !cost || !margin) {
        return;
    }

    const update = () => {
        const promo = Number.parseFloat(sale?.value ?? '');
        const p = promo > 0 ? promo : Number.parseFloat(price.value);
        const c = Number.parseFloat(cost.value);
        margin.value = p > 0 && !Number.isNaN(c) ? `${(((p - c) / p) * 100).toFixed(1)}%` : '';
    };

    [price, sale, cost].forEach((input) => input?.addEventListener('input', update));
}

function bindToggles(form) {
    const toggle = (name, selector, value) => {
        const target = form.querySelector(selector);
        const sync = () => {
            const checked = form.querySelector(`input[name="${name}"]:checked`);
            target.hidden = checked?.value !== value;
        };

        form.querySelectorAll(`input[name="${name}"]`).forEach((radio) => radio.addEventListener('change', sync));
        if (target) {
            sync();
        }
    };

    toggle('assignment_scope', '[data-tenant-select]', 'selected');
}

/* --------------------------------------------------- variants + stock grid */

function initBuilder(form) {
    const island = form.querySelector('[data-product-builder]');

    if (!island) {
        return;
    }

    const state = JSON.parse(island.textContent || '{}');
    const attributes = new Map((state.attributes ?? []).map((attribute) => [attribute.id, attribute]));
    const optionIndex = new Map();
    attributes.forEach((attribute) => attribute.options.forEach((option) => optionIndex.set(option.id, { ...option, attribute: attribute.id })));

    const groupsHost = form.querySelector('[data-attribute-groups]');
    const picker = form.querySelector('[data-attribute-picker]');
    const groupTemplate = form.querySelector('template[data-attribute-group-template]');
    const rowTemplate = form.querySelector('template[data-variant-row-template]');
    const rowsHost = form.querySelector('[data-variant-rows]');
    const table = form.querySelector('[data-variants-table]');
    const empty = form.querySelector('[data-variants-empty]');
    const stockHost = form.querySelector('[data-stock-rows]');
    const branch = state.branch ?? null;
    const quantities = state.quantities ?? {};
    const totals = state.totals ?? {};
    const rows = new Map();
    const stockRows = new Map();
    const groups = new Map();

    /* Groups from the existing variants, in attribute order. */
    Object.values(state.variants ?? {}).forEach((variant) => {
        variant.option_ids.forEach((id) => {
            const option = optionIndex.get(id);

            if (option) {
                groups.set(option.attribute, new Set([...(groups.get(option.attribute) ?? []), id]));
            }
        });
    });

    const markDirty = () => form.dispatchEvent(new Event('input', { bubbles: true }));

    const refreshPicker = () => {
        if (!picker) {
            return;
        }

        picker.replaceChildren(new Option(picker.dataset.placeholder ?? '', ''));
        attributes.forEach((attribute) => {
            if (!groups.has(attribute.id)) {
                picker.appendChild(new Option(attribute.name, String(attribute.id)));
            }
        });
        picker.dispatchEvent(new Event('change'));
    };

    const renderGroups = () => {
        if (!groupsHost) {
            return;
        }

        groupsHost.replaceChildren();
        groups.forEach((selected, attributeId) => {
            const attribute = attributes.get(attributeId);

            if (!attribute) {
                return;
            }

            const node = groupTemplate.content.firstElementChild.cloneNode(true);
            node.dataset.attributeId = String(attributeId);
            node.querySelector('[data-attribute-name]').textContent = attribute.name;
            const remove = node.querySelector('[data-attribute-remove]');
            remove.setAttribute('aria-label', `${remove.getAttribute('aria-label')}${attribute.name}`.trim());
            const select = node.querySelector('[data-attribute-values]');
            select.dataset.select = '';
            select.dataset.placeholder = attribute.name;
            attribute.options.forEach((option) => select.appendChild(new Option(option.name, String(option.id), false, selected.has(option.id))));
            select.addEventListener('change', () => {
                groups.set(attributeId, new Set([...select.selectedOptions].map((option) => Number(option.value))));
                regenerate();
                markDirty();
            });
            remove.addEventListener('click', () => {
                groups.delete(attributeId);
                renderGroups();
                refreshPicker();
                regenerate();
                markDirty();
            });
            groupsHost.appendChild(node);
        });
        initSelect2(groupsHost);
    };

    const combinations = () => {
        const lists = [...groups.values()].map((set) => [...set]).filter((list) => list.length > 0);

        if (lists.length === 0) {
            return [];
        }

        return lists.reduce((result, options) => result.flatMap((combo) => options.map((option) => [...combo, option])), [[]]);
    };

    const createRow = (key, optionIds) => {
        const existing = state.variants?.[key] ?? null;
        const node = rowTemplate.content.firstElementChild.cloneNode(true);
        const base = `variants[${key}]`;
        const names = optionIds.map((id) => optionIndex.get(id)?.name ?? '').join(' / ');
        const swatch = optionIds.map((id) => optionIndex.get(id)).find((option) => option?.swatch);

        node.dataset.key = key;
        node.querySelector('[data-variant-label]').textContent = names;

        if (swatch) {
            const dot = node.querySelector('[data-swatch]');
            dot.classList.remove('hidden');
            dot.style.backgroundColor = swatch.swatch;
        }

        if (existing?.id) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = `${base}[id]`;
            hidden.value = existing.id;
            node.firstElementChild.appendChild(hidden);
        }

        optionIds.forEach((id) => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = `${base}[option_ids][]`;
            hidden.value = id;
            node.firstElementChild.appendChild(hidden);
        });

        node.querySelectorAll('[data-field]').forEach((input) => {
            input.name = `${base}[${input.dataset.field}]`;
            input.setAttribute('aria-label', `${names} — ${input.dataset.label ?? ''}`.trim());
        });

        const activeHidden = document.createElement('input');
        activeHidden.type = 'hidden';
        activeHidden.name = `${base}[is_active]`;
        activeHidden.value = '0';
        node.querySelector('[data-field="is_active"]').insertAdjacentElement('beforebegin', activeHidden);

        if (existing) {
            node.querySelector('[data-field="sku"]').value = existing.sku ?? '';
            node.querySelector('[data-field="price"]').value = existing.price ?? '';
            node.querySelector('[data-field="weight_grams"]').value = existing.weight_grams ?? '';
            node.querySelector('[data-field="is_active"]').checked = existing.is_active !== false;

            if (existing.image) {
                const image = node.querySelector('[data-variant-image]');
                image.src = existing.image;
                image.classList.remove('hidden');
                node.querySelector('[data-remove-image-wrap]').classList.replace('hidden', 'flex');
            }
        }

        return node;
    };

    /* Quantity of the default branch (editable) + what the other branches hold. */
    const otherBranches = (key) => Math.max(0, (Number(totals[key]) || 0) - (Number(quantities[key]) || 0));

    const createStockRow = (key, label) => {
        const row = document.createElement('tr');
        row.dataset.key = key;
        const head = document.createElement('th');
        head.scope = 'row';
        head.className = 'px-3 py-2 text-start font-medium text-ink';
        head.textContent = label;
        row.appendChild(head);

        const cell = document.createElement('td');
        cell.className = 'px-3 py-2 text-end';
        const input = document.createElement('input');
        input.type = 'number';
        input.min = '0';
        input.step = '1';
        input.dir = 'ltr';
        input.className = 'ui-control ms-auto h-9 w-24 px-2 text-end';
        input.name = key === 'base' ? 'stock' : `variants[${key}][stock]`;
        input.value = String(quantities[key] ?? 0);
        input.dataset.validate = '';
        input.dataset.other = String(otherBranches(key));
        input.setAttribute('aria-label', `${label} — ${branch?.name ?? ''}`);
        cell.appendChild(input);
        row.appendChild(cell);

        const total = document.createElement('td');
        total.className = 'px-3 py-2 text-end font-semibold text-ink';
        total.dir = 'ltr';
        total.dataset.total = '';
        row.appendChild(total);

        return row;
    };

    const updateTotals = () => {
        stockHost?.querySelectorAll('tr').forEach((row) => {
            const input = row.querySelector('input');
            const sum = (Number.parseInt(input.value, 10) || 0) + (Number(input.dataset.other) || 0);
            row.querySelector('[data-total]').textContent = sum.toLocaleString();
        });
    };

    function regenerate() {
        const combos = combinations();
        const keys = [];

        combos.forEach((optionIds) => {
            const key = keyOf(optionIds);
            keys.push(key);

            if (!rows.has(key)) {
                rows.set(key, createRow(key, optionIds));
            }

            rowsHost?.appendChild(rows.get(key));
        });

        [...rows.keys()].filter((key) => !keys.includes(key)).forEach((key) => {
            rows.get(key).remove();
            rows.delete(key);
        });

        if (table && empty) {
            table.hidden = keys.length === 0;
            empty.hidden = keys.length > 0;
        }

        if (stockHost) {
            const stockKeys = keys.length === 0 ? ['base'] : keys;

            stockKeys.forEach((key) => {
                if (!stockRows.has(key)) {
                    const label = key === 'base' ? stockHost.dataset.labelProduct : rows.get(key)?.querySelector('[data-variant-label]')?.textContent ?? key;
                    stockRows.set(key, createStockRow(key, label));
                }

                stockHost.appendChild(stockRows.get(key));
            });

            [...stockRows.keys()].filter((key) => !stockKeys.includes(key)).forEach((key) => {
                stockRows.get(key).remove();
                stockRows.delete(key);
            });

            updateTotals();
        }

        const counter = form.querySelector('#product-tabs-variants-tab span[dir="ltr"]');

        if (counter) {
            counter.textContent = String(keys.length);
        }
    }

    form.querySelector('[data-attribute-add]')?.addEventListener('click', () => {
        const id = Number(picker?.value);

        if (!id || groups.has(id)) {
            return;
        }

        groups.set(id, new Set());
        renderGroups();
        refreshPicker();
        markDirty();
    });

    stockHost?.addEventListener('input', updateTotals);

    renderGroups();
    refreshPicker();
    regenerate();
}

/* ------------------------------------------------- category drill-down */

function initCategoryPicker(form) {
    const picker = form.querySelector('[data-category-picker]');
    const island = form.querySelector('[data-category-tree]');

    if (!picker || !island) {
        return;
    }

    const nodes = JSON.parse(island.textContent || '[]');
    const byId = new Map(nodes.map((node) => [node.id, node]));
    const childrenOf = (parent) => nodes.filter((node) => node.parent === parent);
    const levels = picker.querySelector('[data-category-levels]');
    const tags = picker.querySelector('[data-category-tags]');
    const empty = picker.querySelector('[data-category-empty]');
    const template = form.querySelector('template[data-category-tag-template]');
    const error = picker.querySelector('[data-error-for="category_ids"]');
    const markDirty = () => form.dispatchEvent(new Event('input', { bubbles: true }));

    const refreshEmpty = () => {
        empty.hidden = tags.children.length > 0;
    };

    const addTag = (node) => {
        if (tags.querySelector(`[data-category-tag][data-id="${node.id}"]`)) {
            return;
        }

        const tag = template.content.firstElementChild.cloneNode(true);
        tag.dataset.id = String(node.id);
        tag.querySelector('[data-category-path]').textContent = node.path;
        tag.querySelector('[data-category-remove]').setAttribute('aria-label', picker.dataset.removeLabel.replace(':name', node.path));
        tag.querySelector('input').value = String(node.id);
        tags.appendChild(tag);
        error.hidden = true;
        refreshEmpty();
        markDirty();
    };

    const renderLevel = (depth, parent) => {
        [...levels.children].slice(depth).forEach((child) => child.remove());
        const options = childrenOf(parent);

        if (options.length === 0) {
            return;
        }

        const wrap = document.createElement('div');
        const label = picker.dataset.levelLabel.replace(':level', String(depth + 1));
        const select = document.createElement('select');
        select.className = 'ui-control';
        select.dataset.select = '';
        select.dataset.placeholder = picker.dataset.placeholder;
        select.dataset.unsavedIgnore = '';
        select.setAttribute('aria-label', label);
        select.appendChild(new Option(picker.dataset.placeholder, ''));
        options.forEach((node) => select.appendChild(new Option(node.leaf ? node.name : `${node.name} ›`, String(node.id))));
        wrap.appendChild(select);
        levels.appendChild(wrap);
        initSelect2(wrap);

        // Select2 fires a jQuery change that bubbles as a native event on the select.
        select.addEventListener('change', () => {
            const node = byId.get(Number(select.value));

            if (!node) {
                renderLevel(depth + 1, -1);

                return;
            }

            if (node.leaf) {
                addTag(node);
                // Rebuild after Select2 has closed its dropdown.
                window.setTimeout(() => renderLevel(0, null), 0);
            } else {
                renderLevel(depth + 1, node.id);
            }
        });
    };

    tags.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-category-remove]');

        if (remove) {
            remove.closest('[data-category-tag]').remove();
            refreshEmpty();
            markDirty();
        }
    });

    renderLevel(0, null);
}

function initProductForm(form) {
    bindValidateOnBlur(form);
    bindLeaveGuard(form);
    bindSlug(form);
    bindMargin(form);
    bindToggles(form);
    initCategoryPicker(form);
    initBuilder(form);
}

/* ------------------------------------------------------------- sort page */

function initSortPage(root) {
    const list = root.querySelector('[data-sort-list]');
    const template = root.querySelector('template[data-sort-row]');
    const more = root.querySelector('[data-sort-more]');
    const count = root.querySelector('[data-sort-count]');
    const dirty = root.querySelector('[data-sort-dirty]');
    const filters = [...root.querySelectorAll('[data-sort-filter]')];
    let page = 1;
    let controller = null;
    let timer = null;

    const state = (name) => root.querySelectorAll('[data-sort-state]').forEach((element) => (element.hidden = element.dataset.sortState !== name));

    const renumber = () => [...list.children].forEach((item, index) => (item.querySelector('[data-position]').textContent = String(index + 1)));

    const changed = () => {
        renumber();
        dirty.hidden = false;
    };

    const render = (rows, append) => {
        if (!append) {
            list.replaceChildren();
        }

        rows.forEach((row) => {
            const item = template.content.firstElementChild.cloneNode(true);
            item.dataset.id = String(row.id);
            item.querySelector('[data-field="name"]').textContent = row.name;
            item.querySelector('[data-field="subtitle"]').textContent = row.subtitle;
            item.querySelector('[data-drag-handle]').setAttribute('aria-label', `${item.querySelector('[data-drag-handle]').getAttribute('aria-label')} ${row.name}`);
            const status = item.querySelector('[data-field="status"]');
            status.textContent = row.status_label;
            status.dataset.status = row.status;

            if (row.image) {
                const image = document.createElement('img');
                image.src = row.image;
                image.alt = '';
                image.className = 'size-full object-contain bg-media';
                item.querySelector('[data-field="image"]').appendChild(image);
            }

            list.appendChild(item);
        });

        renumber();
    };

    const load = async (append = false) => {
        controller?.abort();
        controller = new AbortController();

        if (!append) {
            page = 1;
            state('loading');
        }

        const params = { page };
        filters.forEach((filter) => {
            if (filter.value) {
                params[filter.name] = filter.value;
            }
        });

        try {
            const payload = await http.get(root.dataset.sortData, params, { signal: controller.signal, silent: true, toast: false });
            render(payload.data ?? [], append);
            more.hidden = !payload.meta?.more;
            count.textContent = root.dataset.countTemplate
                .replace(':from', String(list.children.length ? 1 : 0))
                .replace(':to', String(list.children.length))
                .replace(':total', String(payload.meta?.total ?? 0));
            state(list.children.length === 0 ? 'empty' : '');
            dirty.hidden = true;
        } catch (error) {
            if (error?.name !== 'AbortError') {
                state('error');
            }
        }
    };

    filters.forEach((filter) =>
        filter.addEventListener(filter.tagName === 'INPUT' ? 'input' : 'change', () => {
            clearTimeout(timer);
            timer = setTimeout(() => load(), 300);
        }),
    );

    more?.querySelector('button')?.addEventListener('click', () => {
        page += 1;
        load(true);
    });
    root.querySelector('[data-sort-retry]')?.addEventListener('click', () => load());

    list.addEventListener('keydown', (event) => {
        const handle = event.target.closest('[data-drag-handle]');

        if (!handle || !['ArrowUp', 'ArrowDown'].includes(event.key)) {
            return;
        }

        const item = handle.closest('[data-sort-item]');
        const sibling = event.key === 'ArrowUp' ? item.previousElementSibling : item.nextElementSibling;
        event.preventDefault();

        if (sibling) {
            list.insertBefore(event.key === 'ArrowUp' ? item : sibling, event.key === 'ArrowUp' ? sibling : item);
            handle.focus();
            changed();
        }
    });

    root.querySelector('[data-sort-save]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        const ids = [...list.children].map((item) => Number(item.dataset.id));

        if (ids.length === 0) {
            return;
        }

        button.setAttribute('data-loading', '');

        try {
            await http.put(root.dataset.sortSaveUrl, { ids });
            dirty.hidden = true;
        } catch {
            load();
        } finally {
            button.removeAttribute('data-loading');
        }
    });

    loadSortable().then((Sortable) => {
        Sortable.create(list, {
            handle: '[data-drag-handle]',
            draggable: '[data-sort-item]',
            animation: 150,
            ghostClass: 'opacity-40',
            onEnd: (event) => {
                if (event.oldIndex !== event.newIndex) {
                    changed();
                }
            },
        });
    });

    load();
}

export default function initProducts(page) {
    bindUnsavedForms(page);
    bindExportLinks(page);

    if (document.querySelector('[data-remote-body]')) {
        bindRemoteModals(page);
    }

    page.querySelectorAll('[data-product-form]').forEach((form) => initProductForm(form));
    page.querySelectorAll('[data-product-sort]').forEach((root) => initSortPage(root));
}
