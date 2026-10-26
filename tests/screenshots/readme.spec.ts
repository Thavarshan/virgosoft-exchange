import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import {
    expect,
    test,
    type Browser,
    type Cookie,
    type Page,
} from '@playwright/test';
import { e2eEnvironment } from '../../playwright.config';

// Regenerate with `npm run screenshots` after changing the UI. Output goes to
// .github/screenshots/<device>-<theme>/ and is referenced by the README.

const DEMO_USER = { email: 'demo@example.com', password: 'password' };
const COUNTERPARTY = {
    email: 'counterparty@example.com',
    password: 'password',
};

type OrderInput = {
    symbol: 'BTC' | 'ETH';
    side: 'buy' | 'sell';
    price: string;
    amount: string;
};

test.describe.configure({ mode: 'serial' });

let outputDirectory = '';
let sessionCookies: Cookie[] | null = null;

function isMobile(): boolean {
    return test.info().project.name.startsWith('mobile');
}

async function signIn(page: Page, user: typeof DEMO_USER): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(user.email);
    await page.getByRole('textbox', { name: 'Password' }).fill(user.password);

    await Promise.all([
        page.waitForURL('**/dashboard'),
        page.getByRole('button', { name: 'Log in' }).click(),
    ]);
}

// Places an order through the real API from the signed-in browser, exactly as
// the trading form does, so the matching engine produces genuine trades.
async function placeOrder(page: Page, order: OrderInput): Promise<number> {
    const result = await page.evaluate(async (input) => {
        const token = decodeURIComponent(
            document.cookie
                .split('; ')
                .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                ?.split('=')[1] ?? '',
        );
        const response = await fetch('/api/orders', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': token,
            },
            body: JSON.stringify(input),
        });

        return { status: response.status, body: await response.json() };
    }, order);

    expect(result.status, JSON.stringify(result.body)).toBe(201);

    return result.body.order.id as number;
}

async function cancelOrder(page: Page, id: number): Promise<void> {
    const status = await page.evaluate(async (orderId) => {
        const token = decodeURIComponent(
            document.cookie
                .split('; ')
                .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                ?.split('=')[1] ?? '',
        );
        const response = await fetch(`/api/orders/${orderId}/cancel`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': token,
            },
        });

        return response.status;
    }, id);

    expect(status).toBe(200);
}

async function seedMarket(browser: Browser, baseURL: string): Promise<void> {
    const sellerContext = await browser.newContext({ baseURL });
    const buyerContext = await browser.newContext({ baseURL });
    const seller = await sellerContext.newPage();
    const buyer = await buyerContext.newPage();

    await signIn(seller, COUNTERPARTY);
    await signIn(buyer, DEMO_USER);

    // Resting asks from the counterparty, some of which the demo user lifts.
    await placeOrder(seller, {
        symbol: 'BTC',
        side: 'sell',
        price: '96500.00',
        amount: '0.50000000',
    });
    await placeOrder(seller, {
        symbol: 'BTC',
        side: 'sell',
        price: '97200.00',
        amount: '0.25000000',
    });
    await placeOrder(seller, {
        symbol: 'BTC',
        side: 'sell',
        price: '98000.00',
        amount: '0.10000000',
    });
    await placeOrder(seller, {
        symbol: 'ETH',
        side: 'sell',
        price: '3450.00',
        amount: '2.00000000',
    });
    await placeOrder(seller, {
        symbol: 'BTC',
        side: 'sell',
        price: '95000.00',
        amount: '0.05000000',
    });
    await placeOrder(seller, {
        symbol: 'ETH',
        side: 'sell',
        price: '3400.00',
        amount: '1.50000000',
    });

    // These two buys cross the asks above and settle immediately, at the
    // resting price, with the price improvement refunded.
    await placeOrder(buyer, {
        symbol: 'BTC',
        side: 'buy',
        price: '95500.00',
        amount: '0.05000000',
    });
    await placeOrder(buyer, {
        symbol: 'ETH',
        side: 'buy',
        price: '3400.00',
        amount: '1.50000000',
    });

    // Resting bids and one ask from the demo user, plus a cancelled order.
    await placeOrder(buyer, {
        symbol: 'BTC',
        side: 'buy',
        price: '94000.00',
        amount: '0.20000000',
    });
    await placeOrder(buyer, {
        symbol: 'BTC',
        side: 'buy',
        price: '93500.00',
        amount: '0.10000000',
    });
    await placeOrder(buyer, {
        symbol: 'ETH',
        side: 'buy',
        price: '3300.00',
        amount: '3.00000000',
    });
    await placeOrder(buyer, {
        symbol: 'BTC',
        side: 'sell',
        price: '99000.00',
        amount: '0.30000000',
    });
    const cancelled = await placeOrder(buyer, {
        symbol: 'BTC',
        side: 'buy',
        price: '92000.00',
        amount: '0.15000000',
    });
    await cancelOrder(buyer, cancelled);

    await sellerContext.close();
    await buyerContext.close();
}

test.beforeAll(async ({ browser }) => {
    const info = test.info();
    outputDirectory = path.join('.github', 'screenshots', info.project.name);
    mkdirSync(outputDirectory, { recursive: true });
    sessionCookies = null;

    // Two demo users plus a few historical trades for the 30-day chart.
    execFileSync('php', ['artisan', 'e2e:prepare', '--reset', '--activity'], {
        env: e2eEnvironment,
        stdio: 'inherit',
    });

    // Same starting funds as the deployed demo accounts, so the demo trades fit.
    execFileSync(
        'php',
        [
            'artisan',
            'tinker',
            '--execute',
            [
                "App\\Models\\User::query()->update(['balance' => '100000.00']);",
                "App\\Models\\Asset::query()->where('symbol', 'BTC')->update(['amount' => '10.00000000']);",
                "App\\Models\\Asset::query()->where('symbol', 'ETH')->update(['amount' => '100.00000000']);",
            ].join(' '),
        ],
        { env: e2eEnvironment, stdio: 'inherit' },
    );

    await seedMarket(browser, String(info.project.use.baseURL));
});

async function shoot(page: Page, name: string, fullPage = true): Promise<void> {
    await page.screenshot({
        path: path.join(outputDirectory, `${name}.png`),
        fullPage,
    });
}

async function waitForLive(page: Page): Promise<void> {
    // Shows "Live" when a Pusher-compatible server is reachable; otherwise the
    // capture simply carries on with the polling-only badge.
    await page
        .getByLabel('Realtime updates: Live')
        .waitFor({ timeout: 15_000 })
        .catch(() => undefined);
}

async function settle(page: Page): Promise<void> {
    await page.waitForLoadState('networkidle');
    // Let charts and skeletons finish rendering before the capture.
    await page.waitForTimeout(500);
}

test('login with the demo credentials popover open', async ({ page }) => {
    await page.goto('/login');
    await page.getByRole('button', { name: 'Show demo credentials' }).click();
    await expect(
        page.locator('[data-test="demo-accounts-popover"]'),
    ).toBeVisible();
    await settle(page);

    await shoot(page, '01-login');
});

test('register', async ({ page }) => {
    test.skip(isMobile(), 'Desktop only');

    await page.goto('/register');
    await expect(
        page.getByRole('button', { name: /Create account/ }),
    ).toBeVisible();
    await settle(page);

    await shoot(page, '02-register');
});

test('forgot password', async ({ page }) => {
    test.skip(isMobile(), 'Desktop only');

    await page.goto('/forgot-password');
    await expect(page.getByLabel('Email address')).toBeVisible();
    await settle(page);

    await shoot(page, '03-forgot-password');
});

test.describe('signed in', () => {
    test.beforeEach(async ({ page }) => {
        // Sign in once per project and reuse the session, so the capture
        // never trips the login rate limit.
        if (sessionCookies === null) {
            await signIn(page, DEMO_USER);
            sessionCookies = await page.context().cookies();

            return;
        }

        await page.context().addCookies(sessionCookies);
    });

    test('dashboard', async ({ page }) => {
        await page.goto('/dashboard');
        await expect(
            page.getByRole('heading', { name: 'Dashboard' }),
        ).toBeVisible();
        await page.getByRole('combobox', { name: 'Activity period' }).click();
        await page.getByRole('option', { name: '30 days' }).click();
        await waitForLive(page);
        await settle(page);
        await expect(page.getByText('Dashboard unavailable')).toHaveCount(0);

        await shoot(page, '04-dashboard');
    });

    test('limit order with a live preview', async ({ page }) => {
        await page.goto('/trade');
        await expect(
            page.getByRole('heading', { name: 'Limit Order', exact: true }),
        ).toBeVisible();
        await page.getByLabel('Limit price (USD)').fill('95000.00');
        await page.getByLabel(/Amount \(/).fill('0.02500000');
        await waitForLive(page);
        await settle(page);

        await shoot(page, '05-limit-order');
    });

    test('orders and wallet', async ({ page }) => {
        await page.goto('/wallet');
        await expect(
            page.getByRole('heading', { name: 'Orders & Wallet', exact: true }),
        ).toBeVisible();
        await expect(
            page.getByText('Order history', { exact: true }).first(),
        ).toBeVisible();
        await waitForLive(page);
        await settle(page);

        // The phone layout stacks every order card, so keep that one to a
        // single screen instead of a very tall image.
        await shoot(page, '06-orders-and-wallet', !isMobile());
    });

    test('settings: profile', async ({ page }) => {
        test.skip(isMobile(), 'Desktop only');

        await page.goto('/settings/profile');
        await expect(page.getByLabel('Email address')).toBeVisible();
        await settle(page);

        await shoot(page, '07-settings-profile');
    });

    test('settings: security', async ({ page }) => {
        test.skip(isMobile(), 'Desktop only');

        await page.goto('/settings/security');
        await settle(page);

        await shoot(page, '08-settings-security');
    });

    test('settings: appearance', async ({ page }) => {
        test.skip(isMobile(), 'Desktop only');

        await page.goto('/settings/appearance');
        await settle(page);

        await shoot(page, '09-settings-appearance');
    });
});
