<script setup>
// Single-series line with a light area wash, end dot, crosshair + tooltip. null = no data (gap).
import { computed, ref } from 'vue';
import { compact, fmt, ticks } from './format';

const props = defineProps({
    points: { type: Array, required: true },
    kind: { type: String, default: 'num' },
    title: { type: String, required: true },
});

const W = 640; const H = 220; const L = 48; const R = 14; const T = 14; const B = 28;
const max = computed(() => Math.max(0, ...props.points.map((p) => p.value ?? 0)));
const yTicks = computed(() => ticks(max.value, props.kind === 'pct'));
const top = computed(() => yTicks.value[yTicks.value.length - 1] || 1);
const step = computed(() => (W - L - R) / Math.max(1, props.points.length - 1));
const x = (i) => L + step.value * i;
const y = (v) => T + (H - T - B) * (1 - v / top.value);
const every = computed(() => Math.ceil(props.points.length / 8));
// Every n-th label, plus the last one — dropping the one just before it so they never overlap.
const showLabel = (i) => i === props.points.length - 1 || (i % every.value === 0 && props.points.length - 1 - i >= Math.max(1, every.value / 2 + 0.5));

// Lines are drawn per run of non-null values.
const runs = computed(() => {
    const out = []; let cur = [];
    props.points.forEach((p, i) => { if (p.value == null) { if (cur.length) out.push(cur); cur = []; } else cur.push([x(i), y(p.value)]); });
    if (cur.length) out.push(cur);
    return out;
});
const linePath = (run) => run.map(([a, b], i) => `${i ? 'L' : 'M'}${a},${b}`).join(' ');
const areaPath = (run) => `${linePath(run)} L${run[run.length - 1][0]},${H - B} L${run[0][0]},${H - B} Z`;
const lastIndex = computed(() => { for (let i = props.points.length - 1; i >= 0; i--) if (props.points[i].value != null) return i; return -1; });
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
            <text v-for="(p, i) in points" v-show="showLabel(i)" :key="'l' + i" :x="x(i)" :y="H - 8" :text-anchor="i === points.length - 1 ? 'end' : i === 0 ? 'start' : 'middle'" class="fill-slate-500" font-size="11">{{ p.label }}</text>
            <template v-for="(run, r) in runs" :key="r">
                <path :d="areaPath(run)" fill="#19376D" opacity="0.1" />
                <path :d="linePath(run)" fill="none" stroke="#19376D" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
            </template>
            <line v-if="hover != null" :x1="x(hover)" :x2="x(hover)" :y1="T" :y2="H - B" stroke="#94A3B8" stroke-width="1" />
            <circle v-if="hover != null && tip?.value != null" :cx="x(hover)" :cy="y(tip.value)" r="5" fill="#19376D" stroke="#fff" stroke-width="2" />
            <template v-if="lastIndex >= 0">
                <circle :cx="x(lastIndex)" :cy="y(points[lastIndex].value)" r="5" fill="#F28C28" stroke="#fff" stroke-width="2" />
                <text :x="x(lastIndex) - 8" :y="y(points[lastIndex].value) - 10" text-anchor="end" class="fill-slate-800" font-size="11" font-weight="600">{{ fmt(points[lastIndex].value, kind) }}</text>
            </template>
            <rect v-for="(p, i) in points" :key="'h' + i" :x="x(i) - step / 2" :y="T" :width="step" :height="H - T - B" fill="transparent" @mouseenter="hover = i" @mouseleave="hover = null" />
        </svg>
        <div v-if="tip" class="pointer-events-none absolute -translate-x-1/2 rounded-lg bg-slate-900 px-3 py-1.5 text-xs text-white shadow-lg" :style="{ left: (x(hover) / W) * 100 + '%', top: '0' }">
            <b>{{ tip.label }}</b> · {{ tip.value == null ? 'no classes' : fmt(tip.value, kind) }}
        </div>
        <figcaption class="sr-only">{{ title }}: {{ points.map((p) => `${p.label} ${fmt(p.value, kind)}`).join(', ') }}</figcaption>
    </figure>
</template>
