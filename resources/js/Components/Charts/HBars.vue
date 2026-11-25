<script setup>
// Ranked horizontal bars (one series): label, thin bar, value at the tip.
import { computed } from 'vue';
import { fmt } from './format';

const props = defineProps({ items: { type: Array, required: true }, kind: { type: String, default: 'num' }, title: { type: String, required: true } });
const max = computed(() => Math.max(1, ...props.items.map((i) => i.value ?? 0)));
const total = computed(() => props.items.reduce((t, i) => t + (i.value ?? 0), 0));
</script>

<template>
    <figure>
        <figcaption class="sr-only">{{ title }}</figcaption>
        <p v-if="!items.length" class="text-sm text-lwe-muted">No data in this period.</p>
        <ul class="space-y-3">
            <li v-for="i in items" :key="i.label" :title="`${i.label}: ${fmt(i.value, kind)}${total ? ' (' + Math.round((i.value * 100) / total) + '%)' : ''}`">
                <div class="mb-1 flex justify-between gap-2 text-sm"><span class="text-slate-700">{{ i.label }}</span><span class="font-semibold text-slate-900">{{ fmt(i.value, kind) }}<span v-if="total" class="ml-1 text-xs font-normal text-lwe-muted">{{ Math.round((i.value * 100) / total) }}%</span></span></div>
                <div class="h-2.5 rounded-full bg-slate-100"><div class="h-full rounded-full bg-lwe-navy2" :style="{ width: Math.max(2, (i.value / max) * 100) + '%' }" /></div>
            </li>
        </ul>
    </figure>
</template>
