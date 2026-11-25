<script setup>
// Simple accessible modal dialog.
// While open, the page behind it does not scroll. The lock is always removed when the dialog
// closes OR disappears (e.g. saving it opens another page) — otherwise the next page could not scroll.
import { onMounted, onBeforeUnmount, watch } from 'vue';

const props = defineProps({ show: Boolean, title: String, wide: Boolean });
const emit = defineEmits(['close']);

const lock = (on) => { document.body.style.overflow = on ? 'hidden' : ''; };
const onKey = (e) => { if (e.key === 'Escape' && props.show) emit('close'); };
onMounted(() => { document.addEventListener('keydown', onKey); if (props.show) lock(true); });
onBeforeUnmount(() => { document.removeEventListener('keydown', onKey); lock(false); });
watch(() => props.show, lock);
</script>

<template>
    <Teleport to="body">
        <!-- The outer layer scrolls; my-auto centres short dialogs and lets tall ones scroll top to bottom. -->
        <div v-if="show" class="fixed inset-0 z-50 flex justify-center overflow-y-auto bg-slate-900/50 p-4" @click.self="emit('close')">
            <div role="dialog" aria-modal="true" :aria-label="title"
                class="my-auto h-fit w-full rounded-2xl bg-white shadow-xl" :class="wide ? 'max-w-3xl' : 'max-w-lg'">
                <div class="flex items-center justify-between border-b border-lwe-line px-6 py-4">
                    <h2 class="text-lg font-semibold text-lwe-navy">{{ title }}</h2>
                    <button type="button" class="rounded-lg p-2 hover:bg-slate-100" aria-label="Close" @click="emit('close')">✕</button>
                </div>
                <div class="px-6 py-5"><slot /></div>
            </div>
        </div>
    </Teleport>
</template>
