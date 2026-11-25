<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import Field from '@/Components/Lwe/Field.vue';
import AiButton from '@/Components/Lwe/AiButton.vue';
import StatusBadge from '@/Components/Lwe/StatusBadge.vue';

const props = defineProps({ quiz: Object, questions: Array, lessons: Array, batches: Array });

const typeLabel = { single: 'One right answer', multiple: 'More than one right answer', true_false: 'True / False' };
const clone = (q) => ({ id: q.id ?? null, type: q.type, question: q.question, options: [...q.options], correct: [...q.correct], explanation: q.explanation ?? '', marks: q.marks ?? 1 });

const form = useForm({
    title: props.quiz.title,
    instructions: props.quiz.instructions ?? '',
    lesson_id: props.quiz.lesson_id ?? '',
    batch_id: props.quiz.batch_id ?? '',
    time_limit_minutes: props.quiz.time_limit_minutes ?? '',
    pass_percent: props.quiz.pass_percent,
    max_attempts: props.quiz.max_attempts,
    shuffle_questions: props.quiz.shuffle_questions,
    show_answers: props.quiz.show_answers,
    due_at: props.quiz.due_at ?? '',
    status: props.quiz.status,
    questions: props.questions.map(clone),
});

const addQuestion = (type = 'single') => {
    form.questions.push({ id: null, type, question: '', options: type === 'true_false' ? ['True', 'False'] : ['', '', '', ''], correct: [], explanation: '', marks: 1 });
};
const removeQuestion = (i) => form.questions.splice(i, 1);
const move = (i, d) => { const j = i + d; if (j < 0 || j >= form.questions.length) return; const [q] = form.questions.splice(i, 1); form.questions.splice(j, 0, q); };
const setType = (q, type) => {
    q.type = type;
    if (type === 'true_false') { q.options = ['True', 'False']; q.correct = q.correct.filter((c) => c < 2).slice(0, 1); }
    else if (type === 'single') q.correct = q.correct.slice(0, 1);
};
const toggleCorrect = (q, i) => {
    if (q.type === 'multiple') q.correct = q.correct.includes(i) ? q.correct.filter((c) => c !== i) : [...q.correct, i];
    else q.correct = [i];
};
const addOption = (q) => { if (q.options.length < 8) q.options.push(''); };
const removeOption = (q, i) => {
    if (q.options.length <= 2) return;
    q.options.splice(i, 1);
    q.correct = q.correct.filter((c) => c !== i).map((c) => (c > i ? c - 1 : c));
};
const totalMarks = () => form.questions.reduce((t, q) => t + Number(q.marks || 0), 0);

const save = (status) => {
    form.status = status ?? form.status;
    form.put(route('manage.quizzes.update', props.quiz.id), {
        preserveScroll: true,
        // New questions now have ids: take the saved list back from the server.
        onSuccess: () => { form.questions = props.questions.map(clone); },
    });
};
const firstError = () => Object.entries(form.errors).find(([k]) => k.startsWith('questions'))?.[1];

// AI Quiz Maker
const ai = ref({ lesson_id: props.quiz.lesson_id ?? '', topic: '', count: 10, difficulty: 'medium', language: 'roman_urdu', loading: false, error: '', added: 0 });
const generate = async () => {
    ai.value.loading = true; ai.value.error = ''; ai.value.added = 0;
    try {
        const { data } = await axios.post(route('manage.quizzes.ai', props.quiz.id), {
            lesson_id: ai.value.lesson_id || null, topic: ai.value.topic, count: ai.value.count, difficulty: ai.value.difficulty, language: ai.value.language,
        });
        data.questions.forEach((q) => form.questions.push(clone(q)));
        ai.value.added = data.questions.length;
    } catch (e) {
        ai.value.error = e.response?.data?.message ?? 'AI could not write questions right now.';
    } finally {
        ai.value.loading = false;
    }
};
const destroy = () => { if (confirm('Delete this quiz?')) router.delete(route('manage.quizzes.destroy', props.quiz.id)); };
</script>

<template>
    <AppLayout :title="quiz.title">
        <Link :href="route('manage.quizzes.index')" class="text-sm font-semibold text-lwe-navy2 hover:underline">← Quizzes</Link>
        <div class="mt-2 mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div><h1 class="page-title">{{ form.title || 'Quiz' }}</h1><p class="muted">{{ quiz.course }} · {{ form.questions.length }} questions · {{ totalMarks() }} marks · <StatusBadge :status="quiz.status" /></p></div>
            <div class="flex flex-wrap gap-2">
                <Link :href="route('manage.quizzes.results', quiz.id)" class="btn-outline">Results</Link>
                <button type="button" class="btn-outline" :disabled="form.processing" @click="save('draft')">Save draft</button>
                <button type="button" class="btn-primary" :disabled="form.processing" @click="save('published')">{{ quiz.status === 'published' ? 'Save' : 'Publish' }}</button>
            </div>
        </div>
        <p v-if="form.errors.status || firstError()" class="mb-4 rounded-xl bg-red-50 p-3 text-sm font-semibold text-red-800" role="alert">{{ form.errors.status || firstError() }}</p>
        <p v-if="quiz.has_attempts" class="mb-4 rounded-xl bg-lwe-orangeSoft p-3 text-sm text-amber-900">Students have already taken this quiz. Changing the right answers changes nothing for old attempts — only for new ones.</p>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <!-- Settings -->
            <section class="card space-y-4 xl:col-span-1" aria-label="Quiz settings">
                <h2 class="font-semibold text-lwe-navy">Settings</h2>
                <Field label="Title" for="qt" :error="form.errors.title"><input id="qt" v-model="form.title" type="text" class="input" /></Field>
                <Field label="Instructions for students" for="qi" :error="form.errors.instructions"><textarea id="qi" v-model="form.instructions" rows="3" class="textarea" /></Field>
                <div class="grid grid-cols-2 gap-3">
                    <Field label="Lesson" for="ql"><select id="ql" v-model="form.lesson_id" class="input"><option value="">—</option><option v-for="l in lessons" :key="l.id" :value="l.id">{{ l.title }}</option></select></Field>
                    <Field label="For batch" for="qb" :error="form.errors.batch_id"><select id="qb" v-model="form.batch_id" class="input"><option value="">All batches</option><option v-for="b in batches" :key="b.id" :value="b.id">{{ b.name }}</option></select></Field>
                    <Field label="Time limit (min)" for="qtl" :error="form.errors.time_limit_minutes" hint="Empty = no limit"><input id="qtl" v-model="form.time_limit_minutes" type="number" min="1" class="input" /></Field>
                    <Field label="Pass mark %" for="qp" :error="form.errors.pass_percent"><input id="qp" v-model="form.pass_percent" type="number" min="0" max="100" class="input" /></Field>
                    <Field label="Attempts allowed" for="qa" :error="form.errors.max_attempts"><input id="qa" v-model="form.max_attempts" type="number" min="1" max="10" class="input" /></Field>
                    <Field label="Open until (PK)" for="qd" :error="form.errors.due_at"><input id="qd" v-model="form.due_at" type="datetime-local" class="input" /></Field>
                </div>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.shuffle_questions" type="checkbox" class="rounded" /> Shuffle question order for each student</label>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.show_answers" type="checkbox" class="rounded" /> Show right answers after submitting</label>
                <button v-if="!quiz.has_attempts" type="button" class="text-sm font-semibold text-red-700 hover:underline" @click="destroy">Delete quiz</button>
            </section>

            <!-- Questions -->
            <section class="space-y-4 xl:col-span-2" aria-label="Questions">
                <div class="rounded-2xl border border-violet-200 bg-violet-50 p-4">
                    <p class="font-semibold text-violet-900">AI Quiz Maker</p>
                    <p class="mb-3 text-xs text-violet-900">AI reads the lesson notes (or your topic) and adds questions below. Check them, then Save.</p>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <select v-model="ai.lesson_id" class="input" aria-label="Lesson"><option value="">No lesson — use topic</option><option v-for="l in lessons" :key="l.id" :value="l.id">{{ l.title }}</option></select>
                        <input v-model="ai.topic" type="text" class="input" placeholder="Topic / extra notes (optional)" aria-label="Topic" />
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <label class="text-sm">Questions <input v-model="ai.count" type="number" min="1" max="25" class="input ml-1 inline w-20" /></label>
                        <select v-model="ai.difficulty" class="input w-32" aria-label="Difficulty"><option value="easy">Easy</option><option value="medium">Medium</option><option value="hard">Hard</option></select>
                        <select v-model="ai.language" class="input w-36" aria-label="Language"><option value="roman_urdu">Roman Urdu</option><option value="english">English</option><option value="urdu">اردو</option></select>
                        <AiButton :loading="ai.loading" label="Write questions" @click="generate" />
                    </div>
                    <p v-if="ai.error" class="mt-2 text-xs font-semibold text-red-700">{{ ai.error }}</p>
                    <p v-if="ai.added" class="mt-2 text-xs font-semibold text-green-800">{{ ai.added }} questions added below — check them and press Save.</p>
                </div>

                <div v-for="(q, i) in form.questions" :key="i" class="card space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-semibold text-lwe-navy">Question {{ i + 1 }}</p>
                        <div class="flex items-center gap-1">
                            <select :value="q.type" class="input h-9 w-56 text-sm" aria-label="Question type" @change="setType(q, $event.target.value)"><option v-for="(l, k) in typeLabel" :key="k" :value="k">{{ l }}</option></select>
                            <button type="button" class="btn-outline btn-sm" aria-label="Move up" @click="move(i, -1)">↑</button>
                            <button type="button" class="btn-outline btn-sm" aria-label="Move down" @click="move(i, 1)">↓</button>
                            <button type="button" class="btn-outline btn-sm text-red-700" @click="removeQuestion(i)">Remove</button>
                        </div>
                    </div>
                    <textarea v-model="q.question" rows="2" class="textarea" :aria-label="`Question ${i + 1}`" placeholder="Write the question" />
                    <p class="text-xs text-lwe-muted">Tick the right answer{{ q.type === 'multiple' ? 's' : '' }}:</p>
                    <div v-for="(o, oi) in q.options" :key="oi" class="flex items-center gap-2">
                        <input :type="q.type === 'multiple' ? 'checkbox' : 'radio'" :name="`c${i}`" :checked="q.correct.includes(oi)" class="h-5 w-5" :aria-label="`Option ${oi + 1} is right`" @change="toggleCorrect(q, oi)" />
                        <input v-model="q.options[oi]" type="text" class="input" :readonly="q.type === 'true_false'" :aria-label="`Option ${oi + 1}`" :placeholder="`Option ${oi + 1}`" />
                        <button v-if="q.type !== 'true_false' && q.options.length > 2" type="button" class="rounded p-2 text-slate-500 hover:bg-slate-100" aria-label="Remove option" @click="removeOption(q, oi)">✕</button>
                    </div>
                    <button v-if="q.type !== 'true_false' && q.options.length < 8" type="button" class="text-sm font-semibold text-lwe-navy2 hover:underline" @click="addOption(q)">+ Option</button>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-4">
                        <input v-model="q.explanation" type="text" class="input sm:col-span-3" placeholder="Why this is right (shown after submit)" :aria-label="`Explanation ${i + 1}`" />
                        <label class="flex items-center gap-2 text-sm">Marks <input v-model="q.marks" type="number" min="1" max="10" class="input w-20" /></label>
                    </div>
                    <p v-if="form.errors[`questions.${i}.correct`] || form.errors[`questions.${i}.question`]" class="text-xs font-semibold text-red-700">{{ form.errors[`questions.${i}.correct`] || form.errors[`questions.${i}.question`] }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-outline" @click="addQuestion('single')">+ Question</button>
                    <button type="button" class="btn-outline" @click="addQuestion('true_false')">+ True / False</button>
                    <button type="button" class="btn-outline" @click="addQuestion('multiple')">+ More than one answer</button>
                </div>
                <div class="flex justify-end gap-2 border-t border-lwe-line pt-4">
                    <button type="button" class="btn-outline" :disabled="form.processing" @click="save('draft')">Save draft</button>
                    <button type="button" class="btn-primary" :disabled="form.processing" @click="save('published')">{{ quiz.status === 'published' ? 'Save' : 'Publish' }}</button>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
