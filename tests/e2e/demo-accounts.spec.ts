import { expect, test } from './fixtures';

test('the login page offers demo credentials that sign in', async ({
    page,
}) => {
    await page.goto('/login');

    await expect(page.getByText('Reviewing this demo?')).toBeVisible();
    await page.getByRole('button', { name: 'Show demo credentials' }).click();

    const popover = page.locator('[data-test="demo-accounts-popover"]');
    await expect(popover).toContainText('demo@example.com');
    await expect(popover).toContainText('counterparty@example.com');

    await page
        .locator('[data-test="demo-fill-counterparty@example.com"]')
        .click();

    await expect(page.getByLabel('Email address')).toHaveValue(
        'counterparty@example.com',
    );
    await expect(page.getByRole('textbox', { name: 'Password' })).toHaveValue(
        'password',
    );

    await Promise.all([
        page.waitForURL('**/dashboard'),
        page.getByRole('button', { name: 'Log in' }).click(),
    ]);
    await expect(
        page.getByRole('heading', { name: 'Dashboard' }),
    ).toBeVisible();
});
