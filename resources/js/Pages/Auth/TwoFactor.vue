<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Field from '@/Components/Lwe/Field.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const form = useForm({ code: '' });
const status = usePage().props.flash?.status;
</script>

<template>
    <GuestLayout>
        <Head title="Verify login" />
        <h1 class="text-xl font-semibold text-lwe-navy">Admin login check</h1>
        <p class="muted mt-1">We sent a 6-digit code to your WhatsApp/email.</p>
        <div v-if="status" class="mt-3 rounded-lg bg-lwe-greenSoft px-4 py-3 text-sm text-green-900">{{ status }}</div>

        <form class="mt-6 space-y-4" @submit.prevent="form.post(route('two-factor.verify'))">
            <Field label="Code" for="code" :error="form.errors.code">
                <input id="code" v-model="form.code" type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                    class="input text-center text-lg tracking-[0.5em]" required autofocus />
            </Field>
            <button type="submit" class="btn-primary w-full" :disabled="form.processing">Verify</button>
        </form>
        <div class="mt-6 flex justify-between text-sm">
            <Link :href="route('two-factor.resend')" method="post" as="button" class="font-semibold text-lwe-navy2 hover:underline">Resend code</Link>
            <Link :href="route('logout')" method="post" as="button" class="text-slate-600 hover:underline">Log out</Link>
        </div>
    </GuestLayout>
</template>
