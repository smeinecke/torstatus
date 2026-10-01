import js from '@eslint/js';
import globals from 'globals';
import shadcn from '@shadcn/lint';
import twigProcessor from './eslint/twig-processor.js';

const shadcnRules = {
  // All @shadcn/lint rules enabled. They enforce design-system class usage
  // on className/class strings; the theme is auto-discovered from
  // web/assets/src/app.css (the stylesheet importing Tailwind v4).
  'shadcn/no-restyle': 'error',
  'shadcn/no-raw-colors': 'error',
  'shadcn/no-arbitrary-values': 'error',
  'shadcn/no-inline-styles': 'error',
  'shadcn/require-static-classes': 'error',
  'shadcn/no-unknown-classes': 'error',
};

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
    rules: shadcnRules,
  },
  {
    files: ['*.config.js', 'eslint/*.js'],
    languageOptions: {
      globals: globals.node,
    },
  },
  {
    // Compile Twig templates into virtual JSX so @shadcn/lint's rules can see
    // the class attributes (the plugin only understands JS/Svelte/Vue ASTs).
    files: ['web/templates/**/*.twig'],
    processor: twigProcessor,
  },
  {
    files: ['web/templates/**/*.twig/*.jsx'],
    languageOptions: {
      parserOptions: { ecmaFeatures: { jsx: true } },
    },
    plugins: { shadcn },
    rules: {
      ...shadcnRules,
      // Twig legitimately builds classes from server-side values
      // (class="F{{ FAuthority }}"), so unreadable fragments are warnings.
      'shadcn/require-static-classes': 'warn',
      // ts-*, ti-*, flag-* and the PHP-emitted table classes are plain CSS,
      // not Tailwind utilities — allow them by glob.
      'shadcn/no-unknown-classes': ['error', {
        allow: [
          'ts-*', 'ti', 'ti-*', 'flag', 'flag-*',
          'bwr*', 'fg*', 'TD*', 'TR*', 'HR*', 'F*',
          'r', 'd', 'R', 'B',
          'sr-only', 'header', 'empty', 'nna', 'who', 'displayTable', 'BOXCOLSEL',
        ],
      }],
    },
  },
];
