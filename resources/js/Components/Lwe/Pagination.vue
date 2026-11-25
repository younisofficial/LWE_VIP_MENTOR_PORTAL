<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({ links: { type: Array, default: () => [] } });
</script>

<template>
    <nav v-if="links.length > 3" aria-label="Pages" class="mt-4 flex flex-wrap gap-1">
        <template v-for="(link, i) in links" :key="i">
            <span v-if="!link.url" class="rounded-lg px-3 py-2 text-sm text-slate-400" v-html="link.label" />
            <Link v-else :href="link.url" preserve-scroll
                class="rounded-lg px-3 py-2 text-sm"
                :class="link.active ? 'bg-lwe-navy text-white' : 'bg-white text-slate-700 hover:bg-slate-100'"
                v-html="link.label" />
        </template>
    </nav>
</template>
