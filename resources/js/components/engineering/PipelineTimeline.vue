<script setup lang="ts">
import type { Component } from 'vue';
import { vReveal } from '@/composables/useReveal';

export type PipelineStage = {
    icon: Component;
    title: string;
    body: string;
    code: string;
    metric: string;
    metricLabel: string;
};

defineProps<{ stages: PipelineStage[] }>();
</script>

<template>
    <ol class="pipeline relative">
        <li
            v-for="(stage, index) in stages"
            :key="stage.title"
            v-reveal="index * 60"
            class="relative grid gap-3 pb-12 pl-14 last:pb-0 md:grid-cols-[minmax(0,15rem)_minmax(0,1fr)_auto] md:gap-8 md:pl-20"
        >
            <span
                class="absolute top-0 left-0 grid size-10 place-items-center rounded-xl border border-border bg-card text-lime-600 shadow-sm md:left-4 dark:text-lime-400"
            >
                <component
                    :is="stage.icon"
                    class="size-5"
                    :stroke-width="1.75"
                />
            </span>

            <div class="pt-1.5">
                <h3 class="text-lg font-semibold tracking-tight">
                    {{ stage.title }}
                </h3>
                <code
                    class="mt-1 block truncate font-mono text-xs text-muted-foreground"
                    >{{ stage.code }}</code
                >
            </div>

            <p
                class="max-w-[60ch] pt-1.5 text-sm leading-relaxed text-muted-foreground md:text-base"
            >
                {{ stage.body }}
            </p>

            <div class="pt-1 md:min-w-[9rem] md:text-right">
                <p
                    class="font-mono text-2xl font-semibold tracking-tight tabular-nums"
                >
                    {{ stage.metric }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ stage.metricLabel }}
                </p>
            </div>
        </li>
    </ol>
</template>

<style scoped>
/* The spine: a quiet track, and a lime line drawn down it as the section
   scrolls past (scroll-driven animation; a static full line elsewhere). */
.pipeline::before,
.pipeline::after {
    content: '';
    position: absolute;
    top: 1.25rem;
    bottom: 1.25rem;
    left: 1.25rem;
    width: 2px;
    border-radius: 999px;
    translate: -50% 0;
}

.pipeline::before {
    background: var(--color-border);
}

.pipeline::after {
    background: linear-gradient(#38bdf8, #a3e635 55%, #f97316);
    transform-origin: top;
}

@media (min-width: 768px) {
    .pipeline::before,
    .pipeline::after {
        left: 2.25rem;
    }
}

@supports (animation-timeline: view()) {
    @media (prefers-reduced-motion: no-preference) {
        .pipeline::after {
            animation: draw linear both;
            animation-timeline: view();
            animation-range: entry 20% cover 70%;
        }
    }
}

@keyframes draw {
    from {
        transform: scaleY(0);
    }
    to {
        transform: scaleY(1);
    }
}
</style>
