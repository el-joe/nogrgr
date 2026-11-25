import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';
import { readdirSync } from 'node:fs';
import motionBudget from './resources/js/storefront/core/motion/budget.vite.js';

/**
 * One CSS + one JS entry per surface. Every custom CSS/JS file must be reachable
 * from an entry (no CDN, no inline scripts/styles).
 */
const surfaces = ['admin', 'vendor', 'affiliate', 'central', 'storefront-ecommet', 'storefront-elora', 'storefront-souqify'];

/** Demo imagery (generated locally, docs/plan/comfyui.md) for the storefront motion gallery. */
const demoImages = readdirSync('resources/images/demo')
    .filter((file) => file.endsWith('.webp'))
    .map((file) => `resources/images/demo/${file}`);

export default defineConfig({
    plugins: [
        laravel({
            input: [...surfaces.flatMap((surface) => [`resources/css/${surface}.css`, `resources/js/${surface}.js`]), ...demoImages],
            refresh: ['resources/views/**', 'app/Modules/**/resources/views/**', 'app/Modules/**/routes/**', 'lang/**'],
        }),
        tailwindcss(),
        motionBudget(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            '@css': fileURLToPath(new URL('./resources/css', import.meta.url)),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
