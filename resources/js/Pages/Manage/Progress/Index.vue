<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Lwe/Pagination.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';

const props = defineProps({ rows: Object, filters: Object, stats: Object, courses: Array, batches: Array, lessons: Array });
const isAdmin = computed(() => usePage().props.auth.user.is_staff);

const f = ref({ course_id: props.filters.course_id ?? '', batch_id: props.filters.batch_id ?? '', state: props.filters.state ?? '', search: props.filters.search ?? '' });
let t;
watch(f, () => {
    clearTimeout(t);
    t = setTimeout(() => router.get(route('manage.progress.index'), Object.fromEntries(Object.entries(f.value).filter(([, v]) => v)), { preserveState: true, replace: true }), 300);
}, { deep: true });
const clean = () => Object.fromEntries(Object.entries(f.value).filter(([, v]) => v));
const batchOptions = computed(() => (f.value.course_id ? props.batches.filter((b) => b.course_ids.includes(Number(f.value.course_id))) : props.batches));

const busy = ref(false);
const recalc = () => { busy.value = true; router.post(route('manage.progress.recalculate'), clean(), { preserveScroll: true, onFinish: () => (busy.value = false) }); };
const issue = (r, override = false) => {
    const msg = override ? `${r.student.name} does not meet the rules yet (${r.notes.join(', ')}). Issue the certificate anyway?` : `Issue the certificate to ${r.student.name}?`;
    if (confirm(msg)) router.post(route('manage.progress.issue', r.id), { override }, { preserveScroll: true });
};
const revoke = (r) => {
    const reason = prompt(`Why cancel certificate ${r.certificate.code}?`);
    if (reason) router.post(route('manage.certificates.revoke', r.certificate.id), { reason }, { preserveScroll: true });
};
const badge = { at_risk: ['At risk', 'bg-red-100 text-red-900'], eligible: ['Ready', 'bg-lwe-greenSoft text-green-900'], on_track: ['On track', 'bg-blue-100 text-blue-900'] };
const pct = (v) => (v == null ? '—' : `${v}%`);
const bar = (v) => (v == null ? 'bg-slate-200' : v >= 75 ? 'bg-green-600' : v >= 50 ? 'bg-amber-500' : 'bg-red-500');
</script>

<template>
    <AppLayout title="Results & certificates">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><h1 class="page-title">Results &amp; certificates</h1><p class="muted">Attendance, tasks and quizzes add up to each student's score. Updated every morning at 7 AM — or press Recalculate.</p></div>
            <div class="flex flex-wrap gap-2">
                <a :href="route('manage.progress.export', clean())" class="btn-outline">Export CSV</a>
                <button type="button" class="btn-primary" :disabled="busy" @click="recalc">{{ busy ? 'Calculating…' : 'Recalculate now' }}</button>
            </div>
        </div>

        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
            <button type="button" class="card text-left" @click="f.state = ''"><p class="text-xs text-lwe-muted">Students</p><p class="text-2xl font-bold text-lwe-navy">{{ stats.students }}</p></button>
            <button type="button" class="card text-left" :class="f.state === 'at_risk' ? 'ring-2 ring-red-400' : ''" @click="f.state = 'at_risk'"><p class="text-xs text-lwe-muted">At risk</p><p class="text-2xl font-bold text-red-700">{{ stats.at_risk }}</p></button>
            <button type="button" class="card text-left" :class="f.state === 'eligible' ? 'ring-2 ring-green-500' : ''" @click="f.state = 'eligible'"><p class="text-xs text-lwe-muted">Ready for certificate</p><p class="text-2xl font-bold text-green-800">{{ stats.eligible }}</p></button>
            <button type="button" class="card text-left" :class="f.state === 'completed' ? 'ring-2 ring-lwe-navy' : ''" @click="f.state = 'completed'"><p class="text-xs text-lwe-muted">Certified</p><p class="text-2xl font-bold text-lwe-navy">{{ stats.certified }}</p></button>
            <div class="card"><p class="text-xs text-lwe-muted">Average score</p><p class="text-2xl font-bold text-lwe-navy">{{ stats.average || '—' }}%</p></div>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <input v-model="f.search" type="search" class="input w-56" placeholder="Name, ID or phone" aria-label="Search" />
            <select v-model="f.course_id" class="input w-56" aria-label="Course" @change="f.batch_id = ''"><option value="">All courses</option><option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option></select>
            <select v-model="f.batch_id" class="input w-48" aria-label="Batch"><option value="">All batches</option><option v-for="b in batchOptions" :key="b.id" :value="b.id">{{ b.name }}</option></select>
            <select v-model="f.state" class="input w-48" aria-label="Result"><option value="">Any result</option><option value="at_risk">At risk</option><option value="on_track">On track</option><option value="eligible">Ready for certificate</option><option value="completed">Certified</option></select>
        </div>

        <EmptyState v-if="!rows.data.length" title="No students here" text="Press “Recalculate now” if results look empty." />
        <div v-else class="table-wrap">
            <table class="table">
                <thead><tr><th>Student</th><th>Course</th><th>Attendance</th><th>Tasks</th><th>Quiz</th><th>Score</th><th>Result</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="r in rows.data" :key="r.id">
                        <td><p class="font-semibold">{{ r.student.name }}</p><p class="text-xs text-lwe-muted">{{ r.student.student_code }}</p></td>
                        <td class="text-sm">{{ r.course }}<p v-if="r.batch" class="text-xs text-lwe-muted">{{ r.batch }}</p></td>
                        <td v-for="k in ['attendance', 'tasks', 'quiz']" :key="k" class="w-28">
                            <p class="text-sm font-semibold">{{ pct(r[k]) }}</p>
                            <div class="mt-1 h-1.5 rounded-full bg-slate-100"><div class="h-full rounded-full" :class="bar(r[k])" :style="{ width: (r[k] ?? 0) + '%' }" /></div>
                        </td>
                        <td class="text-lg font-bold text-lwe-navy">{{ pct(r.score) }}</td>
                        <td class="max-w-xs">
                            <span v-if="r.status === 'completed'" class="rounded-full bg-lwe-navy px-2.5 py-1 text-xs font-bold text-white">🎓 Certified</span>
                            <span v-else-if="r.progress_status" class="rounded-full px-2.5 py-1 text-xs font-bold" :class="badge[r.progress_status][1]">{{ badge[r.progress_status][0] }}</span>
                            <p v-if="r.notes.length && r.status !== 'completed'" class="mt-1 text-xs text-slate-600">{{ r.notes.join(' · ') }}</p>
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <template v-if="r.certificate">
                                <a :href="r.certificate.url" target="_blank" class="btn-outline btn-sm">{{ r.certificate.code }}</a>
                                <button v-if="isAdmin && r.certificate.valid" type="button" class="ml-1 text-xs font-semibold text-red-700 hover:underline" @click="revoke(r)">Cancel</button>
                            </template>
                            <button v-else-if="r.progress_status === 'eligible'" type="button" class="btn-primary btn-sm" @click="issue(r)">Issue certificate</button>
                            <button v-else-if="isAdmin" type="button" class="text-xs font-semibold text-lwe-navy2 hover:underline" @click="issue(r, true)">Issue anyway</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="rows.links" />
    </AppLayout>
</template>
