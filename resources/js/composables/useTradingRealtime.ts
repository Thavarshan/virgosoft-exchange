import { onUnmounted, ref, type Ref } from 'vue';
import { echo, isEchoConfigured } from '@/services/echo';
import type { OrderMatchedPayload } from '@/types/trading';

const connectionStatuses = [
    'connected',
    'connecting',
    'reconnecting',
    'disconnected',
    'unconfigured',
] as const;

export type TradingConnectionStatus = (typeof connectionStatuses)[number];

type Subscriber = {
    onMatch: (payload: OrderMatchedPayload) => void;
    onReconnect: () => void;
};

type Subscription = {
    status: Ref<TradingConnectionStatus>;
    subscribers: Set<Subscriber>;
    stopConnectionListener: () => void;
};

const subscriptions = new Map<number, Subscription>();

function connectionStatus(): TradingConnectionStatus {
    if (!echo || !isEchoConfigured) {
        return 'unconfigured';
    }

    const status = echo.connectionStatus();

    if (
        status === 'connected' ||
        status === 'connecting' ||
        status === 'reconnecting'
    ) {
        return status;
    }

    return 'disconnected';
}

function createSubscription(userId: number): Subscription {
    const initialStatus = connectionStatus();
    const status = ref<TradingConnectionStatus>(
        initialStatus === 'connected' ? 'connecting' : initialStatus,
    );
    const subscribers = new Set<Subscriber>();

    if (!echo || !isEchoConfigured) {
        return {
            status,
            subscribers,
            stopConnectionListener: () => undefined,
        };
    }

    const channel = echo.private(`user.${userId}`);

    channel
        .listen('.order.matched', (payload: OrderMatchedPayload) => {
            subscribers.forEach(({ onMatch }) => onMatch(payload));
        })
        .subscribed(() => {
            status.value = 'connected';
            subscribers.forEach(({ onReconnect }) => onReconnect());
        })
        .error(() => {
            status.value = 'disconnected';
        });

    const stopConnectionListener = echo.connector.onConnectionChange(
        (nextStatus) => {
            if (nextStatus === 'connected') {
                status.value = 'connecting';

                return;
            }

            status.value = nextStatus as TradingConnectionStatus;
        },
    );

    return { status, subscribers, stopConnectionListener };
}

/**
 * Share one Echo listener per authenticated user across Inertia pages.
 */
export function useTradingRealtime(
    userId: number,
    onMatch: (payload: OrderMatchedPayload) => void,
    onReconnect: () => void,
): { connectionStatus: Ref<TradingConnectionStatus> } {
    // Server renders must not retain per-user subscriptions across requests.
    if (typeof window === 'undefined') {
        return {
            connectionStatus: ref(
                isEchoConfigured ? 'connecting' : 'unconfigured',
            ),
        };
    }

    const subscription =
        subscriptions.get(userId) ?? createSubscription(userId);

    subscriptions.set(userId, subscription);

    const subscriber = { onMatch, onReconnect };
    subscription.subscribers.add(subscriber);

    const stop = (): void => {
        subscription.subscribers.delete(subscriber);

        if (subscription.subscribers.size > 0) {
            return;
        }

        subscription.stopConnectionListener();
        echo?.leave(`user.${userId}`);
        subscriptions.delete(userId);
    };

    onUnmounted(stop);

    return { connectionStatus: subscription.status };
}
