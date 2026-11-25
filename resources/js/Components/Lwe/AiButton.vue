<script setup>
// Purple "AI" button used next to every AI feature.
defineProps({ loading: Boolean, label: { type: String, default: 'Write with AI' } });
</script>

<template>
    <button type="button" :disabled="loading"
        class="inline-flex h-10 items-center gap-2 rounded-lg bg-violet-700 px-4 text-sm font-semibold text-white hover:bg-violet-800 disabled:opacity-60">
        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z" /><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z" /></svg>
        {{ loading ? 'AI is writing…' : label }}
    </button>
</template>
