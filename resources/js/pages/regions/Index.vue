<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import RegionCard from '@/components/continuum/RegionCard.vue';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';
import { create, index } from '@/routes/regions';
import type { Region } from '@/types/continuum';

defineProps<{ regions: Region[]; canCreate: boolean }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Overview', href: home() },
            { title: 'Areas of interest', href: index() },
        ],
    },
});
</script>

<template>
    <Head title="Areas of interest" />

    <div class="flex flex-col gap-4 p-4">
        <header class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h1 class="text-xl font-semibold">Areas of interest</h1>
                <p class="text-sm text-muted-foreground">
                    Each area gets its own calendar, calibration and anomaly
                    screening.
                </p>
            </div>
            <Button v-if="canCreate" as-child>
                <Link :href="create()"><Plus class="size-4" /> New area</Link>
            </Button>
            <p v-else class="text-xs text-muted-foreground">
                Sign in to draw your own area of interest.
            </p>
        </header>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <RegionCard
                v-for="region in regions"
                :key="region.id"
                :region="region"
            />
        </div>
    </div>
</template>
