import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
import { VitePWA } from "vite-plugin-pwa";

export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
    VitePWA({
      registerType: "autoUpdate",
      manifest: {
        name: "Bibliothèque",
        short_name: "Biblio",
        description: "Gestionnaire de bibliothèque BD/Manga",
        theme_color: "#1e40af",
        background_color: "#0f172a",
        display: "standalone",
        icons: [
          {
            src: "/icon-192.png",
            sizes: "192x192",
            type: "image/png",
            purpose: "any maskable",
          },
          {
            src: "/icon-512.png",
            sizes: "512x512",
            type: "image/png",
            purpose: "any maskable",
          },
        ],
      },
      workbox: {
        runtimeCaching: [
          {
            urlPattern: /^\/api\//,
            handler: "NetworkFirst",
            options: {
              cacheName: "api-cache",
              expiration: { maxEntries: 200, maxAgeSeconds: 7 * 24 * 60 * 60 },
              networkTimeoutSeconds: 5,
            },
          },
          {
            urlPattern: /\/uploads\/covers\//,
            handler: "CacheFirst",
            options: {
              cacheName: "cover-cache",
              expiration: { maxEntries: 500, maxAgeSeconds: 30 * 24 * 60 * 60 },
            },
          },
          {
            urlPattern: /^https:\/\/books\.google\.com\//,
            handler: "CacheFirst",
            options: {
              cacheName: "external-cover-cache",
              expiration: { maxEntries: 500, maxAgeSeconds: 30 * 24 * 60 * 60 },
            },
          },
        ],
      },
    }),
  ],
  build: {
    target: "chrome64",
  },
  server: {
    host: "0.0.0.0",
    port: 5173,
    origin: process.env.DDEV_PRIMARY_URL,
    hmr: {
      host: process.env.DDEV_HOSTNAME?.split(",")[0],
      protocol: "wss",
      clientPort: 5173,
    },
    proxy: {
      "/api": {
        target: "https://localhost",
        changeOrigin: true,
        secure: false,
      },
      "/uploads": {
        target: "https://localhost",
        changeOrigin: true,
        secure: false,
      },
    },
  },
});
