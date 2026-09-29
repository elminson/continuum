<script setup lang="ts">
import { computed } from 'vue';
import { formatNumber } from '@/lib/format';
import type { CriticalPeriods } from '@/types/continuum';

const props = defineProps<{ critical: CriticalPeriods }>();

function inSeason(index: number): boolean {
    const season = props.critical.season;

    if (!season) {
        return false;
    }

    return season.start <= season.end
        ? index >= season.start && index <= season.end
        : index >= season.start || index <= season.end;
}

const max = computed(() =>
    Math.max(1, ...props.critical.climatology.map((c) => c.typical ?? 0)),
);
</script>

<template>
    <div>
        <div
            class="grid gap-1"
            :style="{
                gridTemplateColumns: `repeat(${critical.climatology.length}, minmax(0, 1fr))`,
            }"
        >
            <div
                v-for="c in critical.climatology"
                :key="c.index"
                class="flex flex-col items-center gap-1"
                :title="`${c.label}: typical ${formatNumber(c.typical)} cell-days (${c.years} yr)`"
            >
                <div
                    class="flex h-16 w-full items-end rounded-sm"
                    :class="
                        inSeason(c.index)
                            ? 'bg-orange-500/15 ring-1 ring-orange-500/40'
                            : 'bg-muted/40'
                    "
                >
                    <div
                        class="w-full rounded-sm"
                        :class="
                            critical.peak?.index === c.index
                                ? 'bg-orange-500'
                                : inSeason(c.index)
                                  ? 'bg-orange-400/80'
                                  : 'bg-zinc-400/60'
                        "
                        :style="{
                            height: `${((c.typical ?? 0) / max) * 100}%`,
                        }"
                    />
                </div>
                <span
                    class="text-[10px]"
                    :class="
                        critical.latest?.index === c.index
                            ? 'font-semibold text-lime-500'
                            : 'text-muted-foreground'
                    "
                >
                    {{ c.label }}
                </span>
            </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs">
            <span v-if="critical.season">
                <span class="font-medium">Fire season:</span>
                {{ critical.season.start_label }} –
                {{ critical.season.end_label }} ({{ critical.season.length }}
                {{ critical.period }}s ≥ 50% of peak)
            </span>
            <span v-if="critical.season?.onset_label">
                <span class="font-medium">Onset:</span>
                {{ critical.season.onset_label }}
            </span>
            <span v-if="critical.peak">
                <span class="font-medium">Peak:</span>
                {{ critical.peak.label }} ({{
                    formatNumber(critical.peak.typical)
                }}
                typical cell-days)
            </span>
            <span v-if="critical.latest">
                <span class="font-medium">Latest:</span>
                {{ critical.latest.period_start }} ·
                {{
                    critical.latest.in_season
                        ? 'inside the critical period'
                        : 'outside the critical period'
                }}
            </span>
            <span class="text-muted-foreground"
                >Climatology from {{ critical.years }} year(s) of harmonized
                data.</span
            >
        </div>
    </div>
</template>
