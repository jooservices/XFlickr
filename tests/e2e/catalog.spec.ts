import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

test.describe('Catalog smoke', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
    });

    test('photos catalog page loads with table controls', async ({ page }) => {
        await page.goto('/photos');

        await expect(page.getByTestId('catalog-photos-page')).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Photos' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Table' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Grid' })).toBeVisible();
    });
});
