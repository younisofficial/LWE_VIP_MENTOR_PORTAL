<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from '@/Components/Lwe/Dialog.vue';
import Field from '@/Components/Lwe/Field.vue';
import Pagination from '@/Components/Lwe/Pagination.vue';
import StatusBadge from '@/Components/Lwe/StatusBadge.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';

const props = defineProps({ sessions: Object, filters: Object, batches: Array, mentors: Array, lessons: Array, days: Array, hasZoom: Boolean });

const tabs = [['upcoming', 'Upcoming'], ['past', 'Past'], ['cancelled', 'Cancelled']];
const batchFilter = ref(props.filters.batch_id ?? '');
watch(batchFilter, () => router.get(route('admin.classes.index'), { when: props.filters.when, batch_id: batchFilter.value || undefined }, { preserveState: true, replace: true }));

const dayLabel = (d) => d.charAt(0).toUpperCase() + d.slice(1);
const today = new Date(Date.now() + 5 * 3600 * 1000).toISOString().slice(0, 10); // Pakistan date

// ---- Schedule dialog ----
const showSchedule = ref(false);
const form = useForm({ batch_id: '', course_id: '', start_date: today, time: '20:00', duration: 90, days: [], weeks: 4, mentor_id: '', start_lesson_id: '', title: '', kind: 'live' });
const batch = computed(() => props.batches.find((b) => b.id === Number(form.batch_id)));
watch(() => form.batch_id, () => {
    if (!batch.value) return;
    form.days = [...(batch.value.class_days ?? [])];
    form.time = batch.value.class_time?.slice(0, 5) || form.time;
    form.duration = batch.value.class_duration_minutes || form.duration;
    form.mentor_id = batch.value.mentor_id ?? '';
    form.start_lesson_id = '';
    // One course in the batch → pick it; several → the admin chooses.
    form.course_id = batch.value.courses.length === 1 ? batch.value.courses[0].id : '';
});
watch(() => form.course_id, () => {
    form.start_lesson_id = '';
    // Host = the chosen course's mentor; otherwise the batch's lead mentor.
    const course = batch.value?.courses.find((c) => c.id === Number(form.course_id));
    form.mentor_id = course?.mentor_ids?.[0] ?? batch.value?.mentor_id ?? '';
});
const courseLessons = computed(() => (form.course_id ? props.lessons.filter((l) => l.course_id === Number(form.course_id) && (form.kind !== 'recorded' || l.has_video)) : []));
watch(() => form.kind, () => { form.start_lesson_id = ''; });
const classCount = computed(() => {
    if (!form.start_date || !form.weeks) return 0;
    const start = new Date(form.start_date + 'T00:00:00');
    const days = form.days.length ? form.days : [['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'][start.getDay()]];
    let n = 0;
    for (let i = 0; i < form.weeks * 7; i++) {
        const d = new Date(start); d.setDate(start.getDate() + i);
        if (days.includes(['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'][d.getDay()])) n++;
    }
    return n;
});
const schedule = () => form.post(route('admin.classes.store'), { preserveScroll: true, onSuccess: () => { showSchedule.value = false; form.reset(); } });

// ---- Reschedule / cancel ----
const moving = ref(null);
const moveForm = useForm({ date: '', time: '', duration: 90 });
const openMove = (s) => { moveForm.date = s.date; moveForm.time = s.time; moveForm.duration = s.duration; moveForm.clearErrors(); moving.value = s; };
const move = () => moveForm.post(route('admin.classes.reschedule', moving.value.id), { preserveScroll: true, onSuccess: () => (moving.value = null) });

const cancelling = ref(null);
const cancelForm = useForm({ reason: '' });
const cancel = () => cancelForm.post(route('admin.classes.cancel', cancelling.value.id), { preserveScroll: true, onSuccess: () => { cancelling.value = null; cancelForm.reset(); } });
</script>

<template>
    <AppLayout title="Live classes">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><h1 class="page-title">Live classes</h1><p class="muted">Zoom meetings are created automatically. Students join inside the portal.</p></div>
            <button type="button" class="btn-primary" :disabled="!hasZoom" @click="showSchedule = true">+ Schedule classes</button>
        </div>

        <div v-if="!hasZoom" class="mb-4 rounded-xl bg-lwe-orangeSoft p-4 text-sm text-amber-900">
            No Zoom account is connected yet. A Super Admin can add one in <b>Zoom Accounts</b>.
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <Link v-for="[key, text] in tabs" :key="key" :href="route('admin.classes.index', { when: key, batch_id: batchFilter || undefined })"
                class="rounded-full px-4 py-2 text-sm font-semibold" :class="filters.when === key ? 'bg-lwe-navy text-white' : 'bg-white text-slate-700 ring-1 ring-lwe-line'">{{ text }}</Link>
            <select v-model="batchFilter" class="input ml-auto w-64" aria-label="Batch">
                <option value="">All batches</option>
                <option v-for="b in batches" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
        </div>

        <EmptyState v-if="!sessions.data.length" title="No classes here" text="Use “Schedule classes” to plan a batch's classes in one go." />
        <div v-else class="table-wrap">
            <table class="table">
                <thead><tr><th>When (PK)</th><th>Class</th><th>Batch</th><th>Mentor</th><th>Zoom</th><th>Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="s in sessions.data" :key="s.id">
                        <td class="text-xs font-semibold">{{ s.label }}<span class="block font-normal text-lwe-muted">{{ s.duration }} min</span></td>
                        <td><Link :href="route('admin.classes.show', s.id)" class="font-semibold hover:underline">{{ s.title }}</Link></td>
                        <td class="text-xs">{{ s.course }}<br />{{ s.batch }}</td>
                        <td>{{ s.mentor ?? '—' }}</td>
                        <td class="text-xs">
                            <span v-if="s.kind === 'recorded'" class="font-semibold text-violet-800">▶ Recorded</span>
                            <span v-else-if="s.zoom_ready" class="font-semibold text-lwe-green">Ready</span>
                            <span v-else-if="s.zoom_error" class="font-semibold text-red-700" :title="s.zoom_error">Error</span>
                            <span v-else class="text-lwe-muted">Creating…</span>
                            <span class="block text-lwe-muted">{{ s.zoom_account }}</span>
                        </td>
                        <td><StatusBadge :status="s.status === 'scheduled' ? 'upcoming' : s.status === 'live' ? 'running' : s.status" /></td>
                        <td class="space-x-1 whitespace-nowrap text-right">
                            <a v-if="['scheduled', 'live'].includes(s.status) && s.zoom_ready && s.kind !== 'recorded'" :href="route('host.classes.start', s.id)" class="btn-dark btn-sm">Start</a>
                            <Link :href="route('admin.classes.show', s.id)" class="btn-outline btn-sm">Open</Link>
                            <template v-if="s.status === 'scheduled'">
                                <button type="button" class="btn-outline btn-sm" @click="openMove(s)">Move</button>
                                <button type="button" class="btn-sm text-red-700 hover:underline" @click="cancelling = s">Cancel</button>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="sessions.links" />

        <!-- Schedule -->
        <Dialog :show="showSchedule" title="Schedule live classes" wide @close="showSchedule = false">
            <form class="space-y-4" @submit.prevent="schedule">
                <fieldset>
                    <legend class="label">Class type</legend>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <label class="flex cursor-pointer gap-3 rounded-xl border p-3" :class="form.kind === 'live' ? 'border-lwe-navy ring-2 ring-lwe-navy' : 'border-lwe-line'">
                            <input v-model="form.kind" type="radio" value="live" class="mt-1" />
                            <span><b class="block">🎥 Live class (Zoom)</b><span class="text-xs text-lwe-muted">Mentor teaches live; Zoom meetings are made automatically.</span></span>
                        </label>
                        <label class="flex cursor-pointer gap-3 rounded-xl border p-3" :class="form.kind === 'recorded' ? 'border-lwe-navy ring-2 ring-lwe-navy' : 'border-lwe-line'">
                            <input v-model="form.kind" type="radio" value="recorded" class="mt-1" />
                            <span><b class="block">▶ Recorded lecture (automatic)</b><span class="text-xs text-lwe-muted">Lesson videos play inside the portal at class time — no mentor or Zoom needed. Attendance is automatic.</span></span>
                        </label>
                    </div>
                </fieldset>
                <div v-if="form.errors.zoom" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ form.errors.zoom }}</div>
                <Field label="Batch" for="sb" :error="form.errors.batch_id">
                    <select id="sb" v-model="form.batch_id" class="input" required>
                        <option value="" disabled>Choose…</option>
                        <option v-for="b in batches" :key="b.id" :value="b.id">{{ b.name }} ({{ b.courses.map((c) => c.title).join(', ') }})</option>
                    </select>
                </Field>
                <Field v-if="batch" label="Course (which course these classes are for)" for="sc" :error="form.errors.course_id">
                    <select id="sc" v-model="form.course_id" class="input" required>
                        <option value="" disabled>Choose…</option>
                        <option v-for="c in batch.courses" :key="c.id" :value="c.id">{{ c.title }}</option>
                    </select>
                </Field>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <Field label="First class date" for="sd" :error="form.errors.start_date"><input id="sd" v-model="form.start_date" type="date" class="input" required /></Field>
                    <Field label="Time (PK)" for="st" :error="form.errors.time"><input id="st" v-model="form.time" type="time" class="input" required /></Field>
                    <Field label="Minutes" for="sdu" :error="form.errors.duration"><input id="sdu" v-model="form.duration" type="number" min="15" class="input" required /></Field>
                    <Field label="For weeks" for="sw" :error="form.errors.weeks"><input id="sw" v-model="form.weeks" type="number" min="1" max="52" class="input" required /></Field>
                </div>
                <fieldset>
                    <legend class="label">Class days</legend>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="d in days" :key="d" class="cursor-pointer rounded-full border px-3 py-2 text-xs font-semibold"
                            :class="form.days.includes(d) ? 'border-lwe-navy bg-lwe-navy text-white' : 'border-lwe-line'">
                            <input v-model="form.days" type="checkbox" :value="d" class="sr-only" /> {{ dayLabel(d) }}
                        </label>
                    </div>
                    <p v-if="form.errors.days" class="mt-1 text-xs text-red-700">{{ form.errors.days }}</p>
                </fieldset>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Mentor (host)" for="sm" :error="form.errors.mentor_id">
                        <select id="sm" v-model="form.mentor_id" class="input"><option value="">—</option><option v-for="m in mentors" :key="m.id" :value="m.id">{{ m.name }}</option></select>
                    </Field>
                    <Field :label="form.kind === 'recorded' ? 'First video lesson (next videos follow in order)' : 'Start from lesson (one lesson per class)'" for="sl" :error="form.errors.start_lesson_id">
                        <select id="sl" v-model="form.start_lesson_id" class="input"><option value="">{{ form.kind === 'recorded' ? 'Choose…' : 'No lesson link' }}</option><option v-for="l in courseLessons" :key="l.id" :value="l.id">{{ l.title }}</option></select>
                    </Field>
                </div>
                <Field label="Class title (optional — otherwise the lesson title)" for="sti" :error="form.errors.title"><input id="sti" v-model="form.title" type="text" class="input" /></Field>
                <div class="flex items-center justify-between gap-2 border-t border-lwe-line pt-4">
                    <span class="text-sm text-lwe-muted">{{ classCount }} classes will be created</span>
                    <div class="flex gap-2">
                        <button type="button" class="btn-outline" @click="showSchedule = false">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="form.processing || !classCount">{{ form.processing ? 'Creating Zoom meetings…' : 'Schedule ' + classCount + ' classes' }}</button>
                    </div>
                </div>
            </form>
        </Dialog>

        <!-- Move -->
        <Dialog :show="!!moving" title="Move class" @close="moving = null">
            <form v-if="moving" class="space-y-4" @submit.prevent="move">
                <p class="muted">{{ moving.title }} — students get a WhatsApp message with the new time.</p>
                <div v-if="moveForm.errors.zoom || moveForm.errors.mentor_id" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ moveForm.errors.zoom || moveForm.errors.mentor_id }}</div>
                <div class="grid grid-cols-3 gap-3">
                    <Field label="Date" for="md" :error="moveForm.errors.date"><input id="md" v-model="moveForm.date" type="date" class="input" /></Field>
                    <Field label="Time" for="mt" :error="moveForm.errors.time"><input id="mt" v-model="moveForm.time" type="time" class="input" /></Field>
                    <Field label="Minutes" for="mdu" :error="moveForm.errors.duration"><input id="mdu" v-model="moveForm.duration" type="number" min="15" class="input" /></Field>
                </div>
                <div class="flex justify-end gap-2 border-t border-lwe-line pt-4">
                    <button type="button" class="btn-outline" @click="moving = null">Back</button>
                    <button type="submit" class="btn-primary" :disabled="moveForm.processing">Move class</button>
                </div>
            </form>
        </Dialog>

        <!-- Cancel -->
        <Dialog :show="!!cancelling" title="Cancel class" @close="cancelling = null">
            <form v-if="cancelling" class="space-y-4" @submit.prevent="cancel">
                <p class="muted">{{ cancelling.title }} ({{ cancelling.label }}). The Zoom meeting is deleted and students are told on WhatsApp.</p>
                <Field label="Reason for students (optional)" for="cr"><input id="cr" v-model="cancelForm.reason" type="text" class="input" placeholder="e.g. Mentor is unwell; new date coming soon" /></Field>
                <div class="flex justify-end gap-2 border-t border-lwe-line pt-4">
                    <button type="button" class="btn-outline" @click="cancelling = null">Back</button>
                    <button type="submit" class="btn-danger" :disabled="cancelForm.processing">Cancel class</button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
