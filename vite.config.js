import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

// Vitest sets VITEST while it runs the tests.
const isTest = Boolean(process.env.VITEST);

export default defineConfig({
    plugins: [
        vue(),
        // The Laravel plugin removes public/hot when its process ends. Left on during tests, it would
        // break the page of a dev server that is running at the same time.
        ...(isTest
            ? []
            : [
                  laravel({
                      input: ['resources/css/app.css', 'resources/js/app.ts'],
                      refresh: true,
                  }),
              ]),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.spec.ts'],
    },
});
