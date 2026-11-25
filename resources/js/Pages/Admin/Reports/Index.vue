<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import AiButton from '@/Components/Lwe/AiButton.vue';
import StatTile from '@/Components/Charts/StatTile.vue';
import BarChart from '@/Components/Charts/BarChart.vue';
import LineChart from '@/Components/Charts/LineChart.vue';
import HBars from '@/Components/Charts/HBars.vue';
import { fmt } from '@/Components/Charts/format';

const props = defineProps({ tab: String, filters: Object, data: Object, ai: Object });

const tabs = { overview: 'Overview', revenue: 'Revenue', courses: 'Courses & batches', mentors: 'Mentors', franchise: 'Franchise' };
const ranges = { today: 'Today', '7d': 'Last 7 days', '30d': 'Last 30 days', month: 'This month', last_month: 'Last month', '90d': 'Last 90 days', year: 'This year', custom: 'Custom…' };
const f = ref({ range: props.filters.range, from: props.filters.from, to: props.filters.to });
const query = (extra = {}) => ({ tab: props.tab, range: f.value.range, ...(f.value.range === 'custom' ? { from: f.value.from, to: f.value.to } : {}), ...extra });
const go = (extra = {}) => router.get(route('admin.reports.index'), query(extra), { preserveScroll: true });
const exportUrl = computed(() => route('admin.reports.export', query()));

const notes = ref(props.ai);
const aiState = ref({ language: 'roman_urdu', loading: false, error: '' });
const askAi = async () => {
    aiState.value.loading = true; aiState.value.error = '';
    try {
        const { data } = await axios.post(route('admin.reports.ai', query()), { language: aiState.value.language });
        notes.value = data;
    } catch (e) {
        aiState.value.error = e.response?.data?.message ?? 'AI could not read the report right now.';
    } finally {
        aiState.value.loading = false;
    }
};
const k = computed(() => props.data.kpis);
const status = { upcoming: 'Upcoming', running: 'Running', completed: 'Completed' };
</script>

<template>
    <AppLayout title="Reports">
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div><h1 class="page-title">Reports</h1><p class="muted">{{ filters.label }} · {{ filters.from }} → {{ filters.to }}</p></div>
            <div class="flex flex-wrap items-center gap-2">
                <select v-model="f.range" class="input w-44" aria-label="Period" @change="f.range !== 'custom' && go()"><option v-for="(l, key) in ranges" :key="key" :value="key">{{ l }}</option></select>
                <template v-if="f.range === 'custom'">
                    <input v-model="f.from" type="date" class="input w-40" aria-label="From" />
                    <input v-model="f.to" type="date" class="input w-40" aria-label="To" />
                    <button type="button" class="btn-dark" @click="go()">Apply</button>
                </template>
                <a v-if="tab !== 'overview'" :href="exportUrl" class="btn-outline">Export CSV</a>
            </div>
        </div>

        <nav class="mb-6 flex flex-wrap gap-2" aria-label="Report sections">
            <button v-for="(l, key) in tabs" :key="key" type="button" class="rounded-full px-4 py-2 text-sm font-semibold"
                :class="tab === key ? 'bg-lwe-navy text-white' : 'bg-white text-lwe-navy ring-1 ring-lwe-line'" :aria-current="tab === key ? 'page' : null" @click="go({ tab: key })">{{ l }}</button>
        </nav>

        <!-- AI notes -->
        <section class="mb-6 rounded-2xl border border-violet-200 bg-violet-50 p-4" aria-label="AI notes">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-semibold text-violet-900">✨ AI notes for this period</p>
                <div class="flex items-center gap-2">
                    <select v-model="aiState.language" class="input h-10 w-36" aria-label="Language"><option value="roman_urdu">Roman Urdu</option><option value="english">English</option><option value="urdu">اردو</option></select>
                    <AiButton :loading="aiState.loading" :label="notes ? 'Read again' : 'Read this report'" @click="askAi" />
                </div>
            </div>
            <p v-if="aiState.error" class="mt-2 text-sm font-semibold text-red-700">{{ aiState.error }}</p>
            <div v-if="notes" class="mt-3 grid grid-cols-1 gap-4 text-sm text-violet-950 md:grid-cols-2">
                <p class="font-semibold md:col-span-2">{{ notes.headline }}</p>
                <div><p class="label text-violet-900">What we see</p><ul class="list-disc space-y-1 pl-5"><li v-for="i in notes.insights" :key="i">{{ i }}</li></ul></div>
                <div><p class="label text-violet-900">Do this week</p><ul class="list-disc space-y-1 pl-5"><li v-for="a in notes.actions" :key="a">{{ a }}</li></ul></div>
            </div>
            <p v-else class="mt-1 text-xs text-violet-900">AI reads the numbers below and tells you what matters and what to do. It only uses these numbers.</p>
        </section>

        <!-- Overview -->
        <template v-if="tab === 'overview'">
            <section class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatTile label="Revenue" :value="fmt(k.revenue, 'pkr')" :delta="k.revenue_change" />
                <StatTile label="New enrollments" :value="fmt(k.enrollments)" :delta="k.enrollments_change" />
                <StatTile label="Class attendance" :value="fmt(k.attendance, 'pct')" :delta="k.attendance_change" unit=" pts" hint="No classes in this period" />
                <StatTile label="Active students" :value="fmt(k.active_students)" :hint="`${fmt(k.new_students)} new sign-ups`" />
                <StatTile label="Classes held" :value="fmt(k.classes_held)" />
                <StatTile label="Daily check-ins" :value="fmt(k.checkins)" />
                <StatTile label="Tasks submitted · quizzes taken" :value="`${fmt(k.tasks_submitted)} · ${fmt(k.quizzes_taken)}`" />
                <StatTile label="Certificates issued" :value="fmt(k.certificates)" tone="good" />
                <StatTile label="Average score (active)" :value="fmt(k.avg_score, 'pct')" />
                <StatTile label="Students at risk" :value="fmt(k.at_risk)" :tone="k.at_risk ? 'bad' : 'navy'" />
                <StatTile label="Payments waiting" :value="fmt(k.pending_payments)" :hint="fmt(k.pending_amount, 'pkr')" :tone="k.pending_payments ? 'warn' : 'navy'" />
                <StatTile label="Q&amp;A questions" :value="fmt(k.questions)" />
            </section>
            <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="card"><h2 class="font-semibold text-lwe-navy">Revenue by month</h2><p class="mb-2 text-xs text-lwe-muted">PKR, last 12 months</p><BarChart :points="data.revenueByMonth" kind="pkr" title="Revenue by month" /></div>
                <div class="card"><h2 class="font-semibold text-lwe-navy">New enrollments by month</h2><p class="mb-2 text-xs text-lwe-muted">Last 12 months</p><BarChart :points="data.enrollmentsByMonth" title="New enrollments by month" /></div>
                <div class="card"><h2 class="font-semibold text-lwe-navy">Class attendance by week</h2><p class="mb-2 text-xs text-lwe-muted">Last 12 weeks</p><LineChart :points="data.attendanceByWeek" kind="pct" title="Class attendance by week" /></div>
                <div class="card"><h2 class="font-semibold text-lwe-navy">Daily check-ins</h2><p class="mb-2 text-xs text-lwe-muted">Online + campus, last 30 days</p><LineChart :points="data.checkinsByDay" title="Daily check-ins" /></div>
                <div class="card lg:col-span-2"><h2 class="mb-3 font-semibold text-lwe-navy">Where new students came from</h2><HBars :items="data.sources" title="Enrollment sources" /></div>
            </section>
        </template>

        <!-- Revenue -->
        <template v-else-if="tab === 'revenue'">
            <section class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatTile label="Revenue in period" :value="fmt(k.revenue, 'pkr')" :delta="k.revenue_change" />
                <StatTile label="Payments waiting" :value="fmt(k.pending_payments)" :hint="fmt(k.pending_amount, 'pkr')" :tone="k.pending_payments ? 'warn' : 'navy'" />
                <StatTile label="New enrollments" :value="fmt(k.enrollments)" :delta="k.enrollments_change" />
                <StatTile label="Revenue per enrollment" :value="k.enrollments ? fmt(k.revenue / k.enrollments, 'pkr') : '—'" />
            </section>
            <section class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="card lg:col-span-2"><h2 class="font-semibold text-lwe-navy">Revenue by month</h2><p class="mb-2 text-xs text-lwe-muted">PKR, last 12 months</p><BarChart :points="data.revenueByMonth" kind="pkr" title="Revenue by month" /></div>
                <div class="card"><h2 class="mb-3 font-semibold text-lwe-navy">By payment method</h2><HBars :items="data.methods" kind="pkr" title="Revenue by payment method" /></div>
            </section>
            <div class="table-wrap"><table class="table"><thead><tr><th>Course</th><th class="text-right">Revenue</th><th class="text-right">New enrollments</th><th class="text-right">Active students</th></tr></thead>
                <tbody class="divide-y divide-slate-100"><tr v-for="c in [...data.courses].sort((a, b) => b.revenue - a.revenue)" :key="c.id"><td class="font-semibold">{{ c.title }}</td><td class="text-right tabular-nums">{{ fmt(c.revenue, 'pkr') }}</td><td class="text-right tabular-nums">{{ c.new }}</td><td class="text-right tabular-nums">{{ c.active }}</td></tr></tbody></table></div>
        </template>

        <!-- Courses & batches -->
        <template v-else-if="tab === 'courses'">
            <h2 class="mb-3 text-lg font-semibold text-lwe-navy">Courses</h2>
            <div class="table-wrap mb-8"><table class="table"><thead><tr><th>Course</th><th class="text-right">Active</th><th class="text-right">New</th><th class="text-right">Revenue</th><th class="text-right">Attendance</th><th class="text-right">Avg score</th><th class="text-right">At risk</th><th class="text-right">Certified</th></tr></thead>
                <tbody class="divide-y divide-slate-100"><tr v-for="c in data.courses" :key="c.id"><td class="font-semibold">{{ c.title }}</td><td class="text-right tabular-nums">{{ c.active }}</td><td class="text-right tabular-nums">{{ c.new }}</td><td class="text-right tabular-nums">{{ fmt(c.revenue, 'pkr') }}</td><td class="text-right tabular-nums">{{ fmt(c.attendance, 'pct') }}</td><td class="text-right tabular-nums">{{ fmt(c.avg_score, 'pct') }}</td><td class="text-right tabular-nums" :class="c.at_risk ? 'font-semibold text-red-700' : ''">{{ c.at_risk }}</td><td class="text-right tabular-nums">{{ c.completed }}</td></tr></tbody></table></div>
            <h2 class="mb-3 text-lg font-semibold text-lwe-navy">Batches</h2>
            <div class="table-wrap"><table class="table"><thead><tr><th>Batch</th><th>Courses</th><th>Mentor</th><th class="text-right">Students</th><th class="text-right">Classes</th><th class="text-right">Attendance</th><th class="text-right">Avg score</th><th class="text-right">At risk</th><th class="text-right">Certified</th></tr></thead>
                <tbody class="divide-y divide-slate-100"><tr v-for="b in data.batches" :key="b.id"><td><b>{{ b.name }}</b><p class="text-xs text-lwe-muted">{{ status[b.status] }}</p></td><td class="max-w-xs text-xs">{{ b.courses }}</td><td class="text-sm">{{ b.mentor ?? '—' }}</td><td class="text-right tabular-nums">{{ b.students }}</td><td class="text-right tabular-nums">{{ b.classes }}</td><td class="text-right tabular-nums">{{ fmt(b.attendance, 'pct') }}</td><td class="text-right tabular-nums">{{ fmt(b.avg_score, 'pct') }}</td><td class="text-right tabular-nums" :class="b.at_risk ? 'font-semibold text-red-700' : ''">{{ b.at_risk }}</td><td class="text-right tabular-nums">{{ b.certified }}</td></tr></tbody></table></div>
        </template>

        <!-- Mentors -->
        <template v-else-if="tab === 'mentors'">
            <p class="muted mb-3">Check time = median hours from a student submitting a task to the mentor marking it.</p>
            <div class="table-wrap"><table class="table"><thead><tr><th>Mentor</th><th class="text-right">Students</th><th class="text-right">Live classes</th><th class="text-right">Attendance</th><th class="text-right">Tasks checked</th><th class="text-right">Check time</th><th class="text-right">Waiting</th><th class="text-right">Q&amp;A answers</th><th class="text-right">Unanswered</th><th class="text-right">Avg score</th></tr></thead>
                <tbody class="divide-y divide-slate-100"><tr v-for="m in data.mentors" :key="m.id"><td class="font-semibold">{{ m.name }}</td><td class="text-right tabular-nums">{{ m.students }}</td><td class="text-right tabular-nums">{{ m.classes }}</td><td class="text-right tabular-nums">{{ fmt(m.attendance, 'pct') }}</td><td class="text-right tabular-nums">{{ m.graded }}</td><td class="text-right tabular-nums">{{ m.check_hours != null ? m.check_hours + ' h' : '—' }}</td><td class="text-right tabular-nums" :class="m.waiting > 20 ? 'font-semibold text-amber-700' : ''">{{ m.waiting }}</td><td class="text-right tabular-nums">{{ m.answers }}</td><td class="text-right tabular-nums" :class="m.unanswered ? 'font-semibold text-amber-700' : ''">{{ m.unanswered }}</td><td class="text-right tabular-nums">{{ fmt(m.avg_score, 'pct') }}</td></tr></tbody></table></div>
        </template>

        <!-- Franchise -->
        <template v-else>
            <p v-if="!data.franchise.length" class="card text-sm text-lwe-muted">No franchise partners yet. Add them in Franchise.</p>
            <div v-else class="table-wrap"><table class="table"><thead><tr><th>Partner</th><th class="text-right">Students</th><th class="text-right">New in period</th><th class="text-right">Active</th><th class="text-right">Payment pending</th><th class="text-right">Completed</th><th class="text-right">Revenue in period</th></tr></thead>
                <tbody class="divide-y divide-slate-100"><tr v-for="p in data.franchise" :key="p.id"><td><b>{{ p.name }}</b><p class="text-xs text-lwe-muted">{{ p.city }}</p></td><td class="text-right tabular-nums">{{ p.students }}</td><td class="text-right tabular-nums">{{ p.new }}</td><td class="text-right tabular-nums">{{ p.active }}</td><td class="text-right tabular-nums">{{ p.pending }}</td><td class="text-right tabular-nums">{{ p.completed }}</td><td class="text-right tabular-nums">{{ fmt(p.revenue, 'pkr') }}</td></tr></tbody></table></div>
        </template>
    </AppLayout>
</template>
