/**
 * Product page (Ecommet 1:3380 / m 1:3073): variation pickers ↔ variant data island
 * (#sf-product: price, compare, discount, stock, image per variant), unavailable
 * combinations greyed out, ?variant= kept in the URL, gallery counter "2/8", and the
 * reviews preview + modal (sf-data Store API) with filters, sort, load more and the
 * write-a-review form.
 */
import { http, HttpError } from '../../core/http';
import { notify } from '../../core/notify';
import { openSheet, closeSheet } from '../core/motion/index.js';

const STAR = '<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2.5l2.94 6.08 6.56.95-4.75 4.63 1.12 6.54L12 17.6l-5.87 3.1 1.12-6.54L2.5 9.53l6.56-.95L12 2.5z"/></svg>';

function readIsland() {
    try {
        return JSON.parse(document.getElementById('sf-product')?.textContent || '{}');
    } catch {
        return {};
    }
}

function bindVariants(page, data) {
    const variants = data.variants ?? [];
    const groups = [...page.querySelectorAll('[data-attribute]')];

    if (!variants.length || !groups.length) {
        return;
    }

    const button = page.querySelector('[data-add-to-cart]');
    const chosen = () => Object.fromEntries(groups.map((group) => [group.dataset.attribute, Number(group.querySelector('[aria-checked="true"]')?.dataset.option || 0)]));
    const matches = (variant, selection) => Object.entries(selection).every(([attribute, option]) => !option || Number(variant.options?.[attribute]) === option);

    const render = () => {
        const selection = chosen();
        const variant = variants.find((item) => matches(item, selection) && Object.keys(item.options ?? {}).length === groups.length) ?? null;

        groups.forEach((group) => {
            group.querySelectorAll('[data-option]').forEach((option) => {
                const trial = { ...selection, [group.dataset.attribute]: Number(option.dataset.option) };
                const possible = variants.some((item) => matches(item, trial) && item.in_stock);
                option.toggleAttribute('data-unavailable', !possible);
            });
            const picked = group.querySelector('[aria-checked="true"]');
            const label = group.querySelector('[data-attribute-value]');

            if (label) {
                label.textContent = picked ? picked.getAttribute('aria-label') || picked.textContent.trim() : '';
            }
        });

        const set = (selector, value) => {
            const node = page.querySelector(selector);

            if (node) {
                node.textContent = value ?? '';
            }
        };

        if (variant) {
            set('[data-price]', variant.price);
            set('[data-compare-value]', variant.compare);
            page.querySelector('[data-compare]')?.toggleAttribute('hidden', !variant.compare);
            const discount = page.querySelector('[data-discount]');

            if (discount) {
                discount.hidden = !variant.discount;
                discount.textContent = discount.textContent.replace(/\d+/, String(variant.discount ?? 0));
            }

            const url = new URL(window.location.href);
            url.searchParams.set('variant', variant.id);
            window.history.replaceState(window.history.state, '', url);

            if (variant.image) {
                const gallery = page.querySelector('[data-gallery]')?.swiper;
                const index = [...page.querySelectorAll('[data-gallery-main] img')].findIndex((img) => img.currentSrc === variant.image || img.src === variant.image);

                if (gallery && index >= 0) {
                    gallery.slideTo(index);
                }
            }
        }

        if (button) {
            const available = Boolean(variant?.in_stock);
            button.disabled = !available;
            button.dataset.variantId = variant ? String(variant.id) : '';
            const label = button.querySelector('[data-cart-label]');

            if (label) {
                label.textContent = !variant ? data.texts?.unavailable : available ? data.texts?.add_to_cart : data.texts?.out_of_stock;
            }
        }
    };

    groups.forEach((group) => {
        group.addEventListener('click', (event) => {
            const option = event.target.closest('[data-option]');

            if (!option) {
                return;
            }

            group.querySelectorAll('[data-option]').forEach((other) => other.setAttribute('aria-checked', String(other === option)));
            render();
        });
        group.addEventListener('keydown', (event) => {
            const options = [...group.querySelectorAll('[data-option]')];
            const index = options.findIndex((option) => option === document.activeElement);
            const rtl = document.documentElement.dir === 'rtl';
            const step = { ArrowRight: rtl ? -1 : 1, ArrowDown: 1, ArrowLeft: rtl ? 1 : -1, ArrowUp: -1 }[event.key];

            if (index >= 0 && step) {
                event.preventDefault();
                const next = options[(index + step + options.length) % options.length];
                next.focus();
                next.click();
            }
        });
    });

    render();
}

function bindGalleryCounter(page) {
    const gallery = page.querySelector('[data-gallery]');
    const counter = page.querySelector('[data-gallery-counter]');

    if (!gallery || !counter) {
        return;
    }

    const total = gallery.querySelectorAll('[data-gallery-main] .swiper-slide').length;
    gallery.addEventListener('gallery:ready', (event) => {
        event.detail.swiper.on('slideChange', (swiper) => (counter.textContent = `${swiper.realIndex + 1}/${total}`));
    });
}

/* ---------- Reviews ---------- */

function reviewNode(template, review) {
    const node = template.content.firstElementChild.cloneNode(true);
    node.querySelector('[data-author]').textContent = review.author ?? '';
    node.querySelector('[data-date]').textContent = review.created_human ?? '';
    node.querySelector('[data-date]').setAttribute('title', review.created_at ?? '');
    node.querySelector('[data-verified]').hidden = !review.verified_purchase;
    const stars = node.querySelector('[data-stars]');
    stars.innerHTML = Array.from({ length: 5 }, (_, index) => (index < review.rating ? STAR : STAR.replace('<svg', '<svg class="is-off"'))).join('');
    stars.setAttribute('role', 'img');
    stars.setAttribute('aria-label', `${review.rating}/5`);
    const title = node.querySelector('[data-review-title]');
    title.textContent = review.title ?? '';
    title.hidden = !review.title;
    node.querySelector('[data-body]').textContent = review.body ?? '';

    return node;
}

function bindReviews(page, data) {
    const url = data.reviews_url;
    const modal = document.querySelector('[data-reviews-modal]');
    const template = document.querySelector('[data-review-template]');

    if (!url || !modal || !template) {
        return;
    }

    const list = modal.querySelector('[data-reviews-list]');
    const more = modal.querySelector('[data-reviews-more]');
    const empty = modal.querySelector('[data-reviews-empty]');
    const failed = modal.querySelector('[data-reviews-error]');
    const state = { page: 1, rating: '', sort: 'newest', loaded: false, eligibility: null };

    const fetchPage = (page = 1) => {
        const query = new URLSearchParams({ page: String(page), sort: state.sort });

        if (state.rating) {
            query.set('rating', state.rating);
        }

        return http.get(`${url}?${query}`, null, { toast: false, silent: true });
    };

    const load = async (reset) => {
        if (reset) {
            state.page = 1;
            list.replaceChildren();
        }

        failed.hidden = true;
        more.hidden = true;
        list.setAttribute('aria-busy', 'true');

        try {
            const payload = await fetchPage(state.page);
            const body = payload?.data ?? {};
            state.eligibility = body.eligibility ?? state.eligibility;
            list.append(...(body.items ?? []).map((review) => reviewNode(template, review)));
            empty.hidden = list.children.length > 0;
            more.hidden = !body.meta?.next_page_url;
            state.loaded = true;
        } catch {
            failed.hidden = false;
        } finally {
            list.removeAttribute('aria-busy');
        }
    };

    /* Preview under the gallery: the two newest reviews once the section is near. */
    const preview = page.querySelector('[data-reviews-preview]');

    if (preview) {
        const observer = new IntersectionObserver(async (entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) {
                return;
            }

            observer.disconnect();

            try {
                const payload = await fetchPage(1);
                const items = (payload?.data?.items ?? []).slice(0, 2);
                preview.replaceChildren(...items.map((review) => reviewNode(template, review)));
            } catch {
                preview.replaceChildren();
            }
        }, { rootMargin: '300px' });
        observer.observe(preview);
    }

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-sheet-open="sf-reviews"]') && !state.loaded) {
            load(true);
        }
    });

    modal.querySelectorAll('[data-review-filter]').forEach((chip) => chip.addEventListener('click', () => {
        modal.querySelectorAll('[data-review-filter]').forEach((other) => other.setAttribute('aria-pressed', String(other === chip)));
        state.rating = chip.dataset.reviewFilter;
        load(true);
    }));
    modal.querySelector('[data-review-sort]')?.addEventListener('change', (event) => {
        state.sort = event.target.value;
        load(true);
    });
    more.addEventListener('click', () => {
        state.page += 1;
        load(false);
    });

    bindReviewForm(url, state, () => load(true));
}

function bindReviewForm(url, state, reload) {
    const form = document.querySelector('[data-review-form]');

    if (!form) {
        return;
    }

    const error = form.querySelector('[data-review-form-error]');

    document.querySelectorAll('[data-review-write]').forEach((button) => button.addEventListener('click', () => {
        if (state.eligibility && state.eligibility.can_write === false && state.eligibility.reason === 'guest' && form.dataset.loginUrl) {
            notify.info(form.dataset.guestText);
            window.location.assign(form.dataset.loginUrl);
            return;
        }

        closeSheet('sf-reviews');
        openSheet('sf-review-write');
    }));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        error.hidden = true;
        form.querySelectorAll('[data-error-for]').forEach((node) => (node.hidden = true));
        const submit = form.querySelector('[type="submit"]');
        submit.disabled = true;

        try {
            const payload = await http.post(url, new FormData(form), { toast: false });
            notify.success(payload?.message || '');
            form.reset();
            closeSheet('sf-review-write');
            reload();
        } catch (failure) {
            if (failure instanceof HttpError && failure.status === 422) {
                Object.entries(failure.errors).forEach(([field, messages]) => {
                    const node = form.querySelector(`[data-error-for="${field}"]`);

                    if (node) {
                        node.textContent = messages[0];
                        node.hidden = false;
                    }
                });

                if (!Object.keys(failure.errors).length && failure.payload?.message) {
                    error.textContent = failure.payload.message;
                    error.hidden = false;
                }
            }
        } finally {
            submit.disabled = false;
        }
    });
}

export function initProduct(root = document) {
    const page = root.querySelector('[data-product-page]:not([data-ready])');

    if (!page) {
        return;
    }

    page.dataset.ready = '';
    const data = readIsland();
    bindVariants(page, data);
    bindGalleryCounter(page);
    bindReviews(page, data);
}
