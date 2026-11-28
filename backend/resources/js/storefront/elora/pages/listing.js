/**
 * Elora product listing (Figma 1:17172 / m 1:21453 / filter sheet m 1:22799).
 *  - long filter groups: "Show more" / "Show less" ([data-elp-more]) and a search box that
 *    also finds the hidden values ([data-elp-search]);
 *  - "In stock" / "Out of stock" exclude each other ([data-elp-exclusive]);
 *  - empty fields never reach the URL;
 *  - phone sheet: "show ( n result )" counts the selection before it is applied;
 *  - the next page loads when the end of the grid comes into view ([data-elp-auto]);
 *    the shared catalog/listing.js does the appending;
 *  - the grid dims while a filter / quick pill navigation is on its way.
 */
import { http } from '../../../core/http';

const STEP = 20;

function bindGroups(form) {
    /* Long lists open one step at a time (a store can have 1000+ colours); nothing scrolls inside a group. */
    form.querySelectorAll('[data-elp-more]').forEach((more) => {
        const group = more.closest('.elp-group');
        const less = group.querySelector('[data-elp-less]');

        more.addEventListener('click', () => {
            const hidden = [...group.querySelectorAll('.elp-check[data-more]')];
            hidden.slice(0, STEP).forEach((row) => {
                row.removeAttribute('data-more');
                row.setAttribute('data-revealed', '');
            });
            more.hidden = hidden.length <= STEP;
            less.hidden = false;
        });
        less.addEventListener('click', () => {
            group.querySelectorAll('.elp-check[data-revealed]').forEach((row) => {
                row.removeAttribute('data-revealed');

                if (!row.querySelector('input:checked')) {
                    row.setAttribute('data-more', '');
                }
            });
            more.hidden = false;
            less.hidden = true;
            more.focus({ preventScroll: true });
            group.scrollIntoView({ block: 'nearest' });
        });
    });

    form.querySelectorAll('[data-elp-search]').forEach((input) => {
        const group = input.closest('.elp-group');
        const none = group.querySelector('[data-elp-none]');

        input.addEventListener('input', () => {
            const term = input.value.trim().toLocaleLowerCase();
            let shown = 0;
            group.toggleAttribute('data-searching', term !== '');
            group.querySelectorAll('[data-elp-value]').forEach((row) => {
                const match = !term || row.dataset.elpValue.includes(term);
                row.hidden = !match;
                shown += match ? 1 : 0;
            });

            if (none) {
                none.hidden = shown > 0;
            }
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
    });
}

/** Fields that would only add noise to the URL (empty price, "any" radios). */
function isBlank(field) {
    return field.name && field.value === '' && field.type !== 'hidden';
}

function query(form) {
    const url = new URL(form.action, window.location.href);
    url.search = '';
    new FormData(form).forEach((value, name) => {
        if (value !== '') {
            url.searchParams.append(name, value);
        }
    });

    return url;
}

function bindForm(form) {
    bindGroups(form);

    /* Capture phase: runs before the autosubmit timer reads the form. */
    form.addEventListener('change', (event) => {
        const field = event.target;

        if (field.dataset.elpExclusive && field.checked) {
            form.querySelectorAll(`[data-elp-exclusive="${field.dataset.elpExclusive}"]`).forEach((other) => {
                if (other !== field) {
                    other.checked = false;
                }
            });
        }
    }, true);

    form.addEventListener('submit', () => {
        [...form.elements].filter(isBlank).forEach((field) => {
            field.disabled = true;
            field.dataset.elpBlank = '';
        });
        document.querySelector('.elp-main')?.setAttribute('data-busy', '');
    });
}

function bindCount(form) {
    const label = form.querySelector('[data-elp-count]');

    if (!label) {
        return;
    }

    let timer = null;
    let ticket = 0;

    const refresh = () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const url = query(form);
            url.searchParams.set('fragment', '1');
            const mine = ++ticket;
            label.setAttribute('aria-busy', 'true');

            try {
                const payload = await http.get(url.toString(), null, { toast: false });

                if (mine === ticket) {
                    label.textContent = label.dataset.template.replace('__n__', String(payload.total ?? 0));
                }
            } catch {
                /* The count is a preview: "show" still applies the filters. */
                if (mine === ticket) {
                    label.textContent = '';
                }
            } finally {
                label.removeAttribute('aria-busy');
            }
        }, 300);
    };

    form.addEventListener('change', (event) => {
        if (!event.target.matches('[data-elp-search]')) {
            refresh();
        }
    });
}

function bindAutoLoad(link) {
    if (!('IntersectionObserver' in window)) {
        return;
    }

    let failed = false;
    const observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting) && !link.dataset.busy && !failed) {
            link.click();
        }
    }, { rootMargin: '600px 0px' });

    /* catalog/listing.js moves href to the following page after a successful load. */
    new MutationObserver((changes) => {
        for (const change of changes) {
            if (change.attributeName === 'href') {
                observer.unobserve(link);
                observer.observe(link);
            }

            if (change.attributeName === 'data-busy' && !link.dataset.busy) {
                failed = link.querySelector('[data-load-more-label]')?.textContent === link.dataset.errorText;
            }
        }
    }).observe(link, { attributes: true, attributeFilter: ['href', 'data-busy'] });
    link.addEventListener('click', () => {
        failed = false;
    });
    observer.observe(link);
}

let restored = false;

export function initEloraListing(root = document) {
    root.querySelectorAll('.elp-form:not([data-ready-listing])').forEach((form) => {
        form.dataset.readyListing = '';
        bindForm(form);
        bindCount(form);
    });
    root.querySelectorAll('[data-elp-auto]:not([data-ready-auto])').forEach((link) => {
        link.dataset.readyAuto = '';
        bindAutoLoad(link);
    });
    root.querySelectorAll('[data-elp-nav]:not([data-ready])').forEach((anchor) => {
        anchor.dataset.ready = '';
        anchor.addEventListener('click', (event) => {
            if (!event.defaultPrevented && !event.metaKey && !event.ctrlKey && !event.shiftKey) {
                document.querySelector('.elp-main')?.setAttribute('data-busy', '');
            }
        });
    });

    if (!restored) {
        restored = true;
        /* Back/forward cache: undo the busy and disabled states set while leaving. */
        window.addEventListener('pageshow', (event) => {
            if (!event.persisted) {
                return;
            }

            document.querySelector('.elp-main')?.removeAttribute('data-busy');
            document.querySelectorAll('.elp-form').forEach((form) => {
                form.removeAttribute('aria-busy');
                form.querySelectorAll('[data-elp-blank]').forEach((field) => {
                    field.disabled = false;
                });
            });
        });
    }
}
