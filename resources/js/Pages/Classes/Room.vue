<script setup>
// Live class inside the portal (Zoom Meeting SDK, Component View). No Zoom link or app is used.
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({ session: Object, zoom: Object, role: Number, watermark: String, backUrl: String });

const root = ref(null);
const state = ref('loading'); // loading | joined | error | left
const error = ref('');
const mark = ref({ top: 30, left: 20 });
let client = null;
let heartbeat = null;
let mover = null;

const reload = () => window.location.reload();

const leave = async (to = props.backUrl) => {
    if (state.value === 'left') return;
    state.value = 'left';
    clearInterval(heartbeat);
    clearInterval(mover);
    try { await client?.leaveMeeting(); } catch { /* already left */ }
    window.location.href = to;
};

onMounted(async () => {
    // Moving name watermark (a leaked recording shows who recorded it).
    mover = setInterval(() => { mark.value = { top: 10 + Math.random() * 70, left: 5 + Math.random() * 55 }; }, 25000);

    try {
        const { default: ZoomMtgEmbedded } = await import('@zoom/meetingsdk/embedded');
        client = ZoomMtgEmbedded.createClient();
        await client.init({
            zoomAppRoot: root.value,
            language: 'en-US',
            patchJsMedia: true,
            leaveOnPageUnload: true,
            customize: { video: { isResizable: true, viewSizes: { default: { width: Math.min(1100, root.value.clientWidth), height: Math.round(Math.min(1100, root.value.clientWidth) * 9 / 16) } } } },
        });
        const options = {
            sdkKey: props.zoom.sdkKey,
            signature: props.zoom.signature,
            meetingNumber: props.zoom.meetingNumber,
            password: props.zoom.password,
            userName: props.zoom.userName,
            customerKey: props.zoom.customerKey,
        };
        if (props.zoom.userEmail) options.userEmail = props.zoom.userEmail;
        if (props.zoom.zak) options.zak = props.zoom.zak;
        await client.join(options);
        state.value = 'joined';

        client.on('connection-change', (payload) => {
            if (payload?.state === 'Closed') leave();
        });
    } catch (e) {
        state.value = 'error';
        error.value = e?.reason || e?.message || 'Could not join the class.';
    }

    // Single device: if this account logs in elsewhere, this browser leaves the class.
    heartbeat = setInterval(async () => {
        try {
            const res = await axios.get(route('student.classes.heartbeat'));
            if (res.status !== 204) leave(route('login'));
        } catch {
            leave(route('login'));
        }
    }, 30000);
});

onBeforeUnmount(() => {
    clearInterval(heartbeat);
    clearInterval(mover);
});
</script>

<template>
    <Head :title="session.title" />
    <div class="flex min-h-screen flex-col bg-[#0A1426] text-white">
        <header class="flex flex-wrap items-center justify-between gap-3 bg-lwe-navy px-4 py-3 sm:px-6">
            <div class="min-w-0">
                <p class="truncate text-xs text-slate-300">{{ session.course }} · {{ session.batch }}</p>
                <h1 class="truncate text-base font-semibold">{{ session.title }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <span v-if="state === 'joined'" class="flex items-center gap-2 rounded-full bg-red-600 px-3 py-1 text-xs font-bold">
                    <span class="h-2 w-2 rounded-full bg-white" /> LIVE
                </span>
                <span v-if="role === 0 && state === 'joined'" class="rounded-full bg-green-900 px-3 py-1 text-xs font-semibold text-green-100">Attendance recording</span>
                <span v-if="role === 1" class="rounded-full bg-lwe-orange px-3 py-1 text-xs font-bold text-lwe-navy">HOST</span>
                <button type="button" class="h-11 rounded-lg bg-red-700 px-4 text-sm font-bold hover:bg-red-800" @click="leave()">Leave class</button>
            </div>
        </header>

        <main class="relative flex flex-1 items-center justify-center overflow-hidden p-3 sm:p-6">
            <div ref="root" class="relative w-full max-w-[1100px]" />

            <div v-if="state === 'loading'" class="absolute inset-0 flex flex-col items-center justify-center gap-3">
                <div class="h-10 w-10 animate-spin rounded-full border-4 border-white/20 border-t-lwe-orange" />
                <p class="text-sm text-slate-300">Joining your class…</p>
            </div>
            <div v-if="state === 'error'" role="alert" class="absolute inset-0 flex flex-col items-center justify-center gap-4 p-6 text-center">
                <p class="text-lg font-semibold">Could not join the class</p>
                <p class="max-w-md text-sm text-slate-300">{{ error }}</p>
                <div class="flex gap-2">
                    <button type="button" class="btn-primary" @click="reload">Try again</button>
                    <a :href="backUrl" class="btn-outline">Back</a>
                </div>
            </div>

            <!-- Watermark: a deterrent in the browser; the LWE app (Module 5) blocks screen recording fully -->
            <span v-if="state === 'joined'" aria-hidden="true"
                class="pointer-events-none absolute z-[9999] -rotate-12 select-none whitespace-nowrap text-lg font-semibold text-white/20 transition-all duration-1000"
                :style="{ top: mark.top + '%', left: mark.left + '%' }">{{ watermark }}</span>
        </main>
    </div>
</template>
