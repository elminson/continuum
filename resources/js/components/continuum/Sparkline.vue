<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        values: (number | null)[];
        color?: string;
        height?: number;
    }>(),
    { color: '#f97316', height: 36 },
);

const width = 160;

const path = computed(() => {
    const points = props.values
        .map((value, index) => ({ value, index }))
        .filter(
            (point): point is { value: number; index: number } =>
                point.value !== null,
        );

    if (points.length < 2) {
        return '';
    }

    const max = Math.max(...points.map((point) => point.value), 1);
    const stepX = width / Math.max(props.values.length - 1, 1);

    return points
        .map((point, i) => {
            const x = point.index * stepX;
            const y =
                props.height - (point.value / max) * (props.height - 4) - 2;

            return `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
});
</script>

<template>
    <svg
        :viewBox="`0 0 ${width} ${height}`"
        :height="height"
        class="w-full"
        preserveAspectRatio="none"
        aria-hidden="true"
    >
        <path
            v-if="path"
            :d="path"
            fill="none"
            :stroke="color"
            stroke-width="2"
            stroke-linejoin="round"
            stroke-linecap="round"
        />
        <text
            v-else
            x="0"
            :y="height / 2 + 4"
            class="fill-muted-foreground text-[10px]"
        >
            no recent data
        </text>
    </svg>
</template>
