/**
 * Server-side Yajra DataTables wrapped in the Figma Data Table (34:11868) — see
 * <x-ui::datatable>. DataTables only renders the <table>; info, per-page select,
 * pagination, skeleton rows, empty/error states, bulk selection and the bulk action
 * bar are ours:
 *
 *   <div class="ui-datatable" data-datatable-card data-state="loading">
 *     <table id="products" data-datatable data-url="…" data-table-columns='[…]' data-selectable data-table-length="10">
 *     (not data-columns / data-order / data-page-length: DataTables would read those as its own HTML5 options)
 *     [data-dt-skeleton] [data-dt-info] [data-dt-length] [data-dt-pagination]
 *     <template data-dt-empty> <template data-dt-empty-filtered> <template data-dt-error>
 *     [data-dt-bulk] [data-dt-bulk-count] [data-dt-bulk-clear] [data-bulk-action="<action>"][data-url][data-method]
 *     [data-bulk-confirm="message"][data-bulk-confirm-title] (asks before sending)
 *
 * Filters: every [data-filter] of the filters bar bound to the table (see filters.js)
 * is sent with each request. `datatable:reload` on the card reloads it in place
 * ({detail: {keepPage, quiet}}); `datatable:drawn` fires on the card after each draw.
 */
import { $, DataTable } from './vendor-libs';
import { t, numberLocale } from '../core/i18n';
import { http } from '../core/http';
import { collectFilters, hasActiveFilters } from './ui/filters';
import { confirmDialog } from './ui/confirm';

const PAGE_WINDOW = 1;

function pageList(page, pages) {
    const list = new Set([0, pages - 1]);

    for (let index = page - PAGE_WINDOW; index <= page + PAGE_WINDOW; index++) {
        if (index >= 0 && index < pages) {
            list.add(index);
        }
    }

    const sorted = [...list].filter((index) => index >= 0).sort((a, b) => a - b);
    const result = [];

    sorted.forEach((index, position) => {
        if (position > 0 && index - sorted[position - 1] > 1) {
            result.push('gap');
        }

        result.push(index);
    });

    return result;
}

function renderPagination(card, api) {
    const info = api.page.info();
    const number = new Intl.NumberFormat(numberLocale());
    const infoTarget = card.querySelector('[data-dt-info]');

    if (infoTarget) {
        infoTarget.textContent =
            info.recordsDisplay > 0
                ? t('table.showing', { from: number.format(info.start + 1), to: number.format(info.end), total: number.format(info.recordsDisplay) })
                : t('table.showing_none');
    }

    const nav = card.querySelector('[data-dt-pagination]');

    if (!nav) {
        return;
    }

    const pages = Math.max(info.pages, 1);
    const button = (label, page, { current = false, disabled = false, aria = '', kind = 'page' } = {}) =>
        `<button type="button" data-dt-page="${page}" data-pager="${kind}" class="ui-pager-btn grid h-8 min-w-8 place-items-center rounded-chip px-2 text-[13px] font-medium transition-colors ${
            current ? 'bg-primary text-on-primary' : 'text-ink hover:bg-subtle'
        } disabled:pointer-events-none disabled:text-ink-muted" ${current ? 'aria-current="page"' : ''} ${disabled ? 'disabled' : ''} ${aria ? `aria-label="${aria}"` : ''}>${label}</button>`;
    const arrow = (direction) =>
        `<svg class="ui-icon" data-flip width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${direction === 'prev' ? 'M15 6L9 12L15 18' : 'M9 6L15 12L9 18'}"/></svg>`;

    const desktop = pageList(info.page, pages)
        .map((item) => (item === 'gap' ? '<span class="px-1 text-ink-muted" aria-hidden="true">…</span>' : button(number.format(item + 1), item, { current: item === info.page })))
        .join('');

    nav.innerHTML = `
        ${button(`${arrow('prev')}<span class="ui-pager-label">${t('table.previous_short')}</span>`, info.page - 1, { disabled: info.page <= 0, aria: t('table.previous'), kind: 'step' })}
        <span class="hidden items-center gap-1 sm:flex">${desktop}</span>
        <span class="px-2 text-[13px] text-ink-secondary sm:hidden">${t('table.page_of', { page: number.format(info.page + 1), pages: number.format(pages) })}</span>
        ${button(`<span class="ui-pager-label">${t('table.next_short')}</span>${arrow('next')}`, info.page + 1, { disabled: info.page >= pages - 1, aria: t('table.next'), kind: 'step' })}`;
}

function selectedIds(card) {
    return [...card.querySelectorAll('input[data-dt-select]:checked')].map((input) => input.value);
}

function syncSelection(card) {
    const boxes = [...card.querySelectorAll('input[data-dt-select]')];
    const checked = boxes.filter((box) => box.checked);
    const all = card.querySelector('input[data-dt-select-all]');

    boxes.forEach((box) => box.closest('tr')?.toggleAttribute('data-selected', box.checked));

    if (all) {
        all.checked = boxes.length > 0 && checked.length === boxes.length;
        all.indeterminate = checked.length > 0 && checked.length < boxes.length;
    }

    const bar = card.querySelector('[data-dt-bulk]');

    if (bar) {
        bar.hidden = checked.length === 0;
        const count = bar.querySelector('[data-dt-bulk-count]');

        if (count) {
            count.textContent = new Intl.NumberFormat(numberLocale()).format(checked.length);
        }
    }
}

function showState(card, api, state) {
    card.dataset.state = state;

    if (state !== 'empty' && state !== 'error') {
        return;
    }

    const templateName = state === 'error' ? 'data-dt-error' : hasActiveFilters(card.querySelector('table').id) ? 'data-dt-empty-filtered' : 'data-dt-empty';
    const template = card.querySelector(`template[${templateName}]`) ?? card.querySelector('template[data-dt-empty]');
    const tbody = card.querySelector('table tbody');

    if (template && tbody) {
        const columns = api.columns().visible().filter(Boolean).length;
        tbody.innerHTML = `<tr><td colspan="${columns}" class="dt-empty"></td></tr>`;
        tbody.querySelector('td').appendChild(template.content.cloneNode(true));
    }
}

export function initDataTable(table, options = {}) {
    const card = table.closest('[data-datatable-card]') ?? table.parentElement;
    const selectable = table.dataset.selectable !== undefined;
    const columns = JSON.parse(table.dataset.tableColumns || '[]').map((column) => ({
        ...column,
        className: [column.className, column.align === 'end' ? 'dt-right' : ''].filter(Boolean).join(' '),
    }));

    if (selectable) {
        columns.unshift({
            data: table.dataset.rowKey || 'id',
            orderable: false,
            searchable: false,
            className: 'w-12',
            render: (value) => `<input type="checkbox" class="ui-check" data-dt-select value="${String(value).replace(/"/g, '&quot;')}" aria-label="${t('table.select_row')}">`,
        });
    }

    const titles = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());

    const api = new DataTable(table, {
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        pageLength: Number(table.dataset.tableLength || 10),
        order: JSON.parse(table.dataset.tableOrder || (selectable ? '[[1,"asc"]]' : '[[0,"asc"]]')),
        layout: { topStart: null, topEnd: null, bottomStart: null, bottomEnd: null },
        ajax: {
            url: table.dataset.url,
            data: (data) => {
                /* The filters bar search box drives DataTables' own search[value]; other filters are top-level keys. */
                const { search, ...filters } = collectFilters(table.id);
                data.search = { value: typeof search === 'string' ? search : '', regex: false };

                return Object.assign(data, filters);
            },
            error: (xhr) => {
                if (xhr.status === 401 || xhr.status === 419) {
                    window.location.reload();
                    return;
                }

                showState(card, api, 'error');
            },
        },
        columns,
        createdRow: (row) => {
            row.querySelectorAll('td').forEach((cell, index) => cell.setAttribute('data-label', titles[index] ?? ''));
        },
        drawCallback: function () {
            const instance = this.api();
            renderPagination(card, instance);
            syncSelection(card);
            showState(card, instance, instance.page.info().recordsDisplay === 0 ? 'empty' : 'ready');
            delete card.dataset.quietReload;
            card.dispatchEvent(new CustomEvent('datatable:drawn'));
        },
        language: { processing: '', emptyTable: '', zeroRecords: '' },
        ...options,
    });

    api.on('processing.dt', (event, settings, processing) => {
        if (processing && card.dataset.quietReload === undefined) {
            card.dataset.state = 'loading';
        }
    });

    card.addEventListener('click', (event) => {
        const page = event.target.closest('[data-dt-page]');

        if (page && !page.disabled) {
            api.page(Number(page.dataset.dtPage)).draw('page');
            return;
        }

        if (event.target.closest('[data-dt-bulk-clear]')) {
            card.querySelectorAll('input[data-dt-select]').forEach((box) => (box.checked = false));
            syncSelection(card);
            return;
        }

        if (event.target.closest('[data-dt-retry]')) {
            api.ajax.reload(null, false);
        }
    });

    card.addEventListener('click', async (event) => {
        const action = event.target.closest('[data-bulk-action]');

        if (!action) {
            return;
        }

        event.preventDefault();

        /* data-bulk-confirm="message" (+ data-bulk-confirm-title) asks first; a value on
           data-bulk-action is sent as `action` next to the selected `ids`. */
        if (action.dataset.bulkConfirm !== undefined) {
            const ok = await confirmDialog({ title: action.dataset.bulkConfirmTitle, message: action.dataset.bulkConfirm, tone: 'danger' });

            if (!ok) {
                return;
            }
        }

        const data = { ids: selectedIds(card) };

        if (action.dataset.bulkAction) {
            data.action = action.dataset.bulkAction;
        }

        try {
            await http.request(action.dataset.method || 'POST', action.dataset.url, { data });
            card.querySelectorAll('input[data-dt-select]').forEach((box) => (box.checked = false));
            syncSelection(card);
            api.ajax.reload(null, false);
        } catch {
            // toast already shown by http.js
        }
    });

    card.addEventListener('change', (event) => {
        if (event.target.matches('input[data-dt-select-all]')) {
            card.querySelectorAll('input[data-dt-select]').forEach((box) => (box.checked = event.target.checked));
            syncSelection(card);
        } else if (event.target.matches('input[data-dt-select]')) {
            syncSelection(card);
        } else if (event.target.matches('select[data-dt-length]')) {
            api.page.len(Number(event.target.value)).draw();
        } else if (event.target.matches('input[data-dt-column]')) {
            api.column(Number(event.target.dataset.dtColumn) + (selectable ? 1 : 0)).visible(event.target.checked);
        }
    });

    /*
     * Filters/search changed: go back to page 1 (QA-P0-013). Pass detail.keepPage to stay on
     * the current page, detail.quiet to refresh the rows in place without the skeleton
     * (after a row switch, QA-P8-005). `datatable:drawn` fires on the card after each draw.
     */
    card.addEventListener('datatable:reload', (event) => {
        if (event.detail?.quiet) {
            card.dataset.quietReload = '';
        }

        api.ajax.reload(null, !event.detail?.keepPage);
    });

    return api;
}

export function initDataTables(root = document) {
    root.querySelectorAll('table[data-datatable]:not(.dataTable)').forEach((table) => initDataTable(table));
}

export function reloadDataTable(table) {
    $(table).DataTable().ajax.reload(null, false);
}
