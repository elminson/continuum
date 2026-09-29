<script setup lang="ts">
import { computed } from 'vue';
import type { Severity } from '@/types/continuum';

const props = defineProps<{ severity: Severity }>();

const classes = computed(() => {
    switch (props.severity) {
        case 'critical':
            return 'bg-red-500/15 text-red-600 dark:text-red-400 border-red-500/30';
        case 'warning':
            return 'bg-orange-500/15 text-orange-600 dark:text-orange-400 border-orange-500/30';
        default:
            return 'bg-yellow-500/15 text-yellow-700 dark:text-yellow-400 border-yellow-500/30';
    }
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium capitalize"
        :class="classes"
    >
        {{ severity }}
    </span>
</template>
