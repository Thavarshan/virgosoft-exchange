<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { DashboardSummary } from '@/types/dashboard';

defineProps<{
    summary: DashboardSummary | null;
    loading: boolean;
    refreshing: boolean;
}>();
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Card
            v-for="metric in [
                {
                    label: 'Available USD',
                    value: summary?.available_usd ?? 'Unavailable',
                    suffix: 'USD',
                },
                {
                    label: 'Reserved USD',
                    value: summary?.reserved_usd ?? 'Unavailable',
                    suffix: 'USD',
                },
                {
                    label: 'Open orders',
                    value: summary?.open_orders ?? 'Unavailable',
                    suffix: '',
                },
                {
                    label: 'Executed trade value',
                    value: summary?.executed_trade_value ?? 'Unavailable',
                    suffix: 'USD',
                },
            ]"
            :key="metric.label"
            class="min-w-0"
        >
            <CardHeader class="gap-0">
                <CardTitle class="text-sm font-medium text-muted-foreground">
                    {{ metric.label }}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <template v-if="loading">
                    <Skeleton class="h-8 w-28" />
                </template>
                <template v-else>
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ metric.value }}
                        <span
                            v-if="metric.suffix"
                            class="text-sm font-normal text-muted-foreground"
                        >
                            {{ metric.suffix }}
                        </span>
                    </p>
                </template>
                <p v-if="refreshing" class="mt-2 text-xs text-muted-foreground">
                    Refreshing…
                </p>
            </CardContent>
        </Card>
    </div>
</template>
