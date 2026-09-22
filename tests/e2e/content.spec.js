import { test, expect } from '@playwright/test';
import { ADMIN, AUTO } from './helpers.js';

test('the landing page leads to the catalog through categories and the oil finder', async ({ page }) => {
    await page.goto(`${AUTO}/`);
    await expect(page.getByRole('heading', { name: 'The right oil for every engine' })).toBeVisible();
    await expect(page.locator('.product-card')).toHaveCount(4);

    await page.locator('#finder-value').selectOption('5W-30');
    await page.getByRole('button', { name: 'Show matching products' }).click();
    await expect(page).toHaveURL(/\/catalog\?f%5Bsae_viscosity%5D=5W-30/);
    await expect(page.getByText('3 products')).toBeVisible();

    await page.goto(`${AUTO}/`);
    await page.locator('.category-tile').first().click();
    await expect(page.getByRole('heading', { name: 'Engine oil', level: 1 })).toBeVisible();
});

test('the contact form validates in the browser and sends with AJAX', async ({ page }) => {
    await page.goto(`${AUTO}/contact?subject=business`);
    await expect(page.locator('#contact-subject')).toHaveValue('business');

    await page.getByRole('button', { name: 'Send message' }).click();
    await expect(page.locator('#contact-name')).toHaveClass(/is-invalid/);
    await expect(page.locator('#contact-summary')).toContainText('Name');

    await page.locator('#contact-name').fill('Kees');
    await page.locator('#contact-email').fill('kees@example.test');
    await page.locator('#contact-order').fill('12');
    await page.locator('#contact-message').fill('Do you have volume prices for 208 L drums?');
    await page.getByRole('button', { name: 'Send message' }).click();
    await expect(page.getByText('An order number looks like AUTO-000123.')).toBeVisible();

    await page.locator('#contact-order').fill('');
    await page.getByRole('button', { name: 'Send message' }).click();
    await expect(page.getByText('Thank you. We have received your message')).toBeVisible();
    await expect(page.locator('#contact-form')).toBeHidden();
});

test('FAQ answers open in an accordion and the SDS list filters', async ({ page }) => {
    await page.goto(`${AUTO}/faq`);
    await page.getByRole('button', { name: 'When is shipping free?' }).click();
    await expect(page.getByText('PostNL Standard is free from €100 incl. VAT')).toBeVisible();

    await page.goto(`${AUTO}/safety-data-sheets`);
    const items = page.locator('[data-sds-item]:visible');
    const all = await items.count();
    await page.getByPlaceholder('Filter by product').fill('coolant');
    await expect(items).toHaveCount(1);
    expect(all).toBeGreaterThan(1);
});

test('a manager changes the shop colour and sees the contact message', async ({ page }) => {
    await page.goto(`${ADMIN}/login`);
    await page.getByLabel('Email').fill('manager@myoils.test');
    await page.getByLabel('Password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.getByRole('combobox', { name: 'Store' }).click();
    await page.getByRole('option', { name: "MyOil's Auto" }).click();

    await page.goto(`${ADMIN}/customers?tab=messages`);
    await expect(page.getByText('Do you have volume prices for 208 L drums?')).toBeVisible();

    await page.goto(`${ADMIN}/settings`);
    await page.locator('#store-accent').fill('#E11D48');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Saved.')).toBeVisible();

    await page.goto(`${AUTO}/`);
    const accent = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--brand-accent').trim());
    expect(accent.toUpperCase()).toBe('#E11D48');

    // Put the demo colour back.
    await page.goto(`${ADMIN}/settings`);
    await page.locator('#store-accent').fill('#F2A900');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Saved.')).toBeVisible();
});
