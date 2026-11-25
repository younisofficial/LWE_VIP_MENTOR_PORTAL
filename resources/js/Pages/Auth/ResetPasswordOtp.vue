<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Field from '@/Components/Lwe/Field.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ status: String });
const form = useForm({ code: '', password: '', password_confirmation: '' });
const submit = () => form.post(route('password.otp.update'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <GuestLayout>
        <Head title="Set a new password" />
        <h1 class="text-xl font-semibold text-lwe-navy">Enter the code</h1>
        <div v-if="status" class="mt-3 rounded-lg bg-lwe-greenSoft px-4 py-3 text-sm text-green-900">{{ status }}</div>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <Field label="6-digit code" for="code" :error="form.errors.code">
                <input id="code" v-model="form.code" type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                    class="input text-center text-lg tracking-[0.5em]" required autofocus />
            </Field>
            <Field label="New password" for="password" :error="form.errors.password">
                <input id="password" v-model="form.password" type="password" class="input" autocomplete="new-password" required />
            </Field>
            <Field label="Confirm new password" for="password_confirmation" :error="form.errors.password_confirmation">
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="input" autocomplete="new-password" required />
            </Field>
            <button type="submit" class="btn-primary w-full" :disabled="form.processing">Save new password</button>
        </form>
        <p class="mt-6 text-center text-sm"><Link :href="route('password.otp.request')" class="font-semibold text-lwe-navy2 hover:underline">Send a new code</Link></p>
    </GuestLayout>
</template>
