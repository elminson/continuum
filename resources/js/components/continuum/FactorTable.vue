<script setup lang="ts">
import { computed } from 'vue';
import { formatNumber, formatPercent } from '@/lib/format';
import type { Factor } from '@/types/continuum';

const props = defineProps<{ factors: Factor[] }>();

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

const groups = computed(() => {
    const map = new Map<
        string,
        {
            key: string;
            label: string;
            unit: string;
            reference: string;
            allYear: Factor | null;
            months: Factor[];
        }
    >();

    for (const factor of props.factors) {
        const key = `${factor.method}:${factor.reference}`;

        if (!map.has(key)) {
            map.set(key, {
                key,
                label: factor.method_label,
                unit: factor.unit,
                reference: factor.reference,
                allYear: null,
                months: [],
            });
        }

        const group = map.get(key) as NonNullable<ReturnType<typeof map.get>>;

        if (factor.month === null) {
            group.allYear = factor;
        } else {
            group.months.push(factor);
        }
    }

    return [...map.values()];
});

function describe(factor: Factor): string {
    if (factor.unit === 'MW') {
        return `τ = ${formatNumber(factor.value, 1)} MW`;
    }

    return `× ${formatNumber(factor.value, 3)}`;
}
</script>

<template>
    <div v-if="groups.length > 0" class="space-y-4">
        <div
            v-for="group in groups"
            :key="group.key"
            class="rounded-lg border border-border p-3"
        >
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <p class="text-sm font-medium">
                        {{ group.label }} →
                        {{ group.reference.toUpperCase() }}-equivalent
                    </p>
                    <p
                        v-if="group.allYear"
                        class="text-xs text-muted-foreground"
                    >
                        All-year {{ describe(group.allYear) }}
                        <span
                            v-if="
                                group.unit !== 'MW' &&
                                group.allYear.ci95[0] !== null
                            "
                        >
                            [{{ formatNumber(group.allYear.ci95[0], 3) }},
                            {{ formatNumber(group.allYear.ci95[1], 3) }}] 95%
                            bootstrap
                        </span>
                        · {{ group.allYear.sample_days }} overlap days ({{
                            group.allYear.overlap[0]
                        }}
                        → {{ group.allYear.overlap[1] }})
                    </p>
                </div>
                <p v-if="group.allYear?.validation" class="text-xs">
                    <span class="font-medium">Holdout:</span>
                    MAE
                    {{
                        formatNumber(group.allYear.validation.harmonized.mae, 1)
                    }}
                    vs naive
                    {{ formatNumber(group.allYear.validation.naive.mae, 1) }}
                    <span
                        :class="
                            group.allYear.validation.mae_improvement > 0
                                ? 'text-lime-600 dark:text-lime-400'
                                : 'text-red-500'
                        "
                    >
                        ({{
                            group.allYear.validation.mae_improvement > 0
                                ? '−'
                                : '+'
                        }}{{
                            formatPercent(
                                Math.abs(
                                    group.allYear.validation.mae_improvement,
                                ),
                            )
                        }}
                        error)
                    </span>
                    · r =
                    {{ formatNumber(group.allYear.validation.correlation, 2) }}
                    · {{ group.allYear.validation.test_days }} test days
                </p>
            </div>
            <div
                v-if="group.months.length > 0"
                class="mt-2 grid grid-cols-6 gap-1 text-[11px] sm:grid-cols-12"
            >
                <div
                    v-for="factor in group.months"
                    :key="factor.month ?? 0"
                    class="rounded bg-muted/50 px-1 py-1 text-center"
                    :title="`${factor.sample_days} days`"
                >
                    <div class="text-muted-foreground">
                        {{ MONTHS[(factor.month ?? 1) - 1] }}
                    </div>
                    <div class="font-medium tabular-nums">
                        {{
                            group.unit === 'MW'
                                ? formatNumber(factor.value, 0)
                                : formatNumber(factor.value, 2)
                        }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <p v-else class="text-sm text-muted-foreground">
        Not calibrated yet: this area needs days observed by both MODIS and
        VIIRS.
    </p>
</template>
