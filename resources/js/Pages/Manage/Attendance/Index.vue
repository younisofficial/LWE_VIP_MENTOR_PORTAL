<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Lwe/Pagination.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';

const props = defineProps({ rows: Object, date: String, filters: Object, stats: Object, courses: Array, batches: Array, lessons: Array });

const f = ref({ date: props.date, course_id: props.filters.course_id ?? '', batch_id: props.filters.batch_id ?? '', search: props.filters.search ?? '' });
let t;
watch(f, () => {
    clearTimeout(t);
    t = setTimeout(() => router.get(route('manage.attendance.index'), Object.fromEntries(Object.entries(f.value).filter(([, v]) => v)), { preserveState: true, replace: true }), 300);
}, { deep: true });
const batchOptions = computed(() => (f.value.course_id ? props.batches.filter((b) => b.course_ids.includes(Number(f.value.course_id))) : props.batches));
const exportUrl = computed(() => route('manage.attendance.export', Object.fromEntries(Object.entries(f.value).filter(([, v]) => v))));
const pct = (n) => (props.stats.students ? Math.round((n * 100) / props.stats.students) : 0);
</script>

<template>
    <AppLayout title="Daily attendance">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><h1 class="page-title">Daily attendance</h1><p class="muted">Students mark themselves present online every day, or scan the QR code at campus.</p></div>
            <div class="flex flex-wrap gap-2">
                <a :href="exportUrl" class="btn-outline">Export CSV</a>
                <Link :href="route('manage.attendance.qr')" class="btn-primary">Open campus QR screen</Link>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <input v-model="f.date" type="date" class="input w-44" aria-label="Date" />
            <select v-model="f.course_id" class="input w-56" aria-label="Course" @change="f.batch_id = ''"><option value="">All courses</option><option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option></select>
            <select v-model="f.batch_id" class="input w-48" aria-label="Batch"><option value="">All batches</option><option v-for="b in batchOptions" :key="b.id" :value="b.id">{{ b.name }}</option></select>
            <input v-model="f.search" type="search" class="input w-56" placeholder="Name, ID or phone" aria-label="Search" />
        </div>

        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="card"><p class="text-xs text-lwe-muted">Students</p><p class="text-2xl font-bold text-lwe-navy">{{ stats.students }}</p></div>
            <div class="card"><p class="text-xs text-lwe-muted">Present (any)</p><p class="text-2xl font-bold text-green-800">{{ stats.any }} <span class="text-sm font-semibold">{{ pct(stats.any) }}%</span></p></div>
            <div class="card"><p class="text-xs text-lwe-muted">Online check-in</p><p class="text-2xl font-bold text-lwe-navy">{{ stats.self }}</p></div>
            <div class="card"><p class="text-xs text-lwe-muted">Campus QR</p><p class="text-2xl font-bold text-lwe-navy">{{ stats.campus }}</p></div>
        </div>

        <EmptyState v-if="!rows.data.length" title="No students found" />
        <div v-else class="table-wrap">
            <table class="table">
                <thead><tr><th>Student</th><th>Online check-in</th><th>What they learned</th><th>Campus</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="r in rows.data" :key="r.id">
                        <td><p class="font-semibold">{{ r.name }}</p><p class="text-xs text-lwe-muted">{{ r.student_code }} · {{ r.phone }}</p></td>
                        <td><span v-if="r.self" class="rounded-full bg-lwe-greenSoft px-2 py-0.5 text-xs font-bold text-green-900">✔ {{ r.self.time }}</span><span v-else class="text-xs text-lwe-muted">—</span></td>
                        <td class="max-w-md text-sm">{{ r.self?.note ?? '' }}</td>
                        <td><span v-if="r.campus" class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-bold text-blue-900">✔ {{ r.campus.time }}</span><span v-else class="text-xs text-lwe-muted">—</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="rows.links" />
    </AppLayout>
</template>
