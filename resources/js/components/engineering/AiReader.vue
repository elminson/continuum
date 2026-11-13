<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Braces,
    Check,
    ClipboardCheck,
    HardDrive,
    Lock,
    RefreshCcw,
    Scale,
    Tag,
    X,
} from '@lucide/vue';
import { computed } from 'vue';
import CountUp from '@/components/engineering/CountUp.vue';
import { vReveal } from '@/composables/useReveal';
import { formatDate, formatNumber } from '@/lib/format';
import { t } from '@/lib/i18n';
import { index as alertsIndex } from '@/routes/alerts';

type Reading = {
    baseline_reliable: boolean | null;
    artifact_risk: number;
    cause: string;
    attention: string;
    rationale: string | null;
};

type Expected = {
    baseline_reliable: boolean;
    cause: string;
    attention: string;
};

export type AiShowcase = {
    model: string;
    provider: string;
    instructions: string;
    causes: Record<string, string>;
    attention: Record<string, string>;
    alerts: number;
    read: number;
    act_now: number;
    thin_baseline: number;
    by_cause: Record<string, number>;
    last_read_at: string | null;
    example: {
        region: string;
        region_slug: string;
        message: string;
        state: string;
        reading: Reading;
        triaged_at: string;
    } | null;
    evaluation: {
        model: string;
        cases: number;
        scores: {
            passed: number;
            baseline_reliable: number;
            cause: number;
            attention: number;
            median_seconds: number | null;
        };
        ran_at: string;
        results: {
            name: string;
            want: Expected;
            got: Expected | null;
            checks: Record<keyof Expected, boolean>;
            passed: boolean;
        }[];
    } | null;
};

const props = defineProps<{ ai: AiShowcase }>();

const example = computed(() => props.ai.example);
const evaluation = computed(() => props.ai.evaluation);
const pct = (value: number) => `${formatNumber(value * 100, 0)}%`;

const CAUSE_SHORT: Record<string, string> = {
    seasonal: 'Stronger usual season',
    outbreak: 'Exceptional outbreak',
    data_gap: 'Too little history',
    sensor: 'Sensor change',
};

// The rubric's bullet lines, exactly as the model receives them.
const rubric = computed(() =>
    props.ai.instructions
        .split('\n- ')
        .slice(1)
        .map((line) => line.replace(/\s+/g, ' ').trim())
        .map((line) => ({
            key: line.split(':')[0],
            text: line.split(':').slice(1).join(':').trim(),
        })),
);

const json = computed(() =>
    example.value ? JSON.stringify(example.value.reading, null, 2) : '',
);

const guardrails = computed(() => [
    {
        icon: Scale,
        title: t('Statistics decide, AI reads'),
        body: t(
            'A period is flagged by the robust z-score first. The reading never changes an alert, a calendar or a calibration factor.',
        ),
    },
    {
        icon: Braces,
        title: t('Answers fit a schema'),
        body: t(
            'Output is constrained to a JSON schema: fixed choices for cause and attention, a bounded risk, one sentence. No free-form claims.',
        ),
    },
    {
        icon: ClipboardCheck,
        title: t('Graded before it is trusted'),
        body: t(
            'Every model or prompt change runs the labelled benchmark through the same code. The score, misses included, is published here.',
        ),
    },
    {
        icon: Tag,
        title: t('Labelled as advice'),
        body: t(
            'Every reading names its model and is marked as an AI reading on the alert and in the public API.',
        ),
    },
    {
        icon: HardDrive,
        title: t('Open weights, our hardware'),
        body: t(
            'An open-weights model on our own machine, reached through a token-gated Cloudflare Tunnel. No alert goes to a paid AI vendor.',
        ),
    },
    {
        icon: RefreshCcw,
        title: t('Deterministic and fail-safe'),
        body: t(
            'Temperature 0, so the same numbers give the same reading. If the model is offline, alerts still publish without one.',
        ),
    },
]);
</script>

<template>
    <div class="flex flex-col gap-12">
        <!-- In and out: one real reading. -->
        <div class="grid gap-4 lg:grid-cols-2">
            <figure
                v-reveal
                class="min-w-0 overflow-hidden rounded-2xl border border-border bg-muted/40"
            >
                <div
                    class="flex items-center justify-between gap-3 border-b border-border px-5 py-3 text-xs"
                >
                    <span class="font-medium">{{
                        $t('What the analyst receives')
                    }}</span>
                    <span class="font-mono text-muted-foreground">{{
                        ai.model
                    }}</span>
                </div>
                <div class="space-y-4 p-5 text-[13px] leading-relaxed">
                    <div>
                        <p class="mb-2 font-mono text-xs text-muted-foreground">
                            {{ $t('rubric (system instructions)') }}
                        </p>
                        <ul class="space-y-1.5">
                            <li
                                v-for="line in rubric"
                                :key="line.key"
                                class="text-muted-foreground"
                            >
                                <span class="font-mono text-foreground">{{
                                    line.key
                                }}</span
                                >: {{ line.text }}
                            </li>
                        </ul>
                    </div>
                    <div class="border-t border-border pt-4">
                        <p class="mb-2 font-mono text-xs text-muted-foreground">
                            {{ $t("state, built from the alert's numbers") }}
                        </p>
                        <p v-if="example" class="font-mono">
                            {{ example.state }}
                        </p>
                        <p v-else class="text-muted-foreground">
                            {{
                                $t(
                                    'The first reading appears here once the analyst has read a flagged period.',
                                )
                            }}
                        </p>
                    </div>
                </div>
            </figure>

            <div v-reveal="120" class="flex min-w-0 flex-col gap-4">
                <figure
                    class="overflow-hidden rounded-2xl border border-lime-500/30 bg-card"
                >
                    <div
                        class="flex items-center justify-between gap-3 border-b border-border px-5 py-3 text-xs"
                    >
                        <span class="font-medium">{{
                            $t('What it returns')
                        }}</span>
                        <span class="font-mono text-muted-foreground">{{
                            $t('schema-checked JSON')
                        }}</span>
                    </div>
                    <pre
                        v-if="example"
                        class="overflow-x-auto p-5 font-mono text-[13px] leading-relaxed whitespace-pre-wrap"
                    ><code>{{ json }}</code></pre>
                    <p v-else class="p-5 text-sm text-muted-foreground">
                        {{ $t('No reading yet.') }}
                    </p>
                </figure>

                <div
                    v-if="example"
                    class="rounded-2xl border border-border bg-card p-5"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ $t('Shown on the alert as') }}
                    </p>
                    <p class="mt-2 flex flex-wrap gap-2 text-sm">
                        <span
                            class="rounded-md bg-lime-500/15 px-2 py-0.5 font-medium text-lime-800 dark:text-lime-300"
                            >{{
                                CAUSE_SHORT[example.reading.cause]
                                    ? $t(CAUSE_SHORT[example.reading.cause])
                                    : example.reading.cause
                            }}</span
                        >
                        <span
                            class="rounded-md px-2 py-0.5 font-medium"
                            :class="
                                example.reading.attention === 'act_now'
                                    ? 'bg-red-500/15 text-red-700 dark:text-red-300'
                                    : 'bg-muted'
                            "
                            >{{ ai.attention[example.reading.attention] }}</span
                        >
                        <span class="rounded-md bg-muted px-2 py-0.5">{{
                            example.reading.baseline_reliable
                                ? $t('Baseline reliable')
                                : $t('Baseline too thin')
                        }}</span>
                    </p>
                    <p class="mt-3 text-sm text-muted-foreground">
                        {{
                            $t(':area, read :date.', {
                                area: example.region.split(' (')[0],
                                date: formatDate(
                                    example.triaged_at.slice(0, 10),
                                ),
                            })
                        }}
                        {{ example.message }}
                    </p>
                </div>
            </div>
        </div>

        <!-- The benchmark, misses included. -->
        <div
            v-if="evaluation"
            v-reveal
            class="grid gap-8 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)]"
        >
            <div>
                <h3 class="text-xl font-semibold tracking-tight">
                    {{ $t('Graded before it is trusted') }}
                </h3>
                <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                    {{
                        $t(
                            ':count labelled cases, run through the same code as the live readings.',
                            { count: evaluation.cases },
                        )
                    }}
                </p>
                <p
                    class="mt-6 font-mono text-6xl font-semibold tracking-tighter"
                >
                    <CountUp :value="evaluation.scores.passed" />/{{
                        evaluation.cases
                    }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{ $t('cases fully right') }}
                </p>
                <dl class="mt-6 space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">
                            {{ $t('Baseline check') }}
                        </dt>
                        <dd class="font-mono font-semibold">
                            {{ pct(evaluation.scores.baseline_reliable) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ $t('Cause') }}</dt>
                        <dd class="font-mono font-semibold">
                            {{ pct(evaluation.scores.cause) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">
                            {{ $t('Attention') }}
                        </dt>
                        <dd class="font-mono font-semibold">
                            {{ pct(evaluation.scores.attention) }}
                        </dd>
                    </div>
                    <div
                        v-if="evaluation.scores.median_seconds"
                        class="flex justify-between gap-4"
                    >
                        <dt class="text-muted-foreground">
                            {{ $t('Median time') }}
                        </dt>
                        <dd class="font-mono font-semibold">
                            {{
                                formatNumber(
                                    evaluation.scores.median_seconds,
                                    1,
                                )
                            }}
                            s
                        </dd>
                    </div>
                </dl>
                <p class="mt-4 text-xs text-muted-foreground">
                    {{
                        $t(
                            ':model, run :date. Two local models were compared on this suite; the better one reads the live alerts.',
                            {
                                model: evaluation.model,
                                date: formatDate(
                                    evaluation.ran_at.slice(0, 10),
                                ),
                            },
                        )
                    }}
                </p>
            </div>

            <div class="overflow-x-auto rounded-2xl border border-border">
                <table class="w-full min-w-[640px] text-sm">
                    <thead>
                        <tr
                            class="border-b border-border text-left text-xs text-muted-foreground"
                        >
                            <th class="px-4 py-3 font-medium">
                                {{ $t('Case') }}
                            </th>
                            <th class="px-4 py-3 font-medium">
                                {{ $t('Expected') }}
                            </th>
                            <th class="px-4 py-3 font-medium">
                                {{ $t('Model said') }}
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                {{ $t('Result') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="result in evaluation.results"
                            :key="result.name"
                            class="border-b border-border/60 last:border-0"
                            :class="result.passed ? '' : 'bg-amber-500/5'"
                        >
                            <td class="px-4 py-2.5">{{ result.name }}</td>
                            <td
                                class="px-4 py-2.5 font-mono text-xs text-muted-foreground"
                            >
                                {{ result.want.cause }},
                                {{ result.want.attention }}
                            </td>
                            <td class="px-4 py-2.5 font-mono text-xs">
                                <template v-if="result.got">
                                    <span
                                        :class="
                                            result.checks.cause
                                                ? ''
                                                : 'text-amber-600 dark:text-amber-400'
                                        "
                                        >{{ result.got.cause }}</span
                                    >,
                                    <span
                                        :class="
                                            result.checks.attention
                                                ? ''
                                                : 'text-amber-600 dark:text-amber-400'
                                        "
                                        >{{ result.got.attention }}</span
                                    >
                                    <span
                                        v-if="!result.checks.baseline_reliable"
                                        class="text-amber-600 dark:text-amber-400"
                                        >, {{ $t('baseline') }}</span
                                    >
                                </template>
                                <span v-else class="text-destructive">{{
                                    $t('no answer')
                                }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <Check
                                    v-if="result.passed"
                                    class="ml-auto size-4 text-lime-600 dark:text-lime-400"
                                    :aria-label="$t('passed')"
                                />
                                <X
                                    v-else
                                    class="ml-auto size-4 text-amber-600 dark:text-amber-400"
                                    :aria-label="$t('missed')"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Live, on the real alerts. -->
        <div
            v-reveal
            class="grid grid-cols-2 gap-y-8 border-y border-border py-8 md:grid-cols-4"
        >
            <div class="px-2 md:border-r md:border-border md:px-6">
                <p class="font-mono text-3xl font-semibold tracking-tight">
                    <CountUp :value="ai.read" />
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        $t('of :count unusual periods read', {
                            count: formatNumber(ai.alerts),
                        })
                    }}
                </p>
            </div>
            <div class="px-2 md:border-r md:border-border md:px-6">
                <p class="font-mono text-3xl font-semibold tracking-tight">
                    <CountUp :value="ai.act_now" />
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ $t('marked "act now" for a person to review') }}
                </p>
            </div>
            <div class="px-2 md:border-r md:border-border md:px-6">
                <p class="font-mono text-3xl font-semibold tracking-tight">
                    <CountUp :value="ai.thin_baseline" />
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ $t('with a baseline judged too thin to trust') }}
                </p>
            </div>
            <div class="px-2 md:px-6">
                <p class="font-mono text-3xl font-semibold tracking-tight">
                    $0
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ $t('per reading, on our own hardware') }}
                </p>
            </div>
        </div>

        <!-- Guardrails -->
        <div class="grid gap-x-10 gap-y-8 md:grid-cols-2">
            <article
                v-for="(rule, index) in guardrails"
                :key="rule.title"
                v-reveal="index * 60"
                class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4"
            >
                <component
                    :is="rule.icon"
                    class="mt-0.5 size-5 text-lime-600 dark:text-lime-400"
                    :stroke-width="1.75"
                />
                <div>
                    <h3 class="font-semibold tracking-tight">
                        {{ rule.title }}
                    </h3>
                    <p
                        class="mt-1 max-w-[52ch] text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ rule.body }}
                    </p>
                </div>
            </article>
        </div>

        <p
            v-reveal
            class="flex items-start gap-2 text-sm text-muted-foreground"
        >
            <Lock class="mt-0.5 size-4 shrink-0" />
            <span>
                {{
                    $t(
                        'Built on the Laravel AI SDK: an agent with structured output at temperature 0, served by Ollama on our own machine. We tested a pointer-style decision model first; it could not tell a thin baseline from a real event, so it does not read alerts. See every reading on',
                    )
                }}
                <Link
                    :href="alertsIndex()"
                    class="font-medium text-foreground underline underline-offset-4"
                    >{{ $t('Unusual periods') }}</Link
                >.
            </span>
        </p>
    </div>
</template>
