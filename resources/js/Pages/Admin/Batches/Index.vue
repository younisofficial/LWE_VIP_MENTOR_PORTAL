<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from '@/Components/Lwe/Dialog.vue';
import Field from '@/Components/Lwe/Field.vue';
import Pagination from '@/Components/Lwe/Pagination.vue';
import StatusBadge from '@/Components/Lwe/StatusBadge.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';

const props = defineProps({ batches: Object, filters: Object, courses: Array, mentors: Array, days: Array });

const courseFilter = ref(props.filters.course_id ?? '');
const statusFilter = ref(props.filters.status ?? '');
watch([courseFilter, statusFilter], () => router.get(route('admin.batches.index'), { course_id: courseFilter.value || undefined, status: statusFilter.value || undefined }, { preserveState: true, replace: true }));

const editing = ref(null); // null | 'new' | batch
// Mentors come from the ticked courses — every mentor of every course teaches in the batch.
const courseMentors = computed(() => {
    const seen = new Map();
    props.courses.filter((c) => form.course_ids.includes(c.id)).forEach((c) => (c.mentors ?? []).forEach((m) => seen.set(m.id, m)));
    return [...seen.values()];
});
const form = useForm({ course_ids: [], name: '', mentor_id: '', starts_on: '', ends_on: '', class_days: [], class_time: '20:00', class_duration_minutes: 90, seats: '', status: 'upcoming', enroll_existing: true });
const open = (b = null) => {
    form.reset(); form.clearErrors();
    if (b) {
        Object.assign(form, {
            course_ids: (b.courses ?? []).map((c) => c.id), name: b.name, mentor_id: b.mentor_id ?? '', starts_on: b.starts_on?.slice(0, 10), ends_on: b.ends_on?.slice(0, 10) ?? '',
            class_days: b.class_days ?? [], class_time: b.class_time?.slice(0, 5) ?? '', class_duration_minutes: b.class_duration_minutes, seats: b.seats ?? '', status: b.status,
        });
    } else if (courseFilter.value) {
        form.course_ids = [Number(courseFilter.value)];
    }
    mentorPicked = !!b?.mentor_id;
    editing.value = b ?? 'new';
};
// Lead mentor follows the ticked courses until the admin picks one by hand.
let mentorPicked = false;
watch(() => form.course_ids.slice(), () => {
    if (!mentorPicked) form.mentor_id = courseMentors.value[0]?.id ?? '';
});
const save = () => {
    const opts = { preserveScroll: true, onSuccess: () => (editing.value = null) };
    editing.value === 'new' ? form.post(route('admin.batches.store'), opts) : form.put(route('admin.batches.update', editing.value.id), opts);
};
const remove = (b) => { if (confirm(`Delete batch "${b.name}"?`)) router.delete(route('admin.batches.destroy', b.id), { preserveScroll: true }); };
const dayLabel = (d) => d.charAt(0).toUpperCase() + d.slice(1);
const fmtTime = (t) => { if (!t) return ''; const [h, m] = t.split(':'); const hh = ((+h + 11) % 12) + 1; return `${hh}:${m} ${+h < 12 ? 'AM' : 'PM'}`; };
</script>

<template>
    <AppLayout title="Batches">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><h1 class="page-title">Batches</h1><p class="muted">A batch is a group of students who start together. One batch can have one or many courses.</p></div>
            <div class="flex flex-wrap gap-2">
                <select v-model="courseFilter" class="input w-56" aria-label="Course"><option value="">All courses</option><option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option></select>
                <select v-model="statusFilter" class="input w-36" aria-label="Status"><option value="">All</option><option value="upcoming">Upcoming</option><option value="running">Running</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select>
                <button type="button" class="btn-primary" @click="open()">+ New batch</button>
            </div>
        </div>

        <EmptyState v-if="!batches.data.length" title="No batches yet" text="Create a batch so students can enroll." />
        <div v-else class="table-wrap">
            <table class="table">
                <thead><tr><th>Batch</th><th>Courses</th><th>Mentors</th><th>Dates</th><th>Class time</th><th>Students</th><th>Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="b in batches.data" :key="b.id">
                        <td class="font-semibold">{{ b.name }}</td>
                        <td><div class="flex max-w-xs flex-wrap gap-1"><span v-for="c in b.courses" :key="c.id" class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-lwe-navy">{{ c.title }}</span></div></td>
                        <td class="text-sm">{{ b.mentors?.length ? b.mentors.map((m) => m.name).join(', ') : '—' }}</td>
                        <td class="text-xs">{{ b.starts_on?.slice(0, 10) }}<template v-if="b.ends_on"> → {{ b.ends_on.slice(0, 10) }}</template></td>
                        <td class="text-xs">{{ (b.class_days ?? []).map(dayLabel).join(', ') }}<br />{{ fmtTime(b.class_time) }} · {{ b.class_duration_minutes }} min</td>
                        <td>{{ b.students_count }}<template v-if="b.seats"> / {{ b.seats }}</template></td>
                        <td><StatusBadge :status="b.status" /></td>
                        <td class="space-x-2 text-right">
                            <button type="button" class="btn-outline btn-sm" @click="open(b)">Edit</button>
                            <button type="button" class="btn-sm text-red-700 hover:underline" @click="remove(b)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="batches.links" />

        <Dialog :show="!!editing" :title="editing === 'new' ? 'New batch' : 'Edit batch'" wide @close="editing = null">
            <form class="space-y-4" @submit.prevent="save">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Batch name" for="bn" :error="form.errors.name"><input id="bn" v-model="form.name" type="text" class="input" placeholder="Batch 15" required /></Field>
                    <Field label="Lead mentor (main contact)" for="bm" :error="form.errors.mentor_id">
                        <select id="bm" v-model="form.mentor_id" class="input" @change="mentorPicked = true"><option value="">—</option><option v-for="m in mentors" :key="m.id" :value="m.id">{{ m.name }}</option></select>
                        <span class="mt-1 block text-xs text-lwe-muted">Filled automatically from the courses. Change it if you like.</span>
                    </Field>
                    <Field label="Seats (empty = unlimited)" for="bs" :error="form.errors.seats"><input id="bs" v-model="form.seats" type="number" min="1" class="input" /></Field>
                    <Field label="Start date" for="bsd" :error="form.errors.starts_on"><input id="bsd" v-model="form.starts_on" type="date" class="input" required /></Field>
                    <Field label="End date" for="bed" :error="form.errors.ends_on"><input id="bed" v-model="form.ends_on" type="date" class="input" /></Field>
                </div>
                <fieldset>
                    <legend class="label">Courses in this batch <span class="font-normal text-lwe-muted">(tick as many as you like — {{ form.course_ids.length }} chosen)</span></legend>
                    <div class="mb-2 flex gap-3 text-xs font-semibold">
                        <button type="button" class="text-lwe-navy2 hover:underline" @click="form.course_ids = courses.map((c) => c.id)">Select all</button>
                        <button type="button" class="text-lwe-navy2 hover:underline" @click="form.course_ids = []">Clear</button>
                    </div>
                    <div class="grid max-h-64 grid-cols-1 gap-2 overflow-y-auto rounded-xl border border-lwe-line p-3 sm:grid-cols-2">
                        <label v-for="c in courses" :key="c.id" class="flex items-center gap-2 text-sm">
                            <input v-model="form.course_ids" type="checkbox" :value="c.id" class="rounded" /> {{ c.title }}
                        </label>
                    </div>
                    <p v-if="form.errors.course_ids" class="mt-1 text-xs text-red-700">{{ form.errors.course_ids }}</p>
                    <p v-if="!courses.length" class="mt-1 text-xs text-lwe-muted">Create a course first.</p>
                    <div class="mt-3 rounded-xl border border-lwe-line p-3">
                        <p class="text-sm font-semibold text-lwe-navy">Mentors in this batch <span class="font-normal text-lwe-muted">(from the ticked courses)</span></p>
                        <div v-if="courseMentors.length" class="mt-2 flex flex-wrap gap-1">
                            <span v-for="m in courseMentors" :key="m.id" class="rounded-full bg-lwe-greenSoft px-2.5 py-1 text-xs font-semibold text-green-900">{{ m.name }}</span>
                        </div>
                        <p v-else class="mt-1 text-xs text-lwe-muted">{{ form.course_ids.length ? 'These courses have no mentor yet. Add one in the course wizard (Basic info step).' : 'Tick courses to see their mentors.' }}</p>
                    </div>
                    <label v-if="editing && editing !== 'new'" class="mt-2 flex items-start gap-2 rounded-xl bg-blue-50 p-3 text-sm">
                        <input v-model="form.enroll_existing" type="checkbox" class="mt-0.5 rounded" />
                        <span>Give newly ticked courses to the students already in this batch <span class="block text-xs text-lwe-muted">They get the course free and active, with no new payment.</span></span>
                    </label>
                </fieldset>
                <fieldset>
                    <legend class="label">Class days</legend>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="d in days" :key="d" class="cursor-pointer rounded-full border px-3 py-2 text-xs font-semibold"
                            :class="form.class_days.includes(d) ? 'border-lwe-navy bg-lwe-navy text-white' : 'border-lwe-line'">
                            <input v-model="form.class_days" type="checkbox" :value="d" class="sr-only" /> {{ dayLabel(d) }}
                        </label>
                    </div>
                </fieldset>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Field label="Class time (Pakistan)" for="bt" :error="form.errors.class_time"><input id="bt" v-model="form.class_time" type="time" class="input" /></Field>
                    <Field label="Duration (min)" for="bd" :error="form.errors.class_duration_minutes"><input id="bd" v-model="form.class_duration_minutes" type="number" min="15" class="input" /></Field>
                    <Field label="Status" for="bst" :error="form.errors.status">
                        <select id="bst" v-model="form.status" class="input"><option value="upcoming">Upcoming</option><option value="running">Running</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select>
                    </Field>
                </div>
                <div class="flex justify-end gap-2 border-t border-lwe-line pt-4">
                    <button type="button" class="btn-outline" @click="editing = null">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">Save batch</button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
