<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AssetSymbol, TradingMarket } from '@/types/trading';

defineProps<{
    modelValue: AssetSymbol;
    markets: readonly TradingMarket[];
    id?: string;
    describedBy?: string;
    ariaLabel?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: AssetSymbol];
}>();

function updateMarket(value: unknown): void {
    if (value === 'BTC' || value === 'ETH') {
        emit('update:modelValue', value);
    }
}
</script>

<template>
    <Select :model-value="modelValue" @update:model-value="updateMarket">
        <SelectTrigger
            :id="id"
            :aria-label="ariaLabel"
            :aria-describedby="describedBy"
            class="h-10 w-full"
        >
            <SelectValue placeholder="Select a market" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem
                v-for="market in markets"
                :key="market.symbol"
                :value="market.symbol"
            >
                {{ market.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
