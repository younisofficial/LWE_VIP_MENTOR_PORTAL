<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Lwe/StatusBadge.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';
import Icon from '@/Components/Lwe/Icon.vue';

defineProps({ upcoming: Array, past: Array });

const now = ref(Date.now());
let t;
onMounted(() => (t = setInterval(() => (now.value = Date.now()), 15000)));
onBeforeUnmount(() => clearInterval(t));

const open = (c) => c.status === 'live' || (now.value >= Date.parse(c.join_opens_iso) && now.value <= Date.parse(c.ends_iso));
const opened = ref(null);
const attendanceLabel = { present: 'Present', partial: 'Partial', absent: 'Absent' };
const attendanceClass = { present: 'bg-lwe-greenSoft text-green-900', partial: 'bg-lwe-orangeSoft text-amber-900', absent: 'bg-red-100 text-red-900' };
</script>

<template>
    <AppLayout title="Live classes">
        <h1 class="page-title">Live classes</h1>
        <p class="muted mb-6">Classes open inside the portal. The Join button opens a few minutes before class.</p>

        <h2 class="mb-3 text-lg font-semibold text-lwe-navy">Upcoming</h2>
        <EmptyState v-if="!upcoming.length" title="No classes scheduled yet" text="You will get a WhatsApp reminder before every class." />
        <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div v-for="c in upcoming" :key="c.id" class="card flex flex-col gap-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-xs text-lwe-muted">{{ c.course }} · {{ c.batch }}</p>
                        <h3 class="font-semibold text-lwe-navy">{{ c.title }}</h3>
                        <p class="text-sm">{{ c.label }}<template v-if="c.mentor"> · {{ c.mentor }}</template></p>
                    </div>
                    <span v-if="c.kind === 'recorded'" class="rounded-full bg-violet-100 px-2.5 py-1 text-[11px] font-bold text-violet-900">▶ RECORDED</span>
                    <span v-if="c.status === 'live'" class="rounded-full bg-red-600 px-2.5 py-1 text-[11px] font-bold text-white">LIVE</span>
                </div>
                <Link v-if="open(c) && c.can_join !== false" :href="route('student.classes.join', c.id)" class="btn-primary">
                    <Icon name="video" /> {{ c.kind === 'recorded' ? 'Watch lecture' : 'Join class' }}
                </Link>
                <button v-else type="button" class="btn-outline" disabled>Join opens before class</button>
            </div>
        </div>

        <h2 class="mb-3 text-lg font-semibold text-lwe-navy">Past classes</h2>
        <EmptyState v-if="!past.length" title="No past classes yet" />
        <div class="space-y-3">
            <div v-for="c in past" :key="c.id" class="card">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="font-semibold">{{ c.title }}</p>
                        <p class="text-xs text-lwe-muted">{{ c.label }} · {{ c.course }}</p>
                        <p v-if="c.status === 'cancelled'" class="text-xs text-red-700">Cancelled<template v-if="c.cancel_reason">: {{ c.cancel_reason }}</template></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span v-if="c.attendance" class="rounded-full px-2.5 py-1 text-xs font-bold" :class="attendanceClass[c.attendance]">{{ attendanceLabel[c.attendance] }}</span>
                        <StatusBadge v-else-if="c.status === 'cancelled'" status="cancelled" />
                        <button v-if="c.notes" type="button" class="btn-outline btn-sm" @click="opened = opened === c.id ? null : c.id">
                            {{ opened === c.id ? 'Hide notes' : 'Class notes' }}
                        </button>
                    </div>
                </div>
                <div v-if="opened === c.id && c.notes" class="mt-4 space-y-3 border-t border-lwe-line pt-4 text-sm">
                    <p class="whitespace-pre-line">{{ c.notes.summary }}</p>
                    <div v-if="c.notes.key_points?.length"><p class="label">Key points</p><ul class="list-disc space-y-1 pl-5"><li v-for="k in c.notes.key_points" :key="k">{{ k }}</li></ul></div>
                    <div v-if="c.notes.action_items?.length"><p class="label">Your homework</p><ul class="list-disc space-y-1 pl-5"><li v-for="k in c.notes.action_items" :key="k">{{ k }}</li></ul></div>
                    <div v-if="c.attendance === 'absent' && c.notes.recap" class="rounded-xl bg-lwe-orangeSoft p-3 text-amber-900"><p class="font-semibold">You missed this class — quick recap</p><p class="whitespace-pre-line">{{ c.notes.recap }}</p></div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
