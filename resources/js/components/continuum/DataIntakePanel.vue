<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    Database,
    Loader2,
    RefreshCw,
} from '@lucide/vue';
import { computed } from 'vue';
import {
    formatCompact,
    formatDate,
    formatDateTime,
    formatNumber,
    formatRelative,
    INSTRUMENT_COLORS,
} from '@/lib/format';
import type {
    DataIntake,
    IngestStatus,
    IntakeArea,
    IntakeRun,
} from '@/types/continuum';
import { ingestion } from '@/routes';

const props = defineProps<{ intake: DataIntake }>();

const areaState = (area: IntakeArea): { label: string; tone: string } => {
    if (area.backfill) {
        return { label: 'Backfilling', tone: 'text-sky-500' };
    }
    if (area.runs.running > 0) {
        return { label: 'Ingesting', tone: 'text-sky-500' };
    }
    if (area.rows_inserted === 0) {
        return { label: 'No data yet', tone: 'text-muted-foreground' };
    }
    if (area.history.percent >= 99.5) {
        return { label: 'Full history', tone: 'text-lime-500' };
    }
    if (area.history.windows_done === 0) {
        return { label: 'Samples only', tone: 'text-amber-500' };
    }

    return { label: 'Partial history', tone: 'text-amber-500' };
};

const modisShare = (rows: { modis: number; viirs: number }): number => {
    const total = rows.modis + rows.viirs;

    return total > 0 ? (100 * rows.modis) / total : 0;
};

const totals = computed(() => props.intake.totals);
const feed = computed(() => props.intake.public_feed);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div
                class="rounded-lg border border-sidebar-border/70 bg-background/60 p-3 dark:border-sidebar-border"
            >
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Rows pulled
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ formatCompact(totals.rows_inserted) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    <span :style="{ color: INSTRUMENT_COLORS.modis }"
                        >{{ formatCompact(totals.modis_rows) }} MODIS</span
                    >
                    ·
                    <span :style="{ color: INSTRUMENT_COLORS.viirs }"
                        >{{ formatCompact(totals.viirs_rows) }} VIIRS</span
                    >
                </p>
            </div>
            <div
                class="rounded-lg border border-sidebar-border/70 bg-background/60 p-3 dark:border-sidebar-border"
            >
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    History fetched
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ formatNumber(totals.history_percent, 1) }}%
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    of every FIRMS window across
                    {{ totals.areas }} public areas
                </p>
            </div>
            <div
                class="rounded-lg border border-sidebar-border/70 bg-background/60 p-3 dark:border-sidebar-border"
            >
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    In flight
                </p>
                <p
                    class="mt-1 flex items-center gap-2 text-2xl font-semibold tabular-nums"
                >
                    {{ totals.active_backfills }}
                    <Loader2
                        v-if="
                            totals.active_backfills > 0 ||
                            totals.running_runs > 0
                        "
                        class="size-4 animate-spin text-sky-500"
                    />
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    backfills · {{ totals.running_runs }} running,
                    {{ totals.failed_runs }} failed runs
                </p>
            </div>
            <div
                class="rounded-lg border border-sidebar-border/70 bg-background/60 p-3 dark:border-sidebar-border"
            >
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Daily public feed
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ formatCompact(feed.rows_inserted) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    <template v-if="feed.last_run_at"
                        >last {{ formatRelative(feed.last_run_at) }} ·
                    </template>
                    <template v-if="feed.next_run_at"
                        >next {{ formatRelative(feed.next_run_at) }}</template
                    >
                    <template v-else>not scheduled</template>
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead>
                    <tr
                        class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground dark:border-sidebar-border"
                    >
                        <th class="py-2 pr-3 font-medium">Public area</th>
                        <th class="py-2 pr-3 font-medium">Rows pulled</th>
                        <th class="py-2 pr-3 font-medium">Sensor mix</th>
                        <th class="py-2 pr-3 font-medium">History</th>
                        <th class="py-2 pr-3 font-medium">Dates covered</th>
                        <th class="py-2 pr-3 font-medium">Last run</th>
                        <th class="py-2 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="area in intake.areas"
                        :key="area.id"
                        class="border-b border-sidebar-border/40 align-top last:border-0 dark:border-sidebar-border/60"
                    >
                        <td class="py-2.5 pr-3">
                            <Link
                                :href="area.links.web"
                                class="font-medium hover:underline"
                            >
                                <span
                                    class="mr-2 inline-block size-2.5 rounded-full align-middle"
                                    :style="{ backgroundColor: area.color }"
                                />{{ area.name.split(' (')[0] }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                {{ formatCompact(area.area_km2) }} km² ·
                                {{ area.runs.completed }} runs
                                <span
                                    v-if="area.runs.failed > 0"
                                    class="text-destructive"
                                    >· {{ area.runs.failed }} failed</span
                                >
                            </p>
                        </td>
                        <td class="py-2.5 pr-3 tabular-nums">
                            <span class="font-medium">{{
                                formatNumber(area.rows_inserted)
                            }}</span>
                            <p class="text-xs text-muted-foreground">
                                {{ formatCompact(area.rows_skipped) }} skipped
                            </p>
                        </td>
                        <td class="py-2.5 pr-3">
                            <div
                                class="flex h-2 w-28 overflow-hidden rounded-full bg-muted"
                                :title="`${formatNumber(area.by_instrument.modis)} MODIS · ${formatNumber(area.by_instrument.viirs)} VIIRS`"
                            >
                                <div
                                    class="h-full"
                                    :style="{
                                        width: `${modisShare(area.by_instrument)}%`,
                                        backgroundColor:
                                            INSTRUMENT_COLORS.modis,
                                    }"
                                />
                                <div
                                    class="h-full flex-1"
                                    :style="{
                                        backgroundColor:
                                            INSTRUMENT_COLORS.viirs,
                                    }"
                                />
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ formatCompact(area.by_instrument.modis) }} /
                                {{ formatCompact(area.by_instrument.viirs) }}
                            </p>
                        </td>
                        <td class="py-2.5 pr-3">
                            <div class="flex items-center gap-2">
                                <div
                                    class="h-2 w-28 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full bg-lime-500"
                                        :style="{
                                            width: `${area.history.percent}%`,
                                        }"
                                    />
                                </div>
                                <span class="text-xs tabular-nums"
                                    >{{
                                        formatNumber(area.history.percent, 1)
                                    }}%</span
                                >
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ formatNumber(area.history.windows_done) }} /
                                {{ formatNumber(area.history.windows_total) }}
                                windows since
                                {{ area.history.earliest_possible.slice(0, 4) }}
                            </p>
                            <div
                                v-if="area.backfill"
                                class="mt-1.5 text-xs text-sky-600 dark:text-sky-400"
                                :title="`Started ${formatDateTime(area.backfill.started_at)}`"
                            >
                                <div class="flex items-center gap-2">
                                    <div
                                        class="h-1.5 w-28 overflow-hidden rounded-full bg-sky-500/20"
                                    >
                                        <div
                                            class="h-full rounded-full bg-sky-500 transition-[width]"
                                            :style="{
                                                width: `${area.backfill.percent}%`,
                                            }"
                                        />
                                    </div>
                                    <span class="tabular-nums"
                                        >{{ area.backfill.done }} /
                                        {{ area.backfill.total }}</span
                                    >
                                </div>
                                <span
                                    >live backfill<span
                                        v-if="area.backfill.failed > 0"
                                        class="text-destructive"
                                    >
                                        ·
                                        {{ area.backfill.failed }} failed</span
                                    ></span
                                >
                            </div>
                        </td>
                        <td class="py-2.5 pr-3 text-xs">
                            <template v-if="area.first_date"
                                >{{ formatDate(area.first_date) }} →
                                {{ formatDate(area.last_date) }}</template
                            >
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="py-2.5 pr-3 text-xs">
                            <template v-if="area.last_run_at">
                                {{ formatRelative(area.last_run_at) }}
                                <p class="text-muted-foreground">
                                    {{ formatDateTime(area.last_run_at) }}
                                </p>
                            </template>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="py-2.5 text-xs">
                            <span
                                class="inline-flex items-center gap-1 font-medium"
                                :class="areaState(area).tone"
                            >
                                <Loader2
                                    v-if="
                                        area.backfill || area.runs.running > 0
                                    "
                                    class="size-3.5 animate-spin"
                                />
                                <CheckCircle2
                                    v-else-if="area.history.percent >= 99.5"
                                    class="size-3.5"
                                />
                                <Database v-else class="size-3.5" />
                                {{ areaState(area).label }}
                            </span>
                            <p
                                v-if="area.last_error"
                                class="mt-1 max-w-[220px] truncate text-destructive"
                                :title="area.last_error"
                            >
                                <AlertTriangle class="inline size-3" />
                                {{ area.last_error }}
                            </p>
                        </td>
                    </tr>
                    <tr v-if="intake.areas.length === 0">
                        <td
                            colspan="7"
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No public areas yet. Run
                            <code class="rounded bg-muted px-1"
                                >php artisan continuum:demo</code
                            >
                            to seed the presets.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
            <div
                class="flex flex-col items-start justify-center gap-2 rounded-lg border border-dashed border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <p class="text-sm">
                    Every ingest run, with filters and live backfill progress,
                    now has its own page.
                </p>
                <Link
                    :href="ingestion()"
                    class="inline-flex items-center gap-1 text-sm font-medium hover:underline"
                    >Open ingestion <ArrowRight class="size-4"
                /></Link>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold text-muted-foreground uppercase"
                >
                    Public feeds (key-free)
                </h4>
                <ul class="space-y-1.5 text-xs">
                    <li
                        v-for="item in feed.latest"
                        :key="item.feed"
                        class="flex items-center justify-between gap-2"
                    >
                        <span class="min-w-0 truncate">{{
                            item.feed.replace('Public feed · ', '')
                        }}</span>
                        <span
                            class="shrink-0 text-muted-foreground tabular-nums"
                            >{{ formatCompact(item.rows_inserted) }} ·
                            {{ formatRelative(item.started_at) }}</span
                        >
                    </li>
                    <li
                        v-if="feed.latest.length === 0"
                        class="text-muted-foreground"
                    >
                        No public feed pulled yet. The scheduler pulls the last
                        24 hours of the global feeds every hour.
                    </li>
                </ul>
                <p
                    class="mt-3 flex items-center gap-1.5 text-[11px] text-muted-foreground"
                >
                    <RefreshCw class="size-3" />
                    Updates every 20 s · generated
                    {{ formatRelative(intake.generated_at) }}
                </p>
            </div>
        </div>
    </div>
</template>
