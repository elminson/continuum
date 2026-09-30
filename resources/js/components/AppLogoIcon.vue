<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- Continuum mark: MODIS (sky) → harmonized (lime) → VIIRS (orange) arc around a hotspot. -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 256 256"
        :class="className"
        v-bind="$attrs"
        aria-hidden="true"
    >
        <defs>
            <linearGradient id="continuum-arc" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#38bdf8" />
                <stop offset="0.5" stop-color="#a3e635" />
                <stop offset="1" stop-color="#f97316" />
            </linearGradient>
            <radialGradient id="continuum-ember" cx="0.4" cy="0.35" r="0.7">
                <stop offset="0" stop-color="#fff7ed" />
                <stop offset="0.35" stop-color="#fdba74" />
                <stop offset="1" stop-color="#f97316" />
            </radialGradient>
        </defs>
        <path
            d="M178.14 68.25 A78 78 0 1 0 178.14 187.75"
            fill="none"
            stroke="url(#continuum-arc)"
            stroke-width="30"
            stroke-linecap="round"
        />
        <circle cx="142" cy="128" r="34" fill="#f97316" opacity="0.22" />
        <circle cx="142" cy="128" r="21" fill="url(#continuum-ember)" />
    </svg>
</template>
