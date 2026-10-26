import {
    test,
    expect,
    DEMO_USER,
    login,
    openTrade,
    openWallet,
    submitOrder,
} from './fixtures';

test('mobile navigation, order entry, wallet, and cancellation dialog work', async ({
    page,
}) => {
    await login(page, DEMO_USER);

    await page.getByRole('button', { name: 'Toggle sidebar' }).click();
    await page.getByRole('link', { name: 'Limit Order' }).click();
    await openTrade(page);

    expect(
        (
            await submitOrder(page, {
                side: 'buy',
                price: '90000.00',
                amount: '0.01000000',
            })
        ).status(),
    ).toBe(201);

    await page.getByRole('button', { name: 'Toggle sidebar' }).click();
    await page.getByRole('link', { name: 'Orders & Wallet' }).click();
    await openWallet(page);

    await page
        .locator('button:visible')
        .filter({ hasText: 'Cancel' })
        .first()
        .click();
    await expect(page.getByRole('dialog')).toContainText('Cancel this order?');
    await page.getByRole('button', { name: 'Confirm cancellation' }).click();
    await expect(
        page.getByText('Order cancelled', { exact: true }),
    ).toBeVisible();
});
