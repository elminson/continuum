<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import SeverityBadge from '@/components/continuum/SeverityBadge.vue';
import { formatNumber } from '@/lib/format';
import { show } from '@/routes/regions';
import type { FireAlert } from '@/types/continuum';

defineProps<{ alerts: FireAlert[]; showRegion?: boolean }>();
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
            </div>
        </li>
    </ul>
    <p v-else class="text-sm text-muted-foreground">
        No unusual periods flagged. Anomaly screening needs at least three years
        of harmonized data for the same week or month; backfill the archive to
        enable it.
    </p>
</template>
