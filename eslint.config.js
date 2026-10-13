import js from '@eslint/js';
import tsPlugin from '@typescript-eslint/eslint-plugin';
import prettierRecommended from 'eslint-plugin-prettier/recommended';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import { createRequire } from 'node:module';

// eslint-plugin-react's version detection calls context.getFilename(), which ESLint 10 removed
const reactVersion = createRequire(import.meta.url)('react/package.json').version;

export default [
  js.configs.recommended,
  ...tsPlugin.configs['flat/recommended'],
  react.configs.flat.recommended,
  reactHooks.configs.flat.recommended,
  // Prettier owns formatting, so it comes last and turns off conflicting style rules
  prettierRecommended,
  {
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: { ...globals.browser, ...globals.node },
    },
    settings: {
      react: { version: reactVersion },
    },
    rules: {
      'react/react-in-jsx-scope': 'off',
      'react/prop-types': 'off',
      'react/no-unescaped-entities': 'off',
    },
  },
];
