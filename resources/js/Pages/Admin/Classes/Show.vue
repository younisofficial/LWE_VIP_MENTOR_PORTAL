<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Lwe/StatusBadge.vue';
import AiButton from '@/Components/Lwe/AiButton.vue';
import Field from '@/Components/Lwe/Field.vue';

const props = defineProps({ session: Object, students: Array, unknownJoins: Array, canManage: Boolean, isMentorView: Boolean });

const r = (name) => (props.isMentorView ? 'mentor.' : 'admin.') + name;
const counts = computed(() => ({
    present: props.students.filter((s) => s.status === 'present').length,
    partial: props.students.filter((s) => s.status === 'partial').length,
    absent: props.students.filter((s) => s.status === 'absent').length,
}));
const mark = (student, status) => router.post(route(r('classes.mark'), [props.session.id, student.id]), { status }, { preserveScroll: true });

// ---- AI notes ----
const language = ref(props.session.ai_notes?.language ?? 'roman_urdu');
const generating = ref(false);
const generate = () => {
    generating.value = true;
    router.post(route(r('classes.notes.ai'), props.session.id), { language: language.value }, { preserveScroll: true, onFinish: () => (generating.value = false) });
};
const n = props.session.ai_notes ?? {};
const notes = useForm({
    summary: n.summary ?? '',
    key_points: n.key_points?.length ? [...n.key_points] : [''],
    action_items: n.action_items?.length ? [...n.action_items] : [''],
    recap: n.recap ?? '',
    publish: props.session.notes_published,
});
const saveNotes = (publish) => { notes.publish = publish; notes.put(route(r('classes.notes.save'), props.session.id), { preserveScroll: true }); };
const statusClass = { present: 'bg-lwe-greenSoft text-green-900', partial: 'bg-lwe-orangeSoft text-amber-900', absent: 'bg-red-100 text-red-900' };
</script>

<template>
    <AppLayout :title="session.title">
        <Link :href="isMentorView ? route('mentor.dashboard') : route('admin.classes.index')" class="text-sm font-semibold text-lwe-navy2 hover:underline">← Back</Link>
        <div class="mt-2 mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="page-title flex flex-wrap items-center gap-3">{{ session.title }} <StatusBadge :status="session.status === 'scheduled' ? 'upcoming' : session.status === 'live' ? 'running' : session.status" /></h1>
                <p class="muted">{{ session.label }} · {{ session.duration }} min · {{ session.course }} · {{ session.batch }}<template v-if="session.mentor"> · {{ session.mentor }}</template></p>
                <p v-if="session.lesson" class="muted">Lesson: {{ session.lesson }}</p>
                <p v-if="session.cancel_reason" class="text-sm text-red-700">Cancelled: {{ session.cancel_reason }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a v-if="['scheduled', 'live'].includes(session.status) && session.zoom_ready" :href="route('host.classes.start', session.id)" class="btn-dark">Start class (host)</a>
                <a :href="route(r('classes.export'), session.id)" class="btn-outline">Download attendance</a>
            </div>
        </div>

        <div v-if="session.zoom_error" role="alert" class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-red-50 p-4 text-sm text-red-800">
            <span><b>Zoom problem:</b> {{ session.zoom_error }}</span>
            <Link v-if="canManage" :href="route('admin.classes.retry', session.id)" method="post" as="button" class="btn-danger btn-sm" preserve-scroll>Retry Zoom</Link>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
            <!-- Attendance -->
            <section class="card xl:col-span-3" aria-label="Attendance">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-semibold text-lwe-navy">Attendance</h2>
                    <p class="text-xs text-lwe-muted">
                        <b class="text-green-800">{{ counts.present }}</b> present · <b class="text-amber-800">{{ counts.partial }}</b> partial · <b class="text-red-800">{{ counts.absent }}</b> absent · {{ students.length }} students
                    </p>
                </div>
                <p v-if="session.status !== 'ended'" class="mb-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">Attendance is calculated automatically when the class ends (from Zoom join/leave times). You can correct anyone by hand.</p>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Student</th><th>Minutes</th><th>Status</th><th>Mark</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="s in students" :key="s.id">
                                <td><span class="font-semibold">{{ s.name }}</span><span class="block text-xs text-lwe-muted">{{ s.code }} · {{ s.phone }}</span></td>
                                <td>{{ s.minutes ?? '—' }}<span v-if="s.joins > 1" class="block text-[11px] text-lwe-muted">{{ s.joins }} joins</span></td>
                                <td>
                                    <span v-if="s.status" class="rounded-full px-2 py-0.5 text-xs font-bold capitalize" :class="statusClass[s.status]">{{ s.status }}</span>
                                    <span v-else class="text-xs text-lwe-muted">—</span>
                                    <span v-if="s.source === 'manual'" class="block text-[11px] text-lwe-muted">by hand</span>
                                </td>
                                <td class="space-x-1 whitespace-nowrap">
                                    <button type="button" class="rounded px-2 py-1 text-xs font-bold text-green-800 hover:bg-green-50" @click="mark(s, 'present')">P</button>
                                    <button type="button" class="rounded px-2 py-1 text-xs font-bold text-amber-800 hover:bg-amber-50" @click="mark(s, 'partial')">½</button>
                                    <button type="button" class="rounded px-2 py-1 text-xs font-bold text-red-800 hover:bg-red-50" @click="mark(s, 'absent')">A</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="unknownJoins.length" class="mt-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-700">
                    <p class="font-semibold">Joined but not matched to a student ({{ unknownJoins.length }})</p>
                    <p v-for="(u, i) in unknownJoins" :key="i">{{ u.name }} · {{ u.joined }}</p>
                </div>
            </section>

            <!-- AI notes -->
            <section class="card space-y-4 xl:col-span-2" aria-label="Class notes">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-semibold text-lwe-navy">AI class notes</h2>
                    <span v-if="session.notes_published" class="rounded-full bg-lwe-greenSoft px-2 py-0.5 text-xs font-bold text-green-900">Published</span>
                </div>
                <p v-if="!session.has_transcript" class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                    After the class, Zoom sends the recording and transcript (cloud recording + "audio transcript" must be on in Zoom). AI then writes the notes here automatically.
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <label for="nl" class="sr-only">Language</label>
                    <select id="nl" v-model="language" class="input w-36"><option value="roman_urdu">Roman Urdu</option><option value="english">English</option><option value="urdu">اردو</option></select>
                    <AiButton :loading="generating" :label="session.has_notes ? 'Write again' : 'Write notes with AI'" :disabled="!session.has_transcript" @click="generate" />
                </div>
                <a v-if="session.recording_url" :href="session.recording_url" target="_blank" rel="noopener" class="text-xs font-semibold text-lwe-navy2 underline">Open Zoom recording (staff only)</a>

                <template v-if="session.has_notes">
                    <Field label="Summary" for="ns"><textarea id="ns" v-model="notes.summary" rows="5" class="textarea" /></Field>
                    <fieldset>
                        <legend class="label">Key points</legend>
                        <div v-for="(k, i) in notes.key_points" :key="'k' + i" class="mb-2 flex gap-2">
                            <input v-model="notes.key_points[i]" type="text" class="input" :aria-label="'Key point ' + (i + 1)" />
                            <button type="button" class="btn-outline btn-sm" @click="notes.key_points.splice(i, 1)">✕</button>
                        </div>
                        <button type="button" class="text-xs font-semibold text-lwe-navy2 hover:underline" @click="notes.key_points.push('')">+ Add point</button>
                    </fieldset>
                    <fieldset>
                        <legend class="label">Homework / action items</legend>
                        <div v-for="(k, i) in notes.action_items" :key="'a' + i" class="mb-2 flex gap-2">
                            <input v-model="notes.action_items[i]" type="text" class="input" :aria-label="'Action ' + (i + 1)" />
                            <button type="button" class="btn-outline btn-sm" @click="notes.action_items.splice(i, 1)">✕</button>
                        </div>
                        <button type="button" class="text-xs font-semibold text-lwe-navy2 hover:underline" @click="notes.action_items.push('')">+ Add item</button>
                    </fieldset>
                    <Field label="Recap for students who missed the class" for="nr"><textarea id="nr" v-model="notes.recap" rows="3" class="textarea" /></Field>
                    <div class="flex flex-wrap justify-end gap-2 border-t border-lwe-line pt-4">
                        <button type="button" class="btn-outline" :disabled="notes.processing" @click="saveNotes(false)">Save draft</button>
                        <button type="button" class="btn-primary" :disabled="notes.processing" @click="saveNotes(true)">Save &amp; publish to students</button>
                    </div>
                </template>
            </section>
        </div>
    </AppLayout>
</template>
