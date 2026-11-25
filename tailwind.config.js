import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Poppins', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                lwe: {
                    navy: '#0B2447',
                    navy2: '#19376D',
                    orange: '#F28C28',
                    orangeSoft: '#FFE7CC',
                    green: '#17733F',
                    greenSoft: '#DDF3E5',
                    bg: '#F4F6FA',
                    line: '#E4E9F2',
                    muted: '#5B6474',
                },
            },
        },
    },
    plugins: [forms],
};
