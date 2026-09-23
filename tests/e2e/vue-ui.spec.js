import { test, expect } from '@playwright/test';
import { INDUSTRIE } from './helpers.js';

test.describe('Vue + PrimeVue UI standards', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(`${INDUSTRIE}/ui-kit/vue`);
        await expect(page.getByRole('heading', { name: /UI kit/ })).toBeVisible();
    });

    test('uses the store branding and no licence notice', async ({ page }) => {
        await expect(page.locator('header')).toContainText("MyOil's Industrie");
        await expect(page.getByRole('button', { name: 'Send', exact: true })).toHaveCSS('background-color', 'rgb(43, 47, 54)');
        await expect(page.getByText('Invalid PrimeUI License')).toHaveCount(0);
    });

    test('toasts with Lucide icons and a reference code', async ({ page }) => {
        await page.getByRole('button', { name: 'Error (with reference)' }).click();
        const toast = page.locator('.p-toast-message').filter({ hasText: 'Something went wrong' });
        await expect(toast).toContainText('Reference: 7F3A-91C2');
        await expect(toast.locator('svg.icon').first()).toBeVisible();
    });

    test('form: client checks, then server errors in the same place', async ({ page }) => {
        await page.getByRole('button', { name: 'Send', exact: true }).click();
        await expect(page.getByText('Enter your name.')).toBeVisible();
        await expect(page.locator('#uikit-name')).toBeFocused();
        await expect(page.getByRole('alert').filter({ hasText: 'Please fix 4 field(s)' })).toBeVisible();

        await page.locator('#uikit-postcode').fill('10123');
        await page.getByRole('button', { name: 'Send without browser checks' }).click();
        await expect(page.getByText('Use the format 1234 AB.')).toBeVisible();
    });

    test('confirmation dialog and unsaved-changes guard', async ({ page }) => {
        await page.getByRole('button', { name: 'Delete address "Workshop"' }).click();
        await expect(page.getByRole('alertdialog').or(page.getByRole('dialog'))).toContainText('Delete address "Workshop"?');
        await page.getByRole('button', { name: 'Delete address', exact: true }).click();
        await expect(page.getByText('Address "Workshop" deleted.')).toBeVisible();

        await page.locator('#uikit-name').fill('Jan');
        await page.getByRole('link', { name: 'Go to the Bootstrap UI kit' }).click();
        await expect(page.getByText('Leave without saving?')).toBeVisible();
        await page.getByRole('button', { name: 'Stay on page' }).click();
        await expect(page).toHaveURL(/\/ui-kit\/vue$/);
    });

    test('error matrix: 409 dialog and 429 countdown', async ({ page }) => {
        await page.getByRole('button', { name: '409', exact: true }).click();
        await expect(page.getByText('This was changed in the meantime')).toBeVisible();
        await page.getByRole('button', { name: 'Close', exact: true }).click();

        await page.getByRole('button', { name: '429', exact: true }).click();
        await expect(page.getByText(/Please wait \d+ s before trying again/).first()).toBeVisible();
    });
});
