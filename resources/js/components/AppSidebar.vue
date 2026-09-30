<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BellRing,
    BookOpenText,
    Code2,
    Cpu,
    DatabaseZap,
    FolderGit2,
    Globe2,
    LayoutGrid,
    LogIn,
    MapPinned,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { apiDocs, engineering, home, ingestion, login, method } from '@/routes';
import { index as alerts } from '@/routes/alerts';
import { index as regions } from '@/routes/regions';
import type { NavItem } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth.user);

const mainNavItems: NavItem[] = [
    { title: 'Overview', href: home(), icon: LayoutGrid },
    { title: 'Areas of interest', href: regions(), icon: MapPinned },
    { title: 'Unusual periods', href: alerts(), icon: BellRing },
    { title: 'Ingestion', href: ingestion(), icon: DatabaseZap },
    { title: 'Method', href: method(), icon: BookOpenText },
    { title: 'How it works', href: engineering(), icon: Cpu },
    { title: 'API', href: apiDocs(), icon: Code2 },
];

const footerNavItems: NavItem[] = [
    {
        title: 'NASA FIRMS',
        href: 'https://firms.modaps.eosdis.nasa.gov/',
        icon: Globe2,
    },
    {
        title: 'Space Apps challenge',
        href: 'https://www.spaceappschallenge.org/2026/challenges/harmonization-of-modis-and-viirs-hot-spots/',
        icon: FolderGit2,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="home()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser v-if="user" />
            <SidebarMenu v-else>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        as-child
                        tooltip="Sign in to create areas"
                    >
                        <Link :href="login()">
                            <LogIn />
                            <span>Sign in to add areas</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
