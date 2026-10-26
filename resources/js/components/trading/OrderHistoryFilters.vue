<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    ORDER_SIDE_LABELS,
    ORDER_STATUS_LABELS,
    TRADING_MARKETS,
    type AssetSymbol,
    type OrderHistoryFilters as HistoryFilters,
    type OrderSide,
    type OrderStatus,
} from '@/types/trading';

const props = defineProps<{
    modelValue: HistoryFilters;
}>();

const emit = defineEmits<{
    'update:modelValue': [filters: HistoryFilters];
}>();

const hasActiveFilters = computed(() =>
    Boolean(
        props.modelValue.symbol !== undefined ||
        props.modelValue.side !== undefined ||
        props.modelValue.status !== undefined,
    ),
);

function updateFilter(key: keyof HistoryFilters, value: string): void {
    const nextFilters = { ...props.modelValue };

    if (value === 'all') {
        delete nextFilters[key];
    } else if (key === 'symbol') {
        nextFilters.symbol = value as AssetSymbol;
    } else if (key === 'side') {
        nextFilters.side = value as OrderSide;
    } else {
        nextFilters.status = Number(value) as OrderStatus;
    }

    emit('update:modelValue', nextFilters);
}

function clearFilters(): void {
    emit('update:modelValue', {});
}
</script>

<template>
    <div class="mb-5 flex flex-col gap-3 rounded-lg border border-border p-4">
        <div>
            <p class="text-sm font-medium">Filter order history</p>
            <p class="mt-1 text-xs text-muted-foreground">
                Narrow your orders by market, side, or status.
            </p>
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="space-y-1.5">
                <label for="order-history-market" class="text-sm font-medium"
                    >Market</label
                >
                <Select
                    :model-value="modelValue.symbol ?? 'all'"
                    @update:model-value="updateFilter('symbol', String($event))"
                >
                    <SelectTrigger
                        id="order-history-market"
                        aria-label="Market filter"
                        class="w-full"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All markets</SelectItem>
                        <SelectItem
                            v-for="market in TRADING_MARKETS"
                            :key="market.symbol"
                            :value="market.symbol"
                        >
                            {{ market.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="space-y-1.5">
                <label for="order-history-side" class="text-sm font-medium"
                    >Side</label
                >
                <Select
                    :model-value="modelValue.side ?? 'all'"
                    @update:model-value="updateFilter('side', String($event))"
                >
                    <SelectTrigger
                        id="order-history-side"
                        aria-label="Side filter"
                        class="w-full"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All sides</SelectItem>
                        <SelectItem
                            v-for="side in ['buy', 'sell'] as OrderSide[]"
                            :key="side"
                            :value="side"
                        >
                            {{ ORDER_SIDE_LABELS[side] }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="space-y-1.5">
                <label for="order-history-status" class="text-sm font-medium"
                    >Status</label
                >
                <Select
                    :model-value="String(modelValue.status ?? 'all')"
                    @update:model-value="updateFilter('status', String($event))"
                >
                    <SelectTrigger
                        id="order-history-status"
                        aria-label="Status filter"
                        class="w-full"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All statuses</SelectItem>
                        <SelectItem
                            v-for="status in [1, 2, 3] as OrderStatus[]"
                            :key="status"
                            :value="String(status)"
                        >
                            {{ ORDER_STATUS_LABELS[status] }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>
        <div v-if="hasActiveFilters" class="flex justify-end">
            <Button
                type="button"
                variant="ghost"
                size="sm"
                @click="clearFilters"
            >
                Clear filters
            </Button>
        </div>
    </div>
</template>
