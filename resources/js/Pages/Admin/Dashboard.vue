<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Lwe/StatusBadge.vue';
import StatTile from '@/Components/Charts/StatTile.vue';
import BarChart from '@/Components/Charts/BarChart.vue';
import LineChart from '@/Components/Charts/LineChart.vue';
import { fmt } from '@/Components/Charts/format';

defineProps({ stats: Object, recentPayments: Array, recentEnrollments: Array, kpis: Object, revenueByMonth: Array, attendanceByWeek: Array, today: Object });
const pkr = (n) => 'PKR ' + Number(n).toLocaleString();
</script>

<template>
    <AppLayout title="Admin dashboard">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <h1 class="page-title">Dashboard</h1>
            <Link :href="route('admin.reports.index')" class="btn-outline">Full reports →</Link>
        </div>

        <!-- Today -->
        <section class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Today">
            <Link :href="route('admin.classes.index')" class="card hover:shadow-md"><p class="text-xs text-lwe-muted">Classes today</p><p class="mt-1 text-2xl font-bold text-lwe-navy">{{ today.classes }}</p></Link>
            <Link :href="route('manage.attendance.index')" class="card hover:shadow-md"><p class="text-xs text-lwe-muted">Checked in today</p><p class="mt-1 text-2xl font-bold text-lwe-navy">{{ today.checkins }}</p></Link>
            <Link :href="route('manage.tasks.index')" class="card hover:shadow-md"><p class="text-xs text-lwe-muted">Tasks waiting to be checked</p><p class="mt-1 text-2xl font-bold" :class="today.to_check ? 'text-amber-700' : 'text-lwe-navy'">{{ today.to_check }}</p></Link>
            <Link :href="route('qa.index', { filter: 'unanswered' })" class="card hover:shadow-md"><p class="text-xs text-lwe-muted">Questions not answered</p><p class="mt-1 text-2xl font-bold" :class="today.unanswered ? 'text-amber-700' : 'text-lwe-navy'">{{ today.unanswered }}</p></Link>
        </section>

        <!-- This month -->
        <h2 class="mb-3 text-lg font-semibold text-lwe-navy">This month</h2>
        <section class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="This month">
            <StatTile label="Revenue" :value="fmt(kpis.revenue, 'pkr')" :delta="kpis.revenue_change" />
            <StatTile label="New enrollments" :value="fmt(kpis.enrollments)" :delta="kpis.enrollments_change" />
            <StatTile label="Class attendance" :value="fmt(kpis.attendance, 'pct')" :delta="kpis.attendance_change" unit=" pts" hint="No classes yet" />
            <Link :href="route('manage.progress.index', { state: 'at_risk' })"><StatTile label="Students at risk" :value="fmt(kpis.at_risk)" :tone="kpis.at_risk ? 'bad' : 'navy'" hint="Open the list →" /></Link>
        </section>
        <section class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="card"><h3 class="font-semibold text-lwe-navy">Revenue by month</h3><p class="mb-2 text-xs text-lwe-muted">Approved payments, PKR — last 12 months</p><BarChart :points="revenueByMonth" kind="pkr" title="Revenue by month" /></div>
            <div class="card"><h3 class="font-semibold text-lwe-navy">Class attendance by week</h3><p class="mb-2 text-xs text-lwe-muted">Present + half of partial — last 12 weeks</p><LineChart :points="attendanceByWeek" kind="pct" title="Class attendance by week" /></div>
        </section>

        <h2 class="mb-3 text-lg font-semibold text-lwe-navy">Overall</h2>

        <section class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4" aria-label="Key numbers">
            <div class="card"><p class="text-xs text-lwe-muted">Students</p><p class="mt-1 text-3xl font-bold text-lwe-navy">{{ stats.students.toLocaleString() }}</p></div>
            <div class="card"><p class="text-xs text-lwe-muted">Active enrollments</p><p class="mt-1 text-3xl font-bold text-lwe-green">{{ stats.active_enrollments.toLocaleString() }}</p></div>
            <Link :href="route('admin.payments.index')" class="card hover:shadow-md"><p class="text-xs text-lwe-muted">Payments to review</p><p class="mt-1 text-3xl font-bold" :class="stats.pending_payments ? 'text-amber-700' : 'text-lwe-navy'">{{ stats.pending_payments }}</p></Link>
            <div class="card"><p class="text-xs text-lwe-muted">Approved this month</p><p class="mt-1 text-2xl font-bold text-lwe-navy">{{ pkr(stats.revenue_month) }}</p></div>
            <div class="card"><p class="text-xs text-lwe-muted">Published courses</p><p class="mt-1 text-3xl font-bold text-lwe-navy">{{ stats.published_courses }}</p></div>
            <div class="card"><p class="text-xs text-lwe-muted">Open batches</p><p class="mt-1 text-3xl font-bold text-lwe-navy">{{ stats.running_batches }}</p></div>
            <Link :href="route('admin.students.index', { status: 'flagged' })" class="card hover:shadow-md"><p class="text-xs text-lwe-muted">Many-device alerts (7 days)</p><p class="mt-1 text-3xl font-bold" :class="stats.flagged_students ? 'text-red-700' : 'text-lwe-navy'">{{ stats.flagged_students }}</p></Link>
            <Link :href="route('admin.courses.create')" class="card flex items-center justify-center bg-lwe-orange font-semibold text-lwe-navy hover:brightness-95">+ New course</Link>
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="card">
                <div class="mb-3 flex items-center justify-between"><h2 class="font-semibold text-lwe-navy">Payments waiting</h2><Link :href="route('admin.payments.index')" class="text-sm font-semibold text-lwe-navy2 hover:underline">Review all</Link></div>
                <p v-if="!recentPayments.length" class="muted">Nothing to review.</p>
                <ul class="divide-y divide-slate-100">
                    <li v-for="p in recentPayments" :key="p.id" class="flex items-center justify-between py-3 text-sm">
                        <span><span class="font-semibold">{{ p.student }}</span> <span class="text-xs text-lwe-muted">{{ p.code }}</span><span class="block text-xs text-lwe-muted">{{ p.course }} · {{ p.time }}</span></span>
                        <span class="font-semibold">{{ pkr(p.amount) }}</span>
                    </li>
                </ul>
            </section>
            <section class="card">
                <div class="mb-3 flex items-center justify-between"><h2 class="font-semibold text-lwe-navy">Latest enrollments</h2><Link :href="route('admin.enrollments.index')" class="text-sm font-semibold text-lwe-navy2 hover:underline">See all</Link></div>
                <ul class="divide-y divide-slate-100">
                    <li v-for="e in recentEnrollments" :key="e.id" class="flex items-center justify-between py-3 text-sm">
                        <span><span class="font-semibold">{{ e.student }}</span><span class="block text-xs text-lwe-muted">{{ e.course }} · {{ e.source }} · {{ e.time }}</span></span>
                        <StatusBadge :status="e.status" />
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
