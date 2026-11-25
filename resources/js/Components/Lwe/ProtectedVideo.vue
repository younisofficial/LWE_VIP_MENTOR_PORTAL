<script setup>
// Video player with a moving name/ID watermark, no right-click, and a cover over the YouTube
// title/logo links. It cannot stop a phone camera, but a leaked copy shows who recorded it.
//
// YouTube uses the official IFrame Player API, so the portal can:
//  - start the video itself (recorded lectures), and fall back to muted autoplay + "Tap to unmute"
//    when the browser blocks sound,
//  - show the real reason when YouTube refuses a video (private, embedding off, wrong link).
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    url: { type: String, required: true },
    title: { type: String, default: 'Video' },
    startAt: { type: Number, default: 0 },     // seconds (recorded lecture joined in the middle)
    live: { type: Boolean, default: false },   // lecture mode: no seeking, follows the class clock
});
const emit = defineEmits(['started']);

// ---- watermark ----
const user = computed(() => usePage().props.auth?.user);
const mark = computed(() => (user.value ? `${user.value.name} · ${user.value.student_code ?? ''} · ${user.value.phone ?? ''}` : ''));
const pos = ref({ top: 20, left: 15 });
let mover;

// ---- source ----
const source = computed(() => {
    const u = (props.url || '').trim();
    const yt = u.match(/(?:youtu\.be\/|[?&]v=|\/embed\/|\/shorts\/|\/live\/)([\w-]{11})/);
    if (yt) return { type: 'youtube', id: yt[1] };
    const vimeo = u.match(/vimeo\.com\/(?:video\/)?(\d+)(?:\/(\w+))?/);
    if (vimeo) {
        const p = new URLSearchParams({ title: '0', byline: '0', portrait: '0', dnt: '1', autoplay: props.live ? '1' : '0' });
        if (vimeo[2]) p.set('h', vimeo[2]);
        const s = Math.max(0, Math.floor(props.startAt));
        return { type: 'iframe', src: `https://player.vimeo.com/video/${vimeo[1]}?${p}${s ? `#t=${s}s` : ''}` };
    }
    if (/\.(mp4|webm)(\?|$)/i.test(u)) return { type: 'video', src: u };
    return { type: 'link' };
});

// ---- YouTube player ----
const box = ref(null);
const started = ref(!props.live);
const error = ref('');
const muted = ref(false);
const paused = ref(false);
let player = null;
let openedAt = Date.now();
const lectureSecond = () => Math.max(0, Math.floor(props.startAt + (Date.now() - openedAt) / 1000));

const reasons = {
    2: 'This YouTube link is not correct. Ask your mentor to check the lesson link.',
    5: 'Your browser could not play this video. Try Chrome, or update your browser.',
    100: 'This video was removed or is Private on YouTube. Your mentor must set it to "Unlisted".',
    101: 'The video owner has turned off playing on other websites. In YouTube Studio → video → "Allow embedding" must be ON.',
    150: 'The video owner has turned off playing on other websites. In YouTube Studio → video → "Allow embedding" must be ON.',
    153: 'YouTube blocked the player on this address. Open the portal at its normal address (e.g. http://127.0.0.1:8000 or the https site) and try again.',
};

let apiPromise = null;
const loadApi = () => {
    if (window.YT?.Player) return Promise.resolve();
    if (apiPromise) return apiPromise;
    apiPromise = new Promise((resolve, reject) => {
        const prev = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = () => { prev?.(); resolve(); };
        const s = document.createElement('script');
        s.src = 'https://www.youtube.com/iframe_api';
        s.onerror = () => reject(new Error('api'));
        document.head.appendChild(s);
    });
    return apiPromise;
};

const makePlayer = async () => {
    try {
        await loadApi();
    } catch {
        error.value = 'Could not reach YouTube. Check the internet and refresh the page.';
        return;
    }
    await nextTick();
    if (!box.value) return;
    player = new window.YT.Player(box.value, {
        videoId: source.value.id,
        host: 'https://www.youtube-nocookie.com',
        width: '100%',
        height: '100%',
        playerVars: {
            rel: 0, modestbranding: 1, playsinline: 1, iv_load_policy: 3, fs: 1,
            controls: props.live ? 0 : 1, disablekb: props.live ? 1 : 0,
            start: props.live ? lectureSecond() : 0,
            origin: window.location.origin,
        },
        events: {
            onReady: (e) => {
                if (!props.live) return;
                e.target.seekTo(lectureSecond(), true);
                e.target.playVideo();
                // Browser blocked sound? Play muted and offer "Tap to unmute".
                setTimeout(() => {
                    if (player && player.getPlayerState?.() !== window.YT.PlayerState.PLAYING) {
                        player.mute();
                        player.playVideo();
                        muted.value = true;
                    }
                }, 1500);
            },
            onStateChange: (e) => {
                if (!props.live) return;
                paused.value = e.data === window.YT.PlayerState.PAUSED;
            },
            onError: (e) => { error.value = reasons[e.data] ?? `YouTube could not play this video (error ${e.data}).`; },
        },
    });
};

const start = () => {
    started.value = true;
    emit('started');
    if (source.value.type === 'youtube') makePlayer();
};
const unmute = () => { player?.unMute(); player?.setVolume(100); muted.value = false; };
// Lecture mode: "Resume" jumps back to where the class is now.
const resume = () => { player?.seekTo(lectureSecond(), true); player?.playVideo(); };

onMounted(() => {
    openedAt = Date.now();
    mover = setInterval(() => { pos.value = { top: 8 + Math.random() * 72, left: 4 + Math.random() * 56 }; }, 20000);
    if (started.value && source.value.type === 'youtube') makePlayer();
});
onBeforeUnmount(() => { clearInterval(mover); try { player?.destroy(); } catch { /* already gone */ } });

const onVideoLoaded = (e) => {
    if (props.live) { e.target.currentTime = lectureSecond(); e.target.play().catch(() => { e.target.muted = true; muted.value = true; e.target.play(); }); }
};
</script>

<template>
    <div class="relative aspect-video select-none overflow-hidden rounded-xl bg-black" @contextmenu.prevent>
        <template v-if="started">
            <div v-if="source.type === 'youtube'" class="absolute inset-0 [&>iframe]:h-full [&>iframe]:w-full"><div ref="box" /></div>
            <iframe v-else-if="source.type === 'iframe'" :src="source.src" class="h-full w-full" :title="title"
                allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" />
            <video v-else-if="source.type === 'video'" :src="source.src" class="h-full w-full" :controls="!live" controlsList="nodownload noplaybackrate" disablePictureInPicture playsinline @loadedmetadata="onVideoLoaded" />
            <div v-else class="flex h-full items-center justify-center p-6 text-center text-white">This video link can't be played here. Ask your mentor to use a YouTube (Unlisted) or Vimeo link.</div>

            <!-- Covers YouTube's title/logo links; in lecture mode the whole video (no seeking). -->
            <div v-if="source.type !== 'link' && !error" class="absolute inset-x-0 top-0 h-14" aria-hidden="true" />
            <div v-if="live && !error" class="absolute inset-0" aria-hidden="true" />

            <div v-if="error" class="absolute inset-0 flex items-center justify-center bg-black/90 p-6 text-center text-sm text-white" role="alert">{{ error }}</div>
            <div v-if="live && !error && (muted || paused)" class="absolute inset-x-0 bottom-4 z-10 flex justify-center gap-2">
                <button v-if="muted" type="button" class="rounded-full bg-lwe-orange px-5 py-2.5 font-semibold text-lwe-navy shadow-lg" @click="unmute">🔇 Tap to unmute</button>
                <button v-if="paused" type="button" class="rounded-full bg-white px-5 py-2.5 font-semibold text-lwe-navy shadow-lg" @click="resume">▶ Resume lecture</button>
            </div>
        </template>
        <button v-else type="button" class="flex h-full w-full flex-col items-center justify-center gap-3 text-white" @click="start">
            <span class="flex h-20 w-20 items-center justify-center rounded-full bg-lwe-orange text-3xl text-lwe-navy">▶</span>
            <span class="text-lg font-semibold">{{ startAt > 30 ? 'Join — the lecture is already playing' : 'Start the lecture' }}</span>
        </button>
        <div v-if="mark" class="pointer-events-none absolute z-20 whitespace-nowrap text-sm font-bold text-white/30 transition-all duration-1000 sm:text-base"
            :style="{ top: pos.top + '%', left: pos.left + '%', transform: 'rotate(-12deg)' }" aria-hidden="true">{{ mark }}</div>
    </div>
</template>
