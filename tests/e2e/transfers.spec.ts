import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

test.describe('Transfers smoke', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
    });

    test('operations transfers panel lists download batches', async ({ page }) => {
        await page.goto('/operations?panel=transfers');

        await expect(page.getByTestId('operations-transfers-panel')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Transfers' })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: 'Batch' })).toBeVisible();
        await expect(page.getByText('completed').first()).toBeVisible();
        await expect(page.getByText('failed').first()).toBeVisible();
    });
});
