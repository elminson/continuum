<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import SeverityBadge from '@/components/continuum/SeverityBadge.vue';
import { Sparkles } from '@lucide/vue';
import { formatNumber, formatPercent } from '@/lib/format';
import { show } from '@/routes/regions';
import type { FireAlert } from '@/types/continuum';

defineProps<{ alerts: FireAlert[]; showRegion?: boolean }>();

const CAUSE_SHORT: Record<string, string> = {
    seasonal: 'a stronger usual season',
    outbreak: 'an exceptional outbreak',
    data_gap: 'too little history to judge',
    sensor: 'a change of satellite coverage',
};
</script>

<template>
    <ul v-if="alerts.length > 0" class="divide-y divide-border">
        <li
            v-for="alert in alerts"
            :key="alert.id"
            class="flex flex-wrap items-start gap-3 py-2.5 text-sm"
        >
            <SeverityBadge :severity="alert.severity" />
            <div class="min-w-0 flex-1">
                <p class="leading-snug">
                    <Link
                        v-if="showRegion && alert.region"
                        :href="show(alert.region.slug)"
                        class="font-medium hover:underline"
                        :style="{ color: alert.region.color }"
                    >
                        {{ alert.region.name }} ·
                    </Link>
                    {{ alert.message }}
                </p>
                <p class="text-xs text-muted-foreground">
                    z = {{ formatNumber(alert.z_score, 1) }} ·
                    {{ formatNumber(alert.percentile, 0) }}th percentile ·
                    expected {{ formatNumber(alert.expected) }} ±
                    {{ formatNumber(alert.spread) }} · {{ alert.method }} /
                    {{ alert.reference }}-equivalent
                </p>
                <div
                    v-if="alert.triage"
                    class="mt-2 rounded-lg border border-lime-500/25 bg-lime-500/5 px-3 py-2 text-xs"
                >
                    <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span
                            class="inline-flex items-center gap-1 font-medium text-lime-700 dark:text-lime-400"
                            :title="`Advisory reading by ${alert.triage.model}, a self-hosted model. It never changes the statistics.`"
                        >
                            <Sparkles class="size-3" /> AI reading
                        </span>
                        <span>
                            Likely
                            <span class="font-medium">{{
                                CAUSE_SHORT[alert.triage.cause] ??
                                alert.triage.cause
                            }}</span>
                        </span>
                        <span
                            :class="
                                alert.triage.attention === 'act_now'
                                    ? 'font-medium text-red-600 dark:text-red-400'
                                    : 'text-muted-foreground'
                            "
                            >{{ alert.triage.attention_label }}</span
                        >
                        <span
                            v-if="alert.triage.baseline_reliable === false"
                            class="text-amber-600 dark:text-amber-400"
                            >Thin baseline</span
                        >
                        <span
                            v-else-if="alert.triage.artifact_probability >= 0.5"
                            class="text-amber-600 dark:text-amber-400"
                            >Artifact risk
                            {{
                                formatPercent(alert.triage.artifact_probability)
                            }}</span
                        >
                    </p>
                    <p
                        v-if="alert.triage.rationale"
                        class="mt-1 text-muted-foreground"
                    >
                        {{ alert.triage.rationale }}
                    </p>
                </div>
            </div>
        </li>
    </ul>
    <p v-else class="text-sm text-muted-foreground">
        No unusual periods flagged. Anomaly screening needs at least three years
        of harmonized data for the same week or month; backfill the archive to
        enable it.
    </p>
</template>
