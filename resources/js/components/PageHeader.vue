<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <header class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-0.5">
            <h1 class="text-3xl font-semibold tracking-tight">
                {{ title }}
            </h1>
            <p v-if="description" class="mt-2 text-base text-muted-foreground">
                {{ description }}
            </p>
        </div>

        <div v-if="$slots.actions" class="shrink-0">
            <slot name="actions" />
        </div>
    </header>
</template>
