<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Lwe/Pagination.vue';
import StatusBadge from '@/Components/Lwe/StatusBadge.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';

const props = defineProps({ courses: Object, filters: Object });
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
let t;
watch([search, status], () => {
    clearTimeout(t);
    t = setTimeout(() => router.get(route('admin.courses.index'), { search: search.value || undefined, status: status.value || undefined }, { preserveState: true, replace: true }), 300);
});
const remove = (c) => { if (confirm(`Delete "${c.title}"? This cannot be undone from here.`)) router.delete(route('admin.courses.destroy', c.id)); };
</script>

<template>
    <AppLayout title="Courses">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <h1 class="page-title">Courses</h1>
            <div class="flex flex-wrap gap-2">
                <input v-model="search" type="search" class="input w-56" placeholder="Search…" aria-label="Search courses" />
                <select v-model="status" class="input w-40" aria-label="Status">
                    <option value="">All statuses</option><option value="draft">Draft</option><option value="published">Published</option><option value="private">Private</option>
                </select>
                <Link :href="route('admin.courses.create')" class="btn-primary">+ New course</Link>
            </div>
        </div>

        <EmptyState v-if="!courses.data.length" title="No courses yet" text="Create your first course in 6 short steps.">
            <Link :href="route('admin.courses.create')" class="btn-primary">Create course</Link>
        </EmptyState>

        <div v-else class="table-wrap">
            <table class="table">
                <thead><tr><th>Course</th><th>Fee</th><th>Mentors</th><th>Lessons</th><th>Batches</th><th>Students</th><th>Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="c in courses.data" :key="c.id">
                        <td>
                            <span class="font-semibold">{{ c.title }}</span>
                            <span v-if="c.is_vip" class="ml-2 rounded-full bg-lwe-orangeSoft px-2 py-0.5 text-[10px] font-bold text-amber-900">VIP</span>
                        </td>
                        <td>{{ c.is_free ? 'Free' : 'PKR ' + Number(c.fee).toLocaleString() }}</td>
                        <td class="text-xs">{{ c.mentors.map((m) => m.name).join(', ') || '—' }}</td>
                        <td>{{ c.lessons_count }}</td>
                        <td>{{ c.batches_count }}</td>
                        <td>{{ c.enrollments_count }}</td>
                        <td><StatusBadge :status="c.status" /></td>
                        <td class="space-x-2 text-right">
                            <Link :href="route('admin.courses.edit', c.id)" class="btn-outline btn-sm">Edit</Link>
                            <Link :href="route('admin.courses.curriculum', c.id)" class="btn-dark btn-sm">Lessons</Link>
                            <button type="button" class="btn-sm text-red-700 hover:underline" @click="remove(c)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="courses.links" />
    </AppLayout>
</template>
