import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import symfonyPlugin from 'vite-plugin-symfony';

// Le front vit dans assets/ ; le build sort dans public/build (servi par nginx
// via try_files). Le bundle pentatrion/vite-bundle lit le manifest généré.
export default defineConfig({
    root: '.',
    base: '/build/',
    plugins: [
        vue(),
        symfonyPlugin(),
    ],
    build: {
        manifest: true,
        emptyOutDir: true,
        outDir: '../public/build',
        rollupOptions: {
            input: {
                app: './src/main.js',
            },
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        // Permet au navigateur (servi par nginx) de charger les assets du dev server.
        cors: true,
        origin: 'http://local.plantcompanion.fr:5173',
    },
});
