<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Field from '@/Components/Lwe/Field.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ status: String });
const form = useForm({ login: '' });
</script>

<template>
    <GuestLayout>
        <Head title="Forgot password" />
        <h1 class="text-xl font-semibold text-lwe-navy">Forgot your password?</h1>
        <p class="muted mt-1">Enter your phone number or email. We will send a 6-digit code on WhatsApp (or email).</p>

        <form class="mt-6 space-y-4" @submit.prevent="form.post(route('password.otp.send'))">
            <Field label="Phone number or email" for="login" :error="form.errors.login">
                <input id="login" v-model="form.login" type="text" class="input" placeholder="0300 1234567" required autofocus />
            </Field>
            <button type="submit" class="btn-primary w-full" :disabled="form.processing">Send code</button>
        </form>
        <p class="mt-6 text-center text-sm"><Link :href="route('login')" class="font-semibold text-lwe-navy2 hover:underline">Back to log in</Link></p>
    </GuestLayout>
</template>
