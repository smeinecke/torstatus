import forms from '@tailwindcss/forms';

export default {
  content: [
    './web/templates/**/*.twig',
    './web/public/**/*.php',
    './web/assets/src/**/*.{js,css}'
  ],
  theme: {
    extend: {
      colors: {
        tor: {
          50: '#f4f0ff',
          500: '#7c3aed',
          600: '#6d28d9',
          700: '#5b21b6'
        }
      }
    }
  },
  plugins: [forms]
};
