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
            colors:{
                gray: {
                    50: '#FFF7F2',
                    100: '#FFE9D5',
                    200: '#FFD7B5',
                    300: '#FFC499',
                    400: '#FFB281',
                    500: '#F37622',
                    600: '#E6601A',
                    700: '#CD550C',
                    800: '#B84C09',
                    900: '#A83706',
                },
            }
        },
    },

    plugins: [forms, typography],
};
