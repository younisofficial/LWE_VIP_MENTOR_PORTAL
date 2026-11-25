<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import AiButton from '@/Components/Lwe/AiButton.vue';

const props = defineProps({ question: Object, answers: Array, can: Object });

const form = useForm({ body: '', images: [] });
const previews = ref([]);
const pick = (e) => {
    const files = [...e.target.files].slice(0, 3);
    form.images = files;
    previews.value = files.map((file) => URL.createObjectURL(file));
};
const post = () => form.post(route('qa.answer', props.question.id), {
    preserveScroll: true, forceFormData: true,
    onSuccess: () => { form.reset(); previews.value = []; },
});

const accept = (a) => router.post(route('qa.accept', a.id), {}, { preserveScroll: true });
const removeAnswer = (a) => { if (confirm('Delete this answer?')) router.delete(route('qa.answers.destroy', a.id), { preserveScroll: true }); };
const removeQuestion = () => { if (confirm('Delete this question?')) router.delete(route('qa.destroy', props.question.id)); };
const moderate = (action) => router.post(route('qa.moderate', props.question.id), { action }, { preserveScroll: true });

// AI draft for mentors
const ai = ref({ language: 'roman_urdu', loading: false, error: '' });
const draft = async () => {
    ai.value.loading = true; ai.value.error = '';
    try {
        const { data } = await axios.post(route('qa.ai', props.question.id), { language: ai.value.language });
        form.body = data.answer;
    } catch (e) {
        ai.value.error = e.response?.data?.message ?? 'AI could not write an answer now.';
    } finally {
        ai.value.loading = false;
    }
};
const zoom = ref(null);
</script>

<template>
    <AppLayout :title="question.title">
        <Link :href="route('qa.index')" class="text-sm font-semibold text-lwe-navy2 hover:underline">← Q&amp;A</Link>

        <article class="card mt-2">
            <p class="text-xs text-lwe-muted">{{ [question.batch, question.course, question.lesson].filter(Boolean).join(' · ') }}</p>
            <h1 class="page-title"><span v-if="question.is_pinned">📌 </span>{{ question.title }}</h1>
            <p class="text-xs text-lwe-muted">Asked by {{ question.author }} · {{ question.ago }}<template v-if="question.status === 'closed'"> · <b>Closed</b></template></p>
            <p v-if="question.body" class="mt-4 whitespace-pre-line">{{ question.body }}</p>
            <div v-if="question.images.length" class="mt-4 flex flex-wrap gap-3">
                <button v-for="(src, i) in question.images" :key="src" type="button" class="overflow-hidden rounded-xl border border-lwe-line" @click="zoom = src">
                    <img :src="src" :alt="`Screenshot ${i + 1}`" class="h-40 w-auto object-cover" loading="lazy" />
                </button>
            </div>
            <div v-if="can.moderate || question.is_mine" class="mt-4 flex flex-wrap gap-2 border-t border-lwe-line pt-3">
                <template v-if="can.moderate">
                    <button type="button" class="btn-outline btn-sm" @click="moderate(question.is_pinned ? 'unpin' : 'pin')">{{ question.is_pinned ? 'Unpin' : 'Pin to top' }}</button>
                    <button type="button" class="btn-outline btn-sm" @click="moderate(question.status === 'closed' ? 'reopen' : 'close')">{{ question.status === 'closed' ? 'Reopen' : 'Close' }}</button>
                </template>
                <button v-if="can.moderate || (question.is_mine && !question.answers_count)" type="button" class="btn-outline btn-sm text-red-700" @click="removeQuestion">Delete</button>
            </div>
        </article>

        <h2 class="mt-6 mb-3 text-lg font-semibold text-lwe-navy">{{ answers.length }} answer{{ answers.length === 1 ? '' : 's' }}</h2>
        <div class="space-y-3">
            <div v-for="a in answers" :key="a.id" class="card" :class="a.is_accepted ? 'ring-2 ring-green-500' : ''">
                <div class="mb-2 flex flex-wrap items-center gap-2 text-sm">
                    <b>{{ a.author }}</b>
                    <span v-if="a.is_staff" class="rounded-full bg-lwe-navy px-2 py-0.5 text-[11px] font-bold text-white">Mentor</span>
                    <span v-if="a.is_accepted" class="rounded-full bg-lwe-greenSoft px-2 py-0.5 text-[11px] font-bold text-green-900">✔ Best answer</span>
                    <span class="text-xs text-lwe-muted">{{ a.ago }}</span>
                </div>
                <p class="whitespace-pre-line">{{ a.body }}</p>
                <div v-if="a.images.length" class="mt-3 flex flex-wrap gap-3">
                    <button v-for="(src, i) in a.images" :key="src" type="button" class="overflow-hidden rounded-xl border border-lwe-line" @click="zoom = src">
                        <img :src="src" :alt="`Answer screenshot ${i + 1}`" class="h-32 w-auto object-cover" loading="lazy" />
                    </button>
                </div>
                <div class="mt-3 flex gap-3 text-sm">
                    <button v-if="can.accept && !a.is_accepted" type="button" class="font-semibold text-green-800 hover:underline" @click="accept(a)">Mark as best answer</button>
                    <button v-if="a.is_mine || can.moderate" type="button" class="font-semibold text-red-700 hover:underline" @click="removeAnswer(a)">Delete</button>
                </div>
            </div>
        </div>

        <form v-if="can.answer" class="card mt-6 space-y-3" @submit.prevent="post">
            <h2 class="font-semibold text-lwe-navy">Your answer</h2>
            <div v-if="can.ai" class="flex flex-wrap items-center gap-2 rounded-xl border border-violet-200 bg-violet-50 p-3">
                <p class="flex-1 text-sm text-violet-900">AI reads the question and screenshots and writes a draft. Check it before posting.</p>
                <select v-model="ai.language" class="input w-36" aria-label="Language"><option value="roman_urdu">Roman Urdu</option><option value="english">English</option><option value="urdu">اردو</option></select>
                <AiButton :loading="ai.loading" label="Draft answer" @click="draft" />
                <p v-if="ai.error" class="w-full text-xs font-semibold text-red-700">{{ ai.error }}</p>
            </div>
            <label for="ab" class="sr-only">Your answer</label>
            <textarea id="ab" v-model="form.body" rows="5" class="textarea" required />
            <p v-if="form.errors.body" class="text-xs font-semibold text-red-700">{{ form.errors.body }}</p>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <label for="ai2" class="text-sm font-semibold text-lwe-navy">Add screenshots</label>
                    <input id="ai2" type="file" accept="image/*" multiple class="block text-sm" @change="pick" />
                    <div v-if="previews.length" class="mt-2 flex gap-2"><img v-for="p in previews" :key="p" :src="p" alt="Screenshot preview" class="h-16 w-16 rounded-lg object-cover" /></div>
                </div>
                <button type="submit" class="btn-primary" :disabled="form.processing">Post answer</button>
            </div>
        </form>

        <div v-if="zoom" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" role="dialog" aria-label="Screenshot" @click="zoom = null">
            <img :src="zoom" alt="Screenshot full size" class="max-h-full max-w-full rounded-xl" />
        </div>
    </AppLayout>
</template>
