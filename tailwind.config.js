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
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                'brand-teal': '#006A6A', // Sesuaikan HEX dengan Teal Anda (misalnya)
                'brand-olive': '#999933', // Sesuaikan HEX dengan Olive Anda (misalnya)
                'brand-gray': '#808080',  // Sesuaikan HEX dengan Abu-abu Anda (misalnya)

                // Variasi untuk light/dark mode (bisa disesuaikan lebih lanjut)
                'light-bg': '#F3F4F6',      // Abu-abu sangat terang (slate-100/gray-100)
                'light-card-bg': '#FFFFFF', // Putih
                'light-text': '#1F2937',    // Abu-abu gelap (gray-800)
                'light-text-secondary': '#6B7280', // Abu-abu sedang (gray-500)

                'dark-bg': '#111827',       // Abu-abu sangat gelap (gray-900)
                'dark-card-bg': '#1F2937',  // Abu-abu lebih terang (gray-800)
                'dark-text': '#F3F4F6',     // Abu-abu sangat terang (gray-100/slate-100)
                'dark-text-secondary': '#9CA3AF', // Abu-abu sedang (gray-400)
            },
        },
    },

    plugins: [forms],
};
