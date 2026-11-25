<script setup>
// Small "AI" pill shown next to a field label: fills just that field.
defineProps({ loading: Boolean, label: { type: String, default: 'AI' } });
</script>

<template>
    <button type="button" :disabled="loading" :aria-label="'Fill with AI'"
        class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2.5 py-1 text-[11px] font-bold text-violet-800 hover:bg-violet-200 disabled:opacity-60">
        <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z" /></svg>
        {{ loading ? '…' : label }}
    </button>
</template>
