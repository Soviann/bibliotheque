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
      strategies: "injectManifest",
      srcDir: "src",
      filename: "sw-custom.ts",
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
      injectManifest: {
        globIgnores: ["**/app-icon.png"],
        globPatterns: ["**/*.{js,css,html,ico,png,svg,woff2}"],
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
