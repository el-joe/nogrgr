/**
 * Floating product-video popup shared by every storefront family (#114), same logic as
 * the old storefront:
 *  - gallery in view             → popup hidden, the video plays in the gallery slider;
 *  - gallery scrolled out of view → popup shown in the bottom corner (inline-end, so the
 *    left in RTL, above the phones' bottom nav), continuing from the gallery video's time;
 *  - gallery back in view, or the popup clicked (Enter / Space too)
 *                                → popup hidden, the gallery jumps to the video slide and
 *                                  keeps playing from the popup's time (a click also
 *                                  scrolls to the gallery);
 *  - close button                → popup stays hidden for the rest of the page view.
 * Families only supply an adapter for their gallery (`adapters`); the markup is
 *   <div data-gallery data-video-pip='{"family":"ecommet","src":…,"poster":…,"close":…,"open":…}'>
 * with the video slide marked [data-gallery-video-slide] holding a [data-gallery-video].
 * Styles: .sf-video-pip* in resources/css/storefront/sf.css (no inline styles).
 */
const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

let current = null;

/* Seek once the metadata is known (setting currentTime earlier is ignored). */
function seek(video, time) {
    if (!(time > 0)) {
        return;
    }

    const apply = () => {
        try {
            video.currentTime = time;
        } catch {
            /* ignore */
        }
    };

    if (video.readyState >= 1) {
        apply();
    } else {
        video.addEventListener('loadedmetadata', apply, { once: true });
    }
}

/** Swiper gallery (core/motion/gallery.js) — used by Ecommet, Elora and Souqify. */
function swiperAdapter(anchor) {
    const slides = () => [...anchor.querySelectorAll('[data-gallery-main] .swiper-slide')];
    const videoSlide = () => slides().findIndex((slide) => slide.hasAttribute('data-gallery-video-slide'));

    return {
        goToVideo() {
            const index = videoSlide();

            if (index >= 0 && anchor.swiper) {
                anchor.swiper.slideTo(index, reducedMotion() ? 0 : undefined);
            }
        },
        getGalleryVideo: () => anchor.querySelector('[data-gallery-video-slide] [data-gallery-video]'),
        pauseGalleryVideo() {
            anchor.querySelectorAll('[data-gallery-video]').forEach((video) => video.pause());
        },
    };
}

export const adapters = {
    ecommet: swiperAdapter,
    elora: swiperAdapter,
    souqify: swiperAdapter,
};

export function mountVideoPip(options) {
    current?.destroy();

    const { anchor, video, family = 'ecommet', goToVideo, getGalleryVideo, pauseGalleryVideo = () => {}, closeLabel = 'Close video', openLabel = '' } = options;

    if (!anchor || !video?.src || !('IntersectionObserver' in window)) {
        return null;
    }

    let pip = null;
    let pipVideo = null;
    let dismissed = false;
    let destroyed = false;

    function build() {
        const wrap = document.createElement('div');
        wrap.className = `sf-video-pip sf-video-pip--${family}`;
        wrap.dataset.storefrontVideoPip = '';
        wrap.setAttribute('role', 'button');
        wrap.setAttribute('tabindex', '0');

        if (openLabel) {
            wrap.setAttribute('aria-label', openLabel);
        }

        const element = document.createElement('video');
        element.src = video.src;

        if (video.poster) {
            element.poster = video.poster;
        }

        element.muted = true;
        element.loop = true;
        element.autoplay = true;
        element.playsInline = true;
        element.setAttribute('playsinline', '');
        element.setAttribute('aria-hidden', 'true');

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'sf-video-pip-close';
        close.setAttribute('aria-label', closeLabel);
        close.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
        close.addEventListener('click', (event) => {
            event.stopPropagation();
            dismissed = true;
            hide();
        });
        close.addEventListener('keydown', (event) => event.stopPropagation());

        wrap.addEventListener('click', () => restore(true));
        wrap.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                restore(true);
            }
        });

        wrap.append(element, close);

        return { wrap, element };
    }

    function show() {
        if (pip || dismissed || destroyed) {
            return;
        }

        const galleryVideo = getGalleryVideo();
        const startAt = galleryVideo ? galleryVideo.currentTime : 0;
        pauseGalleryVideo();

        const built = build();
        pip = built.wrap;
        pipVideo = built.element;
        document.body.appendChild(pip);

        seek(pipVideo, startAt);

        requestAnimationFrame(() => requestAnimationFrame(() => pip?.setAttribute('data-shown', '')));
        pipVideo.play().catch(() => {});
    }

    function hide() {
        if (!pip) {
            return null;
        }

        const time = pipVideo ? pipVideo.currentTime : 0;
        pipVideo?.pause();
        const node = pip;
        pip = null;
        pipVideo = null;
        node.removeAttribute('data-shown');
        window.setTimeout(() => node.remove(), reducedMotion() ? 0 : 300);

        return time;
    }

    /* Hand playback back to the gallery: video slide, continue from the popup's time. */
    function restore(scrollToGallery) {
        const time = hide();

        if (time === null) {
            return;
        }

        goToVideo();
        requestAnimationFrame(() => {
            const galleryVideo = getGalleryVideo();

            if (galleryVideo) {
                seek(galleryVideo, time);
                /* Without a user gesture (scrolled back) sound is blocked: keep playing muted. */
                galleryVideo.play().catch(() => {
                    galleryVideo.muted = true;
                    galleryVideo.play().catch(() => {});
                });
            }
        });

        if (scrollToGallery) {
            anchor.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth', block: 'center' });
        }
    }

    const observer = new IntersectionObserver((entries) => {
        const entry = entries[entries.length - 1];

        if (entry.isIntersecting) {
            restore(false);
        } else {
            show();
        }
    });
    observer.observe(anchor);

    const instance = {
        destroy() {
            destroyed = true;
            observer.disconnect();
            pip?.remove();
            pip = null;
            pipVideo = null;

            if (current === instance) {
                current = null;
            }
        },
        get visible() {
            return pip !== null;
        },
    };
    current = instance;

    return instance;
}

/**
 * Only the active slide's video may play: a swipe away pauses the others.
 */
function pauseHiddenVideos(swiper) {
    swiper.on('slideChange', () => {
        swiper.slides.forEach((slide, index) => {
            if (index !== swiper.activeIndex) {
                slide.querySelectorAll('video').forEach((video) => video.pause());
            }
        });
    });
}

function whenGalleryReady(anchor, callback) {
    if (anchor.swiper) {
        callback(anchor.swiper);

        return;
    }

    anchor.addEventListener('gallery:ready', (event) => callback(event.detail.swiper), { once: true });
}

/**
 * Binds the gallery videos + popup of a product page ([data-gallery][data-video-pip]).
 */
export function initVideoPip(root = document) {
    const anchor = root.querySelector('[data-gallery][data-video-pip]');

    if (!anchor) {
        return null;
    }

    let config = {};

    try {
        config = JSON.parse(anchor.dataset.videoPip || '{}');
    } catch {
        config = {};
    }

    const adapter = (adapters[config.family] ?? swiperAdapter)(anchor);
    whenGalleryReady(anchor, pauseHiddenVideos);

    return mountVideoPip({
        anchor,
        family: config.family,
        video: { src: config.src, poster: config.poster },
        closeLabel: config.close,
        openLabel: config.open,
        ...adapter,
    });
}
