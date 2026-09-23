import { test, expect } from '@playwright/test';
import { ADMIN, AUTO } from './helpers.js';

test('a guest adds to the cart, applies a coupon and checks out', async ({ page }) => {
    await page.goto(`${AUTO}/p/synth-pro-5w-30`);
    await page.getByRole('radiogroup', { name: 'Pack size' }).getByRole('radio', { name: /5 L/ }).click();
    await page.getByRole('button', { name: 'Add to cart' }).click();

    // The mini-cart drawer opens with the line and the header badge counts it.
    const drawer = page.locator('.p-drawer');
    await expect(drawer.getByText("MyOil's Synth Pro 5W-30")).toBeVisible();
    await expect(page.getByTestId('cart-count')).toHaveText('1');
    await drawer.locator('.p-inputnumber-increment-button').first().click();
    await expect(page.getByTestId('cart-count')).toHaveText('2');
    await drawer.getByRole('link', { name: 'View cart' }).click();

    await expect(page.getByRole('heading', { name: 'Your cart' })).toBeVisible();
    await page.getByLabel('Coupon code').fill('nope');
    await page.getByRole('button', { name: 'Apply' }).click();
    await expect(page.getByText('This code is not valid.')).toBeVisible();
    await page.getByLabel('Coupon code').fill('welcome10');
    await page.getByRole('button', { name: 'Apply' }).click();
    await expect(page.getByText('Discount (WELCOME10)')).toBeVisible();
    await expect(page.getByTestId('cart-total')).toHaveText('€96.89');

    await page.getByRole('link', { name: 'Checkout' }).click();
    await expect(page.getByRole('heading', { name: 'Checkout', level: 1 })).toBeVisible();

    // Step 1: the email is required for guests.
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page.getByText('Enter your email address.')).toBeVisible();
    await page.getByLabel('Email').fill('piet@example.test');
    await page.getByRole('button', { name: 'Continue' }).click();

    // Step 2: addresses; the server checks the Dutch postcode.
    await page.locator('#billing-firstName').fill('Piet');
    await page.locator('#billing-lastName').fill('Jansen');
    await page.locator('#billing-street').fill('Damrak');
    await page.locator('#billing-houseNumber').fill('1');
    await page.locator('#billing-postcode').fill('12');
    await page.locator('#billing-city').fill('Amsterdam');
    await page.getByRole('button', { name: 'Continue' }).click();

    // Step 3: shipping options for the Netherlands; DHL Europe does not deliver here.
    const shipping = page.getByRole('radiogroup', { name: 'Shipping' });
    await expect(shipping.getByText('PostNL Standard')).toBeVisible();
    await expect(shipping.getByText('Not available for this address')).toBeVisible();
    await page.getByRole('button', { name: 'Continue' }).click();

    // Step 4: review. The server rejects the postcode and the wizard jumps back to the addresses.
    await page.getByText('I accept the terms and conditions').click();
    await page.getByTestId('place-order').click();
    await expect(page.getByText('Enter a Dutch postcode like 1012 AB.').first()).toBeVisible();
    await page.locator('#billing-postcode').fill('1012 LG');
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page.getByTestId('place-order')).toHaveText(/Pay €96.89/);
    await page.getByTestId('place-order').click();

    // The fake payment provider page, then back to the confirmation.
    await expect(page.getByRole('heading', { name: 'Test payment' })).toBeVisible();
    await expect(page.getByTestId('fake-amount')).toHaveText('€96.89');
    await page.getByRole('button', { name: 'Pay now' }).click();
    await expect(page.getByRole('heading', { name: 'Thank you for your order!' })).toBeVisible();
    await expect(page.getByText(/Order AUTO-\d{6}/)).toBeVisible();
    await expect(page.getByTestId('order-total')).toHaveText('€96.89');
    await expect(page.getByTestId('cart-count')).toHaveCount(0);

    // The signed webhook is processed by the worker; the page picks up the new status.
    await expect(page.getByText('Payment received. We are preparing your order.')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByText('Payment received', { exact: true })).toBeVisible();
    const orderNumber = (await page.getByText(/Order AUTO-\d{6}/).first().textContent())?.replace('Order ', '').trim();

    // Staff pick it up in the admin: prepare, ship, deliver.
    await page.goto(`${ADMIN}/login`);
    await page.getByLabel('Email').fill('manager@myoils.test');
    await page.getByLabel('Password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.getByRole('combobox', { name: 'Store' }).click();
    await page.getByRole('option', { name: "MyOil's Auto" }).click();
    await expect(page.getByTestId('kpi-to_fulfil')).toBeVisible();
    await page.goto(`${ADMIN}/orders?state=paid`);
    await page.getByRole('link', { name: orderNumber }).click();
    for (const [action, label] of [['Start processing', 'Being prepared'], ['Mark as shipped', 'Shipped'], ['Mark as delivered', 'Delivered']]) {
        await page.getByTestId('order-actions').getByRole('button', { name: action }).click();
        await page.getByTestId('confirm-transition').click();
        await expect(page.getByTestId('order-actions').locator('xpath=..').getByText(label, { exact: true }).first()).toBeVisible();
    }
    await expect(page.getByTestId('order-actions').getByRole('button', { name: 'Refund' })).toBeVisible();
});

test('a failed payment can be tried again, and an unpaid order cancelled', async ({ page }) => {
    await page.goto(`${AUTO}/p/longlife-0w-20`);
    await page.getByRole('button', { name: 'Add to cart' }).click();
    await page.goto(`${AUTO}/checkout`);
    await page.getByLabel('Email').fill('fail@example.test');
    await page.getByRole('button', { name: 'Continue' }).click();
    for (const [field, value] of [['firstName', 'Els'], ['lastName', 'Smit'], ['street', 'Kade'], ['houseNumber', '3'], ['postcode', '3011 AA'], ['city', 'Rotterdam']]) {
        await page.locator(`#billing-${field}`).fill(value);
    }
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.getByText('I accept the terms and conditions').click();
    await page.getByTestId('place-order').click();

    await page.getByRole('button', { name: 'Simulate a failed payment' }).click();
    await expect(page.getByText('The payment did not go through.')).toBeVisible({ timeout: 20_000 });
    await page.getByTestId('pay-again').click();
    await expect(page.getByRole('heading', { name: 'Test payment' })).toBeVisible();
    await page.getByRole('link', { name: 'Back to the shop' }).click();

    await page.getByRole('button', { name: 'Cancel order' }).click();
    await page.getByRole('button', { name: 'Cancel order' }).last().click();
    await expect(page.getByRole('heading', { name: 'This order has been cancelled' })).toBeVisible();
});

test('a customer registers with one address and manages the address book', async ({ page }) => {
    const email = `e2e-${Date.now()}@example.test`;
    await page.goto(`${AUTO}/register`);
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill('secret-pass');
    await page.locator('#registration_firstName').fill('Eva');
    await page.locator('#registration_lastName').fill('Visser');
    // Delivery fields stay hidden while "Deliver to the billing address" is ticked.
    await expect(page.locator('#delivery-section')).toBeHidden();
    await page.locator('#registration_billing_firstName').fill('Eva');
    await page.locator('#registration_billing_lastName').fill('Visser');
    await page.locator('#registration_billing_street').fill('Kade');
    await page.locator('#registration_billing_houseNumber').fill('3');
    await page.locator('#registration_billing_postcode').fill('3011 AA');
    await page.locator('#registration_billing_city').fill('Rotterdam');
    await page.getByLabel('I accept the terms and conditions').check();
    await page.getByRole('button', { name: 'Create account' }).click();

    await expect(page).toHaveURL(/\/account$/);
    await expect(page.getByRole('heading', { name: 'Hello, Eva' })).toBeVisible();
    await page.getByRole('link', { name: 'Addresses' }).click();
    await expect(page).toHaveURL(/\/account\/addresses$/);
    await expect(page.getByTestId('address-card')).toHaveCount(1);

    // The only address cannot be deleted (it is the last billing and delivery address).
    await page.getByRole('button', { name: 'Delete address' }).click();
    await page.getByRole('button', { name: 'Delete address' }).last().click();
    await expect(page.getByText('At least one billing address is required.')).toBeVisible();

    await page.getByRole('link', { name: 'Profile & security' }).click();
    await page.getByRole('button', { name: 'Log out' }).click();
    await expect(page).toHaveURL(`${AUTO}/`);
});
