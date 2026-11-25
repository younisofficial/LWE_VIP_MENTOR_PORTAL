<script setup>
// The Skill Passport card set — used on the public page and the student's own page.
import QrImage from '@/Components/Lwe/QrImage.vue';

defineProps({ student: Object, verified_skills: Array, learning_skills: Array, courses: Array, stats: Object, badges: Array, verifyUrl: String });
const pct = (v) => (v == null ? '—' : `${v}%`);
</script>

<template>
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl bg-lwe-navy text-white">
            <div class="flex flex-col gap-6 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                <div class="flex items-center gap-5">
                    <img v-if="student.photo" :src="student.photo" :alt="student.name" class="h-20 w-20 rounded-2xl object-cover" />
                    <span v-else class="flex h-20 w-20 items-center justify-center rounded-2xl bg-lwe-orange text-2xl font-bold text-lwe-navy">{{ student.name.split(' ').map((p) => p[0]).slice(0, 2).join('') }}</span>
                    <div>
                        <p class="text-xs uppercase tracking-widest text-lwe-orange">Skill Passport · {{ student.passport_code }}</p>
                        <h1 class="text-3xl font-bold">{{ student.name }}</h1>
                        <p class="text-sm text-white/75"><template v-if="student.city">{{ student.city }} · </template>Learning with Earning student since {{ student.member_since }}</p>
                    </div>
                </div>
                <div v-if="verifyUrl" class="flex items-center gap-3 rounded-xl bg-white p-2"><QrImage :value="verifyUrl" :size="88" alt="Scan to verify this Skill Passport" /></div>
            </div>
            <p v-if="student.summary" class="border-t border-white/10 px-6 py-4 text-[15px] leading-relaxed text-white/90 sm:px-8">{{ student.summary }}</p>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="card">
                <h2 class="mb-3 font-semibold text-lwe-navy">✔ Verified skills</h2>
                <div v-if="verified_skills.length" class="flex flex-wrap gap-2"><span v-for="s in verified_skills" :key="s" class="rounded-full bg-lwe-greenSoft px-3 py-1.5 text-sm font-semibold text-green-900">✔ {{ s }}</span></div>
                <p v-else class="text-sm text-lwe-muted">Skills are verified when a course is completed with a certificate.</p>
            </div>
            <div class="card">
                <h2 class="mb-3 font-semibold text-lwe-navy">Skills in progress</h2>
                <div v-if="learning_skills.length" class="flex flex-wrap gap-2"><span v-for="s in learning_skills" :key="s" class="rounded-full bg-lwe-orangeSoft px-3 py-1.5 text-sm font-semibold text-amber-900">{{ s }}</span></div>
                <p v-else class="text-sm text-lwe-muted">—</p>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="card"><p class="text-xs text-lwe-muted">Live classes attended</p><p class="text-2xl font-bold text-lwe-navy">{{ stats.classes_attended }}</p></div>
            <div class="card"><p class="text-xs text-lwe-muted">Practical tasks done</p><p class="text-2xl font-bold text-lwe-navy">{{ stats.tasks_submitted }}<span v-if="stats.tasks_avg != null" class="text-sm font-semibold text-lwe-muted"> · avg {{ stats.tasks_avg }} marks</span></p></div>
            <div class="card"><p class="text-xs text-lwe-muted">Quizzes passed</p><p class="text-2xl font-bold text-lwe-navy">{{ stats.quizzes_passed }}<span v-if="stats.quiz_avg != null" class="text-sm font-semibold text-lwe-muted"> · avg {{ stats.quiz_avg }}%</span></p></div>
            <div class="card"><p class="text-xs text-lwe-muted">Study days · best streak</p><p class="text-2xl font-bold text-lwe-navy">{{ stats.checkin_days }} · 🔥{{ stats.best_streak }}</p></div>
        </section>

        <section class="card">
            <h2 class="mb-3 font-semibold text-lwe-navy">Courses</h2>
            <p v-if="!courses.length" class="text-sm text-lwe-muted">No results yet.</p>
            <div class="divide-y divide-slate-100">
                <div v-for="c in courses" :key="c.title" class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div><p class="font-semibold">{{ c.title }}</p><p class="text-xs text-lwe-muted">Attendance {{ pct(c.attendance) }} · Tasks {{ pct(c.tasks) }} · Quiz {{ pct(c.quiz) }}</p></div>
                    <div class="flex items-center gap-3">
                        <b v-if="c.score != null" class="text-lg text-lwe-navy">{{ c.score }}%</b>
                        <a v-if="c.certificate" :href="c.certificate.url" class="rounded-full bg-lwe-navy px-3 py-1 text-xs font-bold text-white">🎓 {{ c.certificate.grade }} · {{ c.certificate.code }}</a>
                        <span v-else class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">In progress</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <h2 class="mb-3 font-semibold text-lwe-navy">Badges</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div v-for="b in badges" :key="b.key" class="rounded-xl border p-3 text-center" :class="b.on ? 'border-lwe-orange bg-lwe-orangeSoft' : 'border-lwe-line opacity-45 grayscale'">
                    <p class="text-3xl" aria-hidden="true">{{ b.icon }}</p><p class="text-sm font-semibold">{{ b.title }}</p><p class="text-xs text-lwe-muted">{{ b.text }}</p>
                    <span class="sr-only">{{ b.on ? 'Earned' : 'Not earned yet' }}</span>
                </div>
            </div>
        </section>
    </div>
</template>
