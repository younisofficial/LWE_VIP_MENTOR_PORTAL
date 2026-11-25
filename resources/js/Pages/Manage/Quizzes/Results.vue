<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ quiz: Object, rows: Array, hardest: Array, stats: Object });

const reset = (r) => {
    if (confirm(`Clear ${r.student.name}'s attempts so they can take the quiz again?`)) {
        router.delete(route('manage.quizzes.reset', [props.quiz.id, r.student.id]), { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout :title="`Results — ${quiz.title}`">
        <Link :href="route('manage.quizzes.edit', quiz.id)" class="text-sm font-semibold text-lwe-navy2 hover:underline">← Back to quiz</Link>
        <h1 class="page-title mt-2">{{ quiz.title }} — results</h1>
        <p class="muted mb-6">{{ quiz.course }} · pass mark {{ quiz.pass_percent }}%</p>

        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="card"><p class="text-xs text-lwe-muted">Took the quiz</p><p class="text-2xl font-bold text-lwe-navy">{{ stats.took }} / {{ stats.expected }}</p></div>
            <div class="card"><p class="text-xs text-lwe-muted">Passed</p><p class="text-2xl font-bold text-green-800">{{ stats.passed }}</p></div>
            <div class="card"><p class="text-xs text-lwe-muted">Average</p><p class="text-2xl font-bold text-lwe-navy">{{ stats.average != null ? stats.average + '%' : '—' }}</p></div>
            <div class="card"><p class="text-xs text-lwe-muted">Not taken</p><p class="text-2xl font-bold text-amber-700">{{ stats.expected - stats.took }}</p></div>
        </div>

        <div v-if="hardest.length" class="card mb-6">
            <h2 class="mb-2 font-semibold text-lwe-navy">Hardest questions — explain these again in class</h2>
            <ul class="space-y-1 text-sm">
                <li v-for="h in hardest" :key="h.question" class="flex justify-between gap-4"><span>{{ h.question }}</span><b :class="h.right_percent < 50 ? 'text-red-700' : 'text-lwe-navy'">{{ h.right_percent }}% right</b></li>
            </ul>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Student</th><th>Attempts</th><th>Best</th><th>Result</th><th>Last</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="r in rows" :key="r.student.id">
                        <td><p class="font-semibold">{{ r.student.name }}</p><p class="text-xs text-lwe-muted">{{ r.student.student_code }}</p></td>
                        <td>{{ r.attempts }} / {{ quiz.max_attempts }}</td>
                        <td>{{ r.best_percent != null ? r.best_percent + '%' : '—' }} <span v-if="r.best_score" class="text-xs text-lwe-muted">({{ r.best_score }})</span></td>
                        <td>
                            <span v-if="!r.attempts" class="text-xs text-lwe-muted">Not taken</span>
                            <span v-else-if="r.passed" class="rounded-full bg-lwe-greenSoft px-2 py-0.5 text-xs font-bold text-green-900">Passed</span>
                            <span v-else class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-900">Not passed</span>
                        </td>
                        <td class="text-xs">{{ r.last_label ?? '—' }}</td>
                        <td class="text-right"><button v-if="r.attempts" type="button" class="btn-outline btn-sm" @click="reset(r)">Allow retake</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
