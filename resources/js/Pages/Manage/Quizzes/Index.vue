<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from '@/Components/Lwe/Dialog.vue';
import Field from '@/Components/Lwe/Field.vue';
import Pagination from '@/Components/Lwe/Pagination.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';
import StatusBadge from '@/Components/Lwe/StatusBadge.vue';

const props = defineProps({ quizzes: Object, filters: Object, courses: Array, lessons: Array, batches: Array });

const courseFilter = ref(props.filters.course_id ?? '');
watch(courseFilter, (v) => router.get(route('manage.quizzes.index'), v ? { course_id: v } : {}, { preserveState: true, replace: true }));

const show = ref(false);
const form = useForm({ course_id: '', lesson_id: '', title: '' });
const courseLessons = computed(() => props.lessons.filter((l) => l.course_id === Number(form.course_id)));
const openNew = () => {
    form.reset();
    form.course_id = courseFilter.value ? Number(courseFilter.value) : (props.courses.length === 1 ? props.courses[0].id : '');
    show.value = true;
};
// Picking a lesson suggests a title.
watch(() => form.lesson_id, (id) => {
    const l = courseLessons.value.find((x) => x.id === id);
    if (l && !form.title) form.title = `Quiz: ${l.title}`;
});
</script>

<template>
    <AppLayout title="Quizzes">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><h1 class="page-title">Quizzes</h1><p class="muted">MCQ tests that mark themselves. AI can write the questions from a lesson.</p></div>
            <button type="button" class="btn-primary" :disabled="!courses.length" @click="openNew">+ New quiz</button>
        </div>
        <select v-model="courseFilter" class="input mb-4 w-64" aria-label="Course"><option value="">All courses</option><option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option></select>

        <EmptyState v-if="!quizzes.data.length" title="No quizzes yet" text="Create one — AI can write 10 questions from a lesson in a few seconds." />
        <div v-else class="table-wrap">
            <table class="table">
                <thead><tr><th>Quiz</th><th>Course</th><th>Questions</th><th>Taken</th><th>Average</th><th>Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="q in quizzes.data" :key="q.id">
                        <td><Link :href="route('manage.quizzes.edit', q.id)" class="font-semibold text-lwe-navy hover:underline">{{ q.title }}</Link>
                            <p class="text-xs text-lwe-muted">Pass {{ q.pass_percent }}% · {{ q.max_attempts }} attempt(s)<template v-if="q.time_limit_minutes"> · {{ q.time_limit_minutes }} min</template><template v-if="q.due_label"> · until {{ q.due_label }}</template></p></td>
                        <td class="text-sm">{{ q.course }}<p v-if="q.batch" class="text-xs text-lwe-muted">{{ q.batch }} only</p></td>
                        <td>{{ q.questions_count }}</td>
                        <td>{{ q.attempts_count }}</td>
                        <td>{{ q.attempts_count ? q.average + '%' : '—' }}</td>
                        <td><StatusBadge :status="q.status" /></td>
                        <td class="whitespace-nowrap text-right">
                            <Link :href="route('manage.quizzes.results', q.id)" class="btn-dark btn-sm">Results</Link>
                            <Link :href="route('manage.quizzes.edit', q.id)" class="btn-outline btn-sm ml-1">Edit</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="quizzes.links" />

        <Dialog :show="show" title="New quiz" @close="show = false">
            <form class="space-y-4" @submit.prevent="form.post(route('manage.quizzes.store'))">
                <Field label="Course" for="qc" :error="form.errors.course_id">
                    <select id="qc" v-model="form.course_id" class="input" required @change="form.lesson_id = ''"><option value="" disabled>Choose…</option><option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option></select>
                </Field>
                <Field label="Lesson (optional — AI uses it to write questions)" for="ql" :error="form.errors.lesson_id">
                    <select id="ql" v-model="form.lesson_id" class="input" :disabled="!form.course_id"><option value="">—</option><option v-for="l in courseLessons" :key="l.id" :value="l.id">{{ l.title }}</option></select>
                </Field>
                <Field label="Quiz title" for="qt" :error="form.errors.title"><input id="qt" v-model="form.title" type="text" class="input" required placeholder="e.g. Week 1 quiz: Amazon basics" /></Field>
                <div class="flex justify-end gap-2 border-t border-lwe-line pt-4">
                    <button type="button" class="btn-outline" @click="show = false">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">Create &amp; add questions</button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
