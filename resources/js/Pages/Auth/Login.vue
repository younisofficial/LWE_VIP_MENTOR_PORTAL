<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Field from '@/Components/Lwe/Field.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ canResetPassword: Boolean, status: String });

const form = useForm({ login: '', password: '', remember: false });
const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />
        <h1 class="text-xl font-semibold text-lwe-navy">Welcome back</h1>
        <p class="muted mt-1">Log in with your phone number or email.</p>

        <div v-if="status" class="mt-4 rounded-lg bg-lwe-greenSoft px-4 py-3 text-sm font-medium text-green-900">{{ status }}</div>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <Field label="Phone number or email" for="login" :error="form.errors.login">
                <input id="login" v-model="form.login" type="text" class="input" autocomplete="username" placeholder="0300 1234567" required autofocus />
            </Field>
            <Field label="Password" for="password" :error="form.errors.password">
                <input id="password" v-model="form.password" type="password" class="input" autocomplete="current-password" required />
            </Field>
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.remember" type="checkbox" class="rounded border-slate-300" /> Remember me
                </label>
                <Link v-if="canResetPassword" :href="route('password.otp.request')" class="text-sm font-semibold text-lwe-navy2 hover:underline">Forgot password?</Link>
            </div>
            <button type="submit" class="btn-primary w-full" :disabled="form.processing">Log in</button>
            <p class="text-center text-xs text-lwe-muted">One account works on one device at a time. Logging in here logs you out elsewhere.</p>
        </form>

        <p class="mt-6 text-center text-sm text-slate-600">
            New student? <Link :href="route('register')" class="font-semibold text-lwe-navy2 hover:underline">Create an account</Link>
        </p>
    </GuestLayout>
</template>
