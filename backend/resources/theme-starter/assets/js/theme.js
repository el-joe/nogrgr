/*
 * Starter theme behaviour (plain JavaScript, no frameworks). Cart, wishlist, coupon and
 * variant behaviour comes from the platform runtime ({{ scripts() }}, data-nt-* attributes);
 * this file only adds the theme's own interface touches:
 *   - mobile menu toggle            [data-menu-toggle] -> #site-nav
 *   - product gallery thumbnails    [data-gallery-thumb data-src]
 *   - swipeable rails               [data-rail-track] (touch, trackpad, mouse drag) + arrows
 *   - scroll reveal of sections     .section (respects prefers-reduced-motion)
 */
(() => {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* Mobile menu */
    const toggle = document.querySelector('[data-menu-toggle]');
    const menu = document.getElementById(toggle?.getAttribute('aria-controls') || '');

    if (toggle && menu) {
        const setOpen = (open) => {
            toggle.setAttribute('aria-expanded', String(open));
            menu.classList.toggle('is-open', open);
        };

        toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                setOpen(false);
                toggle.focus();
            }
        });
    }

    /* Gallery thumbnails */
    document.querySelectorAll('[data-gallery]').forEach((gallery) => {
        const main = gallery.querySelector('[data-gallery-main]');

        gallery.addEventListener('click', (event) => {
            const thumb = event.target.closest('[data-gallery-thumb]');

            if (!thumb || !main || thumb.classList.contains('is-active')) {
                return;
            }

            gallery.querySelectorAll('[data-gallery-thumb]').forEach((item) => {
                item.classList.toggle('is-active', item === thumb);
                item.setAttribute('aria-pressed', String(item === thumb));
            });

            const swap = () => {
                main.src = thumb.dataset.src;
                main.classList.remove('is-swapping');
            };

            if (reduceMotion) {
                swap();
            } else {
                main.classList.add('is-swapping');
                window.setTimeout(swap, 180);
            }
        });
    });

    /* Rails: mouse drag (touch and trackpads scroll natively) + previous / next buttons */
    document.querySelectorAll('[data-rail-track]').forEach((track) => {
        let startX = 0;
        let startScroll = 0;
        let dragging = false;
        let moved = false;

        track.addEventListener('pointerdown', (event) => {
            if (event.pointerType !== 'mouse' || event.button !== 0) {
                return;
            }

            dragging = true;
            moved = false;
            startX = event.clientX;
            startScroll = track.scrollLeft;
        });

        track.addEventListener('pointermove', (event) => {
            if (!dragging) {
                return;
            }

            const distance = event.clientX - startX;

            if (!moved && Math.abs(distance) > 6) {
                moved = true;
                track.classList.add('is-dragging');
                track.setPointerCapture(event.pointerId);
            }

            if (moved) {
                track.scrollLeft = startScroll - distance;
            }
        });

        const stop = () => {
            dragging = false;
            window.requestAnimationFrame(() => track.classList.remove('is-dragging'));
        };

        track.addEventListener('pointerup', stop);
        track.addEventListener('pointercancel', stop);
        track.addEventListener('click', (event) => {
            if (moved) {
                event.preventDefault();
                event.stopPropagation();
                moved = false;
            }
        }, true);
        track.addEventListener('dragstart', (event) => event.preventDefault());

        const rail = track.closest('[data-rail]');

        if (!rail) {
            return;
        }

        const prev = rail.querySelector('[data-rail-prev]');
        const next = rail.querySelector('[data-rail-next]');
        const rtl = document.documentElement.dir === 'rtl';

        const update = () => {
            const max = track.scrollWidth - track.clientWidth;
            const position = Math.abs(track.scrollLeft);

            rail.classList.toggle('is-scrollable', max > 4);
            if (prev) {
                prev.disabled = position <= 4;
            }
            if (next) {
                next.disabled = position >= max - 4;
            }
        };

        const step = (direction) => {
            track.scrollBy({ left: direction * (rtl ? -1 : 1) * track.clientWidth * 0.9, behavior: reduceMotion ? 'auto' : 'smooth' });
        };

        prev?.addEventListener('click', () => step(-1));
        next?.addEventListener('click', () => step(1));
        track.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
    });

    /* Cart counter bump when the runtime updates it */
    document.querySelectorAll('[data-nt-cart-count]').forEach((counter) => {
        new MutationObserver(() => {
            counter.classList.remove('is-bumped');
            void counter.offsetWidth;
            counter.classList.add('is-bumped');
        }).observe(counter, { childList: true, characterData: true, subtree: true });
    });

    /* Scroll reveal */
    if (!reduceMotion && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });

        document.querySelectorAll('.section, .product, .cart, .account, .empty-state').forEach((section) => {
            if (section.getBoundingClientRect().top > window.innerHeight) {
                section.classList.add('reveal');
                observer.observe(section);
            }
        });
    }
})();
