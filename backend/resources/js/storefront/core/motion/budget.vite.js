/**
 * Build-time budget for the storefront motion system: the gzipped size of every
 * module under resources/js/storefront/core/motion/ (Swiper and other node_modules
 * excluded) must stay ≤ 25 KB (docs/plan/07-motion-guidelines.md §5). Each module's
 * rendered code is gzipped on its own and summed, which slightly over-counts — a
 * conservative check. The build fails when the budget is exceeded.
 */
import { gzipSync } from 'node:zlib';

const LIMIT = 25 * 1024;
const MATCH = /resources[\\/]js[\\/]storefront[\\/]core[\\/]motion[\\/](?!budget\.vite)/;

export default function motionBudget() {
    return {
        name: 'nogrgr-motion-budget',
        apply: 'build',
        generateBundle(_, bundle) {
            const seen = new Map();

            for (const output of Object.values(bundle)) {
                if (output.type !== 'chunk') {
                    continue;
                }

                for (const [id, module] of Object.entries(output.modules ?? {})) {
                    if (MATCH.test(id) && !id.includes('node_modules') && !seen.has(id)) {
                        seen.set(id, gzipSync(module.code ?? '').length);
                    }
                }
            }

            const total = [...seen.values()].reduce((sum, size) => sum + size, 0);
            const report = `motion budget: ${(total / 1024).toFixed(1)} KB gz across ${seen.size} modules (limit 25 KB, Swiper excluded)`;

            if (seen.size === 0) {
                this.warn('motion budget: no motion modules found in the bundle');
            } else if (total > LIMIT) {
                this.error(report);
            } else {
                this.info?.(report) ?? console.log(report);
            }
        },
    };
}
