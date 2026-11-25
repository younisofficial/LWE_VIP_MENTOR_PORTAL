<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Field from '@/Components/Lwe/Field.vue';

const props = defineProps({ task: Object, submission: Object });

const form = useForm({ body: props.submission?.body ?? '', link: props.submission?.link ?? '', file: null });
const submit = () => form.post(route('student.tasks.submit', props.task.id), { preserveScroll: true, forceFormData: true, onSuccess: () => form.reset('file') });
const wants = (k) => props.task.submission_type === 'any' || props.task.submission_type === k;
const statusText = { submitted: 'Submitted — your mentor will check it soon.', graded: 'Checked by your mentor.', resubmit: 'Your mentor asked you to improve it and submit again.' };
</script>

<template>
    <AppLayout :title="task.title">
        <Link :href="route('student.tasks.index')" class="text-sm font-semibold text-lwe-navy2 hover:underline">← My tasks</Link>
        <div class="mt-2 grid grid-cols-1 gap-6 lg:grid-cols-5">
            <section class="card lg:col-span-3">
                <p class="text-xs text-lwe-muted">{{ task.course }}<template v-if="task.lesson"> · {{ task.lesson }}</template></p>
                <h1 class="page-title">{{ task.title }}</h1>
                <p class="mt-1 text-sm">{{ task.max_marks }} marks<template v-if="task.due_label"> · Submit by <b :class="task.overdue ? 'text-red-700' : ''">{{ task.due_label }}</b></template></p>
                <div class="mt-4 whitespace-pre-line text-[15px] leading-relaxed">{{ task.instructions }}</div>
                <a v-if="task.attachment_name" :href="route('tasks.attachment', task.id)" class="btn-outline btn-sm mt-4">Download {{ task.attachment_name }}</a>
            </section>

            <section class="space-y-4 lg:col-span-2">
                <div v-if="submission" class="card" :class="submission.status === 'resubmit' ? 'ring-2 ring-red-300' : ''">
                    <p class="font-semibold text-lwe-navy">{{ statusText[submission.status] }}</p>
                    <p class="text-xs text-lwe-muted">Sent {{ submission.submitted_label }}<span v-if="submission.is_late" class="ml-1 text-red-700">(late)</span></p>
                    <p v-if="submission.status === 'graded'" class="mt-3 text-3xl font-bold text-lwe-navy">{{ submission.marks }}<span class="text-base text-lwe-muted">/{{ task.max_marks }}</span></p>
                    <div v-if="submission.feedback" class="mt-3 rounded-xl bg-slate-50 p-3 text-sm"><p class="label">Mentor's feedback</p><p class="whitespace-pre-line">{{ submission.feedback }}</p></div>
                    <a v-if="submission.file_name" :href="route('student.tasks.my-file', task.id)" target="_blank" class="mt-3 inline-block text-sm font-semibold text-lwe-navy2 underline">My file: {{ submission.file_name }}</a>
                </div>

                <form v-if="task.can_submit" class="card space-y-4" @submit.prevent="submit">
                    <h2 class="font-semibold text-lwe-navy">{{ submission ? 'Update my work' : 'Submit my work' }}</h2>
                    <Field v-if="wants('text')" label="Your answer" for="sb" :error="form.errors.body"><textarea id="sb" v-model="form.body" rows="6" class="textarea" /></Field>
                    <p v-else-if="form.errors.body" class="text-sm font-semibold text-red-700">{{ form.errors.body }}</p>
                    <Field v-if="wants('link')" label="Link to your work" for="sl" :error="form.errors.link" hint="e.g. Google Drive, Canva, your store or Fiverr gig link"><input id="sl" v-model="form.link" type="url" class="input" placeholder="https://" /></Field>
                    <Field v-if="wants('file')" label="File or screenshot (max 15 MB)" for="sf" :error="form.errors.file">
                        <input id="sf" type="file" class="block w-full text-sm" accept="image/*,.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.txt,.mp4" @input="form.file = $event.target.files[0]" />
                    </Field>
                    <progress v-if="form.progress" :value="form.progress.percentage" max="100" class="w-full">{{ form.progress.percentage }}%</progress>
                    <button type="submit" class="btn-primary w-full" :disabled="form.processing">{{ form.processing ? 'Sending…' : (submission ? 'Update submission' : 'Submit') }}</button>
                    <p v-if="task.overdue" class="text-xs text-red-700">The last date has passed — this will be marked late.</p>
                </form>
                <p v-else-if="!submission" class="card text-sm text-red-800">This task is closed. The last date has passed.</p>
            </section>
        </div>
    </AppLayout>
</template>
