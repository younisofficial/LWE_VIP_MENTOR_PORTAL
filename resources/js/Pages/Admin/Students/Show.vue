<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Lwe/StatusBadge.vue';

const props = defineProps({ student: Object, enrollments: Array, payments: Array, logins: Array });

const act = (action, question) => {
    if (question && !confirm(question)) return;
    router.post(route('admin.students.action', props.student.id), { action }, { preserveScroll: true });
};
</script>

<template>
    <AppLayout :title="student.name">
        <Link :href="route('admin.students.index')" class="text-sm font-semibold text-lwe-navy2 hover:underline">← Students</Link>
        <div class="mt-2 mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="page-title flex items-center gap-3">{{ student.name }} <StatusBadge :status="student.status" /></h1>
                <p class="muted">{{ student.student_code }} · {{ student.phone }}<template v-if="student.email"> · {{ student.email }}</template><template v-if="student.city"> · {{ student.city }}</template></p>
                <p class="muted">Last login: {{ student.last_login ?? 'Never' }}</p>
                <p v-if="student.flagged_at" class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">Many-device alert {{ student.flagged_at }}.
                    <button type="button" class="ml-2 font-semibold underline" @click="act('clear_flag')">Clear alert</button></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-outline btn-sm" @click="act('force_logout', 'Log this student out of every device?')">Log out everywhere</button>
                <button v-if="!student.device_locked" type="button" class="btn-outline btn-sm" @click="act('lock_device', 'Lock this account to the device they last used?')">Lock to last device</button>
                <button v-else type="button" class="btn-outline btn-sm" @click="act('unlock_device')">Unlock device</button>
                <button v-if="student.status === 'active'" type="button" class="btn-danger btn-sm" @click="act('suspend', 'Suspend this account? They will be logged out.')">Suspend</button>
                <button v-else type="button" class="btn-dark btn-sm" @click="act('activate')">Re-activate</button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="card">
                <h2 class="mb-3 font-semibold text-lwe-navy">Courses</h2>
                <p v-if="!enrollments.length" class="muted">Not enrolled yet.</p>
                <ul class="divide-y divide-slate-100">
                    <li v-for="e in enrollments" :key="e.id" class="flex items-center justify-between gap-3 py-3 text-sm">
                        <span><span class="font-semibold">{{ e.course }}</span><span class="block text-xs text-lwe-muted">{{ e.batch ?? 'No batch' }} · {{ e.progress }}% done<template v-if="e.amount_due"> · PKR {{ e.amount_due.toLocaleString() }} due</template></span></span>
                        <StatusBadge :status="e.status" />
                    </li>
                </ul>
            </section>
            <section class="card">
                <h2 class="mb-3 font-semibold text-lwe-navy">Payments</h2>
                <p v-if="!payments.length" class="muted">No payments.</p>
                <ul class="divide-y divide-slate-100">
                    <li v-for="p in payments" :key="p.id" class="flex items-center justify-between gap-3 py-3 text-sm">
                        <span>PKR {{ p.amount.toLocaleString() }} · {{ p.course }}<span class="block text-xs text-lwe-muted">{{ p.date }}</span></span>
                        <StatusBadge :status="p.status" />
                    </li>
                </ul>
            </section>
            <section class="card lg:col-span-2">
                <h2 class="mb-3 font-semibold text-lwe-navy">Login history (last 20)</h2>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Time</th><th>Device</th><th>IP</th><th>Result</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(l, i) in logins" :key="i"><td class="text-xs">{{ l.time }}</td><td>{{ l.device }}</td><td class="font-mono text-xs">{{ l.ip }}</td><td><StatusBadge :status="l.result" /></td></tr>
                        </tbody>
                    </table>
                    <p v-if="!logins.length" class="muted py-3">No logins yet.</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
