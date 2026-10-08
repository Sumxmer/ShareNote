import { defineConfig, globalIgnores } from 'eslint/config';
import js from '@eslint/js';
import ts from 'typescript-eslint';
import hooks from 'eslint-plugin-react-hooks';

export default defineConfig([
  js.configs.recommended, ...ts.configs.recommended,
  { files: ['src/**/*.tsx'], plugins: { 'react-hooks': hooks }, rules: hooks.configs.recommended.rules },
  { rules: { 'no-control-regex': 'off', '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }] } },
  { languageOptions: { globals: { process: 'readonly', console: 'readonly', Buffer: 'readonly', window: 'readonly', File: 'readonly', FormData: 'readonly', Headers: 'readonly', Response: 'readonly', React: 'readonly', URL: 'readonly', URLSearchParams: 'readonly' } } },
  globalIgnores(['.next/**', 'node_modules/**', 'legacy/**', 'tmp/**', 'output/**', 'next-env.d.ts']),
]);
