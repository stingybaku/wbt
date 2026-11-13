import { defineConfig } from 'vite';

export default defineConfig({
  base: './',
  build: {
    outDir: '../backend/storage/app/scorm-player',
    emptyOutDir: true,
    rollupOptions: { input: 'scorm.html' },
  },
});
