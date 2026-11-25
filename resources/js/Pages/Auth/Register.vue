<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Field from '@/Components/Lwe/Field.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({ name: '', phone: '', email: '', city: '', password: '', password_confirmation: '' });
const submit = () => form.post(route('register'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <GuestLayout>
        <Head title="Create account" />
        <h1 class="text-xl font-semibold text-lwe-navy">Create your student account</h1>
        <p class="muted mt-1">Use your WhatsApp number — class reminders come there.</p>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <Field label="Full name" for="name" :error="form.errors.name">
                <input id="name" v-model="form.name" type="text" class="input" autocomplete="name" required autofocus />
            </Field>
            <Field label="WhatsApp number" for="phone" :error="form.errors.phone">
                <input id="phone" v-model="form.phone" type="tel" class="input" placeholder="0300 1234567" autocomplete="tel" required />
            </Field>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Field label="Email (optional)" for="email" :error="form.errors.email">
                    <input id="email" v-model="form.email" type="email" class="input" autocomplete="email" />
                </Field>
                <Field label="City (optional)" for="city" :error="form.errors.city">
                    <input id="city" v-model="form.city" type="text" class="input" autocomplete="address-level2" />
                </Field>
            </div>
            <Field label="Password" for="password" :error="form.errors.password">
                <input id="password" v-model="form.password" type="password" class="input" autocomplete="new-password" required />
            </Field>
            <Field label="Confirm password" for="password_confirmation" :error="form.errors.password_confirmation">
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="input" autocomplete="new-password" required />
            </Field>
            <button type="submit" class="btn-primary w-full" :disabled="form.processing">Create account</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-600">
            Already registered? <Link :href="route('login')" class="font-semibold text-lwe-navy2 hover:underline">Log in</Link>
        </p>
    </GuestLayout>
</template>
