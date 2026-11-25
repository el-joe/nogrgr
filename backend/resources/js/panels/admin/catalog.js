/**
 * Admin › Catalog taxonomy pages (categories, attributes, labels, badges).
 *
 *  - [data-category-tree]      nested drag & drop (SortableJS) + keyboard moves on the
 *                              handles; PUT {tree} at once ([data-tree-autosave]) or on
 *                              [data-sort-save]. Expand / collapse, detail panel.
 *  - [data-sortable-list]      flat drag & drop list; with data-sort-url PUT {ids} at once
 *                              ([data-sort-autosave]) or on [data-sort-save]; without it
 *                              (attribute options) only the DOM order changes.
 *                              [data-sort-move=top|bottom] menu items move a row.
 *  - [data-category-form]      slug follows the English name until edited.
 *  - [data-attribute-form]     options repeater (add / remove / swatch).
 *  - [data-label-form]         live placeholder preview (form loaded in a remote modal).
 *  - [data-badge-assign]       assign modal: debounced search, paging, multi-select.
 *  - [data-badge-settings]     auto-fill options shown for automatic lists.
 *
 * Keyboard (focus a drag handle): ↑ / ↓ move among siblings; in the tree → nests into the
 * previous sibling and ← moves out to the parent's level.
 */
import { loadSortable } from '../core.js';
import { bindRemoteModals } from '../remote-modal.js';
import { bindUnsavedForms } from './unsaved-form.js';
import { http } from '../../core/http';

const isRtl = () => document.documentElement.dir === 'rtl';

/* ---------------------------------------------------------------- shared */

function renumber(list) {
    [...list.children].forEach((item, index) => {
        const position = item.querySelector(':scope > [data-position], :scope [data-position]');

        if (position && item.matches('[data-sortable-item]')) {
            position.textContent = String(index + 1);
        }
    });
}

function setBusy(element, busy) {
    element.toggleAttribute('aria-busy', busy);
}

function markDirty(scope) {
    const note = scope.closest('section, [data-page]')?.querySelector('[data-sort-dirty]');

    if (note) {
        note.hidden = false;
    }

    scope.dataset.dirty = 'true';
    scope.closest('form')?.dispatchEvent(new Event('input', { bubbles: true }));
}

function clearDirty(scope) {
    delete scope.dataset.dirty;
    document.querySelectorAll('[data-sort-dirty]').forEach((note) => (note.hidden = true));
}

function focusHandle(item) {
    item.querySelector('[data-drag-handle]')?.focus();
}

/* ---------------------------------------------------------- category tree */

function serializeTree(list) {
    return [...list.children]
        .filter((item) => item.matches('[data-category-node]'))
        .map((item) => {
            const children = item.querySelector(':scope > [data-category-list]');

            return { id: Number(item.dataset.categoryId), children: children ? serializeTree(children) : [] };
        });
}

function refreshToggles(tree) {
    tree.querySelectorAll('[data-category-node]').forEach((item) => {
        const children = item.querySelector(':scope > [data-category-list]');
        const hasChildren = Boolean(children?.querySelector(':scope > [data-category-node]'));

        if (hasChildren) {
            item.setAttribute('aria-expanded', item.getAttribute('aria-expanded') === 'false' ? 'false' : 'true');
        } else {
            item.removeAttribute('aria-expanded');
            if (children) {
                children.hidden = false;
            }
        }
    });
}

async function saveTree(tree) {
    const root = tree.querySelector('[data-category-list]');

    setBusy(tree, true);

    try {
        await http.put(tree.dataset.treeUrl, { tree: serializeTree(root) });
        clearDirty(tree);
    } catch {
        window.location.reload();
    } finally {
        setBusy(tree, false);
    }
}

function treeChanged(tree) {
    refreshToggles(tree);

    if (tree.dataset.treeAutosave !== undefined) {
        saveTree(tree);
    } else {
        markDirty(tree);
    }
}

function moveTreeNode(tree, item, key) {
    const parentList = item.parentElement;
    const forward = isRtl() ? 'ArrowLeft' : 'ArrowRight';
    const backward = isRtl() ? 'ArrowRight' : 'ArrowLeft';

    if (key === 'ArrowUp' && item.previousElementSibling) {
        parentList.insertBefore(item, item.previousElementSibling);
    } else if (key === 'ArrowDown' && item.nextElementSibling) {
        parentList.insertBefore(item.nextElementSibling, item);
    } else if (key === forward && item.previousElementSibling) {
        const target = item.previousElementSibling.querySelector(':scope > [data-category-list]');
        target.hidden = false;
        item.previousElementSibling.setAttribute('aria-expanded', 'true');
        target.appendChild(item);
    } else if (key === backward && parentList.closest('[data-category-node]')) {
        const parentItem = parentList.closest('[data-category-node]');
        parentItem.parentElement.insertBefore(item, parentItem.nextElementSibling);
    } else {
        return false;
    }

    focusHandle(item);

    return true;
}

function setExpanded(item, expanded) {
    const children = item.querySelector(':scope > [data-category-list]');

    if (!children || !children.querySelector(':scope > [data-category-node]')) {
        return;
    }

    item.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    children.hidden = !expanded;
    const toggle = item.querySelector(':scope > div [data-category-toggle]');
    toggle?.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    toggle?.querySelector('[data-toggle-icon]')?.classList.toggle('-rotate-90', !expanded);
}

function selectCategory(page, id) {
    const panels = page.querySelectorAll('[data-category-detail]');

    if (panels.length === 0) {
        return;
    }

    panels.forEach((panel) => (panel.hidden = panel.dataset.categoryDetail !== String(id)));
    page.querySelector('[data-category-detail-empty]')?.toggleAttribute('hidden', true);
    page.querySelectorAll('[data-category-select]').forEach((row) => {
        const selected = row.dataset.categorySelect === String(id);
        row.closest('[data-category-node]')?.setAttribute('aria-selected', selected ? 'true' : 'false');
        row.classList.toggle('bg-primary-subtle', selected);
    });
}

async function initCategoryTree(page, tree) {
    const editable = Boolean(tree.dataset.treeUrl) && tree.querySelector('[data-drag-handle]');

    tree.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-category-toggle]');

        if (toggle) {
            const item = toggle.closest('[data-category-node]');
            setExpanded(item, item.getAttribute('aria-expanded') === 'false');
            return;
        }

        const row = event.target.closest('[data-category-select]');

        if (row && !event.target.closest('a, button, input, [data-dropdown]')) {
            selectCategory(page, row.dataset.categorySelect);
        }
    });

    const expandAll = page.querySelector('[data-category-expand-all]');

    expandAll?.addEventListener('click', () => {
        const expand = expandAll.getAttribute('aria-pressed') !== 'true';
        tree.querySelectorAll('[data-category-node]').forEach((item) => setExpanded(item, expand));
        expandAll.setAttribute('aria-pressed', expand ? 'true' : 'false');
        expandAll.textContent = expand ? expandAll.dataset.labelCollapse : expandAll.dataset.labelExpand;
    });

    const first = tree.querySelector('[data-category-select]');

    if (first) {
        selectCategory(page, first.dataset.categorySelect);
    }

    if (!editable) {
        return;
    }

    tree.addEventListener('keydown', (event) => {
        const handle = event.target.closest('[data-drag-handle]');

        if (!handle || !['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
            return;
        }

        event.preventDefault();

        if (moveTreeNode(tree, handle.closest('[data-category-node]'), event.key)) {
            treeChanged(tree);
        }
    });

    const Sortable = await loadSortable();

    tree.querySelectorAll('[data-category-list]').forEach((list) => {
        Sortable.create(list, {
            group: 'catalog-categories',
            handle: '[data-drag-handle]',
            draggable: '[data-category-node]',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            emptyInsertThreshold: 12,
            ghostClass: 'opacity-40',
            onEnd: (event) => {
                if (event.from !== event.to || event.oldIndex !== event.newIndex) {
                    treeChanged(tree);
                }
            },
        });
    });
}

/* --------------------------------------------------------------- flat lists */

function listIds(list) {
    return [...list.querySelectorAll(':scope > [data-sortable-item][data-id]')].map((item) => Number(item.dataset.id));
}

async function saveList(list) {
    setBusy(list, true);

    try {
        await http.put(list.dataset.sortUrl, { ids: listIds(list) });
        clearDirty(list);
    } catch {
        window.location.reload();
    } finally {
        setBusy(list, false);
    }
}

function listChanged(list) {
    renumber(list);

    if (!list.dataset.sortUrl) {
        list.closest('form')?.dispatchEvent(new Event('input', { bubbles: true }));
        return;
    }

    if (list.dataset.sortAutosave !== undefined) {
        saveList(list);
    } else {
        markDirty(list);
    }
}

async function initSortableList(list) {
    if (!list.querySelector('[data-drag-handle]')) {
        return;
    }

    list.addEventListener('keydown', (event) => {
        const handle = event.target.closest('[data-drag-handle]');

        if (!handle || !['ArrowUp', 'ArrowDown'].includes(event.key)) {
            return;
        }

        const item = handle.closest('[data-sortable-item]');
        const sibling = event.key === 'ArrowUp' ? item.previousElementSibling : item.nextElementSibling;

        event.preventDefault();

        if (!sibling?.matches('[data-sortable-item]')) {
            return;
        }

        list.insertBefore(event.key === 'ArrowUp' ? item : sibling, event.key === 'ArrowUp' ? sibling : item);
        focusHandle(item);
        listChanged(list);
    });

    list.addEventListener('click', (event) => {
        const move = event.target.closest('[data-sort-move]');

        if (!move) {
            return;
        }

        const item = move.closest('[data-sortable-item]');

        if (move.dataset.sortMove === 'top') {
            list.prepend(item);
        } else {
            list.append(item);
        }

        focusHandle(item);
        listChanged(list);
    });

    const Sortable = await loadSortable();

    Sortable.create(list, {
        handle: '[data-drag-handle]',
        draggable: '[data-sortable-item]',
        animation: 150,
        ghostClass: 'opacity-40',
        onEnd: (event) => {
            if (event.oldIndex !== event.newIndex) {
                listChanged(list);
            }
        },
    });
}

function bindSaveButton(page) {
    page.querySelectorAll('[data-sort-save]').forEach((button) => {
        button.addEventListener('click', async () => {
            const scope = page.querySelector('[data-sort-scope]');

            if (!scope) {
                return;
            }

            button.setAttribute('data-loading', '');

            try {
                if (scope.matches('[data-category-tree]')) {
                    await saveTree(scope);
                } else {
                    await saveList(scope);
                }
            } finally {
                button.removeAttribute('data-loading');
            }
        });
    });
}

/* ---------------------------------------------------------- category form */

function slugify(value) {
    return value
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 180);
}

function initCategoryForm(form) {
    const source = form.querySelector('[data-slug-source]');
    const target = form.querySelector('[data-slug-target]');

    if (!source || !target) {
        return;
    }

    let locked = target.dataset.slugLocked !== undefined || target.value !== '';

    target.addEventListener('input', () => {
        locked = target.value !== '';
    });
    source.addEventListener('input', () => {
        if (!locked) {
            target.value = slugify(source.value);
        }
    });
}

/* --------------------------------------------------------- attribute form */

function initAttributeForm(form) {
    const list = form.querySelector('[data-option-list]');
    const template = form.querySelector('template[data-option-template]');
    const empty = form.querySelector('[data-option-empty]');
    const type = form.querySelector('[data-display-type]');
    const swatch = () => type?.value === 'color';
    const sync = () => {
        empty.hidden = list.children.length > 0;
        renumber(list);
    };

    form.querySelector('[data-option-add]')?.addEventListener('click', () => {
        const index = Number(list.dataset.nextIndex || list.children.length);
        list.dataset.nextIndex = String(index + 1);
        const html = template.innerHTML.replaceAll('__INDEX__', String(index));
        list.insertAdjacentHTML('beforeend', html);
        const row = list.lastElementChild;
        row.querySelectorAll('[data-swatch-field]').forEach((field) => (field.hidden = !swatch()));
        sync();
        row.querySelector('input[type="text"], input:not([type])')?.focus();
        form.dispatchEvent(new Event('input', { bubbles: true }));
    });

    list.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-option-remove]');

        if (remove) {
            const row = remove.closest('[data-option-row]');
            const next = row.nextElementSibling ?? row.previousElementSibling;
            row.remove();
            sync();
            focusHandle(next ?? form);
            form.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    list.addEventListener('input', (event) => {
        if (event.target.matches('[data-swatch-picker]')) {
            const text = event.target.closest('[data-swatch-field]').querySelector('[data-swatch-input]');
            text.value = event.target.value.toUpperCase();
        } else if (event.target.matches('[data-swatch-input]') && /^#[0-9a-f]{6}$/i.test(event.target.value)) {
            event.target.closest('[data-swatch-field]').querySelector('[data-swatch-picker]').value = event.target.value;
        }
    });

    type?.addEventListener('change', () => {
        form.querySelectorAll('[data-option-list] [data-swatch-field]').forEach((field) => (field.hidden = !swatch()));
    });

    sync();
}

/* ------------------------------------------------------------- label form */

function bindLabelPreview() {
    document.addEventListener('input', (event) => {
        const input = event.target.closest?.('[data-label-text]');
        const form = input?.closest('[data-label-form]');

        if (!form) {
            return;
        }

        const preview = form.querySelector('[data-label-preview]');
        let samples = {};

        try {
            samples = JSON.parse(form.querySelector('[data-label-samples]')?.textContent || '{}');
        } catch {
            samples = {};
        }

        const text = Object.entries(samples).reduce((value, [token, sample]) => value.split(token).join(sample), input.value);
        const output = preview.querySelector('[data-label-preview-text]');
        output.textContent = preview.dataset.template.replace(':text', text);
        output.dir = input.dir || 'auto';
    });
}

/* ----------------------------------------------------------- badge assign */

function initBadgeAssign(page, form) {
    const modal = form.closest('[data-overlay]');
    const results = form.querySelector('[data-badge-results]');
    const rowTemplate = form.querySelector('template[data-badge-row]');
    const search = form.querySelector('[data-badge-search]');
    const category = form.querySelector('[data-badge-category]');
    const submit = document.querySelector('[data-badge-submit]');
    const more = form.querySelector('[data-badge-more]');
    const selectAll = form.querySelector('[data-badge-select-all]');
    let currentPage = 1;
    let term = '';
    let controller = null;
    let timer = null;
    let loaded = false;

    const state = (name) => {
        form.querySelectorAll('[data-badge-state]').forEach((element) => (element.hidden = element.dataset.badgeState !== name));
    };
    const checked = () => [...results.querySelectorAll('input[type="checkbox"]:checked')];

    const updateSubmit = () => {
        const count = checked().length;
        const hasCategory = Boolean(category?.value);

        submit.disabled = count === 0 && !hasCategory;
        const label = count > 0 ? form.dataset.labelSubmit.replace(':count', String(count)) : hasCategory ? form.dataset.labelSubmitCategory : form.dataset.labelSubmitNone;
        const textNode = [...submit.childNodes].reverse().find((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '');
        const labelElement = submit.querySelector('.ui-btn-label');

        if (labelElement) {
            labelElement.textContent = label;
        } else if (textNode) {
            textNode.textContent = ` ${label}`;
        }

        const boxes = results.querySelectorAll('input[type="checkbox"]');
        selectAll.checked = boxes.length > 0 && count === boxes.length;
        selectAll.indeterminate = count > 0 && count < boxes.length;
    };

    const render = (rows, append) => {
        if (!append) {
            [...results.children].forEach((item) => {
                if (!item.querySelector('input:checked')) {
                    item.remove();
                }
            });
        }

        const present = new Set([...results.querySelectorAll('input[type="checkbox"]')].map((input) => input.value));

        rows.forEach((row) => {
            if (present.has(String(row.id))) {
                return;
            }

            const item = rowTemplate.content.firstElementChild.cloneNode(true);
            const box = item.querySelector('input');
            box.value = String(row.id);
            box.setAttribute('aria-label', form.dataset.labelSelect.replace(':name', row.name));
            item.querySelector('[data-field="name"]').textContent = row.name;
            item.querySelector('[data-field="sku"]').textContent = [row.sku, row.category].filter(Boolean).join(' · ');
            item.querySelector('[data-field="price"]').textContent = row.price;
            results.appendChild(item);
        });
    };

    const load = async (append = false) => {
        controller?.abort();
        controller = new AbortController();

        if (!append) {
            currentPage = 1;
            state(results.children.length ? '' : 'loading');
        }

        try {
            const payload = await http.get(form.dataset.searchUrl, { q: term, page: currentPage }, { signal: controller.signal, silent: true, toast: false });
            render(payload.data ?? [], append);
            more.hidden = !payload.meta?.more;
            state(results.children.length === 0 ? 'empty' : '');
            loaded = true;
        } catch (error) {
            if (error?.name !== 'AbortError') {
                more.hidden = true;
                state(results.children.length === 0 ? 'error' : '');
            }
        }

        updateSubmit();
    };

    search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            term = search.value.trim();
            load();
        }, 300);
    });
    search.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    });

    form.querySelector('[data-badge-load-more]')?.addEventListener('click', () => {
        currentPage += 1;
        load(true);
    });
    form.querySelector('[data-badge-retry]')?.addEventListener('click', () => load());
    results.addEventListener('change', updateSubmit);
    category?.addEventListener('change', updateSubmit);
    selectAll.addEventListener('change', () => {
        results.querySelectorAll('input[type="checkbox"]').forEach((box) => (box.checked = selectAll.checked));
        updateSubmit();
    });

    page.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-assign-mode]');

        if (!opener) {
            return;
        }

        if (!loaded) {
            load();
        }

        requestAnimationFrame(() => {
            if (opener.dataset.assignMode === 'category' && category) {
                window.jQuery?.(category).select2?.('open');
                category.focus();
            } else {
                search.focus();
            }
        });
    });

    modal?.addEventListener('overlay:close', () => {
        delete form.dataset.dirty;
    });

    updateSubmit();
}

function initBadgeSettings(form) {
    const select = form.querySelector('[data-auto-fill]');
    const options = form.querySelector('[data-auto-fill-options]');

    select?.addEventListener('change', () => {
        options.hidden = select.value === 'manual';
    });
}

/* ------------------------------------------------------------------- init */

let labelPreviewBound = false;

export default function initCatalog(page) {
    page.querySelectorAll('[data-category-tree]').forEach((tree) => initCategoryTree(page, tree));
    page.querySelectorAll('[data-sortable-list]').forEach((list) => initSortableList(list));
    page.querySelectorAll('[data-category-form]').forEach((form) => initCategoryForm(form));
    page.querySelectorAll('[data-attribute-form]').forEach((form) => initAttributeForm(form));
    page.querySelectorAll('[data-badge-settings]').forEach((form) => initBadgeSettings(form));

    const assign = document.querySelector('[data-badge-assign]');

    if (assign) {
        initBadgeAssign(page, assign);
    }

    bindSaveButton(page);
    bindUnsavedForms(page);

    if (document.querySelector('[data-remote-body]')) {
        bindRemoteModals(page);
    }

    if (!labelPreviewBound) {
        labelPreviewBound = true;
        bindLabelPreview();
    }
}
