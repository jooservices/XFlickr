import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import path from 'node:path';

/**
 * FE coverage floor applies to pure logic first (audit T5).
 * Integration-heavy hooks/libs are excluded until targeted tests exist;
 * Components/Pages stay covered by component tests + Playwright smokes.
 */
const coverageInclude = [
    'resources/js/lib/apiPaths.ts',
    'resources/js/lib/cn.ts',
    'resources/js/lib/connections.ts',
    'resources/js/lib/contactCatalog.ts',
    'resources/js/lib/contactGraphMerge.ts',
    'resources/js/lib/crawlSubject.ts',
    'resources/js/lib/flickrTokenHealthBannerDismiss.ts',
    'resources/js/lib/format.ts',
    'resources/js/lib/onboardingProgress.ts',
    'resources/js/lib/publicId.ts',
    'resources/js/lib/settingsOnboarding.ts',
    'resources/js/lib/spiderImpact.ts',
    'resources/js/lib/tableSort.ts',
    'resources/js/lib/toast.ts',
    'resources/js/hooks/useCountdown.ts',
    'resources/js/hooks/useFlashToast.ts',
    'resources/js/hooks/useInvalidFlickrTokenAccounts.ts',
    'resources/js/hooks/useOwnerNsidFilter.ts',
    'resources/js/hooks/usePolledResource.ts',
    'resources/js/hooks/useTableSelection.ts',
    'resources/js/hooks/useTableSort.ts',
];

export default defineConfig({
    plugins: [react()],
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['resources/js/**/*.test.{ts,tsx}'],
        coverage: {
            provider: 'v8',
            include: coverageInclude,
            exclude: ['resources/js/**/*.test.{ts,tsx}'],
            thresholds: {
                lines: 90,
            },
            reporter: ['text', 'text-summary'],
        },
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
});
