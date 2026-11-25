/**
 * Chart cards (x-ui::chart-card, Figma 29:10505) and KPI count-up.
 *
 * Chart.js is imported only when a chart scrolls into view. Config comes from a JSON
 * island next to the canvas:
 *   <canvas data-chart="revenue"></canvas>
 *   <script type="application/json" data-chart-config="revenue">
 *     {"type":"line","ranges":{"30d":{"labels":[…],"series":[{"label":"…","data":[…],"tone":"brand"}]}},"range":"30d"}
 *   </script>
 * Series tones: brand (brand/500), ink (brand/900 light, ink in dark), success, warning,
 * danger, info, muted. Gridlines use border/default; axis labels 12px muted; time axes
 * stay left-to-right in RTL. Segmented [data-chart-range] buttons switch ranges with no
 * animation. Charts redraw on theme change. Reduced motion: no draw animation.
 *
 * KPI count-up: <span data-count-up="18402" data-count-format="number|percent|currency:USD">.
 */
import { numberLocale } from '../../core/i18n';

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

function tone(name) {
    return (
        {
            brand: css('--nog-primary'),
            ink: css('--nog-ink'),
            success: css('--nog-success'),
            warning: css('--nog-warning'),
            danger: css('--nog-danger'),
            info: css('--nog-info'),
            muted: css('--nog-line-strong'),
        }[name] ?? css('--nog-primary')
    );
}

function buildConfig(spec, rangeKey, animate) {
    const range = spec.ranges?.[rangeKey] ?? spec.ranges?.[spec.range] ?? Object.values(spec.ranges ?? {})[0] ?? { labels: [], series: [] };
    const grid = css('--nog-line');
    const muted = css('--nog-ink-muted');
    const font = { family: getComputedStyle(document.body).fontFamily, size: 12 };
    const donut = spec.type === 'doughnut';
    const allZero = range.series.every((series) => series.data.every((value) => Number(value) === 0));

    const datasets = range.series.map((series, index) => {
        const color = donut ? (series.tones ?? []).map(tone) : tone(series.tone ?? (index === 0 ? 'brand' : 'ink'));

        return {
            label: series.label,
            data: series.data,
            borderColor: donut ? css('--nog-surface') : color,
            backgroundColor: donut ? color : spec.type === 'bar' ? color : `${color}`,
            borderWidth: donut ? 2 : 2,
            pointRadius: 0,
            pointHoverRadius: 4,
            tension: 0.25,
            borderRadius: spec.type === 'bar' ? 6 : 0,
            maxBarThickness: 32,
        };
    });

    return {
        type: spec.type,
        data: { labels: range.labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: animate && !reduceMotion() && range.labels.length <= 1000 ? { duration: 400, easing: 'easeOutQuad' } : false,
            interaction: { mode: 'index', intersect: false },
            cutout: donut ? '68%' : undefined,
            plugins: {
                legend: { display: false },
                tooltip: { rtl: document.documentElement.dir === 'rtl', titleFont: font, bodyFont: font },
            },
            scales: donut
                ? {}
                : {
                      x: { reverse: false, grid: { display: false }, border: { display: false }, ticks: { color: muted, font, maxRotation: 0, autoSkipPadding: 16 } },
                      /* An all-zero series keeps a readable 0–4 grid instead of Chart.js' 0–1. */
                      y: { beginAtZero: true, suggestedMax: allZero ? 4 : undefined, grid: { color: grid }, border: { display: false }, ticks: { color: muted, font, precision: 0, maxTicksLimit: 5 } },
                  },
        },
    };
}

async function mountChart(canvas) {
    const key = canvas.dataset.chart;
    const island = canvas.closest('[data-chart-card]')?.querySelector(`script[data-chart-config="${CSS.escape(key)}"]`);

    if (!island) {
        return;
    }

    const spec = JSON.parse(island.textContent || '{}');
    const { default: Chart } = await import('chart.js/auto');
    let current = spec.range;
    let chart = new Chart(canvas, buildConfig(spec, current, true));
    const card = canvas.closest('[data-chart-card]');

    card.querySelectorAll('[data-chart-range]').forEach((button) =>
        button.addEventListener('click', () => {
            current = button.dataset.chartRange;
            card.querySelectorAll('[data-chart-range]').forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
            const next = buildConfig(spec, current, false);
            chart.data = next.data;
            chart.update('none');
        }),
    );

    document.addEventListener('theme:change', () => {
        chart.destroy();
        chart = new Chart(canvas, buildConfig(spec, current, false));
    });
}

function countUp(element) {
    const target = Number(element.dataset.countUp);
    const [kind, currency] = (element.dataset.countFormat || 'number').split(':');
    const decimals = Number(element.dataset.countDecimals || (kind === 'currency' ? 2 : kind === 'percent' ? 1 : 0));
    const format = new Intl.NumberFormat(numberLocale(), { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    /* Same shape as the server: "USD 1,284.00", "2.1%", "18,402". */
    const render = (value) => {
        const number = format.format(value);
        element.textContent = kind === 'percent' ? `${number}%` : kind === 'currency' ? `${currency} ${number}` : number;
    };

    /* Zero or reduced motion: keep the server-rendered text as is. */
    if (!Number.isFinite(target) || target === 0 || reduceMotion()) {
        return;
    }

    const started = performance.now();
    const step = (now) => {
        const progress = Math.min(1, (now - started) / 300);
        render(target * (1 - (1 - progress) ** 3));

        if (progress < 1) {
            requestAnimationFrame(step);
        }
    };

    requestAnimationFrame(step);
}

export function initCharts(root = document) {
    const canvases = [...root.querySelectorAll('canvas[data-chart]:not([data-chart-ready])')];
    const counters = [...root.querySelectorAll('[data-count-up]:not([data-count-ready])')];

    if (!canvases.length && !counters.length) {
        return;
    }

    const observer =
        'IntersectionObserver' in window
            ? new IntersectionObserver(
                  (entries) => {
                      entries.forEach((entry) => {
                          if (!entry.isIntersecting) {
                              return;
                          }

                          observer.unobserve(entry.target);
                          entry.target.matches('canvas') ? mountChart(entry.target) : countUp(entry.target);
                      });
                  },
                  { rootMargin: '120px' },
              )
            : null;

    [...canvases, ...counters].forEach((element) => {
        element.dataset[element.matches('canvas') ? 'chartReady' : 'countReady'] = '';

        if (observer) {
            observer.observe(element);
        } else {
            element.matches('canvas') ? mountChart(element) : countUp(element);
        }
    });
}
