<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { AlertTriangle, Loader2, RefreshCw } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import SectionCard from '@/components/continuum/SectionCard.vue';
import { Badge } from '@/components/ui/badge';
import {
    formatCompact,
    formatDate,
    formatDateTime,
    formatNumber,
    formatRelative,
} from '@/lib/format';
import { t } from '@/lib/i18n';
import { home, ingestion } from '@/routes';
import type { IngestStatus, IntakeRun } from '@/types/continuum';

type Area = { slug: string; name: string; color: string };

const props = defineProps<{
    runs: {
        data: IntakeRun[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { status: IngestStatus | null; area: string | null };
    areas: Area[];
    summary: {
        completed: number;
        running: number;
        failed: number;
        rows_inserted: number;
    };
    batches: {
        id: string;
        total: number;
        done: number;
        failed: number;
        started_at: string;
        percent: number;
        region: Area;
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Overview', href: home() },
            { title: 'Ingestion', href: ingestion() },
        ],
    },
});

const status = ref(props.filters.status ?? '');
const area = ref(props.filters.area ?? '');

watch([status, area], () => {
    router.get(
        ingestion().url,
        { status: status.value || undefined, area: area.value || undefined },
        { preserveState: true, replace: true },
    );
});

// Live while there is something to watch: only the first page moves.
const live = computed(() => props.runs.current_page === 1);
const { start, stop } = usePoll(
    10_000,
    { only: ['runs', 'summary', 'batches'] },
    { keepAlive: true, autoStart: false },
);
watch(live, (value) => (value ? start() : stop()), { immediate: true });

const statusVariant = (
    value: IngestStatus,
): 'default' | 'secondary' | 'destructive' =>
    value === 'failed'
        ? 'destructive'
        : value === 'running'
          ? 'default'
          : 'secondary';

const statusLabel = (value: IngestStatus): string =>
    value === 'running'
        ? t('Running')
        : value === 'failed'
          ? t('Failed')
          : t('Done');

const runWindow = (run: IntakeRun): string => {
    if (!run.date_from) {
        return '';
    }

    if (!run.date_to || run.date_to === run.date_from) {
        return formatDate(run.date_from);
    }

    return t(':from to :to', {
        from: formatDate(run.date_from),
        to: formatDate(run.date_to),
    });
};
</script>

<template>
    <Head :title="$t('Ingestion')" />

    <div class="flex flex-col gap-4 p-4">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ $t('Ingestion') }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'Every pull from NASA FIRMS: area backfills, daily public feeds and bundled samples.',
                        )
                    }}
                </p>
            </div>
            <p
                v-if="live"
                class="flex items-center gap-1.5 text-xs text-muted-foreground"
            >
                <RefreshCw class="size-3" /> {{ $t('Updates every 10 s') }}
            </p>
        </header>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
            >
                <p class="text-xs font-medium text-muted-foreground">
                    {{ $t('Rows stored') }}
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ formatCompact(summary.rows_inserted) }}
                </p>
            </div>
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
            >
                <p class="text-xs font-medium text-muted-foreground">
                    {{ $t('Completed runs') }}
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ formatNumber(summary.completed) }}
                </p>
            </div>
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
            >
                <p class="text-xs font-medium text-muted-foreground">
                    {{ $t('Running now') }}
                </p>
                <p
                    class="mt-1 flex items-center gap-2 text-2xl font-semibold tabular-nums"
                >
                    {{ formatNumber(summary.running) }}
                    <Loader2
                        v-if="summary.running > 0 || batches.length > 0"
                        class="size-4 animate-spin text-sky-500"
                    />
                </p>
            </div>
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
            >
                <p class="text-xs font-medium text-muted-foreground">
                    {{ $t('Failed runs') }}
                </p>
                <p
                    class="mt-1 text-2xl font-semibold tabular-nums"
                    :class="summary.failed > 0 ? 'text-destructive' : ''"
                >
                    {{ formatNumber(summary.failed) }}
                </p>
            </div>
        </div>

        <SectionCard
            v-if="batches.length > 0"
            :title="$t('Backfills in progress')"
            :description="
                $t(
                    'Full FIRMS history, fetched newest first; harmonization follows automatically.',
                )
            "
        >
            <ul class="space-y-3">
                <li
                    v-for="batch in batches"
                    :key="batch.id"
                    class="grid gap-1.5 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)_auto] sm:items-center sm:gap-4"
                >
                    <span class="truncate text-sm font-medium">
                        <span
                            class="mr-2 inline-block size-2.5 rounded-full align-middle"
                            :style="{ backgroundColor: batch.region.color }"
                        />{{ batch.region.name.split(' (')[0] }}
                    </span>
                    <div class="h-2 overflow-hidden rounded-full bg-sky-500/15">
                        <div
                            class="h-full rounded-full bg-sky-500 transition-[width] duration-700"
                            :style="{ width: `${batch.percent}%` }"
                        />
                    </div>
                    <span
                        class="text-xs text-muted-foreground tabular-nums"
                        :title="
                            $t('Started :date', {
                                date: formatDateTime(batch.started_at),
                            })
                        "
                    >
                        {{ formatNumber(batch.done) }} /
                        {{
                            $t(':count windows', {
                                count: formatNumber(batch.total),
                            })
                        }}
                        ·
                        {{ formatNumber(batch.percent, 1) }}%
                        <span v-if="batch.failed > 0" class="text-destructive">
                            ·
                            {{
                                $t(':count failed', { count: batch.failed })
                            }}</span
                        >
                    </span>
                </li>
            </ul>
        </SectionCard>

        <div class="flex flex-wrap gap-3">
            <select
                v-model="status"
                :aria-label="$t('Filter by status')"
                class="h-8 rounded-md border border-input bg-background px-2 text-sm"
            >
                <option value="">{{ $t('All statuses') }}</option>
                <option value="running">{{ $t('Running') }}</option>
                <option value="completed">{{ $t('Completed') }}</option>
                <option value="failed">{{ $t('Failed') }}</option>
            </select>
            <select
                v-model="area"
                :aria-label="$t('Filter by area')"
                class="h-8 rounded-md border border-input bg-background px-2 text-sm"
            >
                <option value="">{{ $t('All areas and feeds') }}</option>
                <option value="public-feeds">
                    {{ $t('Public feeds only') }}
                </option>
                <option v-for="a in areas" :key="a.slug" :value="a.slug">
                    {{ a.name }}
                </option>
            </select>
        </div>

        <SectionCard
            :title="
                $t(':count ingest run(s)', { count: formatNumber(runs.total) })
            "
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-sm">
                    <thead>
                        <tr
                            class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground dark:border-sidebar-border"
                        >
                            <th class="py-2 pr-3 font-medium">
                                {{ $t('Status') }}
                            </th>
                            <th class="py-2 pr-3 font-medium">
                                {{ $t('Started') }}
                            </th>
                            <th class="py-2 pr-3 font-medium">
                                {{ $t('Area') }}
                            </th>
                            <th class="py-2 pr-3 font-medium">
                                {{ $t('Source') }}
                            </th>
                            <th class="py-2 pr-3 font-medium">
                                {{ $t('Dates') }}
                            </th>
                            <th class="py-2 pr-3 text-right font-medium">
                                {{ $t('Rows') }}
                            </th>
                            <th class="py-2 text-right font-medium">
                                {{ $t('Time') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="run in runs.data" :key="run.id">
                            <tr
                                class="border-b border-sidebar-border/40 align-top dark:border-sidebar-border/60"
                            >
                                <td class="py-2 pr-3">
                                    <Badge
                                        :variant="statusVariant(run.status)"
                                        class="w-16 justify-center"
                                    >
                                        <Loader2
                                            v-if="run.status === 'running'"
                                            class="animate-spin"
                                        />
                                        {{ statusLabel(run.status) }}
                                    </Badge>
                                </td>
                                <td
                                    class="py-2 pr-3 whitespace-nowrap text-muted-foreground"
                                    :title="formatDateTime(run.started_at)"
                                >
                                    {{ formatRelative(run.started_at) }}
                                </td>
                                <td class="py-2 pr-3">
                                    <Link
                                        v-if="run.region"
                                        :href="`/regions/${run.region.slug}`"
                                        class="font-medium whitespace-nowrap hover:underline"
                                    >
                                        <span
                                            class="mr-1.5 inline-block size-2 rounded-full align-middle"
                                            :style="{
                                                backgroundColor:
                                                    run.region.color,
                                            }"
                                        />{{ run.region.name.split(' (')[0] }}
                                    </Link>
                                    <span
                                        v-else
                                        class="text-muted-foreground"
                                        >{{ $t('Global') }}</span
                                    >
                                </td>
                                <td class="py-2 pr-3">{{ run.label }}</td>
                                <td
                                    class="py-2 pr-3 whitespace-nowrap text-muted-foreground"
                                >
                                    {{ runWindow(run) || '-' }}
                                </td>
                                <td
                                    class="py-2 pr-3 text-right whitespace-nowrap tabular-nums"
                                >
                                    <span class="font-medium">{{
                                        formatNumber(run.rows_inserted)
                                    }}</span>
                                    <span
                                        v-if="run.rows_skipped > 0"
                                        class="block text-xs text-muted-foreground"
                                        >{{
                                            $t(':count skipped', {
                                                count: formatNumber(
                                                    run.rows_skipped,
                                                ),
                                            })
                                        }}</span
                                    >
                                </td>
                                <td
                                    class="py-2 text-right whitespace-nowrap text-muted-foreground tabular-nums"
                                >
                                    {{
                                        run.duration_s === null
                                            ? '-'
                                            : `${run.duration_s}s`
                                    }}
                                </td>
                            </tr>
                            <tr v-if="run.error">
                                <td
                                    colspan="7"
                                    class="pb-2 text-xs text-destructive"
                                >
                                    <AlertTriangle class="mr-1 inline size-3" />
                                    {{ run.error }}
                                </td>
                            </tr>
                        </template>
                        <tr v-if="runs.data.length === 0">
                            <td
                                colspan="7"
                                class="py-8 text-center text-sm text-muted-foreground"
                            >
                                {{ $t('No ingest runs match these filters.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav
                v-if="runs.last_page > 1"
                class="mt-3 flex flex-wrap gap-1 text-xs"
                :aria-label="$t('Pagination')"
            >
                <template v-for="link in runs.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        class="rounded border px-2 py-1"
                        :class="
                            link.active
                                ? 'bg-foreground text-background'
                                : 'hover:bg-muted'
                        "
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="rounded border px-2 py-1 text-muted-foreground"
                        v-html="link.label"
                    />
                </template>
            </nav>
        </SectionCard>
    </div>
</template>
