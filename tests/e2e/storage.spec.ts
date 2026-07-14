import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

test.describe('Storage smoke', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
    });

    test('google photos browse page shows cached local albums', async ({ page }) => {
        await page.goto('/storages/google-photos');

        await expect(page.getByTestId('storage-browse-page')).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Google Photos' })).toBeVisible();
        await expect(page.getByLabel('Account')).toBeVisible();
        await expect(page.getByRole('columnheader', { name: 'Album' })).toBeVisible();
    });
});
