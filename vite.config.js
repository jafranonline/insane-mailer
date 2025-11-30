import { defineConfig } from 'vite';
import solidPlugin from 'vite-plugin-solid';
import path from 'path';

export default defineConfig({
  plugins: [solidPlugin()],
  assetsInclude: ['**/*.svg'],
  css: {
    postcss: './postcss.config.js',
  },
  build: {
    outDir: './assets',
    emptyOutDir: false,
    cssCodeSplit: false,
    sourcemap: true,
    rollupOptions: {
      input: './src/index.jsx',
      output: {
        entryFileNames: 'script.js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name === 'index.css') {
            return 'style.css';
          }
          return 'style.[ext]';
        },
        format: 'iife',
      },
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
});
