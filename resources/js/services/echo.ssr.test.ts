// @vitest-environment node

import { afterEach, expect, it, vi } from 'vitest';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    usePage: () => ({ props: { auth: { user: { id: 7 } } } }),
}));

afterEach(() => {
    vi.unstubAllEnvs();
    vi.resetModules();
});

it.each(['Wallet', 'Trade', 'Dashboard'])(
    'renders %s on the server with Pusher configured',
    async (page) => {
        vi.stubEnv('VITE_PUSHER_APP_KEY', 'ssr-test-key');
        const pages = import.meta.glob('../pages/*.vue');
        const { default: component } = (await pages[
            `../pages/${page}.vue`
        ]()) as { default: import('vue').Component };

        const html = await renderToString(
            createSSRApp({ render: () => h(component) }),
        );

        expect(html).toContain('Connecting');
    },
);

it('does not initialize a browser socket during server rendering', async () => {
    vi.stubEnv('VITE_PUSHER_APP_KEY', 'ssr-test-key');
    vi.stubEnv('VITE_E2E', 'true');

    const { echo, isEchoConfigured, userChannel } = await import('./echo');

    expect(isEchoConfigured).toBe(true);
    expect(echo).toBeNull();
    expect(userChannel(7)).toBeUndefined();
});
