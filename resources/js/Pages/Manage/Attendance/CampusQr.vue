<script setup>
// Full-screen rotating QR for the campus reception TV / projector.
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import QRCode from 'qrcode';

const props = defineProps({ campus: String, seconds: Number });

const img = ref('');
const left = ref(0);
const count = ref(0);
const error = ref('');
const clock = ref('');
let refreshTimer;
let tick;

const load = async () => {
    try {
        const { data } = await axios.get(route('manage.attendance.qr-token'));
        img.value = await QRCode.toDataURL(data.url, { width: 640, margin: 1, errorCorrectionLevel: 'M' });
        left.value = data.expires_in;
        count.value = data.today_count;
        error.value = '';
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(load, Math.max(2, data.expires_in) * 1000);
    } catch {
        error.value = 'Could not load the code. Check the internet — retrying…';
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(load, 5000);
    }
};

onMounted(() => {
    load();
    tick = setInterval(() => {
        left.value = Math.max(0, left.value - 1);
        clock.value = new Date().toLocaleTimeString('en-PK', { hour: 'numeric', minute: '2-digit' });
    }, 1000);
});
onBeforeUnmount(() => { clearTimeout(refreshTimer); clearInterval(tick); });
</script>

<template>
    <Head title="Campus attendance QR" />
    <main class="flex min-h-screen flex-col items-center justify-center gap-6 bg-lwe-navy p-6 text-center text-white">
        <div>
            <p class="text-sm uppercase tracking-widest text-lwe-orange">Learning with Earning · {{ campus }}</p>
            <h1 class="mt-2 text-3xl font-bold sm:text-4xl">Scan to mark your attendance</h1>
            <p class="mt-2 text-white/80">Open your phone camera → scan → log in if asked. The code changes every {{ seconds }} seconds.</p>
        </div>
        <div class="rounded-3xl bg-white p-5 shadow-2xl">
            <img v-if="img" :src="img" alt="Attendance QR code — scan with your phone camera" class="h-[min(70vh,520px)] w-[min(70vh,520px)]" />
            <div v-else class="flex h-80 w-80 items-center justify-center text-lwe-navy">Loading…</div>
        </div>
        <p v-if="error" class="font-semibold text-lwe-orange" role="alert">{{ error }}</p>
        <div class="flex items-center gap-8 text-lg">
            <p>New code in <b class="tabular-nums">{{ left }}s</b></p>
            <p>Marked today: <b class="tabular-nums">{{ count }}</b></p>
            <p class="tabular-nums">{{ clock }}</p>
        </div>
        <Link :href="route('manage.attendance.index')" class="text-sm text-white/60 underline hover:text-white">Back to attendance</Link>
    </main>
</template>
