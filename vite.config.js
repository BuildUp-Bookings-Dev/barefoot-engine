import { defineConfig } from 'vite';
import path from 'node:path';

export default defineConfig({
  root: '.',
  base: './',
  publicDir: false,
  resolve: {
    alias: {
      '@braudypedrosa/bp-calendar/styles': path.resolve(
        __dirname,
        'node_modules/@braudypedrosa/bp-calendar/src/bp-calendar.scss'
      ),
      '@braudypedrosa/bp-calendar': path.resolve(
        __dirname,
        'node_modules/@braudypedrosa/bp-calendar/src/bp-calendar.js'
      ),
      '@braudypedrosa/bp-search-widget/styles': path.resolve(
        __dirname,
        'node_modules/@braudypedrosa/bp-search-widget/src/bp-search-widget.scss'
      ),
      '@braudypedrosa/bp-search-widget': path.resolve(
        __dirname,
        'node_modules/@braudypedrosa/bp-search-widget/src/bp-search-widget.js'
      ),
    },
  },
  build: {
    manifest: true,
    outDir: 'assets/dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        'admin-script': path.resolve(__dirname, 'assets/src/admin/index.js'),
        'public-script': path.resolve(__dirname, 'assets/src/public/index.js'),
        'admin-style': path.resolve(__dirname, 'assets/src/admin/index.scss'),
        'admin-tailwind-style': path.resolve(__dirname, 'assets/src/admin/tailwind.css'),
        'public-style': path.resolve(__dirname, 'assets/src/public/index.scss')
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
