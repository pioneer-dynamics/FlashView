import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
    appId: 'com.pioneerdynamics.flashview',
    appName: 'FlashView',
    webDir: 'dist',
    server: {
        androidScheme: 'https',
    },
};

export default config;
