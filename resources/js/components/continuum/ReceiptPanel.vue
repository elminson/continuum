<script setup lang="ts">
import { formatNumber } from '@/lib/format';
import type { Receipt } from '@/types/continuum';

defineProps<{ receipt: Receipt }>();
</script>

<template>
    <div class="space-y-3 text-xs">
        <dl class="grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2">
            <dt class="text-muted-foreground">{{ $t('Generated') }}</dt>
            <dd>{{ receipt.generated_at }} · {{ receipt.application }}</dd>
            <dt class="text-muted-foreground">{{ $t('Area') }}</dt>
            <dd>
                {{ receipt.region.name }} · {{ $t('bbox') }}
                {{ receipt.region.bbox.join(', ') }} ·
                {{ formatNumber(receipt.region.area_km2) }} km²
            </dd>
            <dt class="text-muted-foreground">{{ $t('Grid') }}</dt>
            <dd>
                {{
                    $t(':km km harmonization cells', {
                        km: receipt.grid.harmonization_cell_km,
                    })
                }}
                · {{ receipt.grid.cell_day_definition }}
            </dd>
            <dt class="text-muted-foreground">{{ $t('Method') }}</dt>
            <dd>
                {{ receipt.method.label }} →
                {{
                    $t(':reference-equivalent', {
                        reference: receipt.method.reference,
                    })
                }}
                · v{{ receipt.method.version }}
                <span v-if="receipt.method.factor !== null">
                    · {{ $t('factor') }}
                    {{ formatNumber(receipt.method.factor, 3) }}
                    <span
                        v-if="
                            receipt.method.ci95 &&
                            receipt.method.ci95[0] !== null
                        "
                        >[{{ formatNumber(receipt.method.ci95[0], 3) }},
                        {{ formatNumber(receipt.method.ci95[1], 3) }}]</span
                    >
                    ·
                    {{
                        $t(':count overlap days (:from → :to)', {
                            count: receipt.method.overlap_days,
                            from: receipt.method.overlap?.[0] ?? '',
                            to: receipt.method.overlap?.[1] ?? '',
                        })
                    }}
                </span>
                <span v-else> · {{ $t('not calibrated') }}</span>
            </dd>
            <dt class="text-muted-foreground">{{ $t('Sources') }}</dt>
            <dd>
                {{ receipt.sources.modis }}; {{ receipt.sources.viirs }}.
                {{ $t('Static heat sources excluded.') }}
            </dd>
        </dl>
        <details>
            <summary class="cursor-pointer text-muted-foreground">
                {{
                    $t('Ingest runs (:count) and caveats', {
                        count: receipt.sources.ingest_runs.length,
                    })
                }}
            </summary>
            <ul class="mt-1 list-inside list-disc text-muted-foreground">
                <li
                    v-for="run in receipt.sources.ingest_runs"
                    :key="run.source + (run.from ?? '')"
                >
                    {{ run.source }} · {{ run.instrument ?? '?' }} ·
                    {{ run.from }} → {{ run.to }} ·
                    {{ $t(':count rows', { count: formatNumber(run.rows) }) }}
                </li>
            </ul>
            <ul class="mt-2 list-inside list-disc">
                <li v-for="caveat in receipt.caveats" :key="caveat">
                    {{ caveat }}
                </li>
            </ul>
        </details>
    </div>
</template>
