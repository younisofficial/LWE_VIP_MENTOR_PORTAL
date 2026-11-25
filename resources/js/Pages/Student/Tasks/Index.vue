<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';

defineProps({ todo: Array, done: Array });

const badge = {
    todo: ['To do', 'bg-lwe-orangeSoft text-amber-900'],
    late: ['Late — still open', 'bg-red-100 text-red-900'],
    resubmit: ['Improve & resubmit', 'bg-red-100 text-red-900'],
    submitted: ['Submitted — waiting for marks', 'bg-blue-100 text-blue-900'],
    graded: ['Checked', 'bg-lwe-greenSoft text-green-900'],
    missed: ['Missed', 'bg-slate-200 text-slate-700'],
};
</script>

<template>
    <AppLayout title="My tasks">
        <h1 class="page-title">My tasks</h1>
        <p class="muted mb-6">Practical homework from your mentors. Marks count towards your certificate and Skill Passport.</p>

        <h2 class="mb-3 text-lg font-semibold text-lwe-navy">To do</h2>
        <EmptyState v-if="!todo.length" title="Nothing to do right now 🎉" text="New tasks show up here, and you get a message when one is added." />
        <div class="mb-8 grid grid-cols-1 gap-3 md:grid-cols-2">
            <Link v-for="t in todo" :key="t.id" :href="route('student.tasks.show', t.id)" class="card block hover:ring-2 hover:ring-lwe-orange">
                <div class="flex items-start justify-between gap-2">
                    <div><p class="text-xs text-lwe-muted">{{ t.course }}</p><p class="font-semibold text-lwe-navy">{{ t.title }}</p></div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold" :class="badge[t.state][1]">{{ badge[t.state][0] }}</span>
                </div>
                <p class="mt-2 text-sm">{{ t.max_marks }} marks<template v-if="t.due_label"> · Submit by <b>{{ t.due_label }}</b></template></p>
            </Link>
        </div>

        <h2 class="mb-3 text-lg font-semibold text-lwe-navy">Done</h2>
        <EmptyState v-if="!done.length" title="No submitted tasks yet" />
        <div class="space-y-2">
            <Link v-for="t in done" :key="t.id" :href="route('student.tasks.show', t.id)" class="card flex flex-wrap items-center justify-between gap-2 hover:ring-2 hover:ring-lwe-orange">
                <div><p class="font-semibold">{{ t.title }}</p><p class="text-xs text-lwe-muted">{{ t.course }}</p></div>
                <div class="flex items-center gap-2">
                    <b v-if="t.marks != null" class="text-lwe-navy">{{ t.marks }}/{{ t.max_marks }}</b>
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="badge[t.state][1]">{{ badge[t.state][0] }}</span>
                </div>
            </Link>
        </div>
    </AppLayout>
</template>
