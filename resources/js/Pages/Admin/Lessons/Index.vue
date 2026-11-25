<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from '@/Components/Lwe/Dialog.vue';
import Field from '@/Components/Lwe/Field.vue';
import Pagination from '@/Components/Lwe/Pagination.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';
import AiButton from '@/Components/Lwe/AiButton.vue';
import axios from 'axios';

const props = defineProps({ lessons: Object, filters: Object, courses: Array, modules: Array, lessonTypes: Array, area: { type: String, default: 'admin' } });
// Admins use /admin, mentors /manage — same page, same names after the prefix.
const r = (name, ...args) => route(`${props.area}.${name}`, ...args);

const typeLabel = { live_class: 'Live class', recorded_video: 'Recorded video', reading: 'Reading / notes', task: 'Task', quiz: 'Quiz' };

// Filters
const f = ref({ course_id: props.filters.course_id ?? '', search: props.filters.search ?? '' });
let t;
watch(f, () => {
    clearTimeout(t);
    t = setTimeout(() => router.get(r('lessons.index'), Object.fromEntries(Object.entries(f.value).filter(([, v]) => v)), { preserveState: true, replace: true }), 300);
}, { deep: true });

// New lesson: course first, then module (optional)
const show = ref(false);
const form = useForm({
    course_id: '', course_module_id: '', new_module: '',
    title: '', type: 'reading', summary: '', body: '', video_url: '', duration_minutes: '',
    unlock_type: 'immediate', unlock_at: '', is_preview: false, attachment: null,
});
const courseModules = computed(() => props.modules.filter((m) => m.course_id === Number(form.course_id)));
const addingModule = ref(false);
watch(() => form.course_id, () => { form.course_module_id = ''; form.new_module = ''; addingModule.value = false; });

const open = () => {
    form.reset();
    form.clearErrors();
    if (f.value.course_id) form.course_id = Number(f.value.course_id);
    show.value = true;
};
const save = () => form.post(r('lessons.create'), {
    preserveScroll: true, forceFormData: true,
    onSuccess: () => { show.value = false; },
});

// AI lesson writer (needs the course to be chosen first)
const ai = ref({ language: 'roman_urdu', hint: '', loading: false, error: '' });
const writeLesson = async () => {
    if (!form.course_id) { ai.value.error = 'Choose the course first.'; return; }
    if (!form.title) { ai.value.error = 'Write the lesson title first.'; return; }
    ai.value.loading = true; ai.value.error = '';
    try {
        const { data } = await axios.post(r('ai.lesson-content', form.course_id), {
            title: form.title, type: form.type, hint: ai.value.hint, language: ai.value.language,
        });
        form.summary = data.summary;
        form.body = data.body;
    } catch (e) {
        ai.value.error = e.response?.data?.message ?? 'AI could not write this right now.';
    } finally {
        ai.value.loading = false;
    }
};
</script>

<template>
    <AppLayout title="Lessons">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><h1 class="page-title">Lessons</h1><p class="muted">{{ area === 'manage' ? 'Upload lessons for your courses. Choose the course, then the module.' : 'Add a lesson to any course. Choose the course, then the module.' }}</p></div>
            <button type="button" class="btn-primary" :disabled="!courses.length" @click="open">+ New lesson</button>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <input v-model="f.search" type="search" class="input w-64" placeholder="Search lesson title" aria-label="Search" />
            <select v-model="f.course_id" class="input w-64" aria-label="Course"><option value="">All courses</option><option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option></select>
        </div>

        <EmptyState v-if="!courses.length" title="No courses yet" :text="area === 'manage' ? 'No course is assigned to you yet. Ask the admin to add you as a mentor in the course.' : 'Create a course first, then add its lessons here.'" />
        <EmptyState v-else-if="!lessons.data.length" title="No lessons yet" text="Press “New lesson” and choose a course." />
        <div v-else class="table-wrap">
            <table class="table">
                <thead><tr><th>Lesson</th><th>Course</th><th>Module</th><th>Type</th><th>Minutes</th><th>Added</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="l in lessons.data" :key="l.id">
                        <td class="font-semibold">{{ l.title }} <span v-if="l.is_preview" class="ml-1 rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-800">Preview</span></td>
                        <td>{{ l.course }}</td>
                        <td class="text-sm text-lwe-muted">{{ l.module }}</td>
                        <td class="text-sm">{{ typeLabel[l.type] ?? l.type }}</td>
                        <td>{{ l.duration ?? '—' }}</td>
                        <td class="text-xs">{{ l.date }}</td>
                        <td class="text-right"><Link :href="r('courses.curriculum', l.course_id)" class="btn-outline btn-sm">Edit / order</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="lessons.links" />

        <Dialog :show="show" title="New lesson" wide @close="show = false">
            <form class="space-y-4" @submit.prevent="save">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Course" for="lc" :error="form.errors.course_id">
                        <select id="lc" v-model="form.course_id" class="input" required>
                            <option value="" disabled>Choose a course…</option>
                            <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
                        </select>
                    </Field>
                    <Field label="Module (section)" for="lm" :error="form.errors.course_module_id || form.errors.new_module">
                        <input v-if="addingModule" id="lm" v-model="form.new_module" type="text" class="input" placeholder="e.g. Week 1: Basics" />
                        <select v-else id="lm" v-model="form.course_module_id" class="input" :disabled="!form.course_id">
                            <option value="">{{ courseModules.length ? 'Last module (default)' : 'New module “Lessons” (automatic)' }}</option>
                            <option v-for="m in courseModules" :key="m.id" :value="m.id">{{ m.title }}</option>
                        </select>
                        <button v-if="form.course_id" type="button" class="mt-1 text-xs font-semibold text-lwe-navy2 hover:underline"
                            @click="addingModule = !addingModule; form.new_module = ''; form.course_module_id = ''">
                            {{ addingModule ? 'Choose an existing module' : '+ New module' }}
                        </button>
                    </Field>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Field class="sm:col-span-2" label="Lesson title" for="lt" :error="form.errors.title">
                        <input id="lt" v-model="form.title" type="text" class="input" required />
                    </Field>
                    <Field label="Type" for="ltype" :error="form.errors.type">
                        <select id="ltype" v-model="form.type" class="input">
                            <option v-for="t in lessonTypes" :key="t" :value="t">{{ typeLabel[t] }}</option>
                        </select>
                    </Field>
                </div>
                <div class="rounded-xl border border-violet-200 bg-violet-50 p-3">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="min-w-0 flex-1">
                            <label for="lhint" class="label text-violet-900">AI Lesson Writer — extra instructions (optional)</label>
                            <input id="lhint" v-model="ai.hint" type="text" class="input" placeholder="e.g. JazzCash se payment lene ka tareeqa bhi batayein" />
                        </div>
                        <label class="sr-only" for="llang">Language</label>
                        <select id="llang" v-model="ai.language" class="input w-36">
                            <option value="roman_urdu">Roman Urdu</option><option value="english">English</option><option value="urdu">اردو</option>
                        </select>
                        <AiButton :loading="ai.loading" label="Write notes" @click="writeLesson" />
                    </div>
                    <p v-if="ai.error" class="mt-1 text-xs font-semibold text-red-700">{{ ai.error }}</p>
                </div>
                <Field label="Short summary" for="lsum" :error="form.errors.summary"><input id="lsum" v-model="form.summary" type="text" class="input" /></Field>
                <Field v-if="form.type === 'recorded_video'" label="Video link (YouTube/Vimeo)" for="lvid" :error="form.errors.video_url"><input id="lvid" v-model="form.video_url" type="url" class="input" /></Field>
                <Field label="Notes / content" for="lbody" :error="form.errors.body"><textarea id="lbody" v-model="form.body" rows="6" class="textarea" /></Field>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Field label="Duration (minutes)" for="ldur" :error="form.errors.duration_minutes"><input id="ldur" v-model="form.duration_minutes" type="number" min="1" class="input" /></Field>
                    <Field label="Unlock" for="lunlock" :error="form.errors.unlock_type">
                        <select id="lunlock" v-model="form.unlock_type" class="input">
                            <option value="immediate">Immediately</option><option value="date">On a date</option><option value="after_previous">After previous lesson</option>
                        </select>
                    </Field>
                    <Field v-if="form.unlock_type === 'date'" label="Unlock date & time (PK)" for="lat" :error="form.errors.unlock_at"><input id="lat" v-model="form.unlock_at" type="datetime-local" class="input" /></Field>
                </div>
                <Field label="Attachment (PDF, slides, zip — up to 20 MB)" for="latt" :error="form.errors.attachment">
                    <input id="latt" type="file" class="block text-sm" @input="form.attachment = $event.target.files[0]" />
                </Field>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.is_preview" type="checkbox" class="rounded" /> Free preview (visible before enrolling)</label>
                <div class="flex justify-end gap-2 border-t border-lwe-line pt-4">
                    <button type="button" class="btn-outline" @click="show = false">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">Save lesson</button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
