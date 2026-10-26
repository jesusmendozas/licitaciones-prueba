/** Compila los componentes .vue hacia public. En desarrollo reenvía /api al PHP. */
import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue2";

export default defineConfig({
  plugins: [vue()],
  publicDir: false,
  build: {
    outDir: "public",
    emptyOutDir: false,
    assetsDir: "assets",
  },
  server: {
    proxy: {
      "/api": "http://localhost:8000",
    },
  },
});
