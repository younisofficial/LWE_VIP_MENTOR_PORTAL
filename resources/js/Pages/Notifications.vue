<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Lwe/Pagination.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';

defineProps({ items: Object });
</script>

<template>
    <AppLayout title="Notifications">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="page-title">Notifications</h1>
            <Link :href="route('notifications.read-all')" method="post" as="button" class="btn-outline btn-sm">Mark all read</Link>
        </div>
        <EmptyState v-if="!items.data.length" title="No notifications yet" />
        <div v-else class="overflow-hidden rounded-2xl border border-lwe-line bg-white">
            <a v-for="n in items.data" :key="n.id" :href="route('notifications.open', n.id)"
                class="block border-b border-slate-100 px-5 py-4 hover:bg-slate-50" :class="{ 'bg-amber-50/60': !n.read }">
                <span class="block font-semibold">{{ n.title }}</span>
                <span class="block text-sm text-slate-600">{{ n.body }}</span>
                <span class="mt-1 block text-xs text-slate-400">{{ n.time }}</span>
            </a>
        </div>
        <Pagination :links="items.links" />
    </AppLayout>
</template>
