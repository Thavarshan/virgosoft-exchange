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
}>();

const emit = defineEmits<{
    'update:modelValue': [value: AssetSymbol];
}>();
</script>

<template>
    <Select
        :model-value="modelValue"
        @update:model-value="emit('update:modelValue', $event as AssetSymbol)"
    >
        <SelectTrigger :id="id" :aria-describedby="describedBy" class="w-full">
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
