/**
 * Store tracking pixels (Vendor › Settings › Tracking pixels, VEN-083, decision #91).
 *
 * The only place allowed to load third-party marketing scripts (allow-listed, line by line,
 * in tests/Unit/Architecture/NoThirdPartyScriptsTest.php). Reads the #sf-tracking JSON
 * island ({pixels: {meta|tiktok|snap|ga4: id}, cookie}), asks for consent with the
 * [data-tracking-consent] notice and loads the enabled pixels only after "Accept"
 * (answer kept 180 days in a first-party cookie). Events: page view, product view,
 * add to cart (sf:cart-updated), checkout and purchase (data-sf-page).
 */
const PIXEL_SOURCES = {
    meta: 'https://connect.facebook.net/en_US/fbevents.js',
    tiktok: 'https://analytics.tiktok.com/i18n/pixel/events.js',
    snap: 'https://sc-static.net/scevent.min.js',
    ga4: 'https://www.googletagmanager.com/gtag/js?id=',
};

const EVENTS = {
    view: { meta: 'ViewContent', tiktok: 'ViewContent', snap: 'VIEW_CONTENT', ga4: 'view_item' },
    cart: { meta: 'AddToCart', tiktok: 'AddToCart', snap: 'ADD_CART', ga4: 'add_to_cart' },
    checkout: { meta: 'InitiateCheckout', tiktok: 'InitiateCheckout', snap: 'START_CHECKOUT', ga4: 'begin_checkout' },
    purchase: { meta: 'Purchase', tiktok: 'CompletePayment', snap: 'PURCHASE', ga4: 'purchase' },
};

const CONSENT_DAYS = 180;

function readConfig() {
    try {
        return JSON.parse(document.getElementById('sf-tracking')?.textContent ?? 'null');
    } catch {
        return null;
    }
}

function readConsent(name) {
    const match = document.cookie.split('; ').find((part) => part.startsWith(`${name}=`));

    return match ? decodeURIComponent(match.split('=')[1]) : null;
}

function writeConsent(name, value) {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${name}=${value}; Max-Age=${CONSENT_DAYS * 86400}; Path=/; SameSite=Lax${secure}`;
}

function inject(url) {
    const script = document.createElement('script');
    script.async = true;
    script.src = url;
    document.head.appendChild(script);
}

const loaders = {
    meta(id) {
        if (window.fbq) {
            return;
        }

        const fbq = function fbq(...args) {
            if (fbq.callMethod) {
                fbq.callMethod(...args);
            } else {
                fbq.queue.push(args);
            }
        };
        fbq.push = fbq;
        fbq.loaded = true;
        fbq.version = '2.0';
        fbq.queue = [];
        window.fbq = fbq;
        window._fbq = fbq;
        inject(PIXEL_SOURCES.meta);
        fbq('init', id);
        fbq('track', 'PageView');
    },
    tiktok(id) {
        if (window.ttq) {
            return;
        }

        const ttq = [];
        ttq.methods = ['page', 'track', 'identify', 'instances', 'debug', 'on', 'off', 'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie'];
        ttq.setAndDefer = (target, method) => {
            target[method] = (...args) => target.push([method, ...args]);
        };
        ttq.methods.forEach((method) => ttq.setAndDefer(ttq, method));
        ttq.instance = (instanceId) => {
            const instance = ttq._i[instanceId] || [];
            ttq.methods.forEach((method) => ttq.setAndDefer(instance, method));

            return instance;
        };
        ttq.load = (pixelId, options) => {
            ttq._i = ttq._i || {};
            ttq._i[pixelId] = [];
            ttq._i[pixelId]._u = PIXEL_SOURCES.tiktok;
            ttq._t = ttq._t || {};
            ttq._t[pixelId] = Date.now();
            ttq._o = ttq._o || {};
            ttq._o[pixelId] = options || {};
            inject(`${PIXEL_SOURCES.tiktok}?sdkid=${encodeURIComponent(pixelId)}&lib=ttq`);
        };
        window.TiktokAnalyticsObject = 'ttq';
        window.ttq = ttq;
        ttq.load(id);
        ttq.page();
    },
    snap(id) {
        if (window.snaptr) {
            return;
        }

        const snaptr = function snaptr(...args) {
            if (snaptr.handleRequest) {
                snaptr.handleRequest(...args);
            } else {
                snaptr.queue.push(args);
            }
        };
        snaptr.queue = [];
        window.snaptr = snaptr;
        inject(PIXEL_SOURCES.snap);
        snaptr('init', id, {});
        snaptr('track', 'PAGE_VIEW');
    },
    ga4(id) {
        if (window.gtag) {
            return;
        }

        window.dataLayer = window.dataLayer || [];
        window.gtag = function gtag() {
            // gtag.js reads the Arguments object, not an array.
            // eslint-disable-next-line prefer-rest-params
            window.dataLayer.push(arguments);
        };
        inject(`${PIXEL_SOURCES.ga4}${encodeURIComponent(id)}`);
        window.gtag('js', new Date());
        window.gtag('config', id);
    },
};

function track(pixels, event) {
    const names = EVENTS[event];

    if (pixels.meta && window.fbq) {
        window.fbq('track', names.meta);
    }

    if (pixels.tiktok && window.ttq) {
        window.ttq.track(names.tiktok);
    }

    if (pixels.snap && window.snaptr) {
        window.snaptr('track', names.snap);
    }

    if (pixels.ga4 && window.gtag) {
        window.gtag('event', names.ga4);
    }
}

function start(pixels) {
    Object.entries(pixels).forEach(([pixel, id]) => loaders[pixel]?.(id));

    const page = document.querySelector('[data-sf-page]')?.dataset.sfPage;
    const pageEvent = { product: 'view', checkout: 'checkout', 'order-success': 'purchase' }[page];

    if (pageEvent) {
        track(pixels, pageEvent);
    }

    document.addEventListener('sf:cart-updated', () => track(pixels, 'cart'));
}

function askConsent(notice, onAnswer) {
    notice.hidden = false;
    requestAnimationFrame(() => notice.setAttribute('data-open', ''));

    notice.addEventListener('click', (event) => {
        const button = event.target.closest('[data-consent]');

        if (!button) {
            return;
        }

        notice.removeAttribute('data-open');
        window.setTimeout(() => {
            notice.hidden = true;
        }, 300);
        onAnswer(button.dataset.consent);
    });
}

export function initTracking() {
    const config = readConfig();
    const pixels = config?.pixels ?? {};

    if (!config?.cookie || Object.keys(pixels).length === 0) {
        return;
    }

    const answer = readConsent(config.cookie);

    if (answer === 'granted') {
        start(pixels);

        return;
    }

    const notice = document.querySelector('[data-tracking-consent]');

    if (answer === 'denied' || !notice) {
        return;
    }

    askConsent(notice, (value) => {
        writeConsent(config.cookie, value === 'granted' ? 'granted' : 'denied');

        if (value === 'granted') {
            start(pixels);
        }
    });
}
