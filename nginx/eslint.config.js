import js from '@eslint/js';
import globals from 'globals';
import shadcn from '@shadcn/lint';

export default [
  {
    ignores: [
      'node_modules/',
      'vendor/',
      'web/public/', // vite build output
    ],
  },
  js.configs.recommended,
  {
    files: ['web/assets/src/**/*.js'],
    languageOptions: {
      globals: globals.browser,
    },
    plugins: { shadcn },
    rules: {
      // All @shadcn/lint rules enabled. They enforce design-system class usage
      // on className/class strings; the theme is auto-discovered from
      // web/assets/src/app.css (the stylesheet importing Tailwind v4).
      'shadcn/no-restyle': 'error',
      'shadcn/no-raw-colors': 'error',
      'shadcn/no-arbitrary-values': 'error',
      'shadcn/no-inline-styles': 'error',
      'shadcn/require-static-classes': 'error',
      'shadcn/no-unknown-classes': 'error',
    },
  },
  {
    files: ['*.config.js'],
    languageOptions: {
      globals: globals.node,
    },
  },
];
