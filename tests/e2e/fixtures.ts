import { execFileSync } from 'node:child_process';
import {
    expect,
    test as base,
    type Page,
    type Response,
} from '@playwright/test';
import { e2eEnvironment } from '../../playwright.config';

export const DEMO_USER = {
    email: 'demo@example.com',
    password: 'password',
};

export const COUNTERPARTY_USER = {
    email: 'counterparty@example.com',
    password: 'password',
};

type E2eWindow = Window & {
    __e2eEcho?: {
        disconnect: () => void;
        connect: () => void;
    };
};

type E2eFixtures = {
    preparedDatabase: void;
};

export const test = base.extend<E2eFixtures>({
    preparedDatabase: [
        async ({ page }, use): Promise<void> => {
            void page;
            execFileSync('php', ['artisan', 'e2e:prepare', '--reset'], {
                env: e2eEnvironment,
                stdio: 'inherit',
            });

            await use();
        },
        { auto: true },
    ],
});

export { expect };

export async function login(
    page: Page,
    credentials: { email: string; password: string },
): Promise<void> {
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
}

export async function waitForLive(page: Page, timeout = 10_000): Promise<void> {
    await expect(page.getByLabel('Realtime updates: Live')).toBeVisible({
        timeout,
    });
}

export async function disconnectRealtime(page: Page): Promise<void> {
    await page.evaluate(() => (window as E2eWindow).__e2eEcho?.disconnect());
}

export async function reconnectRealtime(page: Page): Promise<void> {
    await page.evaluate(() => (window as E2eWindow).__e2eEcho?.connect());
}

export function seedDashboardActivity(): void {
    execFileSync('php', ['artisan', 'e2e:prepare', '--reset', '--activity'], {
        env: e2eEnvironment,
        stdio: 'inherit',
    });
}

export function seedOrderHistory(): void {
    execFileSync('php', ['artisan', 'e2e:prepare', '--reset', '--history'], {
        env: e2eEnvironment,
        stdio: 'inherit',
    });
}

export async function submitOrder(
    page: Page,
    order: {
        side: 'buy' | 'sell';
        price: string;
        amount: string;
        symbol?: 'BTC' | 'ETH';
    },
): Promise<Response> {
    if (order.symbol && order.symbol !== 'BTC') {
        await page.getByRole('combobox', { name: 'Order market' }).click();
        await page
            .getByRole('option', { name: `${order.symbol} / USD` })
            .click();
    }

    if (order.side === 'sell') {
        await page.getByRole('button', { name: /Sell/ }).click();
    }

    await page.getByLabel('Limit price (USD)').fill(order.price);
    await page.getByLabel(/Amount \(/).fill(order.amount);

    const responsePromise = page.waitForResponse((response) => {
        return (
            response.request().method() === 'POST' &&
            new URL(response.url()).pathname === '/api/orders'
        );
    });

    await page
        .getByRole('button', { name: `Place ${order.side} order` })
        .click();

    return responsePromise;
}

export async function openTrade(page: Page): Promise<void> {
    await page.goto('/trade');
    await expect(
        page.getByRole('heading', { name: 'Limit Order', exact: true }),
    ).toBeVisible();
}

export async function openWallet(page: Page): Promise<void> {
    await page.goto('/wallet');
    await expect(
        page.getByRole('heading', { name: 'Orders & Wallet', exact: true }),
    ).toBeVisible();
}

export async function selectMarket(
    page: Page,
    symbol: 'BTC' | 'ETH',
): Promise<void> {
    await page.getByRole('tab', { name: `${symbol} / USD` }).click();
}
