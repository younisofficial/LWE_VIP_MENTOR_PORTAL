<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from '@/Components/Lwe/Dialog.vue';
import Field from '@/Components/Lwe/Field.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';

defineProps({ mentors: Array });
const show = ref(false);
const form = useForm({ name: '', phone: '', email: '', password: '' });
</script>

<template>
    <AppLayout title="Mentors">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><h1 class="page-title">Mentors</h1><p class="muted">Mentor accounts and their public landing pages. Assign courses to a mentor in the course wizard.</p></div>
            <div class="flex gap-2"><a :href="route('mentors.index')" target="_blank" class="btn-outline">Public mentors page</a><button type="button" class="btn-primary" @click="form.reset(); show = true">+ Add mentor</button></div>
        </div>
        <EmptyState v-if="!mentors.length" title="No mentors yet" />
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div v-for="m in mentors" :key="m.id" class="card flex gap-4">
                <img v-if="m.photo" :src="m.photo" :alt="m.name" class="h-20 w-20 rounded-xl object-cover" />
                <span v-else class="flex h-20 w-20 shrink-0 items-center justify-center rounded-xl bg-lwe-navy text-2xl font-bold text-white">{{ m.name[0] }}</span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2"><p class="font-semibold text-lwe-navy">{{ m.name }}</p>
                        <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="m.published ? 'bg-lwe-greenSoft text-green-900' : 'bg-slate-200 text-slate-700'">{{ m.published ? 'Page live' : 'Page hidden' }}</span></div>
                    <p class="text-xs text-lwe-muted">{{ m.phone }}<template v-if="m.email"> · {{ m.email }}</template></p>
                    <p class="mt-1 truncate text-xs">{{ m.courses.join(', ') || 'No courses yet' }}</p>
                    <div class="mt-3 flex gap-2"><Link :href="route('manage.mentors.edit', m.id)" class="btn-dark btn-sm">Edit page</Link><a v-if="m.url" :href="m.url" target="_blank" class="btn-outline btn-sm">View</a></div>
                </div>
            </div>
        </div>

        <Dialog :show="show" title="Add mentor" @close="show = false">
            <form class="space-y-4" @submit.prevent="form.post(route('admin.mentors.store'))">
                <Field label="Full name" for="mn" :error="form.errors.name"><input id="mn" v-model="form.name" type="text" class="input" required /></Field>
                <Field label="Mobile number (login)" for="mp" :error="form.errors.phone"><input id="mp" v-model="form.phone" type="tel" class="input" required placeholder="0300 1234567" /></Field>
                <Field label="Email (optional)" for="me" :error="form.errors.email"><input id="me" v-model="form.email" type="email" class="input" /></Field>
                <Field label="First password (share it privately)" for="mpw" :error="form.errors.password" hint="At least 8 letters. The mentor can change it from Profile."><input id="mpw" v-model="form.password" type="text" class="input" required autocomplete="new-password" /></Field>
                <div class="flex justify-end gap-2 border-t border-lwe-line pt-4"><button type="button" class="btn-outline" @click="show = false">Cancel</button><button type="submit" class="btn-primary" :disabled="form.processing">Add mentor</button></div>
            </form>
        </Dialog>
    </AppLayout>
</template>
