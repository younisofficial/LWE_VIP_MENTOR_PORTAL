<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-lwe-navy px-4 py-10">
        <Link href="/" class="mb-6 flex items-center gap-3 text-white">
            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-lwe-orange font-bold text-lwe-navy">LWE</span>
            <span class="flex flex-col leading-tight">
                <span class="text-lg font-semibold">Learning Portal</span>
                <span class="text-xs text-slate-300">Learning with Earning</span>
            </span>
        </Link>
        <div class="w-full max-w-md rounded-2xl bg-white px-6 py-8 shadow-xl sm:px-8">
            <slot />
        </div>
    </div>
</template>
