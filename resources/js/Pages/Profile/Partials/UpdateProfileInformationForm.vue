<script setup>
// Replaces Breeze's version: phone shown (read-only), email optional, city + WhatsApp reminders.
import { useForm, usePage } from '@inertiajs/vue3';
import Field from '@/Components/Lwe/Field.vue';

defineProps({ mustVerifyEmail: Boolean, status: String });
const user = usePage().props.auth.user;
const form = useForm({ name: user.name, email: user.email ?? '', city: user.city ?? '', whatsapp_opt_in: user.whatsapp_opt_in ?? true });
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">Profile information</h2>
            <p class="mt-1 text-sm text-gray-600">Your Student ID is <b>{{ user.student_code }}</b>. To change your phone number, contact support.</p>
        </header>
        <form class="mt-6 space-y-4" @submit.prevent="form.patch(route('profile.update'))">
            <Field label="Name" for="name" :error="form.errors.name"><input id="name" v-model="form.name" type="text" class="input" required /></Field>
            <Field label="Phone"><input :value="user.phone" type="text" class="input bg-slate-100" disabled /></Field>
            <Field label="Email (optional)" for="email" :error="form.errors.email"><input id="email" v-model="form.email" type="email" class="input" /></Field>
            <Field label="City" for="city" :error="form.errors.city"><input id="city" v-model="form.city" type="text" class="input" /></Field>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.whatsapp_opt_in" type="checkbox" class="rounded" /> Send me class reminders on WhatsApp</label>
            <div class="flex items-center gap-4">
                <button type="submit" class="btn-primary" :disabled="form.processing">Save</button>
                <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">Saved.</p>
            </div>
        </form>
    </section>
</template>
