import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { readdirSync } from 'node:fs';
import { resolve } from 'node:path';

const entriesDir = resolve('resources/js/entries');
const entryJs = readdirSync(entriesDir).filter((file) => file.endsWith('.js')).map((file) => `resources/js/entries/${file}`);
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css', 
                'resources/js/app.js',
                ...entryJs, 
            ],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
