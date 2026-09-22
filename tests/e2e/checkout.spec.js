import { test, expect } from '@playwright/test';
import { AUTO } from './helpers.js';

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
    await expect(page.getByText('Awaiting payment')).toBeVisible();
    await expect(page.getByTestId('order-total')).toHaveText('€96.89');
    await expect(page.getByTestId('cart-count')).toHaveCount(0);
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
    await expect(page).toHaveURL(/\/catalog$/);
});
