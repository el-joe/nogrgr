/**
 * Header preferences panel: the currency picker as a searchable Select2 (flag, symbol,
 * code and name), styled like the shipping-country field. The dropdown is attached to the
 * sheet (not the scrolling panel) so it is never clipped; Escape closes it before the sheet.
 */
import { $ } from '../../core/select2-lib.js';
import 'intl-tel-input/styles';

function option(state) {
    const element = state.element;

    if (!element) {
        return state.text;
    }

    const row = document.createElement('span');
    row.className = 'sf-currency';
    const flag = Object.assign(document.createElement('span'), { className: `iti__flag iti__${element.dataset.iso2} sf-flag-sprite` });
    flag.setAttribute('aria-hidden', 'true');
    const code = Object.assign(document.createElement('b'), { textContent: element.value });
    code.dir = 'ltr';
    const name = Object.assign(document.createElement('span'), { className: 'sf-currency-name', textContent: element.dataset.name ?? '' });
    const symbol = Object.assign(document.createElement('span'), { className: 'sf-currency-symbol', textContent: element.dataset.symbol ?? '' });
    row.append(flag, code, name, symbol);

    return $(row);
}

export function initCurrencySelect(select, panel) {
    const sheet = panel.closest('[data-sheet]') ?? panel;
    const $select = $(select);

    $select.select2({
        dropdownParent: $(sheet),
        width: '100%',
        dir: document.documentElement.dir || 'ltr',
        templateResult: option,
        templateSelection: option,
        language: { noResults: () => select.dataset.emptyText ?? '' },
        matcher: (params, data) => {
            const term = (params.term ?? '').trim().toLowerCase();

            return term === '' || `${data.text} ${data.element?.dataset.symbol ?? ''}`.toLowerCase().includes(term) ? data : null;
        },
        dropdownCssClass: 'sf-currency-dropdown',
        selectionCssClass: 'sf-currency-selection',
    });

    $select.on('select2:open', () => {
        const search = sheet.querySelector('.sf-currency-dropdown .select2-search__field');

        search?.setAttribute('placeholder', select.dataset.searchText ?? '');
        search?.setAttribute('aria-label', select.dataset.searchText ?? '');
        search?.focus();
    });
}
