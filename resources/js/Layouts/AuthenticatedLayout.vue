<script setup>
// Breeze pages (e.g. Profile) use this layout; it now renders inside the LWE app shell.
import AppLayout from '@/Layouts/AppLayout.vue';
</script>

<template>
    <AppLayout title="My profile">
        <div v-if="$slots.header" class="mb-6"><slot name="header" /></div>
        <slot />
    </AppLayout>
</template>
