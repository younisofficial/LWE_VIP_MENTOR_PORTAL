<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import QrImage from '@/Components/Lwe/QrImage.vue';

defineProps({ found: Boolean, code: String, certificate: Object, verifyUrl: String });
const print = () => window.print();
</script>

<template>
    <PublicLayout :title="certificate ? `Certificate — ${certificate.name}` : 'Certificate check'">
        <div v-if="!found" class="card mx-auto max-w-xl text-center">
            <p class="text-5xl">❌</p>
            <h1 class="page-title mt-2">Certificate not found</h1>
            <p class="muted">No certificate has the code <b>{{ code }}</b>. Check the code and try again.</p>
        </div>

        <template v-else>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
                <p class="rounded-full px-4 py-2 text-sm font-bold" :class="certificate.valid ? 'bg-lwe-greenSoft text-green-900' : 'bg-red-100 text-red-900'">
                    {{ certificate.valid ? '✔ Verified — this certificate is genuine and valid' : '✖ This certificate has been cancelled' + (certificate.revoke_reason ? `: ${certificate.revoke_reason}` : '') }}
                </p>
                <div class="flex gap-2">
                    <a v-if="certificate.passport_url" :href="certificate.passport_url" class="btn-outline">Skill Passport</a>
                    <button type="button" class="btn-primary" @click="print">Download / Print (PDF)</button>
                </div>
            </div>

            <!-- The certificate (A4 landscape when printed) -->
            <article class="cert relative mx-auto aspect-[1.414] w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-xl print:rounded-none print:shadow-none"
                :class="{ 'opacity-60 grayscale': !certificate.valid }">
                <div class="absolute inset-3 rounded-xl border-[3px] border-lwe-navy sm:inset-5" />
                <div class="absolute inset-5 rounded-lg border border-lwe-orange sm:inset-8" />
                <div class="relative flex h-full flex-col items-center justify-between px-8 py-8 text-center sm:px-20 sm:py-14">
                    <div class="flex items-center gap-3">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-lwe-orange text-sm font-bold text-lwe-navy">LWE</span>
                        <span class="text-lg font-bold text-lwe-navy sm:text-xl">Learning with Earning</span>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-[0.3em] text-lwe-muted sm:text-sm">Certificate of completion</p>
                        <p class="mt-4 text-sm text-slate-600">This is to certify that</p>
                        <h1 class="mt-2 text-3xl font-bold text-lwe-navy sm:text-5xl">{{ certificate.name }}</h1>
                        <p class="mt-3 text-sm text-slate-600 sm:text-base">has successfully completed the practical course</p>
                        <h2 class="mt-2 text-xl font-semibold text-lwe-navy2 sm:text-3xl">{{ certificate.course }}</h2>
                        <p class="mt-3 text-sm sm:text-base"><b class="text-lwe-orange">{{ certificate.grade }}</b><template v-if="certificate.score != null"> · Final score {{ certificate.score }}%</template><template v-if="certificate.batch"> · {{ certificate.batch }}</template></p>
                        <p v-if="certificate.skills.length" class="mx-auto mt-3 max-w-2xl text-xs text-slate-600 sm:text-sm">Verified skills: {{ certificate.skills.join(' · ') }}</p>
                    </div>
                    <div class="flex w-full items-end justify-between gap-4 text-left text-xs sm:text-sm">
                        <div><p class="font-semibold text-lwe-navy">{{ certificate.mentors.join(', ') || 'Learning with Earning' }}</p><p class="text-lwe-muted">Mentor</p></div>
                        <div class="text-center"><QrImage :value="verifyUrl" :size="84" /><p class="mt-1 font-mono text-[10px] text-lwe-muted">{{ certificate.code }}</p></div>
                        <div class="text-right"><p class="font-semibold text-lwe-navy">{{ certificate.issued }}</p><p class="text-lwe-muted">Date issued</p></div>
                    </div>
                </div>
            </article>
            <p class="mt-4 text-center text-xs text-lwe-muted print:hidden">Anyone can check this certificate at {{ verifyUrl }}</p>
        </template>
    </PublicLayout>
</template>

<style>
@media print {
    @page { size: A4 landscape; margin: 0; }
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .cert { width: 297mm !important; max-width: none !important; height: 210mm !important; aspect-ratio: auto !important; }
}
</style>
