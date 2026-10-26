<script setup lang="ts">
import { computed, ref } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import type { OrderHistoryResponse, TradingOrder } from '@/types/trading';
import { formatDateTime } from '@/lib/trading';
import OrderSideBadge from '@/components/trading/OrderSideBadge.vue';
import OrderStatusBadge from '@/components/trading/OrderStatusBadge.vue';

const props = withDefaults(
    defineProps<{
        orders: TradingOrder[];
        pagination: OrderHistoryResponse['meta'] | null;
        loading: boolean;
        refreshing?: boolean;
        cancellingOrderIds: ReadonlySet<number>;
        error: string | null;
    }>(),
    { refreshing: false },
);

const emit = defineEmits<{
    cancel: [orderId: number];
    page: [page: number];
}>();

const orderToCancel = ref<TradingOrder | null>(null);
const pageCount = computed(() => props.pagination?.last_page ?? 1);

function openCancelConfirmation(order: TradingOrder): void {
    orderToCancel.value = order;
}

function confirmCancellation(): void {
    if (!orderToCancel.value) {
        return;
    }
    emit('cancel', orderToCancel.value.id);
    orderToCancel.value = null;
}

function isCancelling(orderId: number): boolean {
    return props.cancellingOrderIds.has(orderId);
}
</script>

<template>
    <Card>
        <CardHeader class="gap-0">
            <div>
                <CardTitle class="mb-0.5 text-base leading-6 font-medium"
                    >Order history</CardTitle
                >
                <p class="mt-1 text-sm text-muted-foreground">
                    Your open, filled, and cancelled orders
                </p>
            </div>
            <CardAction v-if="pagination">
                <div class="flex items-center gap-2">
                    <span
                        v-if="refreshing"
                        class="text-xs text-muted-foreground"
                        >Refreshing…</span
                    >
                    <Badge variant="outline"
                        >{{ pagination.total }} total</Badge
                    >
                </div>
            </CardAction>
        </CardHeader>
        <CardContent>
            <Alert v-if="error" variant="destructive" class="mb-4">
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <div v-if="loading" class="space-y-3">
                <Skeleton v-for="row in 5" :key="row" class="h-12 w-full" />
            </div>

            <div
                v-else-if="orders.length === 0 && !error"
                class="rounded-lg border border-dashed border-border p-8 text-center"
            >
                <p class="font-medium">No orders yet</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Place your first limit order to see it here.
                </p>
            </div>

            <template v-else-if="orders.length > 0">
                <div class="space-y-3 md:hidden">
                    <div
                        v-for="order in orders"
                        :key="order.id"
                        class="rounded-lg border border-border p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <OrderSideBadge :side="order.side" />
                                <span class="font-medium">{{
                                    order.symbol
                                }}</span>
                            </div>
                            <OrderStatusBadge :status="order.status" />
                        </div>
                        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-muted-foreground">
                                    Limit price
                                </dt>
                                <dd class="mt-1 tabular-nums">
                                    {{ order.price }} USD
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Amount</dt>
                                <dd class="mt-1 tabular-nums">
                                    {{ order.amount }} {{ order.symbol }}
                                </dd>
                            </div>
                        </dl>
                        <div
                            class="mt-3 flex items-center justify-between gap-3"
                        >
                            <time
                                class="text-xs text-muted-foreground"
                                :datetime="order.created_at"
                                >{{ formatDateTime(order.created_at) }}</time
                            >
                            <Button
                                v-if="order.status === 1"
                                variant="outline"
                                size="sm"
                                :disabled="isCancelling(order.id)"
                                @click="openCancelConfirmation(order)"
                            >
                                {{
                                    isCancelling(order.id)
                                        ? 'Cancelling…'
                                        : 'Cancel'
                                }}
                            </Button>
                        </div>
                    </div>
                </div>

                <Table class="hidden md:table">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Side</TableHead>
                            <TableHead>Symbol</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right"
                                >Limit price</TableHead
                            >
                            <TableHead class="text-right">Amount</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead class="text-right">Action</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="order in orders" :key="order.id">
                            <TableCell
                                ><OrderSideBadge :side="order.side"
                            /></TableCell>
                            <TableCell class="font-medium">{{
                                order.symbol
                            }}</TableCell>
                            <TableCell
                                ><OrderStatusBadge :status="order.status"
                            /></TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                order.price
                            }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                order.amount
                            }}</TableCell>
                            <TableCell
                                ><time
                                    class="text-sm text-muted-foreground"
                                    :datetime="order.created_at"
                                    >{{
                                        formatDateTime(order.created_at)
                                    }}</time
                                ></TableCell
                            >
                            <TableCell class="text-right">
                                <Button
                                    v-if="order.status === 1"
                                    variant="outline"
                                    size="sm"
                                    :disabled="isCancelling(order.id)"
                                    @click="openCancelConfirmation(order)"
                                    >{{
                                        isCancelling(order.id)
                                            ? 'Cancelling…'
                                            : 'Cancel'
                                    }}</Button
                                >
                                <span
                                    v-else
                                    class="text-sm text-muted-foreground"
                                    >—</span
                                >
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <Pagination
                    v-if="pagination && pageCount > 1"
                    :page="pagination.current_page"
                    :items-per-page="pagination.per_page"
                    :total="pagination.total"
                    :sibling-count="1"
                    show-edges
                    class="mt-6"
                    @update:page="emit('page', $event)"
                >
                    <template #default="{ page }">
                        <PaginationContent v-slot="{ items }">
                            <PaginationPrevious />
                            <template
                                v-for="(item, index) in items"
                                :key="index"
                            >
                                <PaginationItem
                                    v-if="item.type === 'page'"
                                    :value="item.value"
                                    ><PaginationLink
                                        :is-active="item.value === page"
                                        >{{ item.value }}</PaginationLink
                                    ></PaginationItem
                                >
                                <PaginationEllipsis v-else />
                            </template>
                            <PaginationNext />
                        </PaginationContent>
                    </template>
                </Pagination>
            </template>
        </CardContent>
    </Card>

    <Dialog
        :open="Boolean(orderToCancel)"
        @update:open="(open) => !open && (orderToCancel = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Cancel this order?</DialogTitle>
                <DialogDescription>
                    This returns the reservation if the order is still open. It
                    may fill while this confirmation is open; the server will
                    verify its current status.
                </DialogDescription>
            </DialogHeader>
            <dl
                v-if="orderToCancel"
                class="grid grid-cols-2 gap-3 rounded-lg border border-border bg-card p-3 text-sm"
            >
                <div>
                    <dt class="text-muted-foreground">Side</dt>
                    <dd class="mt-1">
                        <OrderSideBadge :side="orderToCancel.side" />
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Market</dt>
                    <dd class="mt-1 font-medium">
                        {{ orderToCancel.symbol }} / USD
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Amount</dt>
                    <dd class="mt-1 tabular-nums">
                        {{ orderToCancel.amount }} {{ orderToCancel.symbol }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Limit price</dt>
                    <dd class="mt-1 tabular-nums">
                        {{ orderToCancel.price }} USD
                    </dd>
                </div>
            </dl>
            <DialogFooter>
                <Button variant="outline" @click="orderToCancel = null"
                    >Keep order</Button
                >
                <Button
                    data-test="confirm-cancel"
                    variant="default"
                    @click="confirmCancellation"
                    >Confirm cancellation</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
