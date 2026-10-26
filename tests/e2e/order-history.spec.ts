import {
    DEMO_USER,
    expect,
    login,
    openWallet,
    seedOrderHistory,
    test,
} from './fixtures';

async function selectFilter(
    page: Parameters<typeof login>[0],
    label: string,
    option: string,
): Promise<void> {
    await page.getByLabel(label).click();
    await page.getByRole('option', { name: option, exact: true }).click();
}

test('filters owned order history without changing the orderbook market', async ({
    page,
}) => {
    seedOrderHistory();
    await login(page, DEMO_USER);
    await openWallet(page);

    await selectFilter(page, 'Market filter', 'ETH / USD');
    await selectFilter(page, 'Side filter', 'Buy');
    await selectFilter(page, 'Status filter', 'Filled');

    const historyTable = page.locator('[data-test="order-history-table"]');
    await expect(historyTable.locator('tbody tr')).toHaveCount(1);
    await expect(historyTable).toContainText('ETH');
    await expect(historyTable).toContainText('Buy');
    await expect(historyTable).toContainText('Filled');
    await expect(page.getByLabel('Orderbook market')).toContainText(
        'BTC / USD',
    );

    await page.getByRole('button', { name: 'Clear filters' }).click();
    await expect(page.getByText('23 total', { exact: true })).toBeVisible();

    await selectFilter(page, 'Market filter', 'BTC / USD');
    await selectFilter(page, 'Side filter', 'Sell');
    await selectFilter(page, 'Status filter', 'Open');
    await expect(
        page.getByText('21 matching orders', { exact: true }),
    ).toBeVisible();
    await expect(historyTable.locator('tbody tr')).toHaveCount(20);

    await page
        .locator('[data-slot="pagination-link"]')
        .filter({ hasText: '2' })
        .click();
    await expect(historyTable.locator('tbody tr')).toHaveCount(1);
    await expect(
        page.getByText('21 matching orders', { exact: true }),
    ).toBeVisible();
});
