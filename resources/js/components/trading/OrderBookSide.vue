<script setup lang="ts">
import { computed } from 'vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { AssetSymbol, OrderSide, TradingOrder } from '@/types/trading';

const props = defineProps<{
    side: OrderSide;
    symbol: AssetSymbol;
    orders: TradingOrder[];
}>();

const title = computed(() =>
    props.side === 'buy' ? 'Buy orders' : 'Sell orders',
);
</script>

<template>
    <section>
        <h3
            class="text-sm font-semibold"
            :class="
                side === 'buy' ? 'text-buy-foreground' : 'text-sell-foreground'
            "
        >
            {{ title }}
        </h3>

        <div class="mt-2 space-y-2 md:hidden">
            <div
                v-for="order in orders"
                :key="order.id"
                class="flex items-center justify-between rounded-lg border border-border p-3 text-sm"
            >
                <span
                    class="font-medium tabular-nums"
                    :class="
                        side === 'buy'
                            ? 'text-buy-foreground'
                            : 'text-sell-foreground'
                    "
                >
                    {{ order.price }} USD
                </span>
                <span class="text-muted-foreground tabular-nums">
                    {{ order.amount }} {{ symbol }}
                </span>
            </div>
            <p v-if="orders.length === 0" class="text-sm text-muted-foreground">
                No {{ side }} orders.
            </p>
        </div>

        <Table class="mt-2 hidden md:table">
            <TableHeader>
                <TableRow>
                    <TableHead>Price (USD)</TableHead>
                    <TableHead class="text-right"
                        >Amount ({{ symbol }})</TableHead
                    >
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="order in orders" :key="order.id">
                    <TableCell
                        class="tabular-nums"
                        :class="
                            side === 'buy'
                                ? 'text-buy-foreground'
                                : 'text-sell-foreground'
                        "
                    >
                        {{ order.price }}
                    </TableCell>
                    <TableCell class="text-right tabular-nums">
                        {{ order.amount }}
                    </TableCell>
                </TableRow>
                <TableRow v-if="orders.length === 0">
                    <TableCell colspan="2" class="text-muted-foreground">
                        No {{ side }} orders.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </section>
</template>
