<script setup>
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Field from '@/Components/Lwe/Field.vue';

const props = defineProps({ settings: Object, ai: Object, mail: Object });
const testMail = () => router.post(route('admin.settings.test-mail'), {}, { preserveScroll: true });
const testWhatsapp = () => router.post(route('admin.settings.test-whatsapp'), {}, { preserveScroll: true });
const featureLabel = { class_notes: 'Class notes', course_content: 'Course Builder', course_outline: 'Course outline', lesson_writer: 'Lesson Writer', payment_check: 'Payment Checker' };
const form = useForm({ ...props.settings });
</script>

<template>
    <AppLayout title="Settings">
        <h1 class="page-title mb-6">Settings</h1>
        <form class="card max-w-2xl space-y-5" @submit.prevent="form.put(route('admin.settings.update'), { preserveScroll: true })">
            <Field label="Alert when one account uses more than this many devices in 24 hours" for="md" :error="form.errors.max_devices_per_day">
                <input id="md" v-model="form.max_devices_per_day" type="number" min="1" max="20" class="input max-w-[8rem]" />
            </Field>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.admin_two_factor" type="checkbox" class="rounded" /> Admins must enter a WhatsApp/email code after their password</label>
            <Field label="Payment instructions shown to students" for="pi" :error="form.errors.payment_instructions">
                <textarea id="pi" v-model="form.payment_instructions" rows="5" class="textarea" />
            </Field>
            <fieldset class="rounded-xl border border-lwe-line p-4">
                <legend class="px-1 text-sm font-semibold text-lwe-navy">Live classes</legend>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Field label="Join opens (min before)" for="jb" :error="form.errors.join_before_minutes"><input id="jb" v-model="form.join_before_minutes" type="number" min="0" max="60" class="input" /></Field>
                    <Field label="Present if watched ≥ %" for="pp" :error="form.errors.attendance_present_percent"><input id="pp" v-model="form.attendance_present_percent" type="number" min="1" max="100" class="input" /></Field>
                    <Field label="Partial if watched ≥ %" for="pa" :error="form.errors.attendance_partial_percent"><input id="pa" v-model="form.attendance_partial_percent" type="number" min="0" max="100" class="input" /></Field>
                </div>
                <p class="label mt-4">WhatsApp + app reminders</p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.reminder_day" type="checkbox" class="rounded" /> 1 day before</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.reminder_hour" type="checkbox" class="rounded" /> 1 hour before</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.reminder_start" type="checkbox" class="rounded" /> When Join opens</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.reminder_missed" type="checkbox" class="rounded" /> After class, to students who missed it</label>
                </div>
            </fieldset>
            <fieldset class="rounded-xl border border-lwe-line p-4">
                <legend class="px-1 text-sm font-semibold text-lwe-navy">Automation</legend>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.auto_certificates" type="checkbox" class="rounded" /> Issue certificates by themselves when a batch ends and the student meets the course rules</label>
                <label class="mt-2 flex items-center gap-2 text-sm"><input v-model="form.auto_nudges" type="checkbox" class="rounded" /> Send a friendly weekly nudge (WhatsApp + email) to students who are falling behind</label>
                <label class="mt-2 flex items-center gap-2 text-sm"><input v-model="form.weekly_report" type="checkbox" class="rounded" /> Email a weekly report with AI notes to all admins (Monday 9 AM)</label>
                <p class="mt-2 text-xs text-lwe-muted">Runs every morning at 7 AM. Task-due reminders go out every hour.</p>
            </fieldset>
            <fieldset class="rounded-xl border border-lwe-line p-4">
                <legend class="px-1 text-sm font-semibold text-lwe-navy">Daily attendance</legend>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Campus name" for="cn" :error="form.errors.campus_name"><input id="cn" v-model="form.campus_name" type="text" class="input" /></Field>
                    <Field label="Campus QR changes every (seconds)" for="cq" :error="form.errors.campus_qr_seconds" hint="Short time = a photo of the QR on WhatsApp stops working quickly.">
                        <input id="cq" v-model="form.campus_qr_seconds" type="number" min="15" max="600" class="input" />
                    </Field>
                </div>
                <label class="mt-3 flex items-center gap-2 text-sm"><input v-model="form.checkin_note_required" type="checkbox" class="rounded" /> Online check-in asks “What did you learn today?”</label>
            </fieldset>
            <fieldset class="rounded-xl border border-lwe-line p-4">
                <legend class="px-1 text-sm font-semibold text-lwe-navy">Email</legend>
                <p class="text-xs" :class="mail.ready ? 'text-green-800' : 'text-amber-700'">
                    <template v-if="mail.ready">SMTP ready · {{ mail.host }} · from {{ mail.from }}</template>
                    <template v-else>Email is in “{{ mail.mailer }}” mode — nothing is really sent yet. Add the SMTP keys to the .env file.</template>
                </p>
                <label class="mt-3 flex items-center gap-2 text-sm"><input v-model="form.email_enabled" type="checkbox" class="rounded" /> Send emails</label>
                <div class="mt-2 grid grid-cols-1 gap-2 pl-6 sm:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.email_students" type="checkbox" class="rounded" :disabled="!form.email_enabled" /> To students</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.email_staff" type="checkbox" class="rounded" :disabled="!form.email_enabled" /> To admins &amp; mentors</label>
                </div>
                <p class="mt-2 text-xs text-lwe-muted">Students: welcome, enrollment, payment approved/rejected, new classes, class reminders and changes. Admins: new payments, new enrollments, device alerts. Mentors: their new classes, reminders and changes.</p>
                <button type="button" class="btn-outline btn-sm mt-3" @click="testMail">Send a test email to me</button>
                <button type="button" class="btn-outline btn-sm mt-3 ml-2" @click="testWhatsapp">Send a test WhatsApp to me</button>
            </fieldset>
            <fieldset class="rounded-xl border border-violet-200 bg-violet-50 p-4">
                <legend class="px-1 text-sm font-semibold text-violet-900">AI</legend>
                <p class="text-xs" :class="ai.key_set ? 'text-green-800' : 'text-red-700'">
                    {{ ai.key_set ? 'API key found · model: ' + ai.model : 'No API key: add ANTHROPIC_API_KEY to the .env file.' }}
                </p>
                <label class="mt-3 flex items-center gap-2 text-sm"><input v-model="form.ai_enabled" type="checkbox" class="rounded" /> Use AI features (course builder, lesson writer, payment checker)</label>
                <Field class="mt-3" label="Max AI requests per day (0 = no limit)" for="adl" :error="form.errors.ai_daily_limit">
                    <input id="adl" v-model="form.ai_daily_limit" type="number" min="0" class="input max-w-[8rem]" />
                </Field>
                <p class="mt-3 text-xs text-slate-700">Today: <b>{{ ai.used_today }}</b> requests<template v-if="ai.errors_today"> ({{ ai.errors_today }} failed)</template> · This month: <b>{{ ai.tokens_month.toLocaleString() }}</b> tokens</p>
                <p v-if="Object.keys(ai.by_feature).length" class="text-xs text-slate-700">This month by feature:
                    <template v-for="(c, f) in ai.by_feature" :key="f"> {{ featureLabel[f] ?? f }} {{ c }} ·</template>
                </p>
            </fieldset>
            <fieldset class="rounded-xl border border-lwe-line p-4">
                <legend class="px-1 text-sm font-semibold text-lwe-navy">Ads &amp; tracking (public mentor pages)</legend>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Field label="Meta (Facebook) Pixel ID" for="px" :error="form.errors.meta_pixel_id" hint="Numbers only. From Meta Events Manager."><input id="px" v-model="form.meta_pixel_id" type="text" inputmode="numeric" class="input" placeholder="1234567890123456" /></Field>
                    <Field label="Google Analytics 4 ID" for="ga" :error="form.errors.ga4_id" hint="Optional, e.g. G-ABC123XYZ"><input id="ga" v-model="form.ga4_id" type="text" class="input" placeholder="G-" /></Field>
                    <Field label="Default WhatsApp for enquiries" for="sw" :error="form.errors.site_whatsapp" hint="Used when a mentor has no number."><input id="sw" v-model="form.site_whatsapp" type="tel" class="input" placeholder="0300 1234567" /></Field>
                </div>
                <p class="mt-2 text-xs text-lwe-muted">Mentor pages send PageView, ViewContent and Lead (when someone taps a package or WhatsApp) to the pixel. A mentor can also have their own extra pixel on their page.</p>
            </fieldset>
            <div class="flex justify-end border-t border-lwe-line pt-4"><button type="submit" class="btn-primary" :disabled="form.processing">Save settings</button></div>
        </form>
    </AppLayout>
</template>
