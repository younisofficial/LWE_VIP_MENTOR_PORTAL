<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Lwe/Pagination.vue';
import StatusBadge from '@/Components/Lwe/StatusBadge.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';

const props = defineProps({ students: Object, filters: Object });
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
let t;
watch([search, status], () => {
    clearTimeout(t);
    t = setTimeout(() => router.get(route('admin.students.index'), { search: search.value || undefined, status: status.value || undefined }, { preserveState: true, replace: true }), 300);
});
</script>

<template>
    <AppLayout title="Students">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <h1 class="page-title">Students</h1>
            <div class="flex gap-2">
                <input v-model="search" type="search" class="input w-64" placeholder="Name, phone, email or ID" aria-label="Search students" />
                <select v-model="status" class="input w-44" aria-label="Filter">
                    <option value="">All students</option><option value="flagged">Many-device alert</option><option value="suspended">Suspended</option>
                </select>
            </div>
        </div>
        <EmptyState v-if="!students.data.length" title="No students found" />
        <div v-else class="table-wrap">
            <table class="table">
                <thead><tr><th>Student</th><th>Phone</th><th>City</th><th>Active courses</th><th>Last login</th><th>Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="s in students.data" :key="s.id">
                        <td><span class="font-semibold">{{ s.name }}</span><span class="block text-xs text-lwe-muted">{{ s.code }}<template v-if="s.email"> · {{ s.email }}</template></span></td>
                        <td class="text-xs">{{ s.phone }}</td>
                        <td>{{ s.city ?? '—' }}</td>
                        <td>{{ s.active_courses }}</td>
                        <td class="text-xs">{{ s.last_login ?? 'Never' }}</td>
                        <td class="space-x-1">
                            <StatusBadge :status="s.status" />
                            <span v-if="s.flagged" class="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-bold text-red-900">ALERT</span>
                            <span v-if="s.device_locked" class="rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-bold text-slate-800">LOCKED</span>
                        </td>
                        <td class="text-right"><Link :href="route('admin.students.show', s.id)" class="btn-outline btn-sm">Open</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="students.links" />
    </AppLayout>
</template>
