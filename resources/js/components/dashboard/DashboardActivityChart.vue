<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import type { DashboardDailyValue, DashboardPeriod } from '@/types/dashboard';

const props = defineProps<{
    values: DashboardDailyValue[];
    period: DashboardPeriod;
    loading: boolean;
    refreshing: boolean;
}>();

const emit = defineEmits<{
    'update:period': [period: DashboardPeriod];
}>();

const maximum = computed(() =>
    Math.max(...props.values.map((entry) => Number(entry.value)), 0),
);

function barHeight(value: string): string {
    const numericValue = Number(value);

    if (maximum.value === 0 || numericValue === 0) {
        return '0%';
    }

    return String((numericValue / maximum.value) * 100) + '%';
}

function showNarrowLabel(index: number): boolean {
    return (
        props.period === 7 ||
        index === 0 ||
        index === props.values.length - 1 ||
        (props.period === 30 && index === 15)
    );
}

function showWideLabel(index: number): boolean {
    return (
        props.period === 7 ||
        (props.period === 30 &&
            (index % 5 === 0 || index === props.values.length - 1))
    );
}

function selectPeriod(value: unknown): void {
    if (value === '7' || value === '30') {
        emit('update:period', Number(value) as DashboardPeriod);
    }
}
</script>

<template>
    <Card>
        <CardHeader class="flex-row items-start justify-between gap-4">
            <div>
                <CardTitle class="text-base">Daily executed value</CardTitle>
                <p class="mt-1 text-sm text-muted-foreground">
                    Trade value only, excluding commission.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span v-if="refreshing" class="text-xs text-muted-foreground">
                    Refreshing…
                </span>
                <Select
                    :model-value="String(period)"
                    @update:model-value="selectPeriod"
                >
                    <SelectTrigger
                        class="h-10 w-24"
                        aria-label="Activity period"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="7">7 days</SelectItem>
                        <SelectItem value="30">30 days</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="flex h-64 items-end gap-2">
                <Skeleton
                    v-for="index in period"
                    :key="index"
                    class="h-full flex-1"
                />
            </div>
            <div
                v-else
                class="flex h-64 items-end gap-1.5 border-b border-border px-1 pt-4 sm:gap-2"
                role="img"
                :aria-label="
                    'Executed trade value for the last ' + period + ' days'
                "
            >
                <div
                    v-for="(entry, index) in values"
                    :key="entry.date"
                    data-test="activity-column"
                    class="group flex min-w-0 flex-1 flex-col items-center justify-end gap-2"
                    :title="entry.label + ': ' + entry.value + ' USD'"
                >
                    <div
                        data-test="activity-plot"
                        class="flex h-52 w-full flex-none items-end"
                    >
                        <div
                            data-test="activity-bar"
                            class="w-full rounded-t-sm bg-chart-2 transition-[height]"
                            :style="{ height: barHeight(entry.value) }"
                        />
                    </div>
                    <div
                        data-test="activity-label-row"
                        class="flex h-4 w-full flex-none items-start justify-center"
                    >
                        <span
                            v-if="showNarrowLabel(index)"
                            data-test="activity-label"
                            class="truncate text-[10px] text-muted-foreground sm:hidden"
                        >
                            {{ entry.label }}
                        </span>
                        <span
                            v-if="showWideLabel(index)"
                            data-test="activity-label"
                            class="hidden truncate text-[10px] text-muted-foreground sm:inline sm:text-xs"
                        >
                            {{ entry.label }}
                        </span>
                    </div>
                </div>
            </div>
            <p
                v-if="
                    !loading && values.every((entry) => entry.value === '0.00')
                "
                class="mt-4 text-center text-sm text-muted-foreground"
            >
                No executions in this period.
            </p>
        </CardContent>
    </Card>
</template>
