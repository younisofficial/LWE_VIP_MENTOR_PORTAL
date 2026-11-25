<script setup>
import { reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import AiMini from '@/Components/Lwe/AiMini.vue';

const props = defineProps({ task: Object, rows: Array, stats: Object });

const filter = ref('all'); // all | to_check | missing | graded
const shown = () => props.rows.filter((r) => {
    if (filter.value === 'to_check') return r.submission?.status === 'submitted';
    if (filter.value === 'missing') return !r.submission;
    if (filter.value === 'graded') return r.submission?.status === 'graded';
    return true;
});

// One grading box per submission
const boxes = reactive({});
const box = (s) => {
    if (!boxes[s.id]) boxes[s.id] = { marks: s.marks ?? '', feedback: s.feedback ?? '', saving: false, ai: false, aiError: '', error: '' };
    return boxes[s.id];
};
// Create the boxes up front (not while rendering).
watch(() => props.rows, (rows) => rows.forEach((r) => r.submission && box(r.submission)), { immediate: true });
const language = ref('roman_urdu');

const save = (s, status) => {
    const b = box(s);
    b.saving = true; b.error = '';
    router.post(route('manage.submissions.grade', s.id), { marks: status === 'graded' ? b.marks : null, feedback: b.feedback, status }, {
        preserveScroll: true,
        onError: (e) => (b.error = Object.values(e)[0]),
        onFinish: () => (b.saving = false),
    });
};
const aiCheck = async (s) => {
    const b = box(s);
    b.ai = true; b.aiError = '';
    try {
        const { data } = await axios.post(route('manage.submissions.ai', s.id), { language: language.value });
        s.ai_marks = data.marks;
        s.ai_feedback = data.feedback;
    } catch (e) {
        b.aiError = e.response?.data?.message ?? 'AI could not check this now.';
    } finally {
        b.ai = false;
    }
};
const useAi = (s) => { const b = box(s); b.marks = s.ai_marks; b.feedback = s.ai_feedback; };
const checkAll = () => router.post(route('manage.tasks.ai-all', props.task.id), { language: language.value }, { preserveScroll: true });
const statusLabel = { submitted: 'To check', graded: 'Checked', resubmit: 'Sent back' };
</script>

<template>
    <AppLayout :title="task.title">
        <Link :href="route('manage.tasks.index')" class="text-sm font-semibold text-lwe-navy2 hover:underline">← Tasks</Link>
        <div class="mt-2 mb-6 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="page-title">{{ task.title }}</h1>
                <p class="muted">{{ task.course }}<template v-if="task.batch"> · {{ task.batch }}</template> · {{ task.max_marks }} marks<template v-if="task.due_label"> · Due {{ task.due_label }}</template></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select v-model="language" class="input w-36" aria-label="AI language"><option value="roman_urdu">Roman Urdu</option><option value="english">English</option><option value="urdu">اردو</option></select>
                <button type="button" class="inline-flex h-10 items-center rounded-lg bg-violet-700 px-4 text-sm font-semibold text-white hover:bg-violet-800" :disabled="!stats.to_check" @click="checkAll">AI check all unchecked</button>
            </div>
        </div>

        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="card"><p class="text-xs text-lwe-muted">Students</p><p class="text-2xl font-bold text-lwe-navy">{{ stats.expected }}</p></div>
            <div class="card"><p class="text-xs text-lwe-muted">Submitted</p><p class="text-2xl font-bold text-lwe-navy">{{ stats.submitted }}</p></div>
            <div class="card"><p class="text-xs text-lwe-muted">To check</p><p class="text-2xl font-bold text-amber-700">{{ stats.to_check }}</p></div>
            <div class="card"><p class="text-xs text-lwe-muted">Average marks</p><p class="text-2xl font-bold text-lwe-navy">{{ stats.average != null ? Number(stats.average).toFixed(1) : '—' }}</p></div>
        </div>

        <details class="card mb-6"><summary class="cursor-pointer font-semibold text-lwe-navy">Task instructions &amp; rubric</summary>
            <p class="mt-3 whitespace-pre-line text-sm">{{ task.instructions }}</p>
            <p v-if="task.rubric" class="mt-3 whitespace-pre-line rounded-xl bg-slate-50 p-3 text-sm"><b>Rubric:</b> {{ task.rubric }}</p>
            <a v-if="task.attachment_name" :href="route('tasks.attachment', task.id)" class="btn-outline btn-sm mt-3">Download {{ task.attachment_name }}</a>
        </details>

        <div class="mb-4 flex flex-wrap gap-2" role="tablist">
            <button v-for="(l, k) in { all: 'All', to_check: 'To check', missing: 'Not submitted', graded: 'Checked' }" :key="k" type="button"
                class="rounded-full px-3 py-1.5 text-sm font-semibold" :class="filter === k ? 'bg-lwe-navy text-white' : 'bg-white text-lwe-navy ring-1 ring-lwe-line'" @click="filter = k">{{ l }}</button>
        </div>

        <div class="space-y-3">
            <div v-for="r in shown()" :key="r.student.id" class="card">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-semibold">{{ r.student.name }} <span class="text-xs font-normal text-lwe-muted">{{ r.student.student_code }}</span></p>
                    <span v-if="!r.submission" class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-800">Not submitted</span>
                    <span v-else class="text-xs text-lwe-muted">
                        {{ r.submission.submitted_label }}<span v-if="r.submission.is_late" class="ml-1 rounded bg-red-100 px-1.5 text-red-800">late</span>
                        · <b>{{ statusLabel[r.submission.status] }}</b><template v-if="r.submission.status === 'graded'"> · {{ r.submission.marks }}/{{ task.max_marks }}</template>
                    </span>
                </div>

                <div v-if="r.submission" class="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="space-y-2 text-sm">
                        <p v-if="r.submission.body" class="whitespace-pre-line rounded-xl bg-slate-50 p-3">{{ r.submission.body }}</p>
                        <a v-if="r.submission.link" :href="r.submission.link" target="_blank" rel="noopener noreferrer" class="block break-all font-semibold text-lwe-navy2 underline">{{ r.submission.link }}</a>
                        <template v-if="r.submission.file_name">
                            <img v-if="r.submission.file_is_image" :src="route('manage.submissions.file', r.submission.id)" :alt="`Submission by ${r.student.name}`" class="max-h-72 rounded-xl border border-lwe-line" loading="lazy" />
                            <a :href="route('manage.submissions.file', r.submission.id)" target="_blank" class="btn-outline btn-sm">Open {{ r.submission.file_name }}</a>
                        </template>
                    </div>
                    <div class="space-y-2">
                        <div v-if="r.submission.ai_feedback" class="rounded-xl border border-violet-200 bg-violet-50 p-3 text-sm">
                            <div class="flex items-center justify-between gap-2"><p class="font-semibold text-violet-900">AI suggests {{ r.submission.ai_marks }}/{{ task.max_marks }}</p><button type="button" class="text-xs font-semibold text-violet-800 underline" @click="useAi(r.submission)">Use this</button></div>
                            <p class="mt-1 whitespace-pre-line text-violet-950">{{ r.submission.ai_feedback }}</p>
                        </div>
                        <div class="flex items-end gap-2">
                            <div class="w-28"><label :for="`m${r.submission.id}`" class="label">Marks /{{ task.max_marks }}</label><input :id="`m${r.submission.id}`" v-model="box(r.submission).marks" type="number" step="0.5" min="0" :max="task.max_marks" class="input" /></div>
                            <AiMini :loading="box(r.submission).ai" @click="aiCheck(r.submission)" />
                            <span class="pb-2 text-xs text-violet-800">AI check</span>
                        </div>
                        <label :for="`f${r.submission.id}`" class="label">Feedback for the student</label>
                        <textarea :id="`f${r.submission.id}`" v-model="box(r.submission).feedback" rows="3" class="textarea" />
                        <p v-if="box(r.submission).aiError" class="text-xs font-semibold text-red-700">{{ box(r.submission).aiError }}</p>
                        <p v-if="box(r.submission).error" class="text-xs font-semibold text-red-700">{{ box(r.submission).error }}</p>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn-primary btn-sm" :disabled="box(r.submission).saving || box(r.submission).marks === ''" @click="save(r.submission, 'graded')">Save marks</button>
                            <button type="button" class="btn-outline btn-sm" :disabled="box(r.submission).saving" @click="save(r.submission, 'resubmit')">Ask to redo</button>
                        </div>
                    </div>
                </div>
            </div>
            <p v-if="!shown().length" class="text-sm text-lwe-muted">Nobody here.</p>
        </div>
    </AppLayout>
</template>
