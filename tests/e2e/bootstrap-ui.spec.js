import { test, expect } from '@playwright/test';
import { AUTO } from './helpers.js';

test.describe('Bootstrap + jQuery UI standards', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(`${AUTO}/ui-kit`);
        await page.locator('body[data-ui-kit-ready]').waitFor({ state: 'attached' });
    });

    test('uses the store branding', async ({ page }) => {
        await expect(page.locator('.shop-brand')).toHaveText("MyOil's Auto");
        const primary = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--brand-primary').trim());
        expect(primary).toBe('#0F2742');
        await expect(page.getByRole('button', { name: 'Send', exact: true })).toHaveCSS('background-color', 'rgb(15, 39, 66)');
    });

    test('toasts: success closes by itself, errors stay, at most three', async ({ page }) => {
        await page.getByRole('button', { name: 'Success' }).click();
        const success = page.locator('.app-toast--success');
        await expect(success).toContainText('Address saved');
        await expect(success.locator('svg.icon')).toBeVisible();

        await page.getByRole('button', { name: 'Error (with reference)' }).click();
        await expect(page.locator('.app-toast--error')).toContainText('Reference: 7F3A-91C2');

        for (let i = 0; i < 3; i++) await page.getByRole('button', { name: 'Warning' }).click();
        await expect(page.locator('#toast-stack .toast')).toHaveCount(3);

        await expect(success).toHaveCount(0, { timeout: 6000 });
        await expect(page.locator('.app-toast--warning').first()).toBeVisible();
    });

    test('form: client checks, summary, focus and live re-check', async ({ page }) => {
        await page.getByRole('button', { name: 'Send', exact: true }).click();

        const name = page.getByLabel('Name');
        await expect(name).toHaveClass(/is-invalid/);
        await expect(name).toBeFocused();
        await expect(page.locator('#contact-summary .alert')).toContainText('Please fix 4 field(s)');

        const email = page.getByLabel('Email');
        await email.fill('jan@');
        await email.blur();
        await expect(page.getByText('Enter a full email address, e.g. jan@example.com.')).toBeVisible();
        await email.fill('jan@example.com');
        await expect(email).toHaveClass(/is-valid/);
    });

    test('form: server errors appear under the same fields', async ({ page }) => {
        await page.getByLabel('Postcode').fill('10123');
        await page.getByRole('button', { name: 'Send without browser checks' }).click();

        await expect(page.locator('input[name="ui_kit_contact[postcode]"]')).toHaveClass(/is-invalid/);
        await expect(page.getByText('Use the format 1234 AB.')).toBeVisible();
        await expect(page.getByText('Enter your name.')).toBeVisible();
        await expect(page.locator('#contact-summary .alert')).toBeVisible();
    });

    test('confirmation dialog: Esc keeps, the red button deletes', async ({ page }) => {
        await page.getByRole('button', { name: 'Delete address "Workshop"' }).click();
        const dialog = page.locator('#confirm-modal');
        await expect(dialog.getByRole('heading')).toHaveText('Delete address "Workshop"?');
        await expect(dialog.getByRole('button', { name: 'Keep address' })).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(page.locator('#toast-stack')).toContainText('Nothing was deleted.');

        await page.getByRole('button', { name: 'Delete address "Workshop"' }).click();
        await dialog.getByRole('button', { name: 'Delete address' }).click();
        await expect(page.locator('#toast-stack')).toContainText('Address "Workshop" deleted.');
    });

    test('unsaved changes: leaving asks first', async ({ page }) => {
        await page.getByLabel('Name').fill('Jan');
        await page.getByRole('link', { name: 'Go to the Vue UI kit' }).click();

        const dialog = page.locator('#confirm-modal');
        await expect(dialog.getByRole('heading')).toHaveText('Leave without saving?');
        await dialog.getByRole('button', { name: 'Stay on page' }).click();
        await expect(page).toHaveURL(/\/ui-kit$/);
        await expect(page.getByLabel('Name')).toHaveValue('Jan');
    });

    test('error matrix: 403, 409, 429 and 500 react as documented', async ({ page }) => {
        await page.locator('[data-demo-status="403"]').click();
        await expect(page.locator('.app-toast--error').last()).toContainText('Simulated 403 response');

        await page.locator('[data-demo-status="409"]').click();
        await expect(page.locator('#confirm-modal').getByRole('heading')).toHaveText('This was changed in the meantime');
        await page.locator('#confirm-modal').getByRole('button', { name: 'Close' }).last().click();

        await page.locator('[data-demo-status="429"]').click();
        await expect(page.locator('.app-toast--warning').last()).toContainText(/Please wait \d+ s/);
        await expect(page.locator('[data-demo-status="429"]')).toBeDisabled();
        await expect(page.locator('[data-demo-status="429"]')).toHaveText(/Wait \d+ s/);

        await page.locator('[data-demo-status="500"]').click();
        await expect(page.locator('.app-toast--error').last()).toContainText(/Reference: [0-9A-F]{4}-[0-9A-F]{4}/);
    });

    test('loading: skeleton then list, and the empty state', async ({ page }) => {
        await page.getByRole('button', { name: 'Load products' }).click();
        await expect(page.locator('#demo-list .placeholder').first()).toBeVisible();
        await expect(page.locator('#demo-list')).toContainText('Synth Pro 5W-30');

        await page.getByRole('button', { name: 'Load an empty list' }).click();
        await expect(page.locator('#demo-list')).toContainText('No products match');
    });
});
