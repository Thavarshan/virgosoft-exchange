<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import OrderSideBadge from '@/components/trading/OrderSideBadge.vue';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/trading';
import type { DashboardExecution } from '@/types/dashboard';

defineProps<{
    executions: DashboardExecution[];
    loading: boolean;
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">Recent executions</CardTitle>
            <p class="text-sm text-muted-foreground">
                Your five latest executions in the selected period.
            </p>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="space-y-3">
                <Skeleton v-for="index in 5" :key="index" class="h-10 w-full" />
            </div>
            <div
                v-else-if="executions.length === 0"
                class="rounded-lg border border-dashed border-border p-8 text-center"
            >
                <p class="font-medium">No executions yet</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Completed matches will appear here.
                </p>
            </div>
            <div v-else class="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Market</TableHead>
                            <TableHead>Side</TableHead>
                            <TableHead class="text-right">
                                Execution price
                            </TableHead>
                            <TableHead class="text-right">Amount</TableHead>
                            <TableHead class="text-right">
                                Trade value
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="execution in executions"
                            :key="execution.id"
                        >
                            <TableCell
                                class="text-sm whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDateTime(execution.date) }}
                            </TableCell>
                            <TableCell class="font-medium">
                                {{ execution.market }} / USD
                            </TableCell>
                            <TableCell>
                                <OrderSideBadge :side="execution.side" />
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ execution.price }} USD
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ execution.amount }} {{ execution.market }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ execution.trade_value }} USD
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </CardContent>
    </Card>
</template>
