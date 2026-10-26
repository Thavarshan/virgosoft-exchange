import type { Browser } from '@playwright/test';
import {
    COUNTERPARTY_USER,
    disconnectRealtime,
    DEMO_USER,
    expect,
    login,
    openTrade,
    openWallet,
    reconnectRealtime,
    selectMarket,
    seedDashboardActivity,
    submitOrder,
    test,
    waitForLive,
} from './fixtures';

test('users can log in, reach the dashboard, and log out', async ({ page }) => {
    await login(page, DEMO_USER);

    await page.getByRole('button', { name: /Demo Trader/ }).click();
    await page.getByRole('menuitem', { name: 'Log out' }).click();
    await page.waitForURL('**/login');

    await page.goto('/dashboard');
    await page.waitForURL('**/login');
    await expect(page.getByRole('button', { name: 'Log in' })).toBeVisible();
});

test('a buy order reserves the exact USD cost and fee', async ({ page }) => {
    await login(page, DEMO_USER);
    await openTrade(page);

    const response = await submitOrder(page, {
        side: 'buy',
        price: '90000.00',
        amount: '0.01000000',
    });

    expect(response.status()).toBe(201);
    await expect(page.getByText('Order placed', { exact: true })).toBeVisible();

    await openWallet(page);
    await expect(page.getByText('86.50', { exact: true })).toBeVisible();
    await expect(
        page.getByText(/Available USD · Locked 913\.50/),
    ).toBeVisible();
});

test('a sell order reserves the exact asset quantity', async ({ page }) => {
    await login(page, DEMO_USER);
    await openTrade(page);

    const response = await submitOrder(page, {
        side: 'sell',
        price: '95000.00',
        amount: '0.01000000',
    });

    expect(response.status()).toBe(201);
    await expect(page.getByText('Order placed', { exact: true })).toBeVisible();

    await openWallet(page);
    await expect(page.getByText('0.99000000', { exact: true })).toBeVisible();
    await expect(
        page.getByText(/Available BTC · Locked 0\.01000000/),
    ).toBeVisible();
});

test('cancellation releases an order reservation exactly once', async ({
    page,
}) => {
    await login(page, DEMO_USER);
    await openTrade(page);

    const response = await submitOrder(page, {
        side: 'buy',
        price: '90000.00',
        amount: '0.01000000',
    });

    expect(response.status()).toBe(201);
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
    await expect(page.getByText('1000.00', { exact: true })).toBeVisible();
    await expect(page.getByText(/Available USD · Locked 0\.00/)).toBeVisible();
});

test('invalid precision and insufficient funds are shown without an order', async ({
    page,
}) => {
    await login(page, DEMO_USER);
    await openTrade(page);

    await page.getByLabel('Limit price (USD)').fill('1.001');
    await page.getByLabel(/Amount \(/).fill('0.010000000');
    await page.getByRole('button', { name: 'Place buy order' }).click();
    await expect(page.locator('#price-error')).toContainText(
        'format is invalid',
    );
    await expect(page.locator('#amount-error')).toContainText(
        'format is invalid',
    );

    await page.getByLabel('Limit price (USD)').fill('100000.00');
    await page.getByLabel(/Amount \(/).fill('1.00000000');
    await page.getByRole('button', { name: 'Place buy order' }).click();
    await expect(
        page.getByText('Insufficient available balance.'),
    ).toBeVisible();

    await openWallet(page);
    await expect(
        page.getByText('No orders yet', { exact: true }),
    ).toBeVisible();
});

test('switching markets never shows rows from the previous market', async ({
    page,
}) => {
    await login(page, DEMO_USER);
    await openTrade(page);
    expect(
        (
            await submitOrder(page, {
                side: 'buy',
                price: '100.00',
                amount: '0.01000000',
            })
        ).status(),
    ).toBe(201);

    expect(
        (
            await submitOrder(page, {
                side: 'buy',
                price: '200.00',
                amount: '0.01000000',
                symbol: 'ETH',
            })
        ).status(),
    ).toBe(201);

    await openWallet(page);
    await expect(
        page.locator('[data-test="orderbook-panel"]').getByText('100.00', {
            exact: true,
        }),
    ).toBeVisible();

    let releaseEthResponse: () => void = () => {};
    const ethResponseHeld = new Promise<void>((resolve) => {
        releaseEthResponse = resolve;
    });
    const ethRequest = page.waitForRequest((request) => {
        const url = new URL(request.url());

        return (
            url.pathname === '/api/orders' &&
            url.searchParams.get('symbol') === 'ETH'
        );
    });

    await page.route('**/api/orders**', async (route) => {
        const symbol = new URL(route.request().url()).searchParams.get(
            'symbol',
        );

        if (symbol !== 'ETH') {
            await route.continue();

            return;
        }

        await ethResponseHeld;
        await route.continue();
    });

    await selectMarket(page, 'ETH');
    await ethRequest;
    await expect(
        page.locator('[data-test="orderbook-panel"]').getByText('100.00', {
            exact: true,
        }),
    ).toHaveCount(0);
    releaseEthResponse();
    await expect(
        page.locator('[data-test="orderbook-panel"]').getByText('200.00', {
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page.locator('[data-test="orderbook-panel"]').getByText('100.00', {
            exact: true,
        }),
    ).toHaveCount(0);
    await page.unroute('**/api/orders**');
});

async function createRealtimeMatch(browser: Browser): Promise<{
    buyerContext: Awaited<ReturnType<typeof browser.newContext>>;
    buyerWallet: Awaited<ReturnType<typeof browser.newPage>>;
    sellerContext: Awaited<ReturnType<typeof browser.newContext>>;
    sellerWallet: Awaited<ReturnType<typeof browser.newPage>>;
}> {
    const sellerContext = await browser.newContext();
    const buyerContext = await browser.newContext();
    const sellerTrade = await sellerContext.newPage();
    const buyerTrade = await buyerContext.newPage();
    const sellerWallet = await sellerContext.newPage();
    const buyerWallet = await buyerContext.newPage();

    await login(sellerTrade, COUNTERPARTY_USER);
    await login(buyerTrade, DEMO_USER);
    await openTrade(sellerTrade);
    await openTrade(buyerTrade);
    await openWallet(sellerWallet);
    await openWallet(buyerWallet);
    await Promise.all([
        waitForLive(sellerWallet, 30_000),
        waitForLive(buyerWallet, 30_000),
    ]);
    expect(
        (
            await submitOrder(sellerTrade, {
                side: 'sell',
                price: '95000.00',
                amount: '0.01000000',
            })
        ).status(),
    ).toBe(201);
    expect(
        (
            await submitOrder(buyerTrade, {
                side: 'buy',
                price: '95000.00',
                amount: '0.01000000',
            })
        ).status(),
    ).toBe(201);

    await Promise.all([
        expect(
            sellerWallet.getByText('Order matched', { exact: true }),
        ).toBeVisible(),
        expect(
            buyerWallet.getByText('Order matched', { exact: true }),
        ).toBeVisible(),
    ]);

    return { buyerContext, buyerWallet, sellerContext, sellerWallet };
}

test('two users receive a realtime full match with settled balances', async ({
    browser,
}) => {
    const { buyerContext, buyerWallet, sellerContext, sellerWallet } =
        await createRealtimeMatch(browser);

    await Promise.all([
        expect(buyerWallet.getByText('35.75', { exact: true })).toBeVisible(),
        expect(
            buyerWallet.getByText('1.01000000', { exact: true }),
        ).toBeVisible(),
        expect(
            sellerWallet.getByText('1950.00', { exact: true }),
        ).toBeVisible(),
        expect(
            sellerWallet.getByText('0.99000000', { exact: true }),
        ).toBeVisible(),
        expect(
            buyerWallet.getByRole('table').getByText('Filled', { exact: true }),
        ).toBeVisible(),
        expect(
            sellerWallet.getByRole('table').getByText('Filled', {
                exact: true,
            }),
        ).toBeVisible(),
    ]);

    await buyerContext.close();
    await sellerContext.close();
});

test('a disconnected buyer catches up after its private channel reconnects', async ({
    browser,
}) => {
    const sellerContext = await browser.newContext();
    const buyerContext = await browser.newContext();
    const sellerTrade = await sellerContext.newPage();
    const buyerTrade = await buyerContext.newPage();
    const sellerWallet = await sellerContext.newPage();
    const buyerWallet = await buyerContext.newPage();

    await login(sellerTrade, COUNTERPARTY_USER);
    await login(buyerTrade, DEMO_USER);
    await openTrade(sellerTrade);
    await openTrade(buyerTrade);
    await openWallet(sellerWallet);
    await openWallet(buyerWallet);
    await Promise.all([
        waitForLive(sellerWallet, 30_000),
        waitForLive(buyerWallet, 30_000),
    ]);
    expect(
        (
            await submitOrder(buyerTrade, {
                side: 'buy',
                price: '95000.00',
                amount: '0.01000000',
            })
        ).status(),
    ).toBe(201);

    await disconnectRealtime(buyerWallet);
    await expect(
        buyerWallet
            .getByRole('button', { name: 'Explain connection status' })
            .locator('[aria-live="polite"]'),
    ).toHaveAttribute('aria-label', /unavailable|refresh/i);

    expect(
        (
            await submitOrder(sellerTrade, {
                side: 'sell',
                price: '95000.00',
                amount: '0.01000000',
            })
        ).status(),
    ).toBe(201);
    await expect(
        sellerWallet.getByText('Order matched', { exact: true }),
    ).toBeVisible();

    await reconnectRealtime(buyerWallet);
    await waitForLive(buyerWallet, 30_000);
    await expect(buyerWallet.getByText('35.75', { exact: true })).toBeVisible();
    await expect(
        buyerWallet.getByRole('table').getByText('Filled', { exact: true }),
    ).toBeVisible();

    await buyerContext.close();
    await sellerContext.close();
});

test('the dashboard period switch and chart geometry reflect executions', async ({
    browser,
}) => {
    seedDashboardActivity();

    const { buyerContext, buyerWallet, sellerContext } =
        await createRealtimeMatch(browser);

    await buyerWallet.goto('/dashboard');
    await expect(buyerWallet.getByText('Executed trade value')).toBeVisible();
    await expect(
        buyerWallet.getByRole('paragraph').filter({ hasText: '950.00 USD' }),
    ).toBeVisible();

    await buyerWallet
        .getByRole('combobox', { name: 'Activity period' })
        .click();
    await buyerWallet.getByRole('option', { name: '30 days' }).click();
    await expect(
        buyerWallet.locator('[data-test="activity-column"]'),
    ).toHaveCount(30);

    const chart = buyerWallet.getByRole('img', {
        name: 'Executed trade value for the last 30 days',
    });
    const bars = chart.locator('[data-test="activity-bar"]');
    await expect(chart).toBeVisible();
    await expect(bars).toHaveCount(30);
    const heights = await bars.evaluateAll((elements) =>
        elements.map((element) => element.getBoundingClientRect().height),
    );
    const baselines = await bars.evaluateAll((elements) =>
        elements.map((element) =>
            Math.round(element.getBoundingClientRect().bottom),
        ),
    );

    expect(heights[0]).toBeGreaterThan(0);
    expect(heights[1]).toBeGreaterThan(0);
    expect(heights[29]).toBeGreaterThan(0);
    expect(heights.some((height) => height === 0)).toBe(true);
    expect(new Set(baselines).size).toBe(1);

    await buyerContext.close();
    await sellerContext.close();
});
