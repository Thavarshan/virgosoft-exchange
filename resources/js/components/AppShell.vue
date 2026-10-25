<script setup lang="ts">
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

withDefaults(
    defineProps<{
        variant?: AppVariant;
    }>(),
    {
        variant: 'sidebar',
    },
);
</script>

<template>
    <SidebarProvider>
        <slot />
    </SidebarProvider>
</template>
