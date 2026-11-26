/**
 * AI Advertising Studio (VEN-010c, client frame 12:15119) + ad previews (VEN-009e
 * dashboard widget, Advertising Library). Booted on any vendor page that renders the
 * studio modal [data-ad-studio], an ad preview trigger [data-ad-preview] or the library.
 *  - [data-ad-studio-open] (data-product-id / -name / -image) opens the wizard; without a
 *    product the header shows the Select2 product picker;
 *  - 4 steps (Video type → People and voice → Platform and size → Review), sizes follow
 *    the chosen platform, Review lists every choice;
 *  - Generate → POST, then live progress: Reverb push on tenant.<id>.video-ads (+ poll
 *    fallback) re-reads the ad → result (video or slideshow, caption, download, copy,
 *    share) / failed (try again) / limit reached (429);
 *  - library rows being generated reload the table live; widget country filter.
 */
import { http } from '../../core/http';
import { notify } from '../../core/notify';
import { $ } from '../../core/select2-lib.js';
import { openOverlay } from '../core.js';
import { subscribePrivate } from '../realtime-channel';

const STAGES = ['scripting', 'visuals', 'voice', 'rendering'];
const VIEWS = ['steps', 'progress', 'result', 'failed', 'limit'];

let booted = false;

/* Quiet in-place reload of the Advertising Library table (when on the page). */
function reloadLibrary() {
    document.getElementById('ad-library')?.closest('[data-datatable-card]')?.dispatchEvent(new CustomEvent('datatable:reload', { detail: { keepPage: true, quiet: true } }));
}
let config = null;
let subscribed = false;
const listeners = new Set();

function readConfig() {
    const island = document.querySelector('script[data-ad-studio-config]');

    if (!island) {
        return null;
    }

    try {
        return JSON.parse(island.textContent || '{}');
    } catch {
        return null;
    }
}

function liveSubscribe(live) {
    if (subscribed || !live?.realtime) {
        return;
    }

    subscribed = true;
    subscribePrivate(live.realtime, (data) => listeners.forEach((listener) => listener(data)));
}

const copyText = async (text) => {
    try {
        await navigator.clipboard.writeText(text);

        return true;
    } catch {
        return false;
    }
};

/* Player: MP4 (contain, controls) or animated slideshow of the scenes + voice-over track. */
function renderPlayer(container, ad, texts) {
    container.replaceChildren();
    container.className = `ads-player ${ad.aspect_class || 'aspect-[9/16]'}`;
    window.clearInterval(container.__slideTimer);

    if (ad.video_url) {
        const video = document.createElement('video');
        video.src = ad.video_url;
        video.controls = true;
        video.playsInline = true;
        video.preload = 'metadata';

        if (ad.poster_url) {
            video.poster = ad.poster_url;
        }

        container.append(video);

        return;
    }

    const slides = (ad.frames || []).map((url, index) => {
        const slide = document.createElement('figure');
        slide.className = 'ads-slide';
        const image = document.createElement('img');
        image.src = url;
        image.alt = (texts?.scene || '').replace('__N__', String(index + 1));
        slide.append(image);

        if (ad.captions?.[index]) {
            const caption = document.createElement('figcaption');
            caption.className = 'ads-slide-caption';
            caption.textContent = ad.captions[index];
            slide.append(caption);
        }

        container.append(slide);

        return slide;
    });

    if (slides.length) {
        let current = 0;
        slides[0].dataset.active = '';
        const step = Math.max(2000, ((ad.duration || 15) * 1000) / slides.length);
        container.__slideTimer = window.setInterval(() => {
            if (!container.isConnected) {
                window.clearInterval(container.__slideTimer);

                return;
            }

            delete slides[current].dataset.active;
            current = (current + 1) % slides.length;
            slides[current].dataset.active = '';
        }, step);
    }

    if (ad.audio_url) {
        const audio = document.createElement('audio');
        audio.src = ad.audio_url;
        audio.controls = true;
        audio.className = 'absolute inset-x-3 bottom-3 z-[1] w-[calc(100%-1.5rem)]';
        container.append(audio);
    }
}

function fillDetails(root, ad, texts) {
    renderPlayer(root.querySelector('[data-ad-player]'), ad, texts);
    root.querySelector('[data-ad-result-headline]').textContent = ad.headline || ad.product || '';
    root.querySelector('[data-ad-result-hook]').textContent = ad.hook || '';
    root.querySelector('[data-ad-result-text]').textContent = ad.text || '';
    root.querySelector('[data-ad-slideshow-note]').hidden = ad.output !== 'slideshow';

    const download = root.querySelector('[data-ad-download]');
    download.href = ad.urls?.download || '#';
    download.hidden = !ad.urls?.download;

    const copy = root.querySelector('[data-ad-copy]');
    copy.onclick = async () => {
        if (await copyText(ad.text || '')) {
            notify.success(texts?.copied || '');
        }
    };
}

/* ---------- Preview modal ---------- */

async function openPreview(id) {
    const modal = document.getElementById('ad-preview-modal');

    if (!modal) {
        return;
    }

    const body = modal.querySelector('[data-ad-preview-body]');
    const loading = modal.querySelector('[data-ad-preview-loading]');
    const error = modal.querySelector('[data-ad-preview-error]');
    const url = (body.dataset.showUrl || '').replace('__ID__', String(id));
    const texts = { copied: body.dataset.copied, scene: body.dataset.scene };

    body.hidden = true;
    error.hidden = true;
    loading.hidden = false;
    openOverlay(modal);

    try {
        const { data } = await http.get(url, undefined, { silent: true });
        const ad = data.ad;
        fillDetails(modal, ad, texts);
        modal.querySelector('[data-ad-preview-meta]').textContent = [ad.product, ad.platform, ad.aspect, ad.duration_label].filter(Boolean).join(' · ');
        body.hidden = false;
    } catch {
        error.hidden = false;
    } finally {
        loading.hidden = true;
    }
}

/* ---------- Wizard ---------- */

function initWizard(modal) {
    const form = modal.querySelector('[data-ad-studio-form]');
    const texts = config.texts || {};
    const steps = [...modal.querySelectorAll('[data-ad-step]')];
    const indicators = [...modal.querySelectorAll('[data-ad-step-indicator]')];
    const buttons = {
        back: modal.querySelector('[data-ad-back]'),
        next: modal.querySelector('[data-ad-next]'),
        generate: modal.querySelector('[data-ad-generate]'),
        retry: modal.querySelector('[data-ad-retry]'),
        another: modal.querySelector('[data-ad-another]'),
        library: modal.querySelector('[data-ad-library]'),
    };
    const error = modal.querySelector('[data-ad-error]');
    const picker = modal.querySelector('[data-ad-product-picker]');
    const productSelect = modal.querySelector('[data-ad-product-select]');
    const state = { step: 1, product: null, ad: null, view: 'steps', timer: null };

    const views = {
        steps: modal.querySelector('[data-ad-steps]'),
        progress: modal.querySelector('[data-ad-progress]'),
        result: modal.querySelector('[data-ad-result]'),
        failed: modal.querySelector('[data-ad-failed]'),
        limit: modal.querySelector('[data-ad-limit]'),
    };

    const setError = (message) => {
        error.textContent = message || '';
        error.hidden = !message;
    };

    const value = (name) => form.querySelector(`[name="${name}"]:checked`)?.value ?? form.querySelector(`[name="${name}"]`)?.value ?? '';

    const label = (name) => {
        const checked = form.querySelector(`[name="${name}"]:checked`);

        if (checked) {
            return checked.closest('label')?.textContent.trim() ?? '';
        }

        const select = form.querySelector(`select[name="${name}"]`);

        return select?.selectedOptions[0]?.textContent.trim() ?? '';
    };

    const setProduct = (product) => {
        state.product = product && product.id ? product : null;
        const heading = modal.querySelector('[data-ad-heading]');
        const image = modal.querySelector('[data-ad-product-image]');
        const placeholder = modal.querySelector('[data-ad-product-placeholder]');

        heading.textContent = state.product ? texts.heading.replace('__PRODUCT__', state.product.name) : texts.headingEmpty;
        image.hidden = !state.product?.image;
        placeholder.hidden = Boolean(state.product?.image);

        if (state.product?.image) {
            image.src = state.product.image;
            image.alt = state.product.name;
        }

        picker.hidden = Boolean(state.product);
        modal.querySelector('[data-ad-change-product]').hidden = !state.product;
    };

    const syncButtons = () => {
        const inSteps = state.view === 'steps';
        buttons.back.hidden = !inSteps || state.step === 1;
        buttons.next.hidden = !inSteps || state.step === 4;
        buttons.generate.hidden = !inSteps || state.step !== 4;
        buttons.retry.hidden = state.view !== 'failed';
        buttons.another.hidden = !['result', 'failed', 'limit'].includes(state.view);
        buttons.library.hidden = !['progress', 'result'].includes(state.view);
    };

    const showView = (name) => {
        state.view = name;
        VIEWS.forEach((key) => {
            if (views[key]) {
                views[key].hidden = key !== name;
            }
        });
        modal.querySelector('[data-ad-stepper]').hidden = name !== 'steps';
        syncButtons();
    };

    const summary = () => {
        modal.querySelectorAll('[data-ad-summary-value]').forEach((node) => {
            const field = node.dataset.adSummaryValue;
            node.textContent = field === 'product' ? state.product?.name || '—' : label(field) || texts.marketAll || '—';
        });
    };

    const goTo = (step) => {
        state.step = step;
        steps.forEach((panel) => {
            panel.hidden = Number(panel.dataset.adStep) !== step;
        });
        indicators.forEach((indicator) => {
            const number = Number(indicator.dataset.adStepIndicator);
            indicator.dataset.state = number === step ? 'current' : number < step ? 'done' : 'idle';
            indicator.toggleAttribute('aria-current', number === step);
            indicator.querySelector('[data-ad-step-label]')?.classList.toggle('max-sm:sr-only', number !== step);
        });

        if (step === 4) {
            summary();
        }

        setError('');
        syncButtons();
        form.scrollTop = 0;
    };

    const syncAspects = () => {
        const platform = (config.platforms || []).find((item) => item.value === value('platform'));
        const allowed = platform?.aspects ?? [];
        const inputs = [...form.querySelectorAll('input[name="aspect"]')];

        inputs.forEach((input) => {
            input.closest('label').hidden = !allowed.includes(input.value);
        });

        if (!allowed.includes(value('aspect'))) {
            const first = inputs.find((input) => allowed.includes(input.value));

            if (first) {
                first.checked = true;
            }
        }
    };

    const stopTracking = () => {
        window.clearTimeout(state.timer);
        state.timer = null;
    };

    const paintProgress = (ad) => {
        const percent = Math.max(0, Math.min(100, Number(ad.progress) || 0));
        modal.querySelector('[data-ad-progress-bar]').style.width = `${percent}%`;
        modal.querySelector('[data-ad-progressbar]').setAttribute('aria-valuenow', String(percent));
        modal.querySelector('[data-ad-progress-value]').textContent = texts.percent.replace('__P__', String(percent));
        const index = STAGES.indexOf(ad.status);

        modal.querySelectorAll('[data-ad-stage]').forEach((stage) => {
            const position = STAGES.indexOf(stage.dataset.adStage);
            stage.dataset.state = index === -1 ? 'idle' : position < index ? 'done' : position === index ? 'current' : 'idle';
        });
    };

    const applyAd = (ad) => {
        state.ad = ad;

        if (ad.active) {
            showView('progress');
            paintProgress(ad);

            return;
        }

        stopTracking();

        if (ad.status === 'ready') {
            fillDetails(views.result, ad, texts);
            bindShare(ad);
            showView('result');
            reloadLibrary();
        } else {
            modal.querySelector('[data-ad-failed-text]').textContent = ad.error || texts.generic;
            showView('failed');
        }
    };

    const refresh = async () => {
        if (!state.ad) {
            return;
        }

        try {
            const { data } = await http.get(state.ad.urls.show, undefined, { silent: true });
            applyAd(data.ad);
        } catch {
            // Keep the last state; the next push or poll tries again.
        }
    };

    const track = () => {
        stopTracking();

        const tick = async () => {
            if (!state.ad?.active) {
                return;
            }

            if (!document.hidden) {
                await refresh();
            }

            if (state.ad?.active) {
                state.timer = window.setTimeout(tick, config.live?.pollMs || 4000);
            }
        };

        state.timer = window.setTimeout(tick, config.live?.pollMs || 4000);
    };

    listeners.add((payload) => {
        if (state.ad && Number(payload?.id) === Number(state.ad.id)) {
            refresh();
        }
    });

    const bindShare = (ad) => {
        const url = ad.video_url || ad.poster_url || '';
        const message = [ad.headline, ad.text].filter(Boolean).join('\n\n');
        const links = {
            whatsapp: `https://wa.me/?text=${encodeURIComponent(`${message}\n${url}`)}`,
            x: `https://twitter.com/intent/tweet?text=${encodeURIComponent(ad.headline || '')}&url=${encodeURIComponent(url)}`,
            facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`,
        };

        modal.querySelectorAll('[data-ad-share-link]').forEach((link) => {
            link.href = links[link.dataset.adShareLink] || '#';
        });

        const native = modal.querySelector('[data-ad-share-native]');
        native.hidden = typeof navigator.share !== 'function';
        native.onclick = () => navigator.share({ title: ad.headline || ad.product, text: ad.text || '', url }).catch(() => {});

        modal.querySelector('[data-ad-copy-link]').onclick = async () => {
            if (await copyText(url)) {
                notify.success(texts.linkCopied);
            }
        };
    };

    const failRequest = (requestError) => {
        if (requestError?.status === 429 && requestError.payload?.data?.quota) {
            modal.querySelector('[data-ad-limit-text]').textContent = requestError.payload.message || '';
            modal.querySelector('[data-ad-quota]').textContent = requestError.payload.data.quota.label || '';
            showView('limit');

            return;
        }

        const first = Object.values(requestError?.errors || {})[0];
        setError((Array.isArray(first) ? first[0] : first) || requestError?.payload?.message || texts.generic);
    };

    const busy = (button, on) => {
        button.disabled = on;
        button.setAttribute('aria-busy', on ? 'true' : 'false');
    };

    const reset = (product) => {
        stopTracking();
        state.ad = null;
        form.querySelectorAll('.ads-tiles').forEach((group) => {
            const first = group.querySelector('label:not([hidden]) input');
            const defaultInput = group.querySelector('input[value="female"]') || first;

            if (defaultInput) {
                defaultInput.checked = true;
            }
        });
        const notes = form.querySelector('[name="notes"]');

        if (notes) {
            notes.value = '';
        }

        syncAspects();
        setProduct(product);
        showView('steps');
        goTo(1);
    };

    form.addEventListener('change', (event) => {
        if (event.target.name === 'platform') {
            syncAspects();
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!state.product) {
            goTo(1);
            setError(texts.productRequired);

            return;
        }

        busy(buttons.generate, true);
        setError('');

        try {
            const { data } = await http.post(config.urls.store, {
                product_id: state.product.id,
                type: value('type'),
                presenter: value('presenter'),
                voice: value('voice'),
                tone: value('tone'),
                locale: value('locale'),
                platform: value('platform'),
                aspect: value('aspect'),
                duration: Number(value('duration')),
                country_id: value('country_id') || null,
                notes: form.querySelector('[name="notes"]')?.value || null,
            }, { silent: true });

            if (data?.quota?.label) {
                modal.querySelector('[data-ad-quota]').textContent = data.quota.label;
            }

            applyAd(data.ad);
            track();
            reloadLibrary();
        } catch (requestError) {
            failRequest(requestError);
        } finally {
            busy(buttons.generate, false);
        }
    });

    buttons.next.addEventListener('click', () => {
        if (state.step === 1 && !state.product) {
            setError(texts.productRequired);
            picker.querySelector('select')?.focus();

            return;
        }

        goTo(Math.min(4, state.step + 1));
    });

    buttons.back.addEventListener('click', () => goTo(Math.max(1, state.step - 1)));
    buttons.another.addEventListener('click', () => reset(state.product));

    buttons.retry.addEventListener('click', async () => {
        if (!state.ad?.urls?.retry) {
            reset(state.product);

            return;
        }

        busy(buttons.retry, true);

        try {
            const { data } = await http.post(state.ad.urls.retry, {}, { silent: true });
            applyAd(data.ad);
            track();
        } catch (requestError) {
            failRequest(requestError);
        } finally {
            busy(buttons.retry, false);
        }
    });

    modal.querySelector('[data-ad-change-product]').addEventListener('click', () => {
        setProduct(null);
        picker.querySelector('select')?.focus();
    });

    if (productSelect) {
        $(productSelect).on('select2:select', (event) => {
            const item = event.params?.data;

            if (item?.id) {
                setProduct({ id: Number(item.id), name: item.text, image: item.image || '' });
                setError('');
            }
        });
    }

    liveSubscribe(config.live);

    return {
        open(product) {
            reset(product);
            openOverlay(modal);
        },
        reset,
    };
}

/* ---------- Dashboard widget country filter ---------- */

function initWidgetFilter(root) {
    root.querySelectorAll('[data-ads-widget]').forEach((widget) => {
        const select = widget.querySelector('select[data-ads-country]');

        if (!select) {
            return;
        }

        const apply = () => {
            let visible = 0;
            widget.querySelectorAll('[data-ads-card]').forEach((card) => {
                const show = !select.value || card.dataset.country === select.value || card.dataset.country === '';
                card.hidden = !show;
                visible += show ? 1 : 0;
            });
            widget.querySelector('[data-ads-no-match]').hidden = visible > 0;
        };

        select.addEventListener('change', apply);
    });
}

/* ---------- Advertising Library: live rows ---------- */

function initLibrary(root) {
    const library = root.querySelector('[data-ad-library]');
    const table = document.getElementById('ad-library');

    if (!library || !table) {
        return;
    }

    let timer = null;
    const hasActive = () => table.querySelector('[data-ad-active]') !== null;
    const schedule = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => {
            if (hasActive() && !document.hidden) {
                reloadLibrary();
            }

            schedule();
        }, (config?.live?.pollMs || 4000) * 2);
    };

    listeners.add(() => reloadLibrary());
    schedule();
}

export function initAdStudio(root = document) {
    if (booted) {
        return;
    }

    booted = true;
    config = readConfig();
    const modal = document.getElementById('ad-studio-modal');
    const wizard = modal && config ? initWizard(modal) : null;

    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-ad-studio-open]');

        if (opener && wizard) {
            event.preventDefault();
            const id = Number(opener.dataset.productId || 0);
            wizard.open(id ? { id, name: opener.dataset.productName || '', image: opener.dataset.productImage || '' } : null);

            return;
        }

        const preview = event.target.closest('[data-ad-preview]');

        if (preview) {
            event.preventDefault();
            openPreview(preview.dataset.adPreview);
        }
    });

    initWidgetFilter(root);
    initLibrary(root);

    const library = root.querySelector('[data-ad-library]');

    if (library?.dataset.openAd) {
        openPreview(library.dataset.openAd);
    } else if (library?.dataset.startProduct && wizard) {
        wizard.open({ id: Number(library.dataset.startProduct), name: library.dataset.startName || '', image: library.dataset.startImage || '' });
    }
}

export default function initVendorAdStudio(root) {
    initAdStudio(root.ownerDocument ?? document);
}
