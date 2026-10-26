import { defineConfig, devices } from '@playwright/test';
import base from './playwright.config';

// Captures the README screenshots. It reuses the end-to-end server and
// database setup, so run it the same way as the browser tests (see the README).
export default defineConfig({
    testDir: './tests/screenshots',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 120_000,
    reporter: 'list',
    use: {
        baseURL: base.use?.baseURL,
        actionTimeout: 15_000,
        // Smooth, deterministic frames: no animation mid-capture.
        reducedMotion: 'reduce',
    },
    projects: [
        {
            name: 'desktop-light',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1440, height: 900 },
                colorScheme: 'light',
            },
        },
        {
            name: 'desktop-dark',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1440, height: 900 },
                colorScheme: 'dark',
            },
        },
        {
            name: 'mobile-light',
            use: {
                ...devices['iPhone 13'],
                defaultBrowserType: 'chromium',
                colorScheme: 'light',
            },
        },
        {
            name: 'mobile-dark',
            use: {
                ...devices['iPhone 13'],
                defaultBrowserType: 'chromium',
                colorScheme: 'dark',
            },
        },
    ],
    webServer: base.webServer,
});
