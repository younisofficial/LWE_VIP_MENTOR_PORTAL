<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from '@/Components/Lwe/Dialog.vue';
import Field from '@/Components/Lwe/Field.vue';
import Pagination from '@/Components/Lwe/Pagination.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';

const props = defineProps({ questions: Object, filters: Object, batches: Array, lessons: Array });

const f = ref({ batch_id: props.filters.batch_id ?? '', filter: props.filters.filter ?? '', search: props.filters.search ?? '' });
let t;
watch(f, () => {
    clearTimeout(t);
    t = setTimeout(() => router.get(route('qa.index'), Object.fromEntries(Object.entries(f.value).filter(([, v]) => v)), { preserveState: true, replace: true }), 300);
}, { deep: true });

const show = ref(false);
const form = useForm({ batch_id: '', course_id: '', lesson_id: '', title: '', body: '', images: [] });
const batchCourses = computed(() => props.batches.find((b) => b.id === Number(form.batch_id))?.courses ?? []);
const courseLessons = computed(() => props.lessons.filter((l) => l.course_id === Number(form.course_id)));
const previews = ref([]);
const pick = (e) => {
    const files = [...e.target.files].slice(0, 3);
    form.images = files;
    previews.value = files.map((file) => URL.createObjectURL(file));
};
const openAsk = () => {
    form.reset();
    previews.value = [];
    form.batch_id = f.value.batch_id ? Number(f.value.batch_id) : (props.batches.length === 1 ? props.batches[0].id : '');
    show.value = true;
};
const statusBadge = { open: ['Waiting for answer', 'bg-lwe-orangeSoft text-amber-900'], answered: ['Answered', 'bg-lwe-greenSoft text-green-900'], closed: ['Closed', 'bg-slate-200 text-slate-700'] };
</script>

<template>
    <AppLayout title="Q&A">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><h1 class="page-title">Q&amp;A</h1><p class="muted">Stuck? Ask with a screenshot. Everyone in your batch — mentors and classmates — can see and answer.</p></div>
            <button type="button" class="btn-primary" :disabled="!batches.length" @click="openAsk">+ Ask a question</button>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <input v-model="f.search" type="search" class="input w-64" placeholder="Search questions" aria-label="Search" />
            <select v-model="f.batch_id" class="input w-56" aria-label="Batch"><option value="">All my batches</option><option v-for="b in batches" :key="b.id" :value="b.id">{{ b.name }}</option></select>
            <select v-model="f.filter" class="input w-48" aria-label="Show"><option value="">All questions</option><option value="unanswered">Not answered yet</option><option value="mine">My questions</option></select>
        </div>

        <EmptyState v-if="!questions.data.length" title="No questions yet" text="Be the first to ask — someone else probably has the same question." />
        <div class="space-y-3">
            <Link v-for="q in questions.data" :key="q.id" :href="route('qa.show', q.id)" class="card block hover:ring-2 hover:ring-lwe-orange">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-semibold text-lwe-navy"><span v-if="q.is_pinned" title="Pinned">📌 </span>{{ q.title }} <span v-if="q.has_images" title="Has screenshots">🖼️</span></p>
                        <p v-if="q.excerpt" class="mt-1 line-clamp-2 text-sm text-slate-600">{{ q.excerpt }}</p>
                        <p class="mt-1 text-xs text-lwe-muted">{{ q.author }}<template v-if="q.batch"> · {{ q.batch }}</template><template v-if="q.course"> · {{ q.course }}</template><template v-if="q.lesson"> · {{ q.lesson }}</template> · {{ q.ago }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-lwe-navy">💬 {{ q.answers_count }}</span>
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="statusBadge[q.status][1]">{{ statusBadge[q.status][0] }}</span>
                    </div>
                </div>
            </Link>
        </div>
        <Pagination :links="questions.links" />

        <Dialog :show="show" title="Ask a question" wide @close="show = false">
            <form class="space-y-4" @submit.prevent="form.post(route('qa.store'), { forceFormData: true })">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Field label="Batch" for="qb2" :error="form.errors.batch_id">
                        <select id="qb2" v-model="form.batch_id" class="input" required @change="form.course_id = ''; form.lesson_id = ''"><option value="" disabled>Choose…</option><option v-for="b in batches" :key="b.id" :value="b.id">{{ b.name }}</option></select>
                    </Field>
                    <Field label="Course (optional)" for="qc" :error="form.errors.course_id">
                        <select id="qc" v-model="form.course_id" class="input" :disabled="!form.batch_id" @change="form.lesson_id = ''"><option value="">General / any course</option><option v-for="c in batchCourses" :key="c.id" :value="c.id">{{ c.title }}</option></select>
                    </Field>
                    <Field label="Lesson (optional)" for="ql" :error="form.errors.lesson_id">
                        <select id="ql" v-model="form.lesson_id" class="input" :disabled="!form.course_id"><option value="">—</option><option v-for="l in courseLessons" :key="l.id" :value="l.id">{{ l.title }}</option></select>
                    </Field>
                </div>
                <Field label="Your question (short)" for="qt" :error="form.errors.title"><input id="qt" v-model="form.title" type="text" class="input" maxlength="200" required placeholder="e.g. Shopify par payment gateway add nahi ho raha" /></Field>
                <Field label="Details (what you tried, what error you see)" for="qb" :error="form.errors.body"><textarea id="qb" v-model="form.body" rows="5" class="textarea" /></Field>
                <Field label="Screenshots (up to 3)" for="qi" :error="form.errors.images || form.errors['images.0']">
                    <input id="qi" type="file" accept="image/*" multiple class="block text-sm" @change="pick" />
                    <div v-if="previews.length" class="mt-2 flex gap-2"><img v-for="p in previews" :key="p" :src="p" alt="Screenshot preview" class="h-20 w-20 rounded-lg object-cover" /></div>
                </Field>
                <div class="flex justify-end gap-2 border-t border-lwe-line pt-4">
                    <button type="button" class="btn-outline" @click="show = false">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">Post question</button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
