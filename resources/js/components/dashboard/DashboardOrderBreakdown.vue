<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import OrderStatusBadge from '@/components/trading/OrderStatusBadge.vue';
import type { DashboardStatusCount } from '@/types/dashboard';

defineProps<{
    statuses: DashboardStatusCount[];
    loading: boolean;
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">Orders placed</CardTitle>
            <p class="text-sm text-muted-foreground">
                Selected-period orders grouped by their current status.
            </p>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="space-y-3">
                <div
                    v-for="index in 3"
                    :key="index"
                    class="h-8 animate-pulse rounded bg-muted"
                />
            </div>
            <div v-else class="space-y-3">
                <div
                    v-for="entry in statuses"
                    :key="entry.status"
                    class="flex items-center justify-between gap-3"
                >
                    <OrderStatusBadge :status="entry.status" />
                    <span class="font-semibold tabular-nums">
                        {{ entry.count }}
                    </span>
                </div>
                <p
                    v-if="statuses.every((entry) => entry.count === 0)"
                    class="text-sm text-muted-foreground"
                >
                    No orders placed in this period.
                </p>
            </div>
        </CardContent>
    </Card>
</template>
