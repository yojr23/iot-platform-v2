import { labDemoPlugin } from "./scripts/demo-server.mjs";
import { fileURLToPath, URL } from "node:url";

import vue from "@vitejs/plugin-vue";
import { defineConfig, loadEnv } from "vite";

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), "");

    return {
        define:
            mode === "demo"
                ? {
                      "import.meta.env.VITE_PUSHER_APP_KEY":
                          JSON.stringify("local-preview"),
                      "import.meta.env.VITE_PUSHER_HOST":
                          JSON.stringify("127.0.0.1"),
                      "import.meta.env.VITE_PUSHER_PORT":
                          JSON.stringify("5173"),
                      "import.meta.env.VITE_PUSHER_FORCE_TLS":
                          JSON.stringify("false"),
                      "import.meta.env.VITE_PUSHER_SCHEME":
                          JSON.stringify("http"),
                      "import.meta.env.VITE_API_BASE_URL":
                          JSON.stringify("/api"),
                  }
                : {},
        plugins: [vue(), ...(mode === "demo" ? [labDemoPlugin()] : [])],
        resolve: {
            alias: {
                "@": fileURLToPath(new URL("./src", import.meta.url)),
            },
        },
        server: {
            host: "127.0.0.1",
            port: 5173,
            strictPort: true,
            proxy: {
                "/api": {
                    target:
                        env.VITE_API_PROXY_TARGET || "http://localhost:8000",
                    changeOrigin: true,
                },
            },
        },
    };
});
