<script setup>
// Simple layout for pages anyone can open (mentor pages, Skill Passport, certificate check).
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps({ title: { type: String, default: '' } });
const user = usePage().props.auth?.user;
</script>

<template>
    <Head :title="title" />
    <div class="min-h-screen bg-lwe-bg">
        <header class="bg-lwe-navy text-white print:hidden">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4">
                <a href="https://learningwithearning.org" class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-lwe-orange text-sm font-bold text-lwe-navy">LWE</span>
                    <span class="font-semibold">Learning with Earning</span>
                </a>
                <nav class="flex items-center gap-4 text-sm">
                    <Link :href="route('mentors.index')" class="hover:underline">Mentors</Link>
                    <Link v-if="user" :href="route('dashboard')" class="rounded-lg bg-lwe-orange px-4 py-2 font-semibold text-lwe-navy">My portal</Link>
                    <Link v-else :href="route('login')" class="rounded-lg bg-lwe-orange px-4 py-2 font-semibold text-lwe-navy">Log in</Link>
                </nav>
            </div>
        </header>
        <main class="mx-auto max-w-6xl px-4 py-8 print:max-w-none print:p-0"><slot /></main>
        <footer class="py-8 text-center text-xs text-lwe-muted print:hidden">© Learning with Earning · Multan, Pakistan</footer>
    </div>
</template>
