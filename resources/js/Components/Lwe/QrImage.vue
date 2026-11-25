<script setup>
// QR code for a link (verify pages). Uses the "qrcode" package installed in Module 3.
import { ref, watchEffect } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({ value: String, size: { type: Number, default: 160 }, alt: { type: String, default: 'QR code to verify' } });
const src = ref('');
watchEffect(async () => { src.value = props.value ? await QRCode.toDataURL(props.value, { width: props.size * 2, margin: 1 }) : ''; });
</script>

<template>
    <img v-if="src" :src="src" :width="size" :height="size" :alt="alt" />
</template>
