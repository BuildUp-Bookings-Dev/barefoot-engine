import { defineConfig } from 'vite';
import path from 'node:path';

export default defineConfig({
  root: '.',
  publicDir: false,
  build: {
    manifest: true,
    outDir: 'assets/dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        'admin-script': path.resolve(__dirname, 'assets/src/js/admin/index.js'),
        'public-script': path.resolve(__dirname, 'assets/src/js/public/index.js'),
        'admin-style': path.resolve(__dirname, 'assets/src/scss/admin/index.scss'),
        'admin-tailwind-style': path.resolve(__dirname, 'assets/src/css/admin-tailwind.css'),
        'public-style': path.resolve(__dirname, 'assets/src/scss/public/index.scss')
      },
      output: {
        entryFileNames: 'js/[name]-[hash].js',
        chunkFileNames: 'js/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          const name = assetInfo.name || '';
          if (name.endsWith('.css')) {
            return 'css/[name]-[hash][extname]';
          }

          return 'assets/[name]-[hash][extname]';
        }
      }
    }
  }
});
