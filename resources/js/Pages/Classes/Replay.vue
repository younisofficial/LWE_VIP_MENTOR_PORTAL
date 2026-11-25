<script setup>
// Recorded lecture playing "live" inside the portal at its class time. Attendance comes from
// the 30-second pings while the student has the lecture open.
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import ProtectedVideo from '@/Components/Lwe/ProtectedVideo.vue';

const props = defineProps({ session: Object, backUrl: String });

const started = ref(false);
const ended = ref(false);
const remaining = ref(0);
let ping;
let tick;

const send = () => axios.post(route('student.classes.watch', props.session.id)).catch((e) => {
    if (e.response?.status === 401 || e.response?.status === 419) window.location.href = props.backUrl; // logged in elsewhere
});
const onStart = () => {
    started.value = true;
    send();
    ping = setInterval(() => { if (document.visibilityState === 'visible') send(); }, 30000);
};
onMounted(() => {
    const update = () => {
        remaining.value = Math.max(0, Math.round((Date.parse(props.session.ends_iso) - Date.now()) / 1000));
        if (!remaining.value) { ended.value = true; clearInterval(ping); clearInterval(tick); }
    };
    update();
    tick = setInterval(update, 1000);
});
onBeforeUnmount(() => { clearInterval(ping); clearInterval(tick); });
const mmss = (s) => `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
</script>

<template>
    <Head :title="session.title" />
    <div class="min-h-screen bg-slate-950 text-white">
        <header class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6">
            <div class="min-w-0"><p class="truncate text-xs text-white/60">{{ session.course }} · {{ session.batch }}</p><h1 class="truncate font-semibold">{{ session.title }}</h1></div>
            <div class="flex items-center gap-3">
                <span class="rounded-full bg-red-600 px-2.5 py-1 text-[11px] font-bold">● RECORDED LECTURE</span>
                <span class="tabular-nums text-sm text-white/70">{{ mmss(remaining) }} left</span>
                <Link :href="backUrl" class="rounded-lg border border-white/40 px-3 py-1.5 text-sm hover:bg-white/10">Leave</Link>
            </div>
        </header>
        <main class="mx-auto max-w-5xl px-4 pb-10">
            <div v-if="ended" class="rounded-2xl bg-white/5 p-10 text-center">
                <p class="text-2xl font-semibold">This lecture has finished ✔</p>
                <p class="mt-2 text-white/70">Your attendance is saved. You can watch it again from the course lessons.</p>
                <Link :href="backUrl" class="mt-6 inline-block rounded-lg bg-lwe-orange px-5 py-2.5 font-semibold text-lwe-navy">Back to classes</Link>
            </div>
            <ProtectedVideo v-else :url="session.video_url" :title="session.title" :start-at="session.offset" live @started="onStart" />
            <p v-if="!started && !ended" class="mt-3 text-center text-sm text-white/70">Press play to join. The lecture runs on the class clock for everyone, like a live class — keep this page open for your attendance.</p>
            <div v-if="session.lesson?.summary" class="mt-6 rounded-2xl bg-white/5 p-5 text-sm text-white/85"><p class="mb-1 font-semibold">About this lesson</p>{{ session.lesson.summary }}</div>
        </main>
    </div>
</template>
