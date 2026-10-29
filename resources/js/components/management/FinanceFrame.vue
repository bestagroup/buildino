<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { InvoiceWorkspace } from '../../lib/invoice-workspace';
import '../../../css/finance-workspace.css';
const props = defineProps<{ title: string; resourceKey: string; workspace: InvoiceWorkspace; buildingId: string }>();
const group = computed(() => props.workspace.groups.find(item => item.resources.includes(props.resourceKey)));
const links = computed(() => props.workspace.navigation.filter(item => group.value?.resources.includes(item.key)));
const url = (href: string) => props.buildingId ? href + '?building_id=' + encodeURIComponent(props.buildingId) : href;
</script>
<template>
    <Head :title="title" />
    <div class="ownership-page finance-page" data-finance-page>
        <nav class="finance-breadcrumb" aria-label="مسیر صفحه"><a href="/management">داشبورد</a><span>/</span><span>امور مالی</span><span>/</span><strong>{{ title }}</strong></nav>
        <header class="ownership-hero"><div class="ownership-hero__icon" aria-hidden="true"><svg width="27" height="27" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="5" width="18" height="15" rx="3"/><path d="M17 5V3H6a3 3 0 0 0-3 3m13 5h5v5h-5a2.5 2.5 0 0 1 0-5Z"/></svg></div><div><h2>{{ title }}</h2><p>{{ group?.title }}<template v-if="workspace.buildings.find(item => String(item.id) === buildingId)"> · {{ workspace.buildings.find(item => String(item.id) === buildingId)?.title }}</template></p></div></header>
        <nav class="finance-navigation" aria-label="بخش‌های مالی">
            <div class="finance-tabs"><a v-for="item in links" :key="item.key" :href="url(item.url)" :aria-current="item.key === resourceKey ? 'page' : undefined">{{ item.title }}</a></div>
            <details class="finance-more"><summary>همه بخش‌های مالی</summary><div class="finance-menu"><section v-for="item in workspace.groups.filter(item => workspace.navigation.some(link => item.resources.includes(link.key)))" :key="item.title"><h3>{{ item.title }}</h3><a v-for="link in workspace.navigation.filter(link => item.resources.includes(link.key))" :key="link.key" :href="url(link.url)" :aria-current="link.key === resourceKey ? 'page' : undefined">{{ link.title }}</a></section></div></details>
        </nav>
        <slot />
    </div>
</template>
