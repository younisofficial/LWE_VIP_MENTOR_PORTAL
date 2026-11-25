<script setup>
// "Install app" button: Android/desktop Chrome use the browser's install prompt;
// iPhone shows how to add it from Safari's Share menu.
import { onBeforeUnmount, onMounted, ref } from 'vue';

const can = ref(false);
const ios = ref(false);
const help = ref(false);
const standalone = () => window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true;

const update = () => { can.value = !!window.__lweInstall && !standalone(); };
onMounted(() => {
    update();
    window.addEventListener('lwe-installable', update);
    window.addEventListener('appinstalled', update);
    ios.value = /iphone|ipad|ipod/i.test(navigator.userAgent) && !standalone();
});
onBeforeUnmount(() => { window.removeEventListener('lwe-installable', update); window.removeEventListener('appinstalled', update); });

const install = async () => {
    if (ios.value) { help.value = !help.value; return; }
    const e = window.__lweInstall;
    if (!e) return;
    e.prompt();
    await e.userChoice.catch(() => null);
    window.__lweInstall = null;
    update();
};
</script>

<template>
    <div v-if="can || ios" class="relative">
        <button type="button" class="hidden h-11 items-center gap-2 rounded-lg bg-lwe-orange px-3 text-sm font-semibold text-lwe-navy hover:brightness-95 sm:inline-flex" @click="install">📲 Install app</button>
        <button type="button" class="flex h-11 w-11 items-center justify-center rounded-lg bg-lwe-orange text-lg sm:hidden" aria-label="Install the app" @click="install">📲</button>
        <div v-if="help" class="absolute right-0 mt-2 w-72 rounded-xl border border-lwe-line bg-white p-4 text-sm shadow-lg" role="dialog" aria-label="How to install">
            <p class="font-semibold text-lwe-navy">Install on iPhone</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-slate-700"><li>Open this page in <b>Safari</b></li><li>Tap the <b>Share</b> button (square with arrow)</li><li>Tap <b>Add to Home Screen</b></li></ol>
        </div>
    </div>
</template>
