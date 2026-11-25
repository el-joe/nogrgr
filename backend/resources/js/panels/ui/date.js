/**
 * Date picker (x-ui::date, x-ui::daterange — Figma Date Picker 27:10490).
 * Single or range; popover with a presets column (range: Today, Last 7 days,
 * Last 30 days, This month, Custom) and a month grid of 28px cells. Range ends use a
 * brand fill, days in between brand-subtle. Values are ISO dates in hidden inputs
 * ([data-date-value="single|from|to"]); each update fires a bubbling `change` on them
 * (filters bars listen to that). Display text follows the page locale.
 * Keyboard: arrows move the focused day, Enter/Space picks, PageUp/PageDown change month, Esc closes.
 */
import { t, numberLocale } from '../../core/i18n';
import chevronLeft from '../../../svg/icons/chevron-left.svg?raw';
import chevronRight from '../../../svg/icons/chevron-right.svg?raw';

const pad = (value) => String(value).padStart(2, '0');
const iso = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const parse = (value) => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');

    return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null;
};
const sameDay = (a, b) => a && b && iso(a) === iso(b);
const addDays = (date, days) => new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);
const today = () => {
    const now = new Date();

    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
};

const locale = () => numberLocale();
const formatter = () => new Intl.DateTimeFormat(locale(), { day: '2-digit', month: 'short', year: 'numeric' });

function presets() {
    const now = today();

    return [
        { key: 'today', label: t('date.today'), range: [now, now] },
        { key: '7d', label: t('date.last_7_days'), range: [addDays(now, -6), now] },
        { key: '30d', label: t('date.last_30_days'), range: [addDays(now, -29), now] },
        { key: 'month', label: t('date.this_month'), range: [new Date(now.getFullYear(), now.getMonth(), 1), now] },
    ];
}

class DatePicker {
    constructor(root) {
        this.root = root;
        this.range = root.dataset.mode === 'range';
        this.trigger = root.querySelector('[data-date-trigger]');
        this.display = root.querySelector('[data-date-display]');
        this.popover = root.querySelector('[data-date-popover]');
        this.inputs = {
            single: root.querySelector('[data-date-value="single"]'),
            from: root.querySelector('[data-date-value="from"]'),
            to: root.querySelector('[data-date-value="to"]'),
        };
        this.min = parse(root.dataset.min);
        this.max = parse(root.dataset.max);
        this.from = parse(this.range ? this.inputs.from?.value : this.inputs.single?.value);
        this.to = this.range ? parse(this.inputs.to?.value) : null;
        this.cursor = new Date((this.from ?? today()).getFullYear(), (this.from ?? today()).getMonth(), 1);
        this.focusDay = this.from ?? today();

        this.trigger.addEventListener('click', () => (this.isOpen() ? this.close() : this.open()));
        root.querySelector('[data-date-clear]')?.addEventListener('click', () => this.clear());
        this.popover.addEventListener('click', (event) => this.onClick(event));
        this.popover.addEventListener('keydown', (event) => this.onKey(event));
        document.addEventListener('click', (event) => {
            if (this.isOpen() && !root.contains(event.target)) {
                this.close();
            }
        });
        root.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && this.isOpen()) {
                event.preventDefault();
                this.close(true);
            }
        });
        this.renderDisplay();
    }

    isOpen() {
        return this.popover.dataset.open !== undefined;
    }

    open() {
        this.popover.dataset.open = '';
        this.trigger.setAttribute('aria-expanded', 'true');
        this.render();
        this.popover.querySelector('[data-day][tabindex="0"]')?.focus();
    }

    close(focusTrigger = false) {
        delete this.popover.dataset.open;
        this.trigger.setAttribute('aria-expanded', 'false');

        if (focusTrigger) {
            this.trigger.focus();
        }
    }

    clear() {
        this.from = null;
        this.to = null;
        this.commit();
    }

    disabled(date) {
        return (this.min && date < this.min) || (this.max && date > this.max);
    }

    pick(date) {
        if (this.disabled(date)) {
            return;
        }

        if (!this.range) {
            this.from = date;
            this.commit();
            this.close(true);
            return;
        }

        if (!this.from || this.to || date < this.from) {
            this.from = date;
            this.to = null;
            this.focusDay = date;
            this.render();
            return;
        }

        this.to = date;
        this.commit();
        this.close(true);
    }

    commit() {
        const set = (input, value) => {
            if (input && input.value !== value) {
                input.value = value;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };

        if (this.range) {
            set(this.inputs.from, this.from ? iso(this.from) : '');
            set(this.inputs.to, this.to ? iso(this.to) : '');
        } else {
            set(this.inputs.single, this.from ? iso(this.from) : '');
        }

        this.renderDisplay();
        this.root.dispatchEvent(new CustomEvent('date:change', { bubbles: true, detail: { from: this.from, to: this.to } }));
    }

    renderDisplay() {
        const format = formatter();
        const placeholder = this.display.dataset.placeholder ?? '';
        let text = placeholder;

        if (this.range && this.from && this.to) {
            text = `${format.format(this.from)} → ${format.format(this.to)}`;
        } else if (this.from) {
            text = format.format(this.from);
        }

        this.display.textContent = text;
        this.display.classList.toggle('text-ink-muted', text === placeholder);
        this.root.querySelector('[data-date-clear]')?.toggleAttribute('hidden', !this.from);
    }

    onClick(event) {
        const day = event.target.closest('[data-day]');
        const nav = event.target.closest('[data-month]');
        const preset = event.target.closest('[data-preset]');

        if (day) {
            this.pick(parse(day.dataset.day));
        } else if (nav) {
            this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + Number(nav.dataset.month), 1);
            this.render();
        } else if (preset) {
            if (preset.dataset.preset === 'custom') {
                this.from = null;
                this.to = null;
                this.render();
                return;
            }

            const match = presets().find((item) => item.key === preset.dataset.preset);
            [this.from, this.to] = match.range;
            this.commit();
            this.close(true);
        }
    }

    onKey(event) {
        const day = event.target.closest('[data-day]');

        if (!day) {
            return;
        }

        const rtl = document.documentElement.dir === 'rtl';
        const moves = { ArrowLeft: rtl ? 1 : -1, ArrowRight: rtl ? -1 : 1, ArrowUp: -7, ArrowDown: 7 };
        let next = null;

        if (event.key in moves) {
            next = addDays(parse(day.dataset.day), moves[event.key]);
        } else if (event.key === 'PageUp' || event.key === 'PageDown') {
            const current = parse(day.dataset.day);
            next = new Date(current.getFullYear(), current.getMonth() + (event.key === 'PageDown' ? 1 : -1), current.getDate());
        } else if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            this.pick(parse(day.dataset.day));
            return;
        } else {
            return;
        }

        event.preventDefault();
        this.focusDay = next;
        this.cursor = new Date(next.getFullYear(), next.getMonth(), 1);
        this.render();
        this.popover.querySelector(`[data-day="${iso(next)}"]`)?.focus();
    }

    render() {
        const monthLabel = new Intl.DateTimeFormat(locale(), { month: 'long', year: 'numeric' }).format(this.cursor);
        const weekday = new Intl.DateTimeFormat(locale(), { weekday: 'narrow' });
        const first = new Date(this.cursor.getFullYear(), this.cursor.getMonth(), 1);
        const start = addDays(first, -first.getDay());
        const focus = this.focusDay && this.focusDay.getMonth() === this.cursor.getMonth() ? this.focusDay : first;
        const now = today();
        const activePreset = this.range && this.from && this.to ? presets().find((item) => sameDay(item.range[0], this.from) && sameDay(item.range[1], this.to))?.key : null;

        const presetColumn = this.range
            ? `<div class="flex flex-col gap-1 border-line pe-3 sm:border-e" role="group" aria-label="${t('date.presets')}">
                ${[...presets(), { key: 'custom', label: t('date.custom') }]
                    .map(
                        (item) =>
                            `<button type="button" data-preset="${item.key}" class="ui-menu-item h-8 min-h-8 whitespace-nowrap text-[13px] ${activePreset === item.key || (item.key === 'custom' && !activePreset) ? 'bg-primary-subtle text-ink-brand' : ''}">${item.label}</button>`,
                    )
                    .join('')}
               </div>`
            : '';

        let cells = '';

        for (let index = 0; index < 42; index++) {
            const date = addDays(start, index);
            const inMonth = date.getMonth() === this.cursor.getMonth();

            if (!inMonth) {
                cells += '<span class="size-7" aria-hidden="true"></span>';
                continue;
            }

            const isEnd = sameDay(date, this.from) || sameDay(date, this.to);
            const between = this.range && this.from && this.to && date > this.from && date < this.to;
            const off = this.disabled(date);
            const classes = [
                'grid size-7 place-items-center rounded-chip text-xs transition-colors',
                isEnd ? 'bg-primary text-on-primary font-medium' : between ? 'bg-primary-subtle text-ink' : 'text-ink hover:bg-subtle',
                sameDay(date, now) && !isEnd ? 'ring-1 ring-inset ring-primary' : '',
                off ? 'pointer-events-none text-ink-muted opacity-50' : '',
            ].join(' ');
            cells += `<button type="button" data-day="${iso(date)}" class="${classes}" tabindex="${sameDay(date, focus) ? 0 : -1}" aria-pressed="${isEnd}" ${off ? 'aria-disabled="true"' : ''} aria-label="${formatter().format(date)}">${date.getDate()}</button>`;
        }

        const weekdays = Array.from({ length: 7 }, (_, index) => `<span class="grid size-7 place-items-center text-xs text-ink-muted">${weekday.format(addDays(start, index))}</span>`).join('');

        this.popover.innerHTML = `
            <div class="flex flex-col gap-3 sm:flex-row">
                ${presetColumn}
                <div>
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <button type="button" data-month="-1" class="grid size-7 place-items-center rounded-chip text-ink-secondary hover:bg-subtle" aria-label="${t('date.previous_month')}">${chevronLeft.replace('<svg', '<svg width="16" height="16" class="ui-icon" data-flip')}</button>
                        <p class="text-[13px] font-medium text-ink" aria-live="polite">${monthLabel}</p>
                        <button type="button" data-month="1" class="grid size-7 place-items-center rounded-chip text-ink-secondary hover:bg-subtle" aria-label="${t('date.next_month')}">${chevronRight.replace('<svg', '<svg width="16" height="16" class="ui-icon" data-flip')}</button>
                    </div>
                    <div class="grid grid-cols-7 gap-1" role="grid">${weekdays}${cells}</div>
                </div>
            </div>`;
    }
}

export function initDatePickers(root = document) {
    root.querySelectorAll('[data-datepicker]:not([data-datepicker-ready])').forEach((element) => {
        element.dataset.datepickerReady = '';
        element.__datepicker = new DatePicker(element);
    });
}
