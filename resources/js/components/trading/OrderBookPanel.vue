<script setup lang="ts">
import { computed } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardAction,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import OrderBookSide from '@/components/trading/OrderBookSide.vue';
import { formatTime } from '@/lib/trading';
import type { AssetSymbol, TradingOrder } from '@/types/trading';

const props = defineProps<{
    symbol: AssetSymbol;
    orders: TradingOrder[];
    loading: boolean;
    refreshing: boolean;
    error: string | null;
    lastUpdated: string | null;
}>();

const buys = computed(() =>
    props.orders.filter((order) => order.side === 'buy'),
);
const sells = computed(() =>
    props.orders.filter((order) => order.side === 'sell'),
);
</script>

<template>
    <Card data-test="orderbook-panel">
        <CardHeader class="gap-0">
            <div>
                <CardTitle class="mb-0.5 text-base leading-6 font-medium"
                    >Orderbook</CardTitle
                >
                <p class="mt-1 text-sm text-muted-foreground">
                    Open {{ symbol }} limit orders
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{
                        lastUpdated
                            ? `Updated ${formatTime(lastUpdated)}`
                            : 'Not updated yet'
                    }}
                </p>
            </div>
            <CardAction>
                <div class="flex items-center gap-2">
                    <span
                        v-if="refreshing"
                        class="text-xs text-muted-foreground"
                        >Refreshing…</span
                    >
                    <Badge variant="outline">{{ orders.length }} open</Badge>
                </div>
            </CardAction>
        </CardHeader>
        <CardContent>
            <Alert v-if="error" variant="destructive">
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>
            <div v-else-if="loading" class="space-y-3">
                <Skeleton v-for="row in 4" :key="row" class="h-10 w-full" />
            </div>
            <div
                v-else-if="orders.length === 0"
                class="rounded-lg border border-dashed border-border p-8 text-center"
            >
                <p class="font-medium">No open {{ symbol }} orders</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Choose another market or place an order to populate this
                    book.
                </p>
            </div>
            <div v-else class="grid gap-6 md:grid-cols-2">
                <OrderBookSide side="buy" :symbol="symbol" :orders="buys" />
                <OrderBookSide side="sell" :symbol="symbol" :orders="sells" />
            </div>
        </CardContent>
    </Card>
</template>
