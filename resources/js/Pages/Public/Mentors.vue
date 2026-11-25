<script setup>
import { Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps({ mentors: Array });
</script>

<template>
    <PublicLayout title="Our mentors">
        <h1 class="page-title">Our mentors</h1>
        <p class="muted mb-6">Learn from people who do the work every day.</p>
        <p v-if="!mentors.length" class="card text-sm text-lwe-muted">Mentor pages are coming soon.</p>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <Link v-for="m in mentors" :key="m.slug" :href="route('mentors.show', m.slug)" class="card block hover:ring-2 hover:ring-lwe-orange">
                <img v-if="m.photo" :src="m.photo" :alt="m.name" class="mb-4 aspect-square w-full rounded-xl object-cover" loading="lazy" />
                <div v-else class="mb-4 flex aspect-square w-full items-center justify-center rounded-xl bg-lwe-navy text-5xl font-bold text-white">{{ m.name[0] }}</div>
                <h2 class="text-lg font-bold text-lwe-navy">{{ m.name }}</h2>
                <p class="text-sm text-slate-600">{{ m.headline }}</p>
                <div class="mt-3 flex flex-wrap gap-1"><span v-for="e in m.expertise" :key="e" class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-lwe-navy">{{ e }}</span></div>
            </Link>
        </div>
    </PublicLayout>
</template>
