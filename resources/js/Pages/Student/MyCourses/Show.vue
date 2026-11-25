<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Lwe/Icon.vue';

defineProps({ course: Object, batch: Object, progress: Number, modules: Array });
const typeLabel = { live_class: 'Live class', recorded_video: 'Video', reading: 'Reading', task: 'Task', quiz: 'Quiz' };
</script>

<template>
    <AppLayout :title="course.title">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="page-title">{{ course.title }}</h1>
                <p v-if="batch" class="muted">{{ batch.name }}</p>
            </div>
            <div class="w-full sm:w-72">
                <p class="mb-1 text-xs font-semibold text-slate-700">Your progress: {{ progress }}%</p>
                <div class="h-2.5 rounded-full bg-slate-200" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
                    <div class="h-2.5 rounded-full bg-lwe-orange" :style="{ width: progress + '%' }" />
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <section v-for="(m, mi) in modules" :key="m.id" class="card">
                <h2 class="mb-3 text-base font-semibold text-lwe-navy">{{ mi + 1 }}. {{ m.title }}</h2>
                <ul class="divide-y divide-slate-100">
                    <li v-for="l in m.lessons" :key="l.id">
                        <component :is="l.locked_reason ? 'div' : Link"
                            :href="l.locked_reason ? undefined : route('student.lessons.show', [course.slug, l.id])"
                            class="flex items-center gap-4 py-3" :class="l.locked_reason ? 'opacity-60' : 'hover:bg-slate-50'">
                            <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full"
                                :class="l.done ? 'bg-lwe-green text-white' : l.locked_reason ? 'bg-slate-200 text-slate-500' : 'bg-lwe-orangeSoft text-amber-900'">
                                <Icon :name="l.done ? 'check' : l.locked_reason ? 'lock' : 'play'" :size="14" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium">{{ l.title }}</span>
                                <span class="block text-xs text-lwe-muted">
                                    {{ typeLabel[l.type] }}<template v-if="l.duration_minutes"> · {{ l.duration_minutes }} min</template>
                                    <template v-if="l.locked_reason"> · {{ l.locked_reason }}</template>
                                </span>
                            </span>
                        </component>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
