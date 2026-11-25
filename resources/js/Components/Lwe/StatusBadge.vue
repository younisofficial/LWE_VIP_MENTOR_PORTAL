<script setup>
import { computed } from 'vue';

const props = defineProps({ status: { type: String, required: true } });

const map = {
    active: ['Active', 'bg-lwe-greenSoft text-green-900'],
    approved: ['Approved', 'bg-lwe-greenSoft text-green-900'],
    published: ['Published', 'bg-lwe-greenSoft text-green-900'],
    running: ['Running', 'bg-lwe-greenSoft text-green-900'],
    completed: ['Completed', 'bg-blue-100 text-blue-900'],
    pending: ['Pending', 'bg-lwe-orangeSoft text-amber-900'],
    pending_payment: ['Payment pending', 'bg-lwe-orangeSoft text-amber-900'],
    upcoming: ['Upcoming', 'bg-lwe-orangeSoft text-amber-900'],
    draft: ['Draft', 'bg-slate-200 text-slate-800'],
    private: ['Private', 'bg-slate-200 text-slate-800'],
    suspended: ['Suspended', 'bg-red-100 text-red-900'],
    rejected: ['Rejected', 'bg-red-100 text-red-900'],
    cancelled: ['Cancelled', 'bg-red-100 text-red-900'],
    refunded: ['Refunded', 'bg-slate-200 text-slate-800'],
    success: ['Success', 'bg-lwe-greenSoft text-green-900'],
    blocked_device: ['Blocked device', 'bg-red-100 text-red-900'],
};

const entry = computed(() => map[props.status] ?? [props.status, 'bg-slate-200 text-slate-800']);
</script>

<template>
    <span class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="entry[1]">
        {{ entry[0] }}
    </span>
</template>
