import { defineComponent, h, nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useTradingRealtime } from './useTradingRealtime';

const { echo, privateChannel } = vi.hoisted(() => {
    const channel = {
        listen: vi.fn(),
        subscribed: vi.fn(),
        error: vi.fn(),
    };

    channel.listen.mockReturnValue(channel);
    channel.subscribed.mockReturnValue(channel);
    channel.error.mockReturnValue(channel);

    return {
        privateChannel: channel,
        echo: {
            connectionStatus: vi.fn(() => 'connected'),
            private: vi.fn(() => channel),
            leave: vi.fn(),
            connector: {
                onConnectionChange: vi.fn(() => vi.fn()),
            },
        },
    };
});

vi.mock('@/services/echo', () => ({
    echo,
    isEchoConfigured: true,
}));

describe('useTradingRealtime', () => {
    beforeEach(() => {
        echo.private.mockClear();
        echo.leave.mockClear();
        privateChannel.listen.mockClear();
        privateChannel.subscribed.mockClear();
        privateChannel.error.mockClear();
        echo.connector.onConnectionChange.mockClear();
    });

    it('shares one channel listener and leaves it after the last subscriber', () => {
        const onMatch = vi.fn();
        const onReconnect = vi.fn();
        const Host = defineComponent({
            setup() {
                useTradingRealtime(7, onMatch, onReconnect);

                return () => h('div');
            },
        });
        const first = mount(Host);
        const second = mount(Host);

        expect(echo.private).toHaveBeenCalledTimes(1);
        expect(privateChannel.listen).toHaveBeenCalledTimes(1);

        first.unmount();
        expect(echo.leave).not.toHaveBeenCalled();

        second.unmount();
        expect(echo.leave).toHaveBeenCalledWith('user.7');
    });

    it('waits for private-channel subscription before marking live or refreshing', async () => {
        const onReconnect = vi.fn();
        const Host = defineComponent({
            setup() {
                return useTradingRealtime(7, vi.fn(), onReconnect);
            },
            render() {
                return h('div', {
                    'data-status': this.connectionStatus,
                });
            },
        });
        const wrapper = mount(Host);
        const [connectionChange] = echo.connector.onConnectionChange.mock
            .calls[0] as unknown as [(status: string) => void];
        const [subscribed] = privateChannel.subscribed.mock
            .calls[0] as unknown as [() => void];
        const [subscriptionError] = privateChannel.error.mock
            .calls[0] as unknown as [() => void];

        expect(wrapper.attributes('data-status')).toBe('connecting');
        expect(onReconnect).not.toHaveBeenCalled();

        connectionChange('connected');
        await nextTick();

        expect(wrapper.attributes('data-status')).toBe('connecting');
        expect(onReconnect).not.toHaveBeenCalled();

        subscribed();
        await nextTick();

        expect(wrapper.attributes('data-status')).toBe('connected');
        expect(onReconnect).toHaveBeenCalledTimes(1);

        connectionChange('reconnecting');
        connectionChange('connected');
        await nextTick();

        expect(wrapper.attributes('data-status')).toBe('connecting');
        expect(onReconnect).toHaveBeenCalledTimes(1);

        subscribed();
        await nextTick();

        expect(wrapper.attributes('data-status')).toBe('connected');
        expect(onReconnect).toHaveBeenCalledTimes(2);

        subscriptionError();
        await nextTick();

        expect(wrapper.attributes('data-status')).toBe('disconnected');

        wrapper.unmount();
    });

    it('delivers one match to every active subscriber on the shared channel', () => {
        const firstMatch = vi.fn();
        const secondMatch = vi.fn();
        const Host = defineComponent({
            props: { onMatch: { type: Function, required: true } },
            setup(props) {
                useTradingRealtime(7, props.onMatch as never, vi.fn());

                return () => h('div');
            },
        });
        const first = mount(Host, { props: { onMatch: firstMatch } });
        const second = mount(Host, { props: { onMatch: secondMatch } });
        const onEvent = privateChannel.listen.mock.calls[0]?.[1] as (
            payload: unknown,
        ) => void;

        onEvent({
            buy_order_id: 1,
            sell_order_id: 2,
            symbol: 'BTC',
            execution_price: '95000.00',
            amount: '0.01000000',
            trade_value: '950.00',
            commission: '14.25',
        });

        expect(firstMatch).toHaveBeenCalledTimes(1);
        expect(secondMatch).toHaveBeenCalledTimes(1);

        first.unmount();
        second.unmount();
    });

    it('uses separate private channels for separate users', () => {
        const channels = new Map<string, typeof privateChannel>();
        echo.private.mockImplementation((...args: unknown[]) => {
            const name = String(args[0]);
            const channel = {
                listen: vi.fn(),
                subscribed: vi.fn(),
                error: vi.fn(),
            };
            channel.listen.mockReturnValue(channel);
            channel.subscribed.mockReturnValue(channel);
            channel.error.mockReturnValue(channel);
            channels.set(name, channel);

            return channel;
        });
        const Host = defineComponent({
            props: { userId: { type: Number, required: true } },
            setup(props) {
                useTradingRealtime(props.userId, vi.fn(), vi.fn());

                return () => h('div');
            },
        });
        const first = mount(Host, { props: { userId: 7 } });
        const second = mount(Host, { props: { userId: 8 } });

        expect(echo.private).toHaveBeenCalledWith('user.7');
        expect(echo.private).toHaveBeenCalledWith('user.8');
        expect(channels.has('user.7')).toBe(true);
        expect(channels.has('user.8')).toBe(true);

        first.unmount();
        second.unmount();
    });
});
