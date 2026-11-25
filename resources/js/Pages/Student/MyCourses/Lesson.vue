<script setup>
import ProtectedVideo from '@/Components/Lwe/ProtectedVideo.vue';
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Lwe/Icon.vue';

const props = defineProps({ course: Object, lesson: Object, watermark: String });
const form = useForm({});

const typeLabel = { live_class: 'Live class', recorded_video: 'Video', reading: 'Reading', task: 'Task', quiz: 'Quiz' };
</script>

<template>
    <AppLayout :title="lesson.title">
        <Link :href="route('student.my-courses.show', course.slug)" class="mb-4 inline-flex items-center gap-1 text-sm font-semibold text-lwe-navy2 hover:underline">← {{ course.title }}</Link>

        <div class="card">
            <p class="text-xs font-semibold uppercase tracking-wide text-lwe-muted">{{ typeLabel[lesson.type] }}</p>
            <h1 class="mt-1 page-title">{{ lesson.title }}</h1>
            <p v-if="lesson.summary" class="mt-2 text-slate-600">{{ lesson.summary }}</p>

            <ProtectedVideo v-if="lesson.video_url" class="mt-6" :url="lesson.video_url" :title="lesson.title" />

            <div v-if="lesson.type === 'live_class'" class="mt-6 rounded-xl bg-lwe-orangeSoft p-4 text-sm text-amber-900">
                This is a live class. Join it from <b>Live Classes</b> (or the Today page) at class time.
            </div>

            <div v-if="lesson.body" class="mt-6 whitespace-pre-line text-sm leading-7 text-slate-800">{{ lesson.body }}</div>

            <a v-if="lesson.has_attachment" :href="route('student.lessons.attachment', [course.slug, lesson.id])" class="btn-outline mt-6">
                <Icon name="download" /> Download {{ lesson.attachment_name }}
            </a>

            <div class="mt-8 flex items-center justify-between border-t border-lwe-line pt-5">
                <span v-if="lesson.done" class="flex items-center gap-2 text-sm font-semibold text-lwe-green"><Icon name="check" /> Completed</span>
                <button v-else type="button" class="btn-primary" :disabled="form.processing"
                    @click="form.post(route('student.lessons.complete', [course.slug, lesson.id]), { preserveScroll: true })">
                    Mark as complete
                </button>
                <Link :href="route('student.my-courses.show', course.slug)" class="text-sm font-semibold text-lwe-navy2 hover:underline">Back to lessons</Link>
            </div>
        </div>
    </AppLayout>
</template>
