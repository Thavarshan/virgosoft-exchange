import { mount } from '@vue/test-utils';
import { h } from 'vue';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import Login from './Login.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    Link: { template: '<a><slot /></a>' },
    Form: {
        setup: (_props: unknown, { slots }: { slots: any }) => () =>
            h(
                'form',
                slots.default?.({ errors: {}, processing: false }) ?? [],
            ),
    },
}));
vi.mock('@/routes', () => ({ register: () => '/register' }));
vi.mock('@/routes/login', () => ({ store: { form: () => ({}) } }));
vi.mock('@/routes/password', () => ({ request: () => '/forgot-password' }));

const demoAccounts = [
    {
        name: 'Demo Trader',
        email: 'demo@example.com',
        password: 'password',
    },
    {
        name: 'Demo Counterparty',
        email: 'counterparty@example.com',
        password: 'password',
    },
];

function mountLogin(props: Record<string, unknown> = {}) {
    return mount(Login, {
        attachTo: document.body,
        props: { canResetPassword: true, ...props },
        global: {
            directives: { focus: {} },
            stubs: { PasskeyVerify: true },
        },
    });
}

describe('Login demo accounts', () => {
    beforeAll(() => {
        // Popper positioning needs ResizeObserver, which jsdom lacks.
        vi.stubGlobal(
            'ResizeObserver',
            class {
                observe(): void {}
                unobserve(): void {}
                disconnect(): void {}
            },
        );
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('shows nothing when demo accounts are not enabled', () => {
        const wrapper = mountLogin();

        expect(wrapper.find('[data-test="demo-accounts-notice"]').exists()).toBe(
            false,
        );
    });

    it('announces the demo accounts above the form', () => {
        const wrapper = mountLogin({ demoAccounts });

        const notice = wrapper.get('[data-test="demo-accounts-notice"]');

        expect(notice.text()).toContain('Reviewing this demo?');
        expect(notice.text()).toContain('2 pre-funded demo accounts');
    });

    it('renders a single accessible trigger button, not nested buttons', async () => {
        const wrapper = mountLogin({ demoAccounts });

        expect(
            wrapper.element.querySelectorAll(
                'button[data-test="demo-accounts-trigger"]',
            ),
        ).toHaveLength(1);
        expect(wrapper.element.querySelector('button button')).toBeNull();

        await wrapper
            .get('[data-test="demo-accounts-trigger"]')
            .trigger('click');

        expect(document.body.querySelector('button button')).toBeNull();
    });

    it('lists both accounts with their credentials in the popover', async () => {
        const wrapper = mountLogin({ demoAccounts });

        await wrapper.get('[data-test="demo-accounts-trigger"]').trigger('click');

        const popover = document.body.querySelector(
            '[data-test="demo-accounts-popover"]',
        );

        expect(popover?.textContent).toContain('demo@example.com');
        expect(popover?.textContent).toContain('counterparty@example.com');
        expect(popover?.textContent).toContain('password');
    });

    it('fills the form with the chosen account', async () => {
        const wrapper = mountLogin({ demoAccounts });

        await wrapper.get('[data-test="demo-accounts-trigger"]').trigger('click');
        document.body
            .querySelector<HTMLButtonElement>(
                '[data-test="demo-fill-counterparty@example.com"]',
            )
            ?.click();

        expect(
            document.body.querySelector<HTMLInputElement>('#email')?.value,
        ).toBe('counterparty@example.com');
        expect(
            document.body.querySelector<HTMLInputElement>('#password')?.value,
        ).toBe('password');
    });
});
