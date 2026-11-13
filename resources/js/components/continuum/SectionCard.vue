<script setup lang="ts">
import { ImageDown } from '@lucide/vue';
import { ref } from 'vue';
import { downloadPanelPng } from '@/lib/download';

const props = defineProps<{
    title: string;
    description?: string;
    /** File name (without extension) for the PNG download; omit to hide it. */
    download?: string;
    /** Extra context printed under the title in the PNG, e.g. the date range. */
    downloadNote?: string;
}>();

const body = ref<HTMLDivElement | null>(null);
const failed = ref(false);

function save(): void {
    if (!body.value || !props.download) {
        return;
    }

    failed.value = !downloadPanelPng(body.value, {
        title: props.title,
        subtitle: [props.description, props.downloadNote]
            .filter(Boolean)
            .join(' · '),
        filename: props.download,
    });
}
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
                <button
                    v-if="download"
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-md border border-border text-muted-foreground hover:bg-muted hover:text-foreground"
                    :title="
                        failed
                            ? $t('This view cannot be saved as an image')
                            : $t('Download PNG')
                    "
                    :aria-label="$t('Download :title as PNG', { title })"
                    @click="save"
                >
                    <ImageDown class="size-4" />
                </button>
            </div>
        </header>
        <div ref="body">
            <slot />
        </div>
    </section>
</template>
