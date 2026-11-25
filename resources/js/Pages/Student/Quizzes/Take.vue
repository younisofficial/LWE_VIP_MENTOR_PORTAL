<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ quiz: Object, attempt: Object, questions: Array });

// answers: { questionId: [optionIndex, ...] }
const answers = ref(Object.fromEntries(props.questions.map((q) => [q.id, [...(props.attempt.answers?.[q.id] ?? [])]])));
const isChosen = (q, i) => answers.value[q.id].includes(i);
const choose = (q, i) => {
    if (props.attempt.finished) return;
    const cur = answers.value[q.id];
    answers.value[q.id] = q.type === 'multiple' ? (cur.includes(i) ? cur.filter((x) => x !== i) : [...cur, i]) : [i];
    queueSave();
};
const answered = computed(() => props.questions.filter((q) => answers.value[q.id].length).length);

// Save progress every few seconds after a change (internet may drop).
let saveTimer;
const queueSave = () => {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => axios.put(route('student.quizzes.save', props.attempt.id), { answers: answers.value }).catch(() => {}), 1500);
};

const submitting = ref(false);
const submit = (auto = false) => {
    if (submitting.value || props.attempt.finished) return;
    if (!auto && answered.value < props.questions.length && !confirm(`You answered ${answered.value} of ${props.questions.length}. Submit anyway?`)) return;
    submitting.value = true;
    clearTimeout(saveTimer);
    router.post(route('student.quizzes.submit', props.attempt.id), { answers: answers.value }, { onFinish: () => (submitting.value = false) });
};

// Timer
const left = ref(props.attempt.seconds_left);
let tick;
onMounted(() => {
    if (left.value == null || props.attempt.finished) return;
    tick = setInterval(() => {
        left.value = Math.max(0, left.value - 1);
        if (left.value === 0) { clearInterval(tick); submit(true); }
    }, 1000);
});
onBeforeUnmount(() => { clearInterval(tick); clearTimeout(saveTimer); });
const mmss = computed(() => (left.value == null ? '' : `${Math.floor(left.value / 60)}:${String(left.value % 60).padStart(2, '0')}`));

const optionClass = (q, i) => {
    if (!props.attempt.finished) return isChosen(q, i) ? 'border-lwe-navy bg-blue-50 ring-2 ring-lwe-navy' : 'border-lwe-line hover:border-lwe-navy';
    if (q.correct?.includes(i)) return 'border-green-600 bg-lwe-greenSoft';
    if (isChosen(q, i)) return 'border-red-500 bg-red-50';
    return 'border-lwe-line';
};
</script>

<template>
    <AppLayout :title="quiz.title">
        <Link :href="route('student.quizzes.index')" class="text-sm font-semibold text-lwe-navy2 hover:underline">← Quizzes</Link>
        <div class="sticky top-0 z-10 -mx-4 mt-2 mb-4 flex items-center justify-between gap-3 bg-lwe-bg/95 px-4 py-3 backdrop-blur">
            <div><p class="text-xs text-lwe-muted">{{ quiz.course }}</p><h1 class="text-xl font-bold text-lwe-navy">{{ quiz.title }}</h1></div>
            <div v-if="!attempt.finished" class="text-right">
                <p v-if="mmss" class="text-2xl font-bold tabular-nums" :class="left < 60 ? 'text-red-700' : 'text-lwe-navy'" aria-live="polite">{{ mmss }}</p>
                <p class="text-xs text-lwe-muted">{{ answered }}/{{ questions.length }} answered</p>
            </div>
        </div>

        <div v-if="attempt.finished" class="card mb-6 text-center" :class="attempt.passed ? 'ring-2 ring-green-500' : 'ring-2 ring-red-300'">
            <p class="text-sm text-lwe-muted">Your score</p>
            <p class="text-5xl font-bold text-lwe-navy">{{ attempt.percent }}%</p>
            <p class="mt-1">{{ attempt.score }} / {{ attempt.max_score }} marks · pass mark {{ quiz.pass_percent }}%</p>
            <p class="mt-3 text-lg font-semibold" :class="attempt.passed ? 'text-green-800' : 'text-red-800'">{{ attempt.passed ? '🎉 Passed — well done!' : 'Not passed this time.' }}</p>
            <p v-if="!attempt.passed && attempt.attempts_left" class="mt-1 text-sm">You have {{ attempt.attempts_left }} more attempt(s). Read the lesson again, then try.</p>
        </div>
        <p v-else-if="quiz.instructions" class="card mb-4 whitespace-pre-line text-sm">{{ quiz.instructions }}</p>

        <ol class="space-y-4">
            <li v-for="(q, n) in questions" :key="q.id" class="card">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <p class="font-semibold"><span class="text-lwe-muted">{{ n + 1 }}.</span> {{ q.question }}</p>
                    <span v-if="attempt.finished" class="shrink-0 text-lg" :aria-label="q.is_right ? 'Right' : 'Wrong'">{{ q.is_right ? '✅' : '❌' }}</span>
                </div>
                <p v-if="q.type === 'multiple' && !attempt.finished" class="mb-2 text-xs text-lwe-muted">Choose all the right answers.</p>
                <div class="space-y-2" :role="q.type === 'multiple' ? 'group' : 'radiogroup'">
                    <button v-for="(o, i) in q.options" :key="i" type="button" class="flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left text-[15px] transition"
                        :class="optionClass(q, i)" :aria-pressed="isChosen(q, i)" :disabled="attempt.finished" @click="choose(q, i)">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center border-2 text-xs font-bold" :class="[q.type === 'multiple' ? 'rounded' : 'rounded-full', isChosen(q, i) ? 'border-lwe-navy bg-lwe-navy text-white' : 'border-slate-400']">{{ isChosen(q, i) ? '✓' : '' }}</span>
                        {{ o }}
                    </button>
                </div>
                <p v-if="attempt.finished && q.explanation" class="mt-3 rounded-xl bg-slate-50 p-3 text-sm"><b>Why:</b> {{ q.explanation }}</p>
            </li>
        </ol>

        <div v-if="!attempt.finished" class="mt-6 flex justify-end">
            <button type="button" class="btn-primary px-8" :disabled="submitting" @click="submit()">{{ submitting ? 'Submitting…' : 'Submit quiz' }}</button>
        </div>
        <div v-else class="mt-6 flex justify-end"><Link :href="route('student.quizzes.index')" class="btn-outline">Back to quizzes</Link></div>
    </AppLayout>
</template>
