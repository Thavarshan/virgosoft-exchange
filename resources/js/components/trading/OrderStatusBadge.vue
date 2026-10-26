<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { ORDER_STATUS_LABELS, type OrderStatus } from '@/types/trading';

defineProps<{
    status: OrderStatus;
}>();
</script>

<template>
    <Badge
        variant="outline"
        :class="{
            'border-status-open/40 bg-status-open/10 text-status-open-foreground':
                status === 1,
            'border-status-filled/40 bg-status-filled/10 text-status-filled-foreground':
                status === 2,
            'border-status-cancelled/40 bg-status-cancelled/10 text-status-cancelled-foreground':
                status === 3,
        }"
    >
        {{ ORDER_STATUS_LABELS[status] }}
    </Badge>
</template>
