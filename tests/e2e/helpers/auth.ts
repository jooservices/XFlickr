import { expect, type Page } from '@playwright/test';

const DEFAULT_ADMIN_EMAIL = 'admin@local';
const DEFAULT_ADMIN_PASSWORD = 'password';

export function adminCredentials(): { email: string; password: string } {
    return {
        email: process.env.ADMIN_EMAIL ?? DEFAULT_ADMIN_EMAIL,
        password: process.env.ADMIN_PASSWORD ?? DEFAULT_ADMIN_PASSWORD,
    };
}

export async function loginAsAdmin(page: Page): Promise<void> {
    const { email, password } = adminCredentials();

    await page.goto('/login');
    await page.getByLabel(/email/i).fill(email);
    await page.getByLabel(/password/i).fill(password);
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(/\/dashboard/);
}
