import path from 'path';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  base: './',
  define: {
    'process.env.NODE_ENV': '"production"',
    'process.env': '{}',
  },
  build: {
    cssCodeSplit: true,
    minify: 'terser',
    terserOptions: {
      mangle: {
        reserved: ['$', '$$', '$F', '$A', '$H', '$R', '$w', 'Prototype', 'JobRouter', 'JR'],
      },
    },
    rollupOptions: {
      input: {
        widget: path.resolve(__dirname, './widget.html'),
      },
      output: {
        entryFileNames: 'assets/widget.js',
        chunkFileNames: 'assets/chunk-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name?.endsWith('.css')) {
            return 'assets/widget.css';
          }
          return 'assets/[name]-[hash][extname]';
        },
      },
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
});
