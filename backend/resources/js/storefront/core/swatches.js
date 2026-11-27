/**
 * Colour swatches: [data-swatch="#hex"] gets its colour as --swatch (styles live in sf.css, no inline style
 * attributes in markup); checkbox swatches that merge several option values ([data-swatch-values]) add /
 * remove one hidden input per extra value so the GET filter form sends all of them.
 */
export function initSwatches(root = document) {
    root.querySelectorAll('[data-swatch]:not([data-swatch-ready])').forEach((swatch) => {
        swatch.dataset.swatchReady = '';
        swatch.style.setProperty('--swatch', swatch.dataset.swatch);
    });

    root.querySelectorAll('input[data-swatch-values]:not([data-swatch-ready])').forEach((input) => {
        input.dataset.swatchReady = '';
        const values = JSON.parse(input.dataset.swatchValues || '[]').slice(1);

        input.addEventListener('change', () => {
            input.closest('label')?.parentElement?.querySelectorAll(`input[data-swatch-extra][data-for="${CSS.escape(input.value)}"]`).forEach((extra) => extra.remove());

            if (input.checked) {
                values.forEach((value) => {
                    const extra = document.createElement('input');
                    extra.type = 'hidden';
                    extra.name = input.dataset.swatchName;
                    extra.value = value;
                    extra.dataset.swatchExtra = '';
                    extra.dataset.for = input.value;
                    input.closest('label').after(extra);
                });
            } else {
                values.forEach((value) => input.closest('.sf-swatch-grid')?.querySelectorAll('input[data-swatch-extra]').forEach((extra) => extra.value === value && extra.remove()));
            }
        });
    });
}
