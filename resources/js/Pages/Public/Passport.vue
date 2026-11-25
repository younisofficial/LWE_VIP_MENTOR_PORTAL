<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PassportView from '@/Components/Lwe/PassportView.vue';

const props = defineProps({ student: Object, verified_skills: Array, learning_skills: Array, courses: Array, stats: Object, badges: Array, verifyUrl: String, isOwner: Boolean });
const print = () => window.print();
</script>

<template>
    <PublicLayout :title="`${student.name} — Skill Passport`">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
            <p class="rounded-full bg-lwe-greenSoft px-4 py-2 text-sm font-bold text-green-900">✔ Verified by Learning with Earning — data comes straight from the portal</p>
            <button type="button" class="btn-primary" @click="print">Download / Print (PDF)</button>
        </div>
        <PassportView v-bind="props" />
    </PublicLayout>
</template>
