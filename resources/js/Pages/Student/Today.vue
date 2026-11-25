<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Lwe/Icon.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';

const props = defineProps({ courses: Array, nextClass: Object, stats: Object, today: String, engage: Object });
const user = usePage().props.auth.user;
const firstName = computed(() => user.name.split(' ')[0]);

// Live countdown to the next class
const now = ref(Date.now());
let timer;
onMounted(() => (timer = setInterval(() => (now.value = Date.now()), 1000)));
onBeforeUnmount(() => clearInterval(timer));

const countdown = computed(() => {
    if (!props.nextClass) return null;
    let s = Math.max(0, Math.floor((new Date(props.nextClass.starts_at_iso).getTime() - now.value) / 1000));
    const d = Math.floor(s / 86400); s %= 86400;
    const h = Math.floor(s / 3600); s %= 3600;
    const m = Math.floor(s / 60); s %= 60;
    const pad = (n) => String(n).padStart(2, '0');
    return d > 0 ? `${d}d ${pad(h)}h ${pad(m)}m` : `${pad(h)}:${pad(m)}:${pad(s)}`;
});
const canJoin = computed(() => {
    const c = props.nextClass;
    if (!c?.join_url || !c.join_available) return false;
    return c.is_live_now || (now.value >= Date.parse(c.join_opens_iso) && now.value <= Date.parse(c.join_closes_iso));
});
</script>

<template>
    <AppLayout title="Today">
        <div class="mb-6">
            <h1 class="page-title">Assalam o Alaikum, {{ firstName }}</h1>
            <p class="muted">{{ today }}</p>
        </div>

        <!-- Next class -->
        <section v-if="nextClass" class="mb-6 flex flex-col gap-6 rounded-2xl bg-lwe-navy p-6 text-white sm:flex-row sm:items-center sm:justify-between sm:p-8" aria-label="Next class">
            <div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-full px-2.5 py-1 font-bold" :class="nextClass.is_live_now ? 'bg-red-600' : 'bg-lwe-orange text-lwe-navy'">
                        {{ nextClass.is_live_now ? 'LIVE NOW' : nextClass.label.toUpperCase() }}
                    </span>
                    <span class="text-slate-300">{{ nextClass.course }} · {{ nextClass.batch }}</span>
                </div>
                <h2 class="mt-3 text-2xl font-semibold">Next class: {{ nextClass.time }}</h2>
                <p v-if="nextClass.mentor" class="mt-1 text-sm text-slate-300">With {{ nextClass.mentor }}</p>
            </div>
            <div class="flex flex-col items-start gap-2 sm:items-end">
                <span class="text-xs text-slate-300">Starts in</span>
                <span class="text-3xl font-bold tabular-nums">{{ countdown }}</span>
                <Link v-if="canJoin" :href="nextClass.join_url" class="btn-primary"><Icon name="video" /> Join Class</Link>
                <button v-else type="button" class="btn-primary" disabled><Icon name="video" /> Join Class</button>
                <span class="text-[11px] text-slate-300">
                    <template v-if="canJoin">Class opens inside the portal</template>
                    <template v-else-if="nextClass.join_url">Join opens a few minutes before class</template>
                    <template v-else>Class times from your batch schedule</template>
                </span>
            </div>
        </section>

        <!-- Module 3: today's to-dos -->
        <section v-if="engage" class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3" aria-label="To do today">
            <Link :href="route('student.attendance.index')" class="card flex items-center justify-between gap-3 hover:ring-2 hover:ring-lwe-orange">
                <span><span class="block text-xs text-lwe-muted">Daily attendance</span><b class="text-lwe-navy">{{ engage.checked_in ? 'Marked today ✔' : 'Mark me present' }}</b></span>
                <span class="text-2xl" aria-hidden="true">{{ engage.checked_in ? '✅' : '📍' }}</span>
            </Link>
            <Link :href="route('student.tasks.index')" class="card flex items-center justify-between gap-3 hover:ring-2 hover:ring-lwe-orange">
                <span><span class="block text-xs text-lwe-muted">Tasks to submit</span><b class="text-2xl" :class="engage.tasks_todo ? 'text-amber-700' : 'text-lwe-navy'">{{ engage.tasks_todo }}</b></span>
                <span class="text-2xl" aria-hidden="true">📝</span>
            </Link>
            <Link :href="route('student.quizzes.index')" class="card flex items-center justify-between gap-3 hover:ring-2 hover:ring-lwe-orange">
                <span><span class="block text-xs text-lwe-muted">Quizzes to take</span><b class="text-2xl" :class="engage.quizzes_todo ? 'text-amber-700' : 'text-lwe-navy'">{{ engage.quizzes_todo }}</b></span>
                <span class="text-2xl" aria-hidden="true">✅</span>
            </Link>
        </section>

        <!-- Stats -->
        <section class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4" aria-label="My progress">
            <div class="card"><p class="text-xs text-lwe-muted">Active courses</p><p class="mt-1 text-3xl font-bold text-lwe-navy">{{ stats.active }}</p></div>
            <div class="card"><p class="text-xs text-lwe-muted">Lessons done</p><p class="mt-1 text-3xl font-bold text-lwe-green">{{ stats.lessons_done }}</p></div>
            <div class="card"><p class="text-xs text-lwe-muted">Completed courses</p><p class="mt-1 text-3xl font-bold text-lwe-navy">{{ stats.completed }}</p></div>
            <div class="card"><p class="text-xs text-lwe-muted">Payments pending</p><p class="mt-1 text-3xl font-bold" :class="stats.pending_payments ? 'text-amber-700' : 'text-lwe-navy'">{{ stats.pending_payments }}</p></div>
        </section>

        <!-- Courses -->
        <section class="card" aria-label="My courses">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-lwe-navy">Continue learning</h2>
                <Link :href="route('student.courses.index')" class="text-sm font-semibold text-lwe-navy2 hover:underline">Browse courses</Link>
            </div>

            <EmptyState v-if="!courses.length" title="You are not enrolled in any course yet" text="Pick a course to start learning.">
                <Link :href="route('student.courses.index')" class="btn-primary">See courses</Link>
            </EmptyState>

            <div class="space-y-3">
                <div v-for="c in courses" :key="c.id" class="flex flex-col gap-4 rounded-xl bg-slate-50 p-4 sm:flex-row sm:items-center">
                    <div class="h-16 w-24 flex-none overflow-hidden rounded-lg bg-lwe-navy2">
                        <img v-if="c.thumbnail_url" :src="c.thumbnail_url" alt="" class="h-full w-full object-cover" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ c.title }}</span>
                            <span v-if="c.is_vip" class="rounded-full bg-lwe-orangeSoft px-2 py-0.5 text-[11px] font-bold text-amber-900">VIP</span>
                            <span v-if="c.is_free" class="rounded-full bg-lwe-greenSoft px-2 py-0.5 text-[11px] font-bold text-green-900">FREE</span>
                            <span v-if="c.batch" class="text-xs text-lwe-muted">{{ c.batch }}</span>
                        </div>
                        <template v-if="c.status === 'pending_payment'">
                            <p class="mt-1 text-sm text-amber-800">
                                <template v-if="c.payment_status === 'pending'">Payment uploaded — waiting for approval.</template>
                                <template v-else-if="c.payment_status === 'rejected'">Payment not approved: {{ c.payment_note }}</template>
                                <template v-else>Pay PKR {{ c.amount_due.toLocaleString() }} to unlock this course.</template>
                            </p>
                        </template>
                        <template v-else>
                            <div class="mt-2 h-2 rounded-full bg-slate-200" role="progressbar" :aria-valuenow="c.progress" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-2 rounded-full bg-lwe-orange" :style="{ width: c.progress + '%' }" />
                            </div>
                            <p class="mt-1 text-xs text-lwe-muted">{{ c.progress }}%<template v-if="c.next_lesson"> · Next: {{ c.next_lesson }}</template></p>
                        </template>
                    </div>
                    <Link v-if="c.status === 'pending_payment'" :href="route('student.payments.create', c.id)" class="btn-dark btn-sm">
                        {{ c.payment_status === 'pending' ? 'View payment' : 'Upload payment' }}
                    </Link>
                    <Link v-else :href="route('student.my-courses.show', c.slug)" class="btn-outline btn-sm">Resume</Link>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
