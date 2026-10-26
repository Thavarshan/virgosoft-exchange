import { defineConfig, devices } from '@playwright/test';
import process from 'node:process';

const e2ePort = process.env.E2E_PORT ?? '8080';
const baseURL = process.env.E2E_BASE_URL ?? `http://127.0.0.1:${e2ePort}`;
const inheritedEnvironment = Object.fromEntries(
    Object.entries(process.env).filter(([, value]) => value !== undefined),
) as Record<string, string>;

export const e2eEnvironment: Record<string, string> = {
    ...inheritedEnvironment,
    APP_ENV: process.env.APP_ENV ?? 'testing',
    APP_URL: baseURL,
    DB_CONNECTION: 'pgsql',
    DB_HOST: process.env.E2E_DB_HOST ?? process.env.DB_HOST ?? '127.0.0.1',
    DB_PORT: process.env.DB_PORT ?? '5432',
    DB_DATABASE: process.env.E2E_DB_DATABASE ?? 'virgosoft_e2e',
    DB_USERNAME: process.env.DB_USERNAME ?? 'virgosoft',
    DB_PASSWORD: process.env.DB_PASSWORD ?? 'virgosoft',
    BROADCAST_CONNECTION: process.env.BROADCAST_CONNECTION ?? 'pusher',
    CACHE_STORE: 'database',
    QUEUE_CONNECTION: 'database',
    SESSION_DRIVER: 'database',
    SANCTUM_STATEFUL_DOMAINS: `127.0.0.1,127.0.0.1:${e2ePort},localhost,localhost:${e2ePort}`,
    PUSHER_APP_ID: process.env.PUSHER_APP_ID ?? 'e2e-app',
    PUSHER_APP_KEY: process.env.PUSHER_APP_KEY ?? 'e2e-key',
    PUSHER_APP_SECRET: process.env.PUSHER_APP_SECRET ?? 'e2e-secret',
    PUSHER_APP_CLUSTER: process.env.PUSHER_APP_CLUSTER ?? 'mt1',
    PUSHER_HOST: process.env.PUSHER_HOST ?? '127.0.0.1',
    PUSHER_PORT: process.env.PUSHER_PORT ?? '6001',
    PUSHER_SCHEME: process.env.PUSHER_SCHEME ?? 'http',
    VITE_PUSHER_APP_KEY: process.env.VITE_PUSHER_APP_KEY ?? 'e2e-key',
    VITE_PUSHER_APP_CLUSTER: process.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
    VITE_PUSHER_HOST: process.env.VITE_PUSHER_HOST ?? '127.0.0.1',
    VITE_PUSHER_PORT: process.env.VITE_PUSHER_PORT ?? '6001',
    VITE_PUSHER_SCHEME: process.env.VITE_PUSHER_SCHEME ?? 'http',
    VITE_E2E: process.env.VITE_E2E ?? 'true',
    DEMO_ACCOUNTS: 'true',
};

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    timeout: 30_000,
    expect: {
        timeout: 10_000,
    },
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL,
        actionTimeout: 10_000,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        {
            name: 'desktop',
            testIgnore: /mobile\.spec\.ts/,
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1440, height: 900 },
            },
        },
        {
            name: 'mobile',
            testMatch: /mobile\.spec\.ts/,
            use: devices['iPhone 13'],
        },
    ],
    webServer: {
        command: `php artisan serve --host=127.0.0.1 --port=${e2ePort} > storage/logs/e2e-server.log 2>&1`,
        url: `${baseURL}/login`,
        env: e2eEnvironment,
        reuseExistingServer: false,
        timeout: 120_000,
    },
});
