<script setup lang="ts">
type Props = {
    label: string;
    value: string;
    hint?: string;
    accent?: string;
};

withDefaults(defineProps<Props>(), { hint: '', accent: '' });
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
    >
        <p
            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
        >
            {{ label }}
        </p>
        <p
            class="mt-1 text-2xl font-semibold tabular-nums"
            :style="accent ? { color: accent } : undefined"
        >
            {{ value }}
        </p>
        <p v-if="hint" class="mt-1 text-xs text-muted-foreground">{{ hint }}</p>
    </div>
</template>
