import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            keyframes: {
                blink: {
                  "50%": {
                    borderColor: "transparent"
                  },
                  "100%": {
                    borderColor: "currentColor"
                  }
                }
              },
              animation: {
                typing: "infinite alternate, blink 1s infinite"
              },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },
            boxShadow: {
                'neon-cyan': '0 0 8px 0 rgba(0, 212, 245, 0.4), 0 0 20px 0 rgba(0, 212, 245, 0.15)',
                'neon-cyan-sm': '0 0 4px 0 rgba(0, 212, 245, 0.3)',
            },
            colors: {
                "lime_green": {
                    50:  "#e0fff0",
                    100: "#b3ffda",
                    200: "#66ffb8",
                    300: "#1aff96",
                    400: "#00f080",
                    500: "#00cc6a",
                    600: "#00aa55",
                    700: "#008844",
                    800: "#006633",
                    900: "#004422",
                },
                "gamboge": {
                    50:  "#e0f9ff",
                    100: "#b3f0ff",
                    200: "#66e3ff",
                    300: "#00d4f5",
                    400: "#00b8d9",
                    500: "#009ab5",
                    600: "#007a91",
                    700: "#005c6e",
                    800: "#00404f",
                    900: "#002830",
                },
                "gray": {
                    50:  "#c8d8e8",
                    100: "#a0b8cc",
                    200: "#7898b0",
                    300: "#507890",
                    400: "#3a5f78",
                    500: "#2a4860",
                    600: "#1e3448",
                    700: "#162436",
                    800: "#0e1826",
                    900: "#080c16",
                },
            }
        },
    },

    plugins: [forms, typography],
};
