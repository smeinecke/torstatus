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
      // Inline styles may only carry CSS custom properties (data channels
      // like --bw for the bandwidth bar); presentational props belong in CSS.
      'shadcn/no-inline-styles': ['error', { allow: ['--*'] }],
      // Note: no-unknown-classes needs no `allow` list — it treats every
      // class selector in web/assets/src/app.css and anything reachable
      // through its @import chain (tabler-icons, tabler-flags) as known.
      // Classes that exist only at runtime (F0/F1, bwr*, TDS…) live inside
      // Twig {{ }} interpolations, which the analyzer skips. A typo'd or
      // missing class is therefore a real error.
    },
  },
];
