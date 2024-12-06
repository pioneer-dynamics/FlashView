import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                "lime_green": {
                    50: "#e2e6d9",
                    100: "#c6cfb1",
                    200: "#aab886",
                    300: "#929f70",
                    400: "#7c875e",
                    500: "#666f4e",
                    600: "#51593e",
                    700: "#3d432f",
                    800: "#2a2e20",
                    900: "#181b13",

                },
                "gamboge": {
                    50: "#fedfc6",
                    100: "#fdbf80",
                    200: "#f49f1b",
                    300: "#d68b18",
                    400: "#b27414",
                    500: "#966211",
                    600: "#784f0d",
                    700: "#5d3d0a",
                    800: "#3c2707",
                    900: "#1b1203",

                },
                "gray": {
                    50: "#e0e5eb",
                    100: "#c2ccd9",
                    200: "#a4b4c7",
                    300: "#859cb7",
                    400: "#6e849e",
                    500: "#5b6d82",
                    600: "#485767",
                    700: "#37414e",
                    800: "#262d36",
                    900: "#161a1f",

                },
            }
        },
    },

    plugins: [forms, typography],
};
