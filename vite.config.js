import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { existsSync } from 'node:fs';

// Optional master-only entry: present only where the module exists, so an install without it still builds.
const optional = ['resources/js/n2n/realtime.js', 'resources/js/n2n/chat.js', 'resources/js/n2n/nvp.js', 'resources/js/n2n/gesture-practice.js', 'resources/js/n2n/shell.js', 'resources/js/n2n/status-camera.js', 'resources/js/n2n/global-calls.js', 'resources/js/n2n/group.js', 'resources/css/brain.css', 'resources/js/brain/shell.js', 'resources/js/handbooks/reader.js', 'resources/js/master-marketing/widget-dock.js'].filter((f) => existsSync(f));

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', ...optional],
            refresh: true,
        }),
    ],
});
