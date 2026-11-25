<script setup>
defineProps({ title: String, text: String });
</script>

<template>
    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
        <p class="font-semibold text-lwe-navy">{{ title }}</p>
        <p v-if="text" class="mt-1 text-sm text-lwe-muted">{{ text }}</p>
        <div class="mt-4"><slot /></div>
    </div>
</template>
