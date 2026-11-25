<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Lwe/Pagination.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';
import StatusBadge from '@/Components/Lwe/StatusBadge.vue';
import StatTile from '@/Components/Charts/StatTile.vue';
import { fmt } from '@/Components/Charts/format';

const props = defineProps({ stats: Object, rows: Object, filters: Object });
const f = ref({ status: props.filters.status ?? '', search: props.filters.search ?? '' });
let t;
watch(f, () => {
    clearTimeout(t);
    t = setTimeout(() => router.get(route('partner.dashboard'), Object.fromEntries(Object.entries(f.value).filter(([, v]) => v)), { preserveState: true, replace: true }), 300);
}, { deep: true });
const risk = { at_risk: ['Falling behind', 'bg-red-100 text-red-900'], eligible: ['Ready for certificate', 'bg-lwe-greenSoft text-green-900'], on_track: ['On track', 'bg-blue-100 text-blue-900'] };
</script>

<template>
    <AppLayout title="My students">
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div><h1 class="page-title">My students</h1><p class="muted">Students who joined through you, and how they are doing.</p></div>
            <a :href="route('partner.export', Object.fromEntries(Object.entries(f).filter(([, v]) => v)))" class="btn-outline">Export CSV</a>
        </div>
        <section class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-6">
            <StatTile label="Students" :value="fmt(stats.students)" />
            <StatTile label="Active" :value="fmt(stats.active)" tone="good" />
            <StatTile label="Payment pending" :value="fmt(stats.pending)" :tone="stats.pending ? 'warn' : 'navy'" />
            <StatTile label="Completed" :value="fmt(stats.completed)" />
            <StatTile label="Falling behind" :value="fmt(stats.at_risk)" :tone="stats.at_risk ? 'bad' : 'navy'" />
            <StatTile label="Paid this month" :value="fmt(stats.paid_month, 'pkr')" />
        </section>
        <div class="mb-4 flex flex-wrap gap-2">
            <input v-model="f.search" type="search" class="input w-56" placeholder="Name or student ID" aria-label="Search" />
            <select v-model="f.status" class="input w-48" aria-label="Status"><option value="">All</option><option value="active">Active</option><option value="pending_payment">Payment pending</option><option value="completed">Completed</option></select>
        </div>
        <EmptyState v-if="!rows.data.length" title="No students yet" text="When the academy enrolls a student you brought, they appear here." />
        <div v-else class="table-wrap">
            <table class="table">
                <thead><tr><th>Student</th><th>Course</th><th>Status</th><th>Attendance</th><th>Score</th><th>Progress</th><th>Joined</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="r in rows.data" :key="r.id">
                        <td><p class="font-semibold">{{ r.student.name }}</p><p class="text-xs text-lwe-muted">{{ r.student.student_code }} · {{ r.student.phone }}</p></td>
                        <td class="text-sm">{{ r.course }}<p v-if="r.batch" class="text-xs text-lwe-muted">{{ r.batch }}</p></td>
                        <td><StatusBadge :status="r.status" /></td>
                        <td class="tabular-nums">{{ fmt(r.attendance, 'pct') }}</td>
                        <td class="tabular-nums">{{ fmt(r.score, 'pct') }}</td>
                        <td><a v-if="r.certificate" :href="r.certificate" target="_blank" class="text-xs font-semibold text-lwe-navy2 underline">🎓 Certificate</a><span v-else-if="r.progress_status" class="rounded-full px-2 py-0.5 text-xs font-bold" :class="risk[r.progress_status][1]">{{ risk[r.progress_status][0] }}</span></td>
                        <td class="text-xs">{{ r.joined }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="rows.links" />
    </AppLayout>
</template>
