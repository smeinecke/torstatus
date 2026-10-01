import { resolve } from 'node:path';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';

const projectRoot = resolve(process.cwd());

export default defineConfig({
  root: resolve(projectRoot, 'web/assets/src'),
  plugins: [tailwindcss()],
  publicDir: false,
  build: {
    emptyOutDir: false,
    outDir: resolve(projectRoot, 'web/public'),
    rollupOptions: {
      input: resolve(projectRoot, 'web/assets/src/app.js'),
      output: {
        entryFileNames: 'js/app.js',
        chunkFileNames: 'js/[name].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'css/app.css';
          }
          return 'assets/[name][extname]';
        }
      }
    }
  }
});
