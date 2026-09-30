<script setup lang="ts">
import type { Component } from 'vue';
import { vReveal } from '@/composables/useReveal';

export type ArchitectureNode = {
    icon: Component;
    title: string;
    detail: string;
};

export type ArchitectureLayer = {
    name: string;
    nodes: ArchitectureNode[];
};

defineProps<{ layers: ArchitectureLayer[] }>();
</script>

<template>
    <div class="grid gap-0 lg:grid-cols-5">
        <div
            v-for="(layer, index) in layers"
            :key="layer.name"
            v-reveal="index * 90"
            class="relative flex flex-col"
        >
            <p
                class="mb-3 text-sm font-medium text-muted-foreground lg:min-h-[1.25rem]"
            >
                {{ layer.name }}
            </p>
            <div class="flex flex-1 flex-col gap-2 lg:pr-6">
                <div
                    v-for="node in layer.nodes"
                    :key="node.title"
                    class="rounded-xl border border-border bg-card/80 p-3 shadow-sm backdrop-blur"
                >
                    <div class="flex items-center gap-2">
                        <component
                            :is="node.icon"
                            class="size-4 shrink-0 text-lime-600 dark:text-lime-400"
                            :stroke-width="1.75"
                        />
                        <p class="text-sm font-semibold">{{ node.title }}</p>
                    </div>
                    <p
                        class="mt-1 text-xs leading-relaxed text-muted-foreground"
                    >
                        {{ node.detail }}
                    </p>
                </div>
            </div>

            <!-- Connector to the next layer; the moving pulse is a data packet in flight. -->
            <div
                v-if="index < layers.length - 1"
                class="connector"
                aria-hidden="true"
            >
                <span
                    class="packet"
                    :style="{ animationDelay: `${index * 0.45}s` }"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.connector {
    position: relative;
    height: 2rem;
    width: 2px;
    margin-left: 1.25rem;
    background: var(--color-border);
    overflow: hidden;
}

.packet {
    position: absolute;
    left: 0;
    width: 100%;
    height: 40%;
    background: linear-gradient(transparent, #a3e635, transparent);
    animation: flow-y 2.2s cubic-bezier(0.45, 0, 0.55, 1) infinite;
}

@media (min-width: 1024px) {
    .connector {
        position: absolute;
        top: 50%;
        right: 0;
        width: 1.5rem;
        height: 2px;
        margin: 0;
    }

    .packet {
        top: 0;
        width: 50%;
        height: 100%;
        background: linear-gradient(90deg, transparent, #a3e635, transparent);
        animation-name: flow-x;
    }
}

@keyframes flow-y {
    from {
        transform: translateY(-100%);
    }
    to {
        transform: translateY(250%);
    }
}

@keyframes flow-x {
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(200%);
    }
}

@media (prefers-reduced-motion: reduce) {
    .packet {
        display: none;
    }
}
</style>
