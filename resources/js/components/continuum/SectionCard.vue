<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <section
        class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
    >
        <header class="mb-3 flex flex-wrap items-start justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold">{{ title }}</h3>
                <p v-if="description" class="text-xs text-muted-foreground">
                    {{ description }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <slot name="actions" />
            </div>
        </header>
        <slot />
    </section>
</template>
