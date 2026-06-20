import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// In dev, proxy API calls to the PHP built-in server (npm run api on :8000).
export default defineConfig({
  plugins: [vue()],
  base: './',
  server: {
    proxy: {
      '/api': { target: 'http://127.0.0.1:8000', changeOrigin: true },
    },
  },
})
