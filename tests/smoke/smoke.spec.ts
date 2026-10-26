import {
    expect,
    test,
    type APIRequestContext,
    type Page,
} from '@playwright/test';
import process from 'node:process';

function required(name: string): string {
    const value = process.env[name];

    if (!value) {
        throw new Error(`${name} is required to run the smoke checks.`);
    }

    return value;
}

const baseURL = required('SMOKE_BASE_URL');
const credentials = {
    email: required('SMOKE_USER_EMAIL'),
    password: required('SMOKE_USER_PASSWORD'),
};
// Set to "false" for a deployment that intentionally has no Pusher.
const requireRealtime = process.env.SMOKE_REQUIRE_REALTIME !== 'false';

// A far-from-market buy with a unique amount can never match a real order,
// costs a few cents to reserve, and is cancelled again before the run ends.
const order = {
    price: '0.01',
    amount: `0.00${String(Date.now()).slice(-6)}`,
};

type Profile = { user: { balance: string; locked_balance: string } };

async function waitUntilHealthy(request: APIRequestContext): Promise<void> {
    await expect
        .poll(async () => (await request.get('/up')).status(), {
            // A scaled-to-zero environment can take a while to wake up.
            timeout: 120_000,
            intervals: [2_000, 5_000],
        })
        .toBe(200);
}

// Sanctum serves cookie-authenticated API calls only to requests that look
// like the SPA, meaning they carry an Origin or Referer from a stateful
// domain. Browser XHR always does; Playwright's request context does not.
function apiGet(page: Page, path: string) {
    return page.request.get(path, {
        headers: { Accept: 'application/json', Referer: `${baseURL}/` },
    });
}

async function profile(page: Page): Promise<Profile['user']> {
    const response = await apiGet(page, '/api/profile');
    expect(response.status()).toBe(200);

    return ((await response.json()) as Profile).user;
}

test.describe.configure({ mode: 'serial' });

test.describe('deployed application', () => {
    let page: Page;

    test.beforeAll(async ({ browser }) => {
        const context = await browser.newContext({ baseURL });
        page = await context.newPage();

        await waitUntilHealthy(context.request);
    });

    test.afterEach(async () => {
        const testInfo = test.info();

        if (testInfo.status !== testInfo.expectedStatus) {
            await testInfo.attach('failure', {
                body: await page.screenshot(),
                contentType: 'image/png',
            });
        }
    });

    test.afterAll(async () => {
        await page.context().close();
    });

    test('serves the login page', async () => {
        await page.goto('/login');

        await expect(
            page.getByRole('button', { name: 'Log in' }),
        ).toBeVisible();
    });

    test('rejects anonymous API access', async () => {
        const response = await page.request.get('/api/profile', {
            headers: { Accept: 'application/json' },
        });

        expect(response.status()).toBe(401);
    });

    test('the smoke user can log in', async () => {
        await page.goto('/login');
        await page.getByLabel('Email address').fill(credentials.email);
        await page
            .getByRole('textbox', { name: 'Password' })
            .fill(credentials.password);

        await Promise.all([
            page.waitForURL('**/dashboard'),
            page.getByRole('button', { name: 'Log in' }).click(),
        ]);

        await expect(
            page.getByRole('heading', { name: 'Dashboard' }),
        ).toBeVisible();

        // The page shell is server-rendered; its data comes from the API.
        await page.waitForLoadState('networkidle');
        await expect(page.getByText('Dashboard unavailable')).toHaveCount(0);
    });

    test('the API accepts same-origin requests without a Referer', async () => {
        const response = await page.request.get('/api/dashboard', {
            headers: {
                Accept: 'application/json',
                'Sec-Fetch-Site': 'same-origin',
            },
        });

        expect(response.status()).toBe(200);
        expect((await response.json()).dashboard).toBeDefined();
    });

    test('the profile and orderbook APIs respond', async () => {
        const user = await profile(page);
        expect(user.balance).toMatch(/^\d+\.\d{2}$/);
        expect(user.locked_balance).toMatch(/^\d+\.\d{2}$/);

        const orderbook = await apiGet(page, '/api/orders?symbol=BTC');
        expect(orderbook.status()).toBe(200);
        expect(Array.isArray((await orderbook.json()).orders)).toBe(true);
    });

    test('realtime updates connect', async () => {
        test.skip(!requireRealtime, 'Realtime is not required here.');

        await page.goto('/wallet');

        await expect(page.getByLabel('Realtime updates: Live')).toBeVisible({
            timeout: 45_000,
        });
    });

    test('an order can be placed and cancelled, restoring the balance', async () => {
        const before = await profile(page);

        await page.goto('/trade');
        await page.getByLabel('Limit price (USD)').fill(order.price);
        await page.getByLabel(/Amount \(/).fill(order.amount);

        const placed = page.waitForResponse(
            (response) =>
                response.request().method() === 'POST' &&
                new URL(response.url()).pathname === '/api/orders',
        );
        await page.getByRole('button', { name: 'Place buy order' }).click();

        expect((await placed).status()).toBe(201);
        await expect(
            page.getByText('Order placed', { exact: true }),
        ).toBeVisible();

        const reserved = await profile(page);
        expect(reserved.locked_balance).not.toBe(before.locked_balance);

        await page.goto('/wallet');

        const row = page
            .locator('[data-test="order-history-table"] tbody tr')
            .filter({ hasText: order.amount });
        await expect(row).toContainText('Open');
        await row.getByRole('button', { name: 'Cancel' }).click();
        await expect(page.getByRole('dialog')).toContainText(
            'Cancel this order?',
        );
        await page
            .getByRole('button', { name: 'Confirm cancellation' })
            .click();

        await expect(
            page.getByText('Order cancelled', { exact: true }),
        ).toBeVisible();

        const after = await profile(page);
        expect(after).toEqual(before);
    });
});
