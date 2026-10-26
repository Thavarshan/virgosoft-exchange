import { defineConfig, devices } from '@playwright/test';
import process from 'node:process';

const baseURL = process.env.SMOKE_BASE_URL;

if (!baseURL) {
    throw new Error(
        'SMOKE_BASE_URL is required, for example https://your-app.laravel.cloud',
    );
}

// Smoke checks run against a deployed application. Unlike the E2E suite they
// never start a server, never touch a database directly, and never import the
// E2E fixtures, which reset the isolated test database.
export default defineConfig({
    testDir: './tests/smoke',
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    // Generous limits: a sleeping Laravel Cloud environment cold-starts.
    timeout: 90_000,
    expect: {
        timeout: 20_000,
    },
    reporter: process.env.CI
        ? [['list'], ['html', { open: 'never', outputFolder: 'smoke-report' }]]
        : 'list',
    outputDir: 'smoke-results',
    use: {
        baseURL,
        actionTimeout: 20_000,
        navigationTimeout: 45_000,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1440, height: 900 },
            },
        },
    ],
});
