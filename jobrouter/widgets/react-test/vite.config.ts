import path from "path"
import tailwindcss from "@tailwindcss/vite"
import react from "@vitejs/plugin-react"
import { defineConfig } from "vite"

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  base: './',
  build: {
    minify: 'terser',
    terserOptions: {
      mangle: {
        reserved: ['$', '$$', '$F', '$A', '$H', '$R', '$w', 'Prototype', 'JobRouter', 'JR'],
      },
    },
    rollupOptions: {
      output: {
        format: 'iife',
      },
    },
  },
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
})
