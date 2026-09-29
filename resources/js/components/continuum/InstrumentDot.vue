<script setup lang="ts">
import { INSTRUMENT_COLORS } from '@/lib/format';

defineProps<{
    instrument: 'modis' | 'viirs' | 'harmonized' | 'naive';
    label?: string;
}>();
</script>

<template>
    <span class="inline-flex items-center gap-1.5 text-xs">
        <span
            class="inline-block size-2.5 rounded-full"
            :style="{ backgroundColor: INSTRUMENT_COLORS[instrument] }"
        />
        <span>{{ label ?? instrument.toUpperCase() }}</span>
    </span>
</template>
