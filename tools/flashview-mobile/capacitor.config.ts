import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
    appId: 'com.pioneerdynamics.flashview',
    appName: 'FlashView',
    webDir: 'dist',
    server: {
        androidScheme: 'https',
    },
    plugins: {
        // Routes all fetch() calls through native HTTP, bypassing WebView CORS restrictions.
        // Authentication security is enforced by Bearer tokens, not CORS.
        CapacitorHttp: {
            enabled: true,
        },
    },
};

export default config;
