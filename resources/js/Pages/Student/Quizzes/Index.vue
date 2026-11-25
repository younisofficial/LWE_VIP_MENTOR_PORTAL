<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';

defineProps({ quizzes: Array });

const start = (q) => {
    if (q.open_attempt_id) return router.visit(route('student.quizzes.take', q.open_attempt_id));
    const msg = q.time_limit_minutes ? `This quiz has a ${q.time_limit_minutes}-minute timer that starts now. Ready?` : 'Start the quiz now?';
    if (confirm(msg)) router.post(route('student.quizzes.start', q.id));
};
</script>

<template>
    <AppLayout title="Quizzes">
        <h1 class="page-title">Quizzes</h1>
        <p class="muted mb-6">Short tests to check what you learned. You see your result straight away.</p>
        <EmptyState v-if="!quizzes.length" title="No quizzes yet" text="Your mentor will add quizzes after lessons." />
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div v-for="q in quizzes" :key="q.id" class="card flex flex-col gap-3">
                <div>
                    <p class="text-xs text-lwe-muted">{{ q.course }}</p>
                    <h2 class="font-semibold text-lwe-navy">{{ q.title }}</h2>
                    <p class="text-sm">{{ q.questions }} questions · pass {{ q.pass_percent }}%<template v-if="q.time_limit_minutes"> · {{ q.time_limit_minutes }} min</template> · {{ q.used }}/{{ q.max_attempts }} attempts used</p>
                    <p v-if="q.due_label" class="text-xs" :class="q.closed ? 'text-red-700' : 'text-lwe-muted'">{{ q.closed ? 'Closed' : 'Open until' }} {{ q.due_label }}</p>
                </div>
                <div v-if="q.best_percent != null" class="flex items-center gap-2">
                    <b class="text-2xl text-lwe-navy">{{ q.best_percent }}%</b>
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="q.passed ? 'bg-lwe-greenSoft text-green-900' : 'bg-red-100 text-red-900'">{{ q.passed ? 'Passed' : 'Not passed yet' }}</span>
                </div>
                <div class="mt-auto flex flex-wrap gap-2">
                    <button v-if="q.can_start" type="button" class="btn-primary" @click="start(q)">{{ q.open_attempt_id ? 'Continue quiz' : (q.used ? 'Try again' : 'Start quiz') }}</button>
                    <Link v-if="q.last_attempt_id" :href="route('student.quizzes.take', q.last_attempt_id)" class="btn-outline">See my result</Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
