import { defineConfig } from "vitest/config";
import react from "@vitejs/plugin-react";

export default defineConfig({
  plugins: [react()],
  test: {
    alias: {
      "react-virtuoso": new URL(
        "./src/__mocks__/react-virtuoso.tsx",
        import.meta.url,
      ).pathname,
      "virtual:pwa-register": new URL(
        "./src/__mocks__/virtual-pwa-register.ts",
        import.meta.url,
      ).pathname,
    },
    environment: "jsdom",
    globals: true,
    setupFiles: "./src/test-setup.ts",
  },
});
