<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FlaskConical } from '@lucide/vue';
import SeverityBadge from '@/components/continuum/SeverityBadge.vue';
import Sparkline from '@/components/continuum/Sparkline.vue';
import {
    formatCompact,
    formatDate,
    formatNumber,
    formatPercent,
} from '@/lib/format';
import { lab, show } from '@/routes/regions';
import type { Region } from '@/types/continuum';

defineProps<{ region: Region }>();
</script>

<template>
    <article
        class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 transition hover:border-foreground/30 dark:border-sidebar-border"
    >
        <header class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <Link
                    :href="show(region.slug)"
                    class="block truncate font-semibold hover:underline"
                >
                    <span
                        class="mr-2 inline-block size-2.5 rounded-full align-middle"
                        :style="{ backgroundColor: region.color }"
                    />{{ region.name }}
                </Link>
                <p class="text-xs text-muted-foreground">
                    {{ formatNumber(region.area_km2) }} km² ·
                    {{ region.is_preset ? $t('preset') : $t('your area') }}
                    <span v-if="region.summary?.first_date">
                        · {{ formatDate(region.summary.first_date) }} →
                        {{ formatDate(region.summary.last_date) }}</span
                    >
                </p>
            </div>
            <Link
                :href="lab(region.slug)"
                class="rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                :title="$t('Harmonization lab')"
            >
                <FlaskConical class="size-4" />
            </Link>
        </header>

        <Sparkline
            :values="region.summary?.sparkline ?? []"
            :color="region.color"
        />

        <dl v-if="region.summary" class="grid grid-cols-3 gap-2 text-xs">
            <div>
                <dt class="text-muted-foreground">MODIS 7 d</dt>
                <dd class="font-medium tabular-nums">
                    {{
                        formatCompact(
                            region.summary.last_7_days.modis.cell_days,
                        )
                    }}
                    {{ $t('cells') }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">VIIRS 7 d</dt>
                <dd class="font-medium tabular-nums">
                    {{
                        formatCompact(
                            region.summary.last_7_days.viirs.cell_days,
                        )
                    }}
                    {{ $t('cells') }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">{{ $t('Transfer') }}</dt>
                <dd class="font-medium tabular-nums">
                    <template v-if="region.summary.calibration.calibrated">
                        ×{{ formatNumber(region.summary.calibration.ratio, 2) }}
                        <span
                            v-if="
                                region.summary.calibration.mae_improvement !==
                                null
                            "
                            class="text-lime-600 dark:text-lime-400"
                            >−{{
                                formatPercent(
                                    region.summary.calibration.mae_improvement,
                                )
                            }}
                            {{ $t('err') }}</span
                        >
                    </template>
                    <span v-else class="text-muted-foreground">{{
                        $t('pending')
                    }}</span>
                </dd>
            </div>
        </dl>

        <p
            v-if="region.summary?.top_alert"
            class="flex items-center gap-2 text-xs"
        >
            <SeverityBadge :severity="region.summary.top_alert.severity" />
            <span class="truncate text-muted-foreground">{{
                region.summary.top_alert.message
            }}</span>
        </p>
    </article>
</template>
