<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Lwe/StatusBadge.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';
import { Link } from '@inertiajs/vue3';

defineProps({ courses: Array, batches: Array, sessions: { type: Array, default: () => [] }, todo: { type: Object, default: () => ({ to_check: 0, unanswered: 0, at_risk: [] }) } });
</script>

<template>
    <AppLayout title="My batches">
        <h1 class="page-title">My batches</h1>
        <p class="muted mb-6">Your classes, what is waiting for you, and students who need a push.</p>

        <section class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3" aria-label="Waiting for you">
            <Link :href="route('manage.tasks.index')" class="card hover:shadow-md"><p class="text-xs text-lwe-muted">Tasks to check</p><p class="mt-1 text-3xl font-bold" :class="todo.to_check ? 'text-amber-700' : 'text-lwe-navy'">{{ todo.to_check }}</p></Link>
            <Link :href="route('qa.index', { filter: 'unanswered' })" class="card hover:shadow-md"><p class="text-xs text-lwe-muted">Questions to answer</p><p class="mt-1 text-3xl font-bold" :class="todo.unanswered ? 'text-amber-700' : 'text-lwe-navy'">{{ todo.unanswered }}</p></Link>
            <Link :href="route('manage.progress.index', { state: 'at_risk' })" class="card hover:shadow-md"><p class="text-xs text-lwe-muted">Students falling behind</p><p class="mt-1 text-3xl font-bold" :class="todo.at_risk.length ? 'text-red-700' : 'text-lwe-navy'">{{ todo.at_risk.length }}</p></Link>
        </section>
        <section v-if="todo.at_risk.length" class="card mb-8" aria-label="Students falling behind">
            <h2 class="mb-1 text-lg font-semibold text-lwe-navy">Students who need a push</h2>
            <p class="muted mb-3 text-sm">A short message from you works better than any reminder.</p>
            <ul class="divide-y divide-slate-100">
                <li v-for="s in todo.at_risk" :key="s.code + s.course" class="py-2.5 text-sm"><b>{{ s.name }}</b> <span class="text-xs text-lwe-muted">{{ s.code }} · {{ s.course }}</span><p class="text-xs text-red-800">{{ s.notes.join(' · ') }}</p></li>
            </ul>
        </section>

        <section class="card mb-8" aria-label="My upcoming classes">
            <h2 class="mb-3 text-lg font-semibold text-lwe-navy">My upcoming classes</h2>
            <p v-if="!sessions.length" class="muted">No classes scheduled.</p>
            <ul class="divide-y divide-slate-100">
                <li v-for="s in sessions" :key="s.id" class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <span><span class="font-semibold">{{ s.title }}</span><span class="block text-xs text-lwe-muted">{{ s.label }} · {{ s.course }} · {{ s.batch }}</span></span>
                    <span class="flex gap-2">
                        <a v-if="s.can_start" :href="route('host.classes.start', s.id)" class="btn-primary btn-sm">Start class</a>
                        <span v-else-if="!s.zoom_ready" class="text-xs text-red-700">Zoom not ready — tell admin</span>
                        <span v-else class="text-xs text-lwe-muted">Start opens 30 min before</span>
                        <a :href="route('mentor.classes.show', s.id)" class="btn-outline btn-sm">Attendance &amp; notes</a>
                    </span>
                </li>
            </ul>
        </section>

        <EmptyState v-if="!batches.length" title="No batches assigned yet" text="An admin assigns you to a batch." />
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div v-for="b in batches" :key="b.id" class="card">
                <div class="flex items-start justify-between gap-2"><h2 class="font-semibold text-lwe-navy">{{ b.name }}</h2><StatusBadge :status="b.status" /></div>
                <p class="muted">{{ b.course }}</p>
                <p class="mt-3 text-sm"><b>{{ b.students }}</b> active students</p>
                <p class="text-sm">Next class: <b>{{ b.next_class ?? '—' }}</b></p>
            </div>
        </div>

        <h2 class="mt-8 mb-3 text-lg font-semibold text-lwe-navy">My courses</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Course</th><th>Lessons</th><th>Students</th><th>Status</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="c in courses" :key="c.id"><td class="font-semibold">{{ c.title }}</td><td>{{ c.lessons_count }}</td><td>{{ c.enrollments_count }}</td><td><StatusBadge :status="c.status" /></td></tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
