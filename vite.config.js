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
        'admin-script': path.resolve(__dirname, 'assets/src/admin/index.js'),
        'public-script': path.resolve(__dirname, 'assets/src/public/index.js'),
        'search-widget-script': path.resolve(__dirname, 'assets/src/widgets/search-widget/index.js'),
        'admin-style': path.resolve(__dirname, 'assets/src/admin/index.scss'),
        'admin-tailwind-style': path.resolve(__dirname, 'assets/src/admin/tailwind.css'),
        'public-style': path.resolve(__dirname, 'assets/src/public/index.scss'),
        'search-widget-style': path.resolve(__dirname, 'assets/src/widgets/search-widget/index.scss')
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
