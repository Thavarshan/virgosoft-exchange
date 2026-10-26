<script setup lang="ts">
import type { PopoverCloseProps } from 'reka-ui';
import { PopoverClose } from 'reka-ui';

defineProps<PopoverCloseProps>();
</script>

<template>
    <PopoverClose data-slot="popover-close">
        <slot />
    </PopoverClose>
</template>
