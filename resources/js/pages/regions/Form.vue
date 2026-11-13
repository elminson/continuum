<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FireMap from '@/components/continuum/FireMap.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { home } from '@/routes';
import { index, store, update } from '@/routes/regions';
import type { Region } from '@/types/continuum';

const props = defineProps<{ region: Region | null }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Overview', href: home() },
            { title: 'Areas of interest', href: index() },
        ],
    },
});

const form = useForm({
    name: props.region?.name ?? '',
    description: props.region?.description ?? '',
    west: props.region?.bbox[0] ?? -70,
    south: props.region?.bbox[1] ?? -35,
    east: props.region?.bbox[2] ?? -55,
    north: props.region?.bbox[3] ?? -15,
    color: props.region?.color ?? '#f97316',
});

const map = ref<InstanceType<typeof FireMap> | null>(null);

const bbox = computed<[number, number, number, number]>(() => [
    Number(form.west),
    Number(form.south),
    Number(form.east),
    Number(form.north),
]);

const valid = computed(
    () =>
        bbox.value.every((v) => Number.isFinite(v)) &&
        bbox.value[0] < bbox.value[2] &&
        bbox.value[1] < bbox.value[3],
);

function setBox(bounds: [number, number, number, number]): void {
    form.west = Number(bounds[0].toFixed(3));
    form.south = Number(bounds[1].toFixed(3));
    form.east = Number(bounds[2].toFixed(3));
    form.north = Number(bounds[3].toFixed(3));
}

function useMapView(): void {
    const bounds = map.value?.getBounds();

    if (bounds) {
        setBox(bounds);
    }
}

function submit(): void {
    if (props.region) {
        form.put(update(props.region.slug).url);
    } else {
        form.post(store().url);
    }
}
</script>

<template>
    <Head
        :title="
            region
                ? $t('Edit :name', { name: region.name })
                : $t('New area of interest')
        "
    />

    <div class="grid gap-4 p-4 lg:grid-cols-2">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <h1 class="text-xl font-semibold">
                    {{
                        region
                            ? $t('Edit area of interest')
                            : $t('New area of interest')
                    }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'Draw a rectangle on the map or type its corners in degrees.',
                        )
                    }}
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="name">{{ $t('Name') }}</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    required
                    maxlength="120"
                    :placeholder="$t('e.g. Pantanal wetlands')"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="description">{{ $t('Description') }}</Label>
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="2"
                    class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                    :placeholder="
                        $t('Why this area matters, season, land use…')
                    "
                />
                <InputError :message="form.errors.description" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-2">
                    <Label for="west">{{ $t('West (lon)') }}</Label>
                    <Input
                        id="west"
                        v-model="form.west"
                        type="number"
                        step="0.001"
                        min="-180"
                        max="180"
                        required
                    />
                    <InputError :message="form.errors.west" />
                </div>
                <div class="grid gap-2">
                    <Label for="east">{{ $t('East (lon)') }}</Label>
                    <Input
                        id="east"
                        v-model="form.east"
                        type="number"
                        step="0.001"
                        min="-180"
                        max="180"
                        required
                    />
                    <InputError :message="form.errors.east" />
                </div>
                <div class="grid gap-2">
                    <Label for="south">{{ $t('South (lat)') }}</Label>
                    <Input
                        id="south"
                        v-model="form.south"
                        type="number"
                        step="0.001"
                        min="-90"
                        max="90"
                        required
                    />
                    <InputError :message="form.errors.south" />
                </div>
                <div class="grid gap-2">
                    <Label for="north">{{ $t('North (lat)') }}</Label>
                    <Input
                        id="north"
                        v-model="form.north"
                        type="number"
                        step="0.001"
                        min="-90"
                        max="90"
                        required
                    />
                    <InputError :message="form.errors.north" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="color">{{ $t('Colour') }}</Label>
                <input
                    id="color"
                    v-model="form.color"
                    type="color"
                    class="h-9 w-16 cursor-pointer rounded-md border border-input bg-transparent"
                />
                <InputError :message="form.errors.color" />
            </div>

            <div class="flex flex-wrap gap-2">
                <Button type="submit" :disabled="form.processing || !valid">{{
                    region ? $t('Save changes') : $t('Create and harmonize')
                }}</Button>
                <Button type="button" variant="outline" @click="useMapView">{{
                    $t('Use map view')
                }}</Button>
            </div>
        </form>

        <div>
            <FireMap
                v-if="valid"
                ref="map"
                :bbox="bbox"
                :date="region?.summary?.last_date ?? null"
                preview
                draw
                height="h-[520px]"
                @drawn="setBox"
            />
        </div>
    </div>
</template>
