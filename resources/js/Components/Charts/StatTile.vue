<script setup>
// Label · value · optional change vs the previous period (green when the change is good).
import { computed } from 'vue';

const props = defineProps({
    label: String,
    value: [String, Number],
    delta: { type: Number, default: null },   // % (or points when unit = 'pts')
    unit: { type: String, default: '%' },
    upIsGood: { type: Boolean, default: true },
    hint: String,
    tone: { type: String, default: 'navy' },   // navy | warn | bad | good
});
const good = computed(() => (props.delta == null ? null : props.delta === 0 ? null : (props.delta > 0) === props.upIsGood));
const toneClass = { navy: 'text-lwe-navy', warn: 'text-amber-700', bad: 'text-red-700', good: 'text-green-800' };
</script>

<template>
    <div class="card">
        <p class="text-xs text-lwe-muted">{{ label }}</p>
        <p class="mt-1 text-2xl font-bold tabular-nums" :class="toneClass[tone]">{{ value }}</p>
        <p v-if="delta != null" class="mt-0.5 text-xs font-semibold" :class="good === null ? 'text-slate-500' : good ? 'text-green-700' : 'text-red-700'">
            <span aria-hidden="true">{{ delta > 0 ? '▲' : delta < 0 ? '▼' : '■' }}</span> {{ delta > 0 ? '+' : '' }}{{ delta }}{{ unit }} <span class="font-normal text-lwe-muted">vs previous period</span>
        </p>
        <p v-else-if="hint" class="mt-0.5 text-xs text-lwe-muted">{{ hint }}</p>
    </div>
</template>
