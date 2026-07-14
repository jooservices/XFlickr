import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

test.describe('Contacts smoke', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
    });

    test('contacts list renders for the active Flickr account', async ({ page }) => {
        await page.goto('/contacts');

        await expect(page.getByTestId('contacts-page')).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Contacts' })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: 'NSID' })).toBeVisible();
        await expect(page.getByRole('row').nth(1)).toBeVisible();
    });
});
