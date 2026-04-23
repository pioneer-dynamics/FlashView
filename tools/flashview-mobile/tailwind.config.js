/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './index.html',
        './src/**/*.{vue,js,ts,jsx,tsx}',
    ],
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                gamboge: {
                    50:  '#e0f9ff',
                    100: '#b3f0ff',
                    200: '#66e3ff',
                    300: '#00d4f5',
                    400: '#00b8d9',
                    500: '#009ab5',
                    600: '#007a91',
                    700: '#005c6e',
                    800: '#00404f',
                    900: '#002830',
                },
            },
        },
    },
    plugins: [
        function ({ addUtilities }) {
            addUtilities({
                '.pb-safe': {
                    'padding-bottom': 'env(safe-area-inset-bottom, 0px)',
                },
                '.shadow-neon-cyan': {
                    'box-shadow': '0 0 8px 0 rgba(0, 212, 245, 0.4), 0 0 20px 0 rgba(0, 212, 245, 0.15)',
                },
                '.shadow-neon-cyan-sm': {
                    'box-shadow': '0 0 4px 0 rgba(0, 212, 245, 0.3)',
                },
                '.shadow-neon-cyan-lg': {
                    'box-shadow': '0 0 12px 0 rgba(0, 212, 245, 0.5), 0 0 40px 0 rgba(0, 212, 245, 0.2)',
                },
            })
        },
    ],
};
