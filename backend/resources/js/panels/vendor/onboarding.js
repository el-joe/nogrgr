/**
 * Vendor onboarding (VEN-007): product tour stepper (Back / Next buttons, arrow keys,
 * swipe by touch or mouse drag on the tour card) and the logo file preview.
 */
const SWIPE_THRESHOLD = 48;

function initTour(page) {
    const card = page.querySelector('[data-tour-card]');

    if (!card) {
        return;
    }

    const cardSteps = [...card.querySelectorAll('[data-tour-card-step]')];
    const listSteps = [...page.querySelectorAll('[data-tour-step]')];
    const prev = card.querySelector('[data-tour-prev]');
    const next = card.querySelector('[data-tour-next]');
    const rtl = document.documentElement.dir === 'rtl';
    let index = 0;

    const show = (target) => {
        index = Math.min(Math.max(target, 0), cardSteps.length - 1);
        cardSteps.forEach((step, i) => {
            step.hidden = i !== index;
            step.classList.toggle('vnd-tour-enter', i === index);
        });
        listSteps.forEach((step, i) => step.toggleAttribute('data-current', i === index));
        listSteps[index]?.scrollIntoView({ block: 'nearest', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        prev?.toggleAttribute('disabled', index === 0);
        next?.toggleAttribute('disabled', index === cardSteps.length - 1);
    };

    prev?.addEventListener('click', () => show(index - 1));
    next?.addEventListener('click', () => show(index + 1));
    listSteps.forEach((step, i) => step.addEventListener('click', (event) => {
        if (!event.target.closest('a')) {
            show(i);
        }
    }));

    card.tabIndex = 0;
    card.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
            const forward = (event.key === 'ArrowRight') !== rtl;
            show(index + (forward ? 1 : -1));
            event.preventDefault();
        }
    });

    // Swipe: touch and mouse drag (pointer events), mirrored in RTL.
    let startX = null;
    card.addEventListener('pointerdown', (event) => {
        if (event.target.closest('button, a')) {
            return;
        }
        startX = event.clientX;
        card.setPointerCapture(event.pointerId);
    });
    card.addEventListener('pointerup', (event) => {
        if (startX === null) {
            return;
        }
        const delta = event.clientX - startX;
        startX = null;

        if (Math.abs(delta) >= SWIPE_THRESHOLD) {
            const forward = (delta < 0) !== rtl;
            show(index + (forward ? 1 : -1));
        }
    });
    card.addEventListener('pointercancel', () => {
        startX = null;
    });

    show(0);
}

function initLogoPreview(page) {
    const input = page.querySelector('[data-logo-input]');
    const preview = page.querySelector('[data-logo-preview]');

    if (!input || !preview) {
        return;
    }

    let url = null;
    input.addEventListener('change', () => {
        const file = input.files?.[0];

        if (!file || !file.type.startsWith('image/')) {
            return;
        }

        if (url) {
            URL.revokeObjectURL(url);
        }

        url = URL.createObjectURL(file);
        const image = document.createElement('img');
        image.src = url;
        image.alt = '';
        image.className = 'size-full object-contain bg-media';
        preview.replaceChildren(image);
    });
}

export default function initOnboarding(page) {
    initTour(page);
    initLogoPreview(page);
}
