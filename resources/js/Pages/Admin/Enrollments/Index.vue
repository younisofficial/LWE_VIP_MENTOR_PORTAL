<script setup>
import { ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from '@/Components/Lwe/Dialog.vue';
import Field from '@/Components/Lwe/Field.vue';
import Pagination from '@/Components/Lwe/Pagination.vue';
import StatusBadge from '@/Components/Lwe/StatusBadge.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';

const props = defineProps({ enrollments: Object, filters: Object, courses: Array, batches: Array, statuses: Array, importReport: Object, franchisers: { type: Array, default: () => [] } });

const f = ref({ search: props.filters.search ?? '', course_id: props.filters.course_id ?? '', status: props.filters.status ?? '' });
let t;
watch(f, () => {
    clearTimeout(t);
    t = setTimeout(() => router.get(route('admin.enrollments.index'), Object.fromEntries(Object.entries(f.value).filter(([, v]) => v)), { preserveState: true, replace: true }), 300);
}, { deep: true });

// Manual enroll
const showAdd = ref(false);
const addForm = useForm({ name: '', phone: '', email: '', batch_id: '', course_ids: [], source: 'admin', activate_now: false, coupon: '', franchiser_id: '' });
const coursesOf = (batchId) => props.batches.find((b) => b.id === batchId)?.courses ?? [];
// Picking a batch ticks all of its courses; the admin can untick any.
watch(() => addForm.batch_id, (id) => { addForm.course_ids = coursesOf(id).map((c) => c.id); });
const add = () => addForm.post(route('admin.enrollments.store'), { preserveScroll: true, onSuccess: () => { showAdd.value = false; addForm.reset(); } });

// CSV import
const showImport = ref(false);
const importForm = useForm({ file: null, batch_id: '', course_ids: [], source: 'scholarship', activate_now: false, franchiser_id: '' });
watch(() => importForm.batch_id, (id) => { importForm.course_ids = coursesOf(id).map((c) => c.id); });
const doImport = () => importForm.post(route('admin.enrollments.import'), { forceFormData: true, preserveScroll: true, onSuccess: () => { showImport.value = false; importForm.reset(); } });

// Change status / batch
const editing = ref(null);
const editForm = useForm({ status: '', batch_id: '', notes: '' });
const openEdit = (e) => { editForm.status = e.status; editForm.batch_id = e.batch_id ?? ''; editForm.notes = ''; editing.value = e; };
const saveEdit = () => editForm.put(route('admin.enrollments.update', editing.value.id), { preserveScroll: true, onSuccess: () => (editing.value = null) });
const batchesFor = (courseId) => props.batches.filter((b) => b.courses.some((c) => c.id === courseId));
const label = (s) => s.replace('_', ' ');
</script>

<template>
    <AppLayout title="Enrollments">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div><h1 class="page-title">Enrollments</h1><p class="muted">Who is in which course and batch.</p></div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-outline" @click="showImport = true">Import CSV</button>
                <button type="button" class="btn-primary" @click="showAdd = true">+ Enroll a student</button>
            </div>
        </div>

        <div v-if="importReport" class="mb-4 rounded-xl border border-lwe-line bg-white p-4 text-sm">
            <p class="font-semibold text-lwe-navy">Last import: {{ importReport.enrolled }} enrolled · {{ importReport.created }} new students · {{ importReport.existing }} already registered · {{ importReport.skipped }} skipped</p>
            <ul v-if="Object.keys(importReport.errors).length" class="mt-2 list-disc pl-5 text-xs text-red-700">
                <li v-for="(err, line) in importReport.errors" :key="line">Row {{ line }}: {{ err }}</li>
            </ul>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <input v-model="f.search" type="search" class="input w-64" placeholder="Name, phone or student ID" aria-label="Search" />
            <select v-model="f.course_id" class="input w-56" aria-label="Course"><option value="">All courses</option><option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option></select>
            <select v-model="f.status" class="input w-44" aria-label="Status"><option value="">All statuses</option><option v-for="s in statuses" :key="s" :value="s">{{ label(s) }}</option></select>
        </div>

        <EmptyState v-if="!enrollments.data.length" title="No enrollments found" />
        <div v-else class="table-wrap">
            <table class="table">
                <thead><tr><th>Student</th><th>Phone</th><th>Course</th><th>Batch</th><th>Source</th><th>Due</th><th>Status</th><th>Date</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="e in enrollments.data" :key="e.id">
                        <td><Link :href="route('admin.students.show', e.student_id)" class="font-semibold hover:underline">{{ e.student }}</Link><span class="block text-xs text-lwe-muted">{{ e.code }}</span></td>
                        <td class="text-xs">{{ e.phone }}</td>
                        <td>{{ e.course }}</td>
                        <td>{{ e.batch ?? '—' }}</td>
                        <td class="text-xs capitalize">{{ e.source }}</td>
                        <td>{{ e.amount_due ? 'PKR ' + e.amount_due.toLocaleString() : '—' }}</td>
                        <td><StatusBadge :status="e.status" /></td>
                        <td class="text-xs">{{ e.date }}</td>
                        <td class="text-right"><button type="button" class="btn-outline btn-sm" @click="openEdit(e)">Change</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="enrollments.links" />

        <!-- Manual enroll -->
        <Dialog :show="showAdd" title="Enroll a student" @close="showAdd = false">
            <form class="space-y-4" @submit.prevent="add">
                <p class="muted">If the phone number is new, a student account is created. They set their password with "Forgot password" (code on WhatsApp).</p>
                <Field label="Student name" for="an" :error="addForm.errors.name"><input id="an" v-model="addForm.name" type="text" class="input" required /></Field>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="WhatsApp number" for="ap" :error="addForm.errors.phone"><input id="ap" v-model="addForm.phone" type="tel" class="input" placeholder="0300 1234567" required /></Field>
                    <Field label="Email (optional)" for="ae" :error="addForm.errors.email"><input id="ae" v-model="addForm.email" type="email" class="input" /></Field>
                </div>
                <Field label="Batch" for="ab" :error="addForm.errors.batch_id || addForm.errors.course">
                    <select id="ab" v-model="addForm.batch_id" class="input" required><option value="" disabled>Choose…</option><option v-for="b in batches" :key="b.id" :value="b.id">{{ b.name }} ({{ b.courses.length }} course{{ b.courses.length === 1 ? '' : 's' }})</option></select>
                </Field>
                <fieldset v-if="addForm.batch_id">
                    <legend class="label">Courses</legend>
                    <label v-for="c in coursesOf(addForm.batch_id)" :key="c.id" class="mb-1 flex items-center gap-2 text-sm"><input v-model="addForm.course_ids" type="checkbox" :value="c.id" class="rounded" /> {{ c.title }}</label>
                    <p v-if="addForm.errors.course_ids" class="mt-1 text-xs text-red-700">{{ addForm.errors.course_ids }}</p>
                </fieldset>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Source" for="as"><select id="as" v-model="addForm.source" class="input"><option value="admin">Admin</option><option value="scholarship">Scholarship session</option><option value="franchise">Franchise</option></select></Field>
                    <Field label="Coupon (optional)" for="ac" :error="addForm.errors.coupon"><input id="ac" v-model="addForm.coupon" type="text" class="input uppercase" /></Field>
                </div>
                <Field v-if="addForm.source === 'franchise'" label="Franchise partner" for="af" :error="addForm.errors.franchiser_id">
                    <select id="af" v-model="addForm.franchiser_id" class="input"><option value="">—</option><option v-for="f in franchisers" :key="f.id" :value="f.id">{{ f.name }}<template v-if="f.city"> ({{ f.city }})</template></option></select>
                </Field>
                <label class="flex items-center gap-2 text-sm"><input v-model="addForm.activate_now" type="checkbox" class="rounded" /> Activate now (payment already received / free seat)</label>
                <div class="flex justify-end gap-2 border-t border-lwe-line pt-4">
                    <button type="button" class="btn-outline" @click="showAdd = false">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="addForm.processing">Enroll</button>
                </div>
            </form>
        </Dialog>

        <!-- CSV import -->
        <Dialog :show="showImport" title="Import students from CSV" @close="showImport = false">
            <form class="space-y-4" @submit.prevent="doImport">
                <p class="muted">Columns: <b>name, phone</b> (optional: email, city). Export from Google Sheets as CSV. Students already registered (same phone) are not created twice.
                    <a :href="route('admin.enrollments.template')" class="font-semibold text-lwe-navy2 underline">Download template</a></p>
                <Field label="CSV file" for="if" :error="importForm.errors.file"><input id="if" type="file" accept=".csv,text/csv" class="block text-sm" required @input="importForm.file = $event.target.files[0]" /></Field>
                <Field label="Batch" for="ib" :error="importForm.errors.batch_id">
                    <select id="ib" v-model="importForm.batch_id" class="input" required><option value="" disabled>Choose…</option><option v-for="b in batches" :key="b.id" :value="b.id">{{ b.name }} ({{ b.courses.length }} course{{ b.courses.length === 1 ? '' : 's' }})</option></select>
                </Field>
                <fieldset v-if="importForm.batch_id">
                    <legend class="label">Enroll every student in these courses</legend>
                    <label v-for="c in coursesOf(importForm.batch_id)" :key="c.id" class="mb-1 flex items-center gap-2 text-sm"><input v-model="importForm.course_ids" type="checkbox" :value="c.id" class="rounded" /> {{ c.title }}</label>
                    <p v-if="importForm.errors.course_ids" class="mt-1 text-xs text-red-700">{{ importForm.errors.course_ids }}</p>
                </fieldset>
                <Field label="Source" for="is"><select id="is" v-model="importForm.source" class="input"><option value="scholarship">Scholarship session</option><option value="csv">Google Sheet / CSV</option><option value="franchise">Franchise</option></select></Field>
                <Field v-if="importForm.source === 'franchise'" label="Franchise partner" for="if2" :error="importForm.errors.franchiser_id">
                    <select id="if2" v-model="importForm.franchiser_id" class="input"><option value="">—</option><option v-for="f in franchisers" :key="f.id" :value="f.id">{{ f.name }}</option></select>
                </Field>
                <label class="flex items-center gap-2 text-sm"><input v-model="importForm.activate_now" type="checkbox" class="rounded" /> Activate everyone now (no payment needed)</label>
                <div class="flex justify-end gap-2 border-t border-lwe-line pt-4">
                    <button type="button" class="btn-outline" @click="showImport = false">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="importForm.processing">Import</button>
                </div>
            </form>
        </Dialog>

        <!-- Edit -->
        <Dialog :show="!!editing" :title="editing ? 'Change: ' + editing.student : ''" @close="editing = null">
            <form v-if="editing" class="space-y-4" @submit.prevent="saveEdit">
                <Field label="Status" for="es" :error="editForm.errors.status">
                    <select id="es" v-model="editForm.status" class="input"><option v-for="s in statuses" :key="s" :value="s">{{ label(s) }}</option></select>
                </Field>
                <Field label="Batch" for="eb" :error="editForm.errors.batch_id">
                    <select id="eb" v-model="editForm.batch_id" class="input"><option value="">No batch</option><option v-for="b in batchesFor(editing.course_id)" :key="b.id" :value="b.id">{{ b.name }}</option></select>
                </Field>
                <Field label="Note (optional)" for="en"><textarea id="en" v-model="editForm.notes" rows="2" class="textarea" /></Field>
                <div class="flex justify-end gap-2 border-t border-lwe-line pt-4">
                    <button type="button" class="btn-outline" @click="editing = null">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="editForm.processing">Save</button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
