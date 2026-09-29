<script setup lang="ts">
import { formatNumber } from '@/lib/format';
import type { Receipt } from '@/types/continuum';

defineProps<{ receipt: Receipt }>();
</script>

<template>
    <div class="space-y-3 text-xs">
        <dl class="grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2">
            <dt class="text-muted-foreground">Generated</dt>
            <dd>{{ receipt.generated_at }} · {{ receipt.application }}</dd>
            <dt class="text-muted-foreground">Area</dt>
            <dd>
                {{ receipt.region.name }} · bbox
                {{ receipt.region.bbox.join(', ') }} ·
                {{ formatNumber(receipt.region.area_km2) }} km²
            </dd>
            <dt class="text-muted-foreground">Grid</dt>
            <dd>
                {{ receipt.grid.harmonization_cell_km }} km harmonization cells
                · {{ receipt.grid.cell_day_definition }}
            </dd>
            <dt class="text-muted-foreground">Method</dt>
            <dd>
                {{ receipt.method.label }} →
                {{ receipt.method.reference }}-equivalent · v{{
                    receipt.method.version
                }}
                <span v-if="receipt.method.factor !== null">
                    · factor {{ formatNumber(receipt.method.factor, 3) }}
                    <span
                        v-if="
                            receipt.method.ci95 &&
                            receipt.method.ci95[0] !== null
                        "
                        >[{{ formatNumber(receipt.method.ci95[0], 3) }},
                        {{ formatNumber(receipt.method.ci95[1], 3) }}]</span
                    >
                    · {{ receipt.method.overlap_days }} overlap days ({{
                        receipt.method.overlap?.[0]
                    }}
                    → {{ receipt.method.overlap?.[1] }})
                </span>
                <span v-else> · not calibrated</span>
            </dd>
            <dt class="text-muted-foreground">Sources</dt>
            <dd>
                {{ receipt.sources.modis }}; {{ receipt.sources.viirs }}. Static
                heat sources excluded.
            </dd>
        </dl>
        <details>
            <summary class="cursor-pointer text-muted-foreground">
                Ingest runs ({{ receipt.sources.ingest_runs.length }}) and
                caveats
            </summary>
            <ul class="mt-1 list-inside list-disc text-muted-foreground">
                <li
                    v-for="run in receipt.sources.ingest_runs"
                    :key="run.source + (run.from ?? '')"
                >
                    {{ run.source }} · {{ run.instrument ?? '?' }} ·
                    {{ run.from }} → {{ run.to }} ·
                    {{ formatNumber(run.rows) }} rows
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
