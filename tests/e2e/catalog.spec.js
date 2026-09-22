import { test, expect } from '@playwright/test';
import { ADMIN, AUTO, INDUSTRIE } from './helpers.js';

test('shoppers filter a category and the filters stay in the address', async ({ page }) => {
    await page.goto(`${AUTO}/c/engine-oil`);
    await expect(page.getByRole('heading', { name: 'Engine oil', level: 1 })).toBeVisible();
    await expect(page.getByText('6 products')).toBeVisible();

    await page.locator('#f-sae_viscosity-5W-30').click();
    await expect(page.getByText('3 products')).toBeVisible();
    await expect(page).toHaveURL(/f%5Bsae_viscosity%5D=5W-30/);

    await page.getByRole('button', { name: '208 L', exact: true }).click();
    await expect(page.getByText('1 product', { exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: "MyOil's Synth Pro 5W-30" }).first()).toBeVisible();

    // Reloading the address gives the same result; clearing everything restores the category.
    await page.reload();
    await expect(page.getByText('1 product', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Clear all' }).click();
    await expect(page.getByText('6 products')).toBeVisible();
});

test('the product page switches pack sizes with gross, net and per-litre prices', async ({ page }) => {
    await page.goto(`${AUTO}/p/synth-pro-5w-30`);
    await expect(page.getByRole('heading', { name: "MyOil's Synth Pro 5W-30", level: 1 })).toBeVisible();

    const packs = page.getByRole('radiogroup', { name: 'Pack size' });
    await packs.getByRole('radio', { name: /5 L/ }).click();
    await expect(page.getByText('€49.95').first()).toBeVisible();
    await expect(page.getByText('€41.28 excl. VAT')).toBeVisible();
    await expect(page.getByText('€9.99 per litre')).toBeVisible();

    await packs.getByRole('radio', { name: /208 L/ }).click();
    await expect(page.getByText('€7.16 per litre')).toBeVisible();
    await expect(page.getByText(/Only \d+ left/)).toBeVisible();

    await page.getByRole('tab', { name: 'Specifications' }).click();
    await expect(page.getByText('VW 504.00/507.00, MB 229.51')).toBeVisible();

    await page.getByRole('button', { name: 'Add to cart' }).click();
    await expect(page.getByText('The cart arrives in phase 5.')).toBeVisible();
});

test('a product of another store is not found', async ({ page }) => {
    const response = await page.goto(`${INDUSTRIE}/p/synth-pro-5w-30`);
    expect(response?.status()).toBe(404);
});

test('staff edit a price in the admin and the shop shows it', async ({ page }) => {
    await page.goto(`${ADMIN}/login`);
    await page.getByLabel('Email').fill('manager@myoils.test');
    await page.getByLabel('Password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.getByRole('combobox', { name: 'Store' }).click();
    await page.getByRole('option', { name: "MyOil's Auto" }).click();

    await page.goto(`${ADMIN}/catalog/products`);
    await page.getByRole('link', { name: "MyOil's Coolant G12++" }).click();
    await page.getByRole('tab', { name: 'Pack sizes & stock' }).click();
    const price = page.getByRole('textbox', { name: 'Net price' }).first();
    const original = await price.inputValue();

    // A wrong amount is shown on the field, on the tab and in a toast.
    await price.fill('abc');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Enter an amount like 41.28 (net, without VAT).')).toBeVisible();

    await price.fill('8.26');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Saved.')).toBeVisible();

    const shop = await page.context().newPage();
    await shop.goto(`${AUTO}/p/coolant-g12-plus-plus`);
    await expect(shop.getByText('€9.99').first()).toBeVisible();
    await shop.close();

    // Put the demo price back.
    await price.fill(original);
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Saved.').first()).toBeVisible();
});
