<script setup>
// Single-series column chart: thin columns, rounded data-end, hairline grid, hover tooltip.
import { computed, ref } from 'vue';
import { compact, fmt, ticks } from './format';

const props = defineProps({
    points: { type: Array, required: true },   // [{ label, value }]
    kind: { type: String, default: 'num' },    // num | pkr | pct
    title: { type: String, required: true },    // for screen readers
    highlightLast: { type: Boolean, default: true },
});

const W = 640; const H = 220; const L = 48; const R = 8; const T = 14; const B = 28;
const max = computed(() => Math.max(0, ...props.points.map((p) => p.value ?? 0)));
const yTicks = computed(() => ticks(max.value, props.kind === 'pct'));
const top = computed(() => yTicks.value[yTicks.value.length - 1] || 1);
const band = computed(() => (W - L - R) / Math.max(1, props.points.length));
const bw = computed(() => Math.min(24, band.value * 0.6));
const y = (v) => T + (H - T - B) * (1 - (v ?? 0) / top.value);
const x = (i) => L + band.value * i + band.value / 2;
const every = computed(() => Math.ceil(props.points.length / 8));
// Every n-th label, plus the last one — dropping the one just before it so they never overlap.
const showLabel = (i) => i === props.points.length - 1 || (i % every.value === 0 && props.points.length - 1 - i >= Math.max(1, every.value / 2 + 0.5));
const bar = (i, v) => {
    const x0 = x(i) - bw.value / 2; const y0 = y(v); const h = H - B - y0; const r = Math.min(4, h, bw.value / 2);
    if (h <= 0) return '';
    return `M${x0},${H - B} V${y0 + r} Q${x0},${y0} ${x0 + r},${y0} H${x0 + bw.value - r} Q${x0 + bw.value},${y0} ${x0 + bw.value},${y0 + r} V${H - B} Z`;
};
const hover = ref(null);
const tip = computed(() => (hover.value == null ? null : props.points[hover.value]));
</script>

<template>
    <figure class="relative">
        <svg :viewBox="`0 0 ${W} ${H}`" class="h-auto w-full" role="img" :aria-label="title">
            <g v-for="t in yTicks" :key="t">
                <line :x1="L" :x2="W - R" :y1="y(t)" :y2="y(t)" stroke="#E2E8F0" stroke-width="1" />
                <text :x="L - 8" :y="y(t) + 4" text-anchor="end" class="fill-slate-500" font-size="11">{{ kind === 'pct' ? t + '%' : compact(t) }}</text>
            </g>
            <template v-for="(p, i) in points" :key="p.label">
                <path :d="bar(i, p.value)" :fill="highlightLast && i === points.length - 1 ? '#F28C28' : '#19376D'" :opacity="hover == null || hover === i ? 1 : 0.55" />
                <text v-if="showLabel(i)" :x="x(i)" :y="H - 8" text-anchor="middle" class="fill-slate-500" font-size="11">{{ p.label }}</text>
                <rect :x="x(i) - band / 2" :y="T" :width="band" :height="H - T - B" fill="transparent" @mouseenter="hover = i" @mouseleave="hover = null" />
            </template>
            <text v-if="points.length" :x="x(points.length - 1)" :y="y(points[points.length - 1].value) - 6" text-anchor="middle" class="fill-slate-800" font-size="11" font-weight="600">{{ kind === 'pkr' ? compact(points[points.length - 1].value) : fmt(points[points.length - 1].value, kind) }}</text>
        </svg>
        <div v-if="tip" class="pointer-events-none absolute -translate-x-1/2 rounded-lg bg-slate-900 px-3 py-1.5 text-xs text-white shadow-lg"
            :style="{ left: (x(hover) / W) * 100 + '%', top: '0' }">
            <b>{{ tip.label }}</b> · {{ fmt(tip.value, kind) }}
        </div>
        <figcaption class="sr-only">{{ title }}: {{ points.map((p) => `${p.label} ${fmt(p.value, kind)}`).join(', ') }}</figcaption>
    </figure>
</template>
