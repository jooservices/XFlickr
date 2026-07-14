import eslint from '@eslint/js';
import importPlugin from 'eslint-plugin-import';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    {
        ignores: ['node_modules/**', 'vendor/**', 'public/build/**', 'resources/js/bootstrap.js'],
    },
    eslint.configs.recommended,
    ...tseslint.configs.recommended,
    {
        files: ['resources/js/**/*.{ts,tsx}'],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: {
                ...globals.browser,
            },
            parserOptions: {
                projectService: true,
                tsconfigRootDir: import.meta.dirname,
            },
        },
        plugins: {
            import: importPlugin,
            'react-hooks': reactHooks,
        },
        rules: {
            'react-hooks/rules-of-hooks': 'error',
            'react-hooks/exhaustive-deps': 'warn',
            '@typescript-eslint/no-unused-vars': 'error',
            'import/first': 'error',
            'import/order': [
                'error',
                {
                    groups: ['builtin', 'external', 'internal', 'parent', 'sibling', 'index'],
                    pathGroups: [
                        {
                            pattern: '@/**',
                            group: 'internal',
                            position: 'before',
                        },
                    ],
                    pathGroupsExcludedImportTypes: ['builtin'],
                    alphabetize: {
                        order: 'asc',
                        caseInsensitive: true,
                    },
                    'newlines-between': 'always',
                },
            ],
        },
    },
    {
        files: ['resources/js/hooks/**/*.{ts,tsx}', 'resources/js/Pages/**/*.{ts,tsx}'],
        rules: {
            'no-restricted-syntax': [
                'error',
                {
                    selector:
                        "CallExpression[callee.name='setInterval'], CallExpression[callee.property.name='setInterval']",
                    message:
                        'Use usePolledResource for API polling in hooks/Pages. UI timers belong in useCountdown; setInterval is only allowed in usePolledResource.ts and useCountdown.ts.',
                },
            ],
        },
    },
    {
        files: ['resources/js/hooks/usePolledResource.ts', 'resources/js/hooks/useCountdown.ts'],
        rules: {
            'no-restricted-syntax': 'off',
        },
    },
);
