<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConnectionBadge from '@/components/trading/ConnectionBadge.vue';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import OrderBookPanel from '@/components/trading/OrderBookPanel.vue';
import OrderHistoryPanel from '@/components/trading/OrderHistoryPanel.vue';
import PageHeader from '@/components/PageHeader.vue';
import WalletSummary from '@/components/trading/WalletSummary.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { useOrderHistory } from '@/composables/useOrderHistory';
import { useOrderbook } from '@/composables/useOrderbook';
import { useTradingProfile } from '@/composables/useTradingProfile';
import { useTradingRealtime } from '@/composables/useTradingRealtime';
import { formatMatchNotification } from '@/lib/trading';
import { toApiError } from '@/services/api';
import { cancelOrder } from '@/services/trading';
import { wallet } from '@/routes';
import type { Auth } from '@/types/auth';
import {
    TRADING_MARKETS,
    type AssetSymbol,
    type OrderMatchedPayload,
} from '@/types/trading';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Orders & Wallet', href: wallet() }] },
});

const page = usePage<{ auth: Auth }>();
const userId = page.props.auth.user.id;
const profile = useTradingProfile();
const history = useOrderHistory();
const book = useOrderbook();
const selectedSymbol = ref<AssetSymbol>('BTC');
const pendingCancellationIds = ref<Set<number>>(new Set());
const pageError = ref<string | null>(null);
let orderbookPollId: number | null = null;
const ORDERBOOK_POLL_INTERVAL_MS = 15_000;

const { connectionStatus } = useTradingRealtime(
    userId,
    (payload) => {
        void refreshAfterMatch(payload);
        toast.success('Order matched', {
            description: formatMatchNotification(payload),
        });
    },
    () => {
        void refreshAll();
    },
);

async function refreshAll(
    pageNumber = history.pagination.value?.current_page ?? 1,
): Promise<void> {
    await Promise.all([
        profile.loadProfile(),
        history.loadHistory(pageNumber),
        book.loadOrderbook(selectedSymbol.value),
    ]);
}

async function refreshAfterMatch(payload: OrderMatchedPayload): Promise<void> {
    await Promise.all([
        profile.loadProfile(),
        history.loadHistory(history.pagination.value?.current_page ?? 1),
        payload.symbol === selectedSymbol.value
            ? book.loadOrderbook(payload.symbol)
            : Promise.resolve(),
    ]);
}

function selectSymbol(value: AssetSymbol): void {
    if (!TRADING_MARKETS.some((market) => market.symbol === value)) {
        return;
    }
    selectedSymbol.value = value;
    void book.loadOrderbook(value);
}

function refreshOrderbookIfVisible(): void {
    if (document.visibilityState === 'visible') {
        void book.loadOrderbook(selectedSymbol.value, false);
    }
}

async function handleCancel(orderId: number): Promise<void> {
    if (pendingCancellationIds.value.has(orderId)) {
        return;
    }
    pendingCancellationIds.value = new Set(pendingCancellationIds.value).add(
        orderId,
    );
    pageError.value = null;

    try {
        await cancelOrder(orderId);
        toast.success('Order cancelled', {
            description: 'The reservation has been returned to your wallet.',
        });
        await refreshAll(history.pagination.value?.current_page ?? 1);
    } catch (error) {
        pageError.value = `The order could no longer be cancelled. ${toApiError(error).message}`;
        await refreshAll(history.pagination.value?.current_page ?? 1);
    } finally {
        const pendingIds = new Set(pendingCancellationIds.value);
        pendingIds.delete(orderId);
        pendingCancellationIds.value = pendingIds;
    }
}

onMounted(() => {
    void refreshAll();
    document.addEventListener('visibilitychange', refreshOrderbookIfVisible);
    orderbookPollId = window.setInterval(
        refreshOrderbookIfVisible,
        ORDERBOOK_POLL_INTERVAL_MS,
    );
});

onUnmounted(() => {
    document.removeEventListener('visibilitychange', refreshOrderbookIfVisible);
    if (orderbookPollId !== null) {
        window.clearInterval(orderbookPollId);
    }
});
</script>

<template>
    <Head title="Orders & Wallet" />
    <div class="flex flex-1 flex-col p-4 md:p-6">
        <PageHeader
            title="Orders & Wallet"
            description="Track balances, open markets, and every order placed from your account."
        >
            <template #actions>
                <ConnectionBadge :status="connectionStatus" polling-active />
            </template>
        </PageHeader>

        <Alert v-if="pageError" variant="destructive" class="mb-6">
            <AlertTitle>Something needs attention</AlertTitle>
            <AlertDescription>{{ pageError }}</AlertDescription>
        </Alert>

        <WalletSummary
            :profile="profile.profile.value"
            :loading="profile.loading.value"
            :refreshing="profile.refreshing.value"
            :error="profile.error.value"
            @retry="profile.loadProfile"
        />

        <div
            class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]"
        >
            <OrderHistoryPanel
                :orders="history.orders.value"
                :pagination="history.pagination.value"
                :loading="history.loading.value"
                :refreshing="history.refreshing.value"
                :cancelling-order-ids="pendingCancellationIds"
                :error="history.error.value"
                :filters="history.filters.value"
                @cancel="handleCancel"
                @page="history.loadHistory"
                @filters="history.setFilters"
            />

            <div class="space-y-4">
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <h2 class="text-base leading-6 font-medium">Market</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Choose a symbol for the open orderbook.
                        </p>
                    </div>
                    <Tabs
                        :model-value="selectedSymbol"
                        class="shrink-0"
                        @update:model-value="
                            selectSymbol($event as AssetSymbol)
                        "
                    >
                        <TabsList aria-label="Orderbook market">
                            <TabsTrigger
                                v-for="market in TRADING_MARKETS"
                                :key="market.symbol"
                                :value="market.symbol"
                            >
                                {{ market.label }}
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>
                <OrderBookPanel
                    :symbol="selectedSymbol"
                    :orders="book.orders.value"
                    :loading="book.loading.value"
                    :refreshing="book.refreshing.value"
                    :error="book.error.value"
                    :last-updated="book.lastUpdated.value"
                />
            </div>
        </div>
    </div>
</template>
