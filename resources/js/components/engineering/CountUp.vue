<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { onFirstVisible, prefersReducedMotion } from '@/composables/useReveal';
import { intlLocale } from '@/lib/i18n';

/**
 * A number that counts up from zero the first time it is seen, so the eye
 * lands on the figures in the order they are read. Static under reduced
 * motion. The final value is always rendered for screen readers.
 */
const props = withDefaults(
    defineProps<{
        value: number;
        format?: (value: number) => string;
        duration?: number;
    }>(),
    {
        format: (value: number) =>
            Math.round(value).toLocaleString(intlLocale()),
        duration: 1400,
    },
);

const root = ref<HTMLElement | null>(null);
const shown = ref(0);
let frame = 0;
let cleanup: () => void = () => {};

function run(): void {
    if (prefersReducedMotion()) {
        shown.value = props.value;

        return;
    }

    const start = performance.now();
    const target = props.value;

    const step = (now: number) => {
        const t = Math.min(1, (now - start) / props.duration);
        // easeOutExpo: fast start, gentle settle on the real number.
        const eased = t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
        shown.value = target * eased;

        if (t < 1) {
            frame = requestAnimationFrame(step);
        }
    };

    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(step);
}

onMounted(() => {
    if (root.value) {
        cleanup = onFirstVisible(root.value, run);
    }
});

onBeforeUnmount(() => {
    cleanup();
    cancelAnimationFrame(frame);
});

watch(
    () => props.value,
    () => run(),
);
</script>

<template>
    <span ref="root" class="tabular-nums">
        <span aria-hidden="true">{{ format(shown) }}</span>
        <span class="sr-only">{{ format(value) }}</span>
    </span>
</template>
