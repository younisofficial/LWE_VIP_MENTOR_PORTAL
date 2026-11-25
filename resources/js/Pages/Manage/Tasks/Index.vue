<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from '@/Components/Lwe/Dialog.vue';
import Field from '@/Components/Lwe/Field.vue';
import Pagination from '@/Components/Lwe/Pagination.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';
import StatusBadge from '@/Components/Lwe/StatusBadge.vue';
import AiButton from '@/Components/Lwe/AiButton.vue';

const props = defineProps({ tasks: Object, filters: Object, courses: Array, lessons: Array, batches: Array });

const typeLabel = { any: 'Text, link or file', text: 'Written answer', file: 'File / screenshot', link: 'Link' };

const f = ref({ course_id: props.filters.course_id ?? '', status: props.filters.status ?? '' });
watch(f, () => router.get(route('manage.tasks.index'), Object.fromEntries(Object.entries(f.value).filter(([, v]) => v)), { preserveState: true, replace: true }), { deep: true });

const show = ref(false);
const editing = ref(null);
const blank = { course_id: '', lesson_id: '', batch_id: '', title: '', instructions: '', rubric: '', submission_type: 'any', max_marks: 10, due_at: '', allow_late: true, status: 'published', attachment: null };
const form = useForm({ ...blank });
const courseLessons = computed(() => props.lessons.filter((l) => l.course_id === Number(form.course_id)));
const courseBatches = computed(() => props.batches.filter((b) => b.course_ids.includes(Number(form.course_id))));

const openNew = () => {
    editing.value = null;
    form.defaults({ ...blank, course_id: f.value.course_id ? Number(f.value.course_id) : (props.courses.length === 1 ? props.courses[0].id : '') });
    form.reset();
    form.clearErrors();
    show.value = true;
};
const openEdit = (t) => {
    editing.value = t;
    form.defaults({ ...blank, ...t, lesson_id: t.lesson_id ?? '', batch_id: t.batch_id ?? '', due_at: t.due_at ?? '', rubric: t.rubric ?? '', attachment: null });
    form.reset();
    form.clearErrors();
    show.value = true;
};
const save = () => {
    const opts = { preserveScroll: true, forceFormData: true, onSuccess: () => (show.value = false) };
    if (editing.value) form.transform((d) => ({ ...d, _method: 'put' })).post(route('manage.tasks.update', editing.value.id), opts);
    else form.transform((d) => d).post(route('manage.tasks.store'), opts);
};
const remove = (t) => {
    if (confirm(`Delete "${t.title}"?`)) router.delete(route('manage.tasks.destroy', t.id), { preserveScroll: true });
};

// AI Task Writer
const ai = ref({ topic: '', language: 'roman_urdu', loading: false, error: '' });
const writeWithAi = async () => {
    if (!form.course_id) { ai.value.error = 'Choose the course first.'; return; }
    ai.value.loading = true; ai.value.error = '';
    try {
        const { data } = await axios.post(route('manage.tasks.ai'), { course_id: form.course_id, lesson_id: form.lesson_id || null, topic: ai.value.topic, language: ai.value.language });
        Object.assign(form, data);
    } catch (e) {
        ai.value.error = e.response?.data?.message ?? 'AI could not write this right now.';
    } finally {
        ai.value.loading = false;
    }
};
</script>

<template>
    <AppLayout title="Tasks">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><h1 class="page-title">Tasks</h1><p class="muted">Homework students submit as text, a link or a file. Check and mark them here.</p></div>
            <button type="button" class="btn-primary" :disabled="!courses.length" @click="openNew">+ New task</button>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <select v-model="f.course_id" class="input w-64" aria-label="Course"><option value="">All courses</option><option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option></select>
            <select v-model="f.status" class="input w-40" aria-label="Status"><option value="">Any status</option><option value="published">Published</option><option value="draft">Draft</option></select>
        </div>

        <EmptyState v-if="!tasks.data.length" title="No tasks yet" text="Create a task — or let AI write one from a lesson." />
        <div v-else class="table-wrap">
            <table class="table">
                <thead><tr><th>Task</th><th>Course</th><th>Due</th><th>Submitted</th><th>To check</th><th>Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="t in tasks.data" :key="t.id">
                        <td><Link :href="route('manage.tasks.show', t.id)" class="font-semibold text-lwe-navy hover:underline">{{ t.title }}</Link><p class="text-xs text-lwe-muted">{{ t.max_marks }} marks · {{ typeLabel[t.submission_type] }}<template v-if="t.lesson"> · {{ t.lesson }}</template></p></td>
                        <td class="text-sm">{{ t.course }}<p v-if="t.batch" class="text-xs text-lwe-muted">{{ t.batch }} only</p></td>
                        <td class="text-sm">{{ t.due_label ?? '—' }}</td>
                        <td>{{ t.submissions_count }} / {{ t.expected }}</td>
                        <td><span v-if="t.to_check_count" class="rounded-full bg-lwe-orangeSoft px-2 py-0.5 text-xs font-bold text-amber-900">{{ t.to_check_count }}</span><span v-else class="text-xs text-lwe-muted">—</span></td>
                        <td><StatusBadge :status="t.status" /></td>
                        <td class="whitespace-nowrap text-right">
                            <Link :href="route('manage.tasks.show', t.id)" class="btn-dark btn-sm">Check</Link>
                            <button type="button" class="btn-outline btn-sm ml-1" @click="openEdit(t)">Edit</button>
                            <button v-if="!t.submissions_count" type="button" class="btn-outline btn-sm ml-1" @click="remove(t)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="tasks.links" />

        <Dialog :show="show" :title="editing ? 'Edit task' : 'New task'" wide @close="show = false">
            <form class="space-y-4" @submit.prevent="save">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Field label="Course" for="tc" :error="form.errors.course_id">
                        <select id="tc" v-model="form.course_id" class="input" required @change="form.lesson_id = ''; form.batch_id = ''">
                            <option value="" disabled>Choose…</option><option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
                        </select>
                    </Field>
                    <Field label="Lesson (optional)" for="tl" :error="form.errors.lesson_id">
                        <select id="tl" v-model="form.lesson_id" class="input" :disabled="!form.course_id"><option value="">—</option><option v-for="l in courseLessons" :key="l.id" :value="l.id">{{ l.title }}</option></select>
                    </Field>
                    <Field label="For batch" for="tb" :error="form.errors.batch_id">
                        <select id="tb" v-model="form.batch_id" class="input" :disabled="!form.course_id"><option value="">All batches</option><option v-for="b in courseBatches" :key="b.id" :value="b.id">{{ b.name }}</option></select>
                    </Field>
                </div>

                <div class="rounded-xl border border-violet-200 bg-violet-50 p-3">
                    <p class="mb-2 text-sm font-semibold text-violet-900">AI Task Writer <span class="font-normal">— choose a lesson above (or write a topic), AI writes the task, steps and marking rubric.</span></p>
                    <div class="flex flex-wrap items-end gap-2">
                        <input v-model="ai.topic" type="text" class="input min-w-0 flex-1" placeholder="Topic / notes, e.g. Canva mein Instagram post banana" aria-label="Topic" />
                        <select v-model="ai.language" class="input w-36" aria-label="Language"><option value="roman_urdu">Roman Urdu</option><option value="english">English</option><option value="urdu">اردو</option></select>
                        <AiButton :loading="ai.loading" label="Write task" @click="writeWithAi" />
                    </div>
                    <p v-if="ai.error" class="mt-1 text-xs font-semibold text-red-700">{{ ai.error }}</p>
                </div>

                <Field label="Task title" for="tt" :error="form.errors.title"><input id="tt" v-model="form.title" type="text" class="input" required /></Field>
                <Field label="Instructions (what students must do)" for="ti" :error="form.errors.instructions"><textarea id="ti" v-model="form.instructions" rows="7" class="textarea" required /></Field>
                <Field label="Marking rubric (only mentors and AI see this)" for="tr" :error="form.errors.rubric"><textarea id="tr" v-model="form.rubric" rows="3" class="textarea" placeholder="Full marks if… Lose marks if…" /></Field>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Field label="Students submit" for="ts" :error="form.errors.submission_type">
                        <select id="ts" v-model="form.submission_type" class="input"><option v-for="(l, k) in typeLabel" :key="k" :value="k">{{ l }}</option></select>
                    </Field>
                    <Field label="Total marks" for="tm" :error="form.errors.max_marks"><input id="tm" v-model="form.max_marks" type="number" min="1" max="100" class="input" required /></Field>
                    <Field label="Last date & time (PK)" for="td" :error="form.errors.due_at"><input id="td" v-model="form.due_at" type="datetime-local" class="input" /></Field>
                </div>
                <div class="flex flex-wrap items-center gap-6">
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.allow_late" type="checkbox" class="rounded" /> Accept late submissions (marked “late”)</label>
                    <Field label="Attachment (optional)" for="ta" :error="form.errors.attachment">
                        <input id="ta" type="file" class="block text-sm" @input="form.attachment = $event.target.files[0]" />
                        <p v-if="editing?.attachment_name" class="mt-1 text-xs text-lwe-muted">Current: {{ editing.attachment_name }}</p>
                    </Field>
                </div>
                <fieldset>
                    <legend class="label">Status</legend>
                    <div class="flex gap-4 text-sm">
                        <label class="flex items-center gap-2"><input v-model="form.status" type="radio" value="published" /> Publish now (students are told)</label>
                        <label class="flex items-center gap-2"><input v-model="form.status" type="radio" value="draft" /> Draft</label>
                    </div>
                </fieldset>
                <div class="flex justify-end gap-2 border-t border-lwe-line pt-4">
                    <button type="button" class="btn-outline" @click="show = false">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">Save task</button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
