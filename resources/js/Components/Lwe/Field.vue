<script setup>
// Label + slot + error message. With `ai`, a small AI button sits next to the label.
import AiMini from '@/Components/Lwe/AiMini.vue';

defineProps({ label: String, error: String, hint: String, for: String, ai: Boolean, aiLoading: Boolean });
defineEmits(['ai']);
</script>

<template>
    <div>
        <div v-if="label" class="mb-1 flex items-center justify-between gap-2">
            <label :for="$props.for" class="label mb-0">{{ label }}</label>
            <AiMini v-if="ai" :loading="aiLoading" @click="$emit('ai')" />
        </div>
        <slot />
        <p v-if="hint && !error" class="mt-1 text-xs text-lwe-muted">{{ hint }}</p>
        <p v-if="error" class="mt-1 text-xs font-medium text-red-700">{{ error }}</p>
    </div>
</template>
