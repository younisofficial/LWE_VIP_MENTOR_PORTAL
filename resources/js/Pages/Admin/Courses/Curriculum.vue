<script setup>
import { ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from '@/Components/Lwe/Dialog.vue';
import Field from '@/Components/Lwe/Field.vue';
import Icon from '@/Components/Lwe/Icon.vue';
import StatusBadge from '@/Components/Lwe/StatusBadge.vue';
import AiButton from '@/Components/Lwe/AiButton.vue';
import axios from 'axios';

const props = defineProps({ course: Object, modules: Array, lessonTypes: Array, area: { type: String, default: 'admin' } });
const r = (name, ...args) => route(`${props.area}.${name}`, ...args);

const typeLabel = { live_class: 'Live class', recorded_video: 'Recorded video', reading: 'Reading / notes', task: 'Task', quiz: 'Quiz' };

// Local copy for drag-and-drop; refreshed whenever the server sends new data.
const list = ref([]);
watch(() => props.modules, (m) => { list.value = JSON.parse(JSON.stringify(m)); }, { immediate: true });

// ---- Modules ----
const moduleForm = useForm({ title: '' });
const addModule = () => moduleForm.post(r('modules.store', props.course.id), { preserveScroll: true, onSuccess: () => moduleForm.reset() });
const renameModule = (m) => {
    const title = prompt('Module name', m.title);
    if (title && title !== m.title) router.put(r('modules.update', m.id), { title }, { preserveScroll: true });
};
const deleteModule = (m) => {
    if (confirm(`Delete "${m.title}" and all ${m.lessons.length} lessons inside it?`)) router.delete(r('modules.destroy', m.id), { preserveScroll: true });
};
const moveModule = (index, dir) => {
    const target = index + dir;
    if (target < 0 || target >= list.value.length) return;
    const [m] = list.value.splice(index, 1);
    list.value.splice(target, 0, m);
    saveOrder();
};

// ---- Drag-and-drop lessons (also between modules) ----
const dragging = ref(null); // { mi, li }
const onDragStart = (mi, li) => { dragging.value = { mi, li }; };
const onDrop = (mi, li) => {
    if (!dragging.value) return;
    const { mi: fromM, li: fromL } = dragging.value;
    const [lesson] = list.value[fromM].lessons.splice(fromL, 1);
    const insertAt = li === null ? list.value[mi].lessons.length : li;
    list.value[mi].lessons.splice(insertAt, 0, lesson);
    dragging.value = null;
    saveOrder();
};
const saveOrder = () => router.post(r('courses.reorder', props.course.id), {
    modules: list.value.map((m) => ({ id: m.id, lessons: m.lessons.map((l) => l.id) })),
}, { preserveScroll: true, preserveState: true });

// ---- Lesson editor ----
const editing = ref(null); // { moduleId, lesson|null }
const lessonForm = useForm({
    title: '', type: 'reading', summary: '', body: '', video_url: '', duration_minutes: '',
    unlock_type: 'immediate', unlock_at: '', is_preview: false, attachment: null,
});
const openLesson = (moduleId, lesson = null) => {
    lessonForm.reset();
    lessonForm.clearErrors();
    if (lesson) {
        Object.assign(lessonForm, {
            title: lesson.title, type: lesson.type, summary: lesson.summary ?? '', body: lesson.body ?? '', video_url: lesson.video_url ?? '',
            duration_minutes: lesson.duration_minutes ?? '', unlock_type: lesson.unlock_type, unlock_at: lesson.unlock_at ?? '', is_preview: !!lesson.is_preview,
        });
    }
    editing.value = { moduleId, lesson };
};
const saveLesson = () => {
    const opts = { preserveScroll: true, forceFormData: true, onSuccess: () => (editing.value = null) };
    if (editing.value.lesson) {
        lessonForm.transform((d) => ({ ...d, _method: 'put' })).post(r('lessons.update', editing.value.lesson.id), opts);
    } else {
        lessonForm.transform((d) => d).post(r('lessons.store', editing.value.moduleId), opts);
    }
};
// ---- AI: outline + lesson writer ----
const showOutline = ref(false);
const outlineForm = useForm({ weeks: 4, notes: '', language: 'roman_urdu' });
const buildOutline = () => outlineForm.post(route('admin.ai.course-outline', props.course.id), { preserveScroll: true, onSuccess: () => { showOutline.value = false; } });

const lessonAi = ref({ language: 'roman_urdu', hint: '', loading: false, error: '' });
const writeLesson = async () => {
    if (!lessonForm.title) { lessonAi.value.error = 'Write the lesson title first.'; return; }
    lessonAi.value.loading = true; lessonAi.value.error = '';
    try {
        const { data } = await axios.post(r('ai.lesson-content', props.course.id), {
            title: lessonForm.title, type: lessonForm.type, hint: lessonAi.value.hint, language: lessonAi.value.language,
        });
        lessonForm.summary = data.summary;
        lessonForm.body = data.body;
    } catch (e) {
        lessonAi.value.error = e.response?.data?.message ?? 'AI could not write this right now.';
    } finally {
        lessonAi.value.loading = false;
    }
};

const deleteLesson = (l) => {
    if (confirm(`Delete lesson "${l.title}"?`)) router.delete(r('lessons.destroy', l.id), { preserveScroll: true });
};
</script>

<template>
    <AppLayout :title="'Lessons · ' + course.title">
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <Link v-if="area === 'admin'" :href="route('admin.courses.edit', course.id)" class="text-sm font-semibold text-lwe-navy2 hover:underline">← Course settings</Link>
                <Link v-else :href="route('manage.lessons.index')" class="text-sm font-semibold text-lwe-navy2 hover:underline">← All my lessons</Link>
                <h1 class="page-title mt-1 flex items-center gap-3">{{ course.title }} <StatusBadge :status="course.status" /></h1>
                <p class="muted">Drag lessons to reorder them, even into another module.</p>
            </div>
            <div class="flex flex-wrap gap-2">
            <AiButton v-if="area === 'admin'" label="Build outline with AI" @click="showOutline = true" />
            <form class="flex gap-2" @submit.prevent="addModule">
                <label for="newModule" class="sr-only">New module name</label>
                <input id="newModule" v-model="moduleForm.title" type="text" class="input w-64" placeholder="New module, e.g. Week 1: Basics" required />
                <button type="submit" class="btn-dark" :disabled="moduleForm.processing">+ Module</button>
            </form>
            </div>
        </div>

        <div v-if="!list.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-lwe-muted">
            Start by adding a module (for example "Week 1"), then add lessons inside it.
        </div>

        <div class="space-y-5">
            <section v-for="(m, mi) in list" :key="m.id" class="card" @dragover.prevent @drop="onDrop(mi, null)">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-semibold text-lwe-navy">{{ mi + 1 }}. {{ m.title }} <span class="text-xs font-normal text-lwe-muted">· {{ m.lessons.length }} lessons</span></h2>
                    <div class="flex gap-1">
                        <button type="button" class="btn-outline btn-sm" :disabled="mi === 0" aria-label="Move module up" @click="moveModule(mi, -1)">↑</button>
                        <button type="button" class="btn-outline btn-sm" :disabled="mi === list.length - 1" aria-label="Move module down" @click="moveModule(mi, 1)">↓</button>
                        <button type="button" class="btn-outline btn-sm" @click="renameModule(m)">Rename</button>
                        <button type="button" class="btn-sm text-red-700 hover:underline" @click="deleteModule(m)">Delete</button>
                    </div>
                </div>

                <ul class="space-y-2">
                    <li v-for="(l, li) in m.lessons" :key="l.id" draggable="true"
                        class="flex items-center gap-3 rounded-xl border border-lwe-line bg-white px-3 py-3"
                        :class="{ 'opacity-50': dragging && dragging.mi === mi && dragging.li === li }"
                        @dragstart="onDragStart(mi, li)" @dragover.prevent @drop.stop="onDrop(mi, li)">
                        <span class="cursor-grab text-slate-400" aria-hidden="true"><Icon name="grip" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold">{{ l.title }}</span>
                            <span class="block text-xs text-lwe-muted">
                                {{ typeLabel[l.type] }}<template v-if="l.duration_minutes"> · {{ l.duration_minutes }} min</template>
                                <template v-if="l.unlock_type === 'date'"> · unlocks {{ l.unlock_at?.replace('T', ' ') }}</template>
                                <template v-if="l.unlock_type === 'after_previous'"> · after previous lesson</template>
                                <template v-if="l.is_preview"> · free preview</template>
                                <template v-if="l.attachment_name"> · file: {{ l.attachment_name }}</template>
                            </span>
                        </span>
                        <button type="button" class="btn-outline btn-sm" @click="openLesson(m.id, l)">Edit</button>
                        <button type="button" class="btn-sm text-red-700 hover:underline" @click="deleteLesson(l)">Delete</button>
                    </li>
                </ul>
                <button type="button" class="mt-3 text-sm font-semibold text-lwe-navy2 hover:underline" @click="openLesson(m.id)">+ Add lesson</button>
            </section>
        </div>

        <Dialog :show="showOutline" title="Build the course outline with AI" @close="showOutline = false">
            <form class="space-y-4" @submit.prevent="buildOutline">
                <p class="muted">AI adds modules and lessons after the ones you already have. You can edit, move or delete anything afterwards.</p>
                <div class="grid grid-cols-2 gap-4">
                    <Field label="Length (weeks)" for="ow" :error="outlineForm.errors.weeks"><input id="ow" v-model="outlineForm.weeks" type="number" min="1" max="26" class="input" /></Field>
                    <Field label="Language" for="ol">
                        <select id="ol" v-model="outlineForm.language" class="input"><option value="roman_urdu">Roman Urdu</option><option value="english">English</option><option value="urdu">اردو</option></select>
                    </Field>
                </div>
                <Field label="Notes for AI (topics, tools, level)" for="on" :error="outlineForm.errors.notes">
                    <textarea id="on" v-model="outlineForm.notes" rows="4" class="textarea" placeholder="e.g. Week 1 basics, Shopify setup, product research, Facebook ads, COD orders in Pakistan…" />
                </Field>
                <div class="flex justify-end gap-2 border-t border-lwe-line pt-4">
                    <button type="button" class="btn-outline" @click="showOutline = false">Cancel</button>
                    <AiButton :loading="outlineForm.processing" label="Build outline" @click="buildOutline" />
                </div>
            </form>
        </Dialog>

        <Dialog :show="!!editing" :title="editing?.lesson ? 'Edit lesson' : 'New lesson'" wide @close="editing = null">
            <form class="space-y-4" @submit.prevent="saveLesson">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Field class="sm:col-span-2" label="Lesson title" for="lt" :error="lessonForm.errors.title">
                        <input id="lt" v-model="lessonForm.title" type="text" class="input" required />
                    </Field>
                    <Field label="Type" for="ltype" :error="lessonForm.errors.type">
                        <select id="ltype" v-model="lessonForm.type" class="input">
                            <option v-for="t in lessonTypes" :key="t" :value="t">{{ typeLabel[t] }}</option>
                        </select>
                    </Field>
                </div>
                <div class="rounded-xl border border-violet-200 bg-violet-50 p-3">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="min-w-0 flex-1">
                            <label for="lhint" class="label text-violet-900">AI Lesson Writer — extra instructions (optional)</label>
                            <input id="lhint" v-model="lessonAi.hint" type="text" class="input" placeholder="e.g. JazzCash se payment lene ka tareeqa bhi batayein" />
                        </div>
                        <label class="sr-only" for="llang">Language</label>
                        <select id="llang" v-model="lessonAi.language" class="input w-36">
                            <option value="roman_urdu">Roman Urdu</option><option value="english">English</option><option value="urdu">اردو</option>
                        </select>
                        <AiButton :loading="lessonAi.loading" label="Write notes" @click="writeLesson" />
                    </div>
                    <p v-if="lessonAi.error" class="mt-1 text-xs font-semibold text-red-700">{{ lessonAi.error }}</p>
                </div>
                <Field label="Short summary" for="lsum" :error="lessonForm.errors.summary">
                    <input id="lsum" v-model="lessonForm.summary" type="text" class="input" />
                </Field>
                <Field v-if="lessonForm.type === 'recorded_video'" label="Video link (YouTube/Vimeo for now — protected player in Module 4)" for="lvid" :error="lessonForm.errors.video_url">
                    <input id="lvid" v-model="lessonForm.video_url" type="url" class="input" />
                </Field>
                <Field label="Notes / content" for="lbody" :error="lessonForm.errors.body">
                    <textarea id="lbody" v-model="lessonForm.body" rows="6" class="textarea" />
                </Field>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Field label="Duration (minutes)" for="ldur" :error="lessonForm.errors.duration_minutes">
                        <input id="ldur" v-model="lessonForm.duration_minutes" type="number" min="1" class="input" />
                    </Field>
                    <Field label="Unlock" for="lunlock" :error="lessonForm.errors.unlock_type">
                        <select id="lunlock" v-model="lessonForm.unlock_type" class="input">
                            <option value="immediate">Immediately</option>
                            <option value="date">On a date</option>
                            <option value="after_previous">After previous lesson</option>
                        </select>
                    </Field>
                    <Field v-if="lessonForm.unlock_type === 'date'" label="Unlock date & time (PK)" for="lat" :error="lessonForm.errors.unlock_at">
                        <input id="lat" v-model="lessonForm.unlock_at" type="datetime-local" class="input" />
                    </Field>
                </div>
                <Field label="Attachment (PDF, slides, zip — up to 20 MB)" for="latt" :error="lessonForm.errors.attachment">
                    <input id="latt" type="file" class="block text-sm" @input="lessonForm.attachment = $event.target.files[0]" />
                </Field>
                <label class="flex items-center gap-2 text-sm"><input v-model="lessonForm.is_preview" type="checkbox" class="rounded" /> Free preview (visible before enrolling)</label>
                <div class="flex justify-end gap-2 border-t border-lwe-line pt-4">
                    <button type="button" class="btn-outline" @click="editing = null">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="lessonForm.processing">Save lesson</button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
