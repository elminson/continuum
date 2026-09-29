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
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        :class="className"
        v-bind="$attrs"
        aria-hidden="true"
    >
        <path
            d="M12 2c1.5 3.5 4.5 5.2 4.5 9.2a4.5 4.5 0 0 1-9 0c0-1.3.4-2.3 1-3.2.3 1.2 1 2 2 2.4C10.2 8.3 11 5.5 12 2Z"
            fill="currentColor"
            opacity="0.95"
        />
        <path
            d="M3 19.5h18M5 16.5h14"
            stroke="currentColor"
            stroke-width="1.6"
            stroke-linecap="round"
            opacity="0.7"
        />
    </svg>
</template>
