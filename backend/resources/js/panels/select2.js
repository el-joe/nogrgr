/**
 * <select data-select data-placeholder="…" data-ajax-url="/admin/…/options">
 * Remote options must return {results: [{id, text}], pagination: {more}}.
 * (Not data-select2: Select2 reads data-* into its own data store and would treat
 * data-select2="" as an existing instance.)
 * <select data-select data-icon-picker> (<x-ui::icon-picker>): each option carries its
 * server-rendered icon in data-icon-svg; the list and the selected value show icon +
 * title, and search matches the title or the icon name.
 */
import { $ } from '../core/select2-lib.js';
import { t } from '../core/i18n';

function iconTemplate(state) {
    const svg = state.element?.dataset?.iconSvg;

    if (!state.id || !svg) {
        return state.text;
    }

    const option = document.createElement('span');
    option.className = 'ui-icon-option';
    const glyph = document.createElement('span');
    glyph.className = 'ui-icon-option-glyph';
    glyph.innerHTML = svg;
    const text = document.createElement('span');
    text.className = 'ui-icon-option-text';
    text.textContent = state.text;
    option.append(glyph, text);

    return $(option);
}

function iconMatcher(params, data) {
    const term = (params.term || '').trim().toLowerCase();

    if (term === '') {
        return data;
    }

    return `${data.text || ''} ${data.id || ''}`.toLowerCase().includes(term) ? data : null;
}

const iconPickerOptions = { templateResult: iconTemplate, templateSelection: iconTemplate, matcher: iconMatcher };

export function initSelect2(root = document) {
    $(root)
        .find('select[data-select]')
        .not('.select2-hidden-accessible')
        .each((_, element) => {
            const $element = $(element);
            const modal = element.closest('.ui-overlay-panel, [data-modal], dialog');
            const ajaxUrl = element.dataset.ajaxUrl;

            $element.select2({
                width: '100%',
                dir: document.documentElement.dir || 'ltr',
                placeholder: element.dataset.placeholder ?? '',
                minimumResultsForSearch: element.dataset.search === 'false' ? Infinity : 0,
                language: { noResults: () => t('select.no_results'), searching: () => t('select.searching') },
                allowClear: element.dataset.allowClear === 'true',
                dropdownParent: modal ? $(modal) : $(document.body),
                ajax: ajaxUrl
                    ? {
                          url: ajaxUrl,
                          dataType: 'json',
                          delay: 250,
                          data: (params) => ({ q: params.term, page: params.page || 1 }),
                      }
                    : undefined,
                ...(element.dataset.iconPicker !== undefined ? iconPickerOptions : {}),
            });

            /* jQuery's trigger('change') skips native listeners (filters bar, forms): bridge it. */
            $element.on('select2:select select2:unselect select2:clear', () => {
                element.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
}
