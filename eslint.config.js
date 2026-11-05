import js from '@eslint/js';
import { defineConfig, globalIgnores } from 'eslint/config';
import prettier from 'eslint-config-prettier';
import pluginVue from 'eslint-plugin-vue';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default defineConfig([
    globalIgnores(['vendor/**', 'node_modules/**', 'public/**', 'storage/**', 'bootstrap/cache/**']),

    js.configs.recommended,
    tseslint.configs.recommended,
    pluginVue.configs['flat/recommended'],

    {
        files: ['resources/js/**/*.{ts,vue}'],
        languageOptions: {
            globals: globals.browser,
            // Lets <script setup lang="ts"> blocks inside .vue files be read as TypeScript.
            parserOptions: { parser: tseslint.parser },
        },
    },
    {
        files: ['*.js'],
        languageOptions: { globals: globals.node },
    },

    // Last, so the formatting rules of ESLint never fight with Prettier.
    prettier,
]);
