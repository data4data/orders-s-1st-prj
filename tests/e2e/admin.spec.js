import { test, expect } from '@playwright/test';
import { ADMIN } from './helpers.js';

test('staff log in on the Twig page and use the store switcher in the admin SPA', async ({ page }) => {
    await page.goto(`${ADMIN}/orders`);
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole('heading', { name: 'Log in to the admin' })).toBeVisible();

    await page.getByLabel('Email').fill('manager@myoils.test');
    await page.getByLabel('Password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();

    // Back to the page that was requested before the login.
    await expect(page).toHaveURL(/\/orders$/);
    await expect(page.getByText('Pick a store to start')).toBeVisible();

    await page.getByRole('link', { name: 'Dashboard' }).click();
    await expect(page.getByText('Pick a store to start')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Platform' })).toHaveCount(0);

    await page.getByRole('combobox', { name: 'Store' }).click();
    await expect(page.getByRole('option')).toHaveCount(2);
    await page.getByRole('option', { name: "MyOil's Auto" }).click();
    await expect(page.getByText("Now working in MyOil's Auto.")).toBeVisible();

    await page.getByRole('link', { name: 'Orders' }).click();
    await expect(page.getByRole('heading', { name: 'Orders' })).toBeVisible();

    await page.goto(`${ADMIN}/does-not-exist`);
    await expect(page.getByText('This admin page does not exist')).toBeVisible();
});

test('the admin router asks before leaving unsaved changes, and staff can log out', async ({ page }) => {
    await page.goto(`${ADMIN}/login`);
    await page.getByLabel('Email').fill('manager@myoils.test');
    await page.getByLabel('Password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    const sidebar = page.locator('aside');

    await sidebar.getByRole('link', { name: 'UI kit (dev)' }).click();
    await page.locator('#uikit-name').fill('Jan');
    await sidebar.getByRole('link', { name: 'Dashboard' }).click();
    await expect(page.getByText('Leave without saving?')).toBeVisible();
    await page.getByRole('button', { name: 'Stay on page' }).click();
    await expect(page).toHaveURL(/\/ui-kit$/);

    // With the form clean again, navigation and logout happen without questions.
    await page.locator('#uikit-name').fill('');
    await page.getByRole('button', { name: 'Account menu' }).click();
    await page.getByRole('menuitem', { name: 'Log out' }).click();
    await expect(page).toHaveURL(/\/login$/);

    await page.goto(`${ADMIN}/`);
    await expect(page).toHaveURL(/\/login$/);
});

test('super-admins see Platform and the read-only "All stores" view', async ({ page }) => {
    await page.goto(`${ADMIN}/login`);
    await page.getByLabel('Email').fill('admin@myoils.test');
    await page.getByLabel('Password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();

    await expect(page.getByRole('link', { name: 'Platform' })).toBeVisible();
    await page.getByRole('combobox', { name: 'Store' }).click();
    await expect(page.getByRole('option')).toHaveCount(4);
    await page.getByRole('option', { name: 'All stores (read-only)' }).click();
    await expect(page.getByText('Viewing all stores. Changes are disabled.')).toBeVisible();
    await expect(page.getByText('Read-only', { exact: true })).toBeVisible();
});
