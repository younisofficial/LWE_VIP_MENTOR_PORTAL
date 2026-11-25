<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Field from '@/Components/Lwe/Field.vue';

const props = defineProps({ today: String, marked: Object, campus_today: Boolean, note_required: Boolean, days: Array, streak: Number, this_month: Number, courses: Array });

const form = useForm({ note: '', course_id: props.courses.length === 1 ? props.courses[0].id : '' });
const submit = () => form.post(route('student.attendance.store'), { preserveScroll: true });
</script>

<template>
    <AppLayout title="Daily attendance">
        <h1 class="page-title">Daily attendance</h1>
        <p class="muted mb-6">{{ today }} — mark yourself present every day you study. At campus, scan the QR code on the screen instead.</p>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <section class="card lg:col-span-2" aria-labelledby="today-h">
                <h2 id="today-h" class="mb-3 font-semibold text-lwe-navy">Today</h2>
                <div v-if="marked" class="rounded-xl bg-lwe-greenSoft p-4 text-green-900">
                    <p class="text-lg font-bold">✔ You are present today</p>
                    <p v-if="marked.note" class="mt-1 text-sm">“{{ marked.note }}”</p>
                </div>
                <form v-else class="space-y-3" @submit.prevent="submit">
                    <Field v-if="courses.length > 1" label="Which course did you study today?" for="ac">
                        <select id="ac" v-model="form.course_id" class="input"><option value="">—</option><option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option></select>
                    </Field>
                    <Field :label="note_required ? 'What did you learn or practise today?' : 'What did you learn today? (optional)'" for="an" :error="form.errors.note">
                        <textarea id="an" v-model="form.note" rows="3" maxlength="500" class="textarea" placeholder="e.g. Aaj maine Canva mein 2 Instagram posts banaye aur brand colors set kiye." />
                    </Field>
                    <button type="submit" class="btn-primary w-full sm:w-auto" :disabled="form.processing">I'm present today</button>
                </form>
                <p v-if="campus_today" class="mt-3 text-sm font-semibold text-blue-900">🏫 Campus attendance also marked today.</p>
            </section>

            <section class="card" aria-label="Your record">
                <div class="grid grid-cols-2 gap-3 text-center">
                    <div class="rounded-xl bg-lwe-orangeSoft p-3"><p class="text-3xl font-bold text-lwe-navy">🔥 {{ streak }}</p><p class="text-xs">day streak</p></div>
                    <div class="rounded-xl bg-blue-50 p-3"><p class="text-3xl font-bold text-lwe-navy">{{ this_month }}</p><p class="text-xs">days this month</p></div>
                </div>
                <p class="mt-3 text-xs text-lwe-muted">Regular attendance counts towards your certificate and Skill Passport.</p>
            </section>
        </div>

        <section class="card mt-6" aria-labelledby="cal-h">
            <h2 id="cal-h" class="mb-3 font-semibold text-lwe-navy">Last 30 days</h2>
            <div class="grid grid-cols-7 gap-2 sm:grid-cols-10">
                <div v-for="d in days" :key="d.date" :title="d.label" class="flex aspect-square flex-col items-center justify-center rounded-lg text-xs font-semibold"
                    :class="d.campus ? 'bg-blue-600 text-white' : d.self ? 'bg-green-600 text-white' : 'bg-slate-100 text-slate-500'">
                    {{ d.day }}<span class="sr-only">{{ d.label }}: {{ d.campus ? 'campus' : d.self ? 'present' : 'not marked' }}</span>
                </div>
            </div>
            <p class="mt-3 flex flex-wrap gap-4 text-xs"><span><span class="mr-1 inline-block h-3 w-3 rounded bg-green-600" />Online</span><span><span class="mr-1 inline-block h-3 w-3 rounded bg-blue-600" />Campus</span><span><span class="mr-1 inline-block h-3 w-3 rounded bg-slate-200" />Not marked</span></p>
        </section>
    </AppLayout>
</template>
