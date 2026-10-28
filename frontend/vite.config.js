import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Le proxy garde le front et l'API sur la même origine (localhost:5173),
// ce qui permet l'authentification par cookies de Laravel Sanctum.
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': { target: 'http://127.0.0.1:8000', changeOrigin: false },
      '/sanctum': { target: 'http://127.0.0.1:8000', changeOrigin: false },
    },
  },
});
