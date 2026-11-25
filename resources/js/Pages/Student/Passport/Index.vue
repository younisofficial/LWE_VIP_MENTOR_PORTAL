<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PassportView from '@/Components/Lwe/PassportView.vue';
import AiButton from '@/Components/Lwe/AiButton.vue';

const props = defineProps({
    student: Object, verified_skills: Array, learning_skills: Array, courses: Array, stats: Object, badges: Array,
    progress: Array, publicUrl: String, isPublic: Boolean, canAskAi: Boolean,
});

const form = useForm({ passport_public: props.isPublic, passport_summary: props.student.summary ?? '' });
const save = () => form.put(route('student.passport.update'), { preserveScroll: true });
const language = ref('english');
const asking = ref(false);
const askAi = () => {
    asking.value = true;
    router.post(route('student.passport.ai'), { language: language.value }, {
        preserveScroll: true,
        onSuccess: () => { form.passport_summary = props.student.summary ?? ''; },
        onFinish: () => (asking.value = false),
    });
};
const copied = ref(false);
const copy = async () => { try { await navigator.clipboard.writeText(props.publicUrl); copied.value = true; setTimeout(() => (copied.value = false), 2000); } catch { /* old browser */ } };
const shareWa = () => window.open(`https://wa.me/?text=${encodeURIComponent('My Skill Passport — Learning with Earning: ' + props.publicUrl)}`, '_blank');
const statusBadge = { eligible: ['Ready for certificate', 'bg-lwe-greenSoft text-green-900'], at_risk: ['Falling behind', 'bg-red-100 text-red-900'], on_track: ['On track', 'bg-blue-100 text-blue-900'] };
</script>

<template>
    <AppLayout title="Skill Passport">
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div><h1 class="page-title">My Skill Passport</h1><p class="muted">Your verified skills and real effort — share the link with clients and employers.</p></div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-outline" @click="copy">{{ copied ? 'Link copied ✔' : 'Copy link' }}</button>
                <button type="button" class="btn-outline" @click="shareWa">Share on WhatsApp</button>
                <a :href="publicUrl" target="_blank" class="btn-primary">Open public page / PDF</a>
            </div>
        </div>

        <!-- What is left in each course -->
        <section class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-2" aria-label="Course progress">
            <div v-for="p in progress" :key="p.course" class="card">
                <div class="flex items-center justify-between gap-2">
                    <p class="font-semibold text-lwe-navy">{{ p.course }}</p>
                    <span v-if="p.status === 'completed'" class="rounded-full bg-lwe-navy px-2.5 py-1 text-xs font-bold text-white">🎓 Completed</span>
                    <span v-else-if="p.progress_status" class="rounded-full px-2.5 py-1 text-xs font-bold" :class="statusBadge[p.progress_status][1]">{{ statusBadge[p.progress_status][0] }}</span>
                </div>
                <ul v-if="p.notes.length && p.status !== 'completed'" class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-700"><li v-for="n in p.notes" :key="n">{{ n }}</li></ul>
                <p v-else-if="p.status !== 'completed'" class="mt-2 text-sm text-green-800">Everything is on track. Keep going!</p>
            </div>
        </section>

        <section class="card mb-6 space-y-3" aria-label="Passport settings">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold text-lwe-navy">About me (shown on the passport)</h2>
                <div class="flex items-center gap-2">
                    <select v-model="language" class="input h-10 w-36" aria-label="Language"><option value="english">English</option><option value="roman_urdu">Roman Urdu</option><option value="urdu">اردو</option></select>
                    <AiButton :loading="asking" label="Write with AI" :disabled="!canAskAi" @click="askAi" />
                </div>
            </div>
            <textarea v-model="form.passport_summary" rows="3" maxlength="800" class="textarea" aria-label="About me" placeholder="AI writes this from your real record — skills, tasks, attendance." />
            <div class="flex flex-wrap items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-sm"><input v-model="form.passport_public" type="checkbox" class="rounded" /> Anyone with the link can see my passport (phone number is never shown)</label>
                <button type="button" class="btn-primary" :disabled="form.processing" @click="save">Save</button>
            </div>
        </section>

        <PassportView :student="student" :verified_skills="verified_skills" :learning_skills="learning_skills" :courses="courses" :stats="stats" :badges="badges" :verify-url="publicUrl" />
    </AppLayout>
</template>
