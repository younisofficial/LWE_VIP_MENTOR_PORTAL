<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Field from '@/Components/Lwe/Field.vue';

defineProps({ count: Number, last: String, report: Object });
const form = useForm({ file: null });
</script>

<template>
    <AppLayout title="Old Skill Passports">
        <h1 class="page-title">Bring in old Skill Passport IDs</h1>
        <p class="muted mb-6">Students who already have a passport ID from the website keep the same ID and link. New IDs always start after the highest one.</p>
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <form class="card space-y-4 lg:col-span-2" @submit.prevent="form.post(route('admin.passports.import'), { forceFormData: true })">
                <ol class="list-decimal space-y-1 pl-5 text-sm text-slate-700">
                    <li>Download the template and fill one row per old passport: <b>passport_code, name, phone, email</b>.</li>
                    <li>A student with the same phone gets that ID. Anyone not in the portal yet gets a new account (they set a password with "Forgot password").</li>
                    <li>Upload. Rows with problems are skipped and listed below — nothing is overwritten.</li>
                </ol>
                <a :href="route('admin.passports.template')" class="btn-outline btn-sm">Download template</a>
                <Field label="CSV file" for="pf" :error="form.errors.file"><input id="pf" type="file" accept=".csv,text/csv" class="block text-sm" @input="form.file = $event.target.files[0]" /></Field>
                <button type="submit" class="btn-primary" :disabled="form.processing || !form.file">Import</button>
            </form>
            <div class="card space-y-2">
                <p class="text-xs text-lwe-muted">Students with a passport ID</p><p class="text-3xl font-bold text-lwe-navy">{{ count }}</p>
                <p class="text-xs text-lwe-muted">Highest ID in use</p><p class="font-mono font-semibold">{{ last ?? '—' }}</p>
            </div>
        </div>
        <div v-if="report" class="card mt-6">
            <p class="font-semibold text-lwe-navy">Last import: {{ report.linked }} linked · {{ report.created }} new accounts · {{ report.skipped }} skipped</p>
            <ul v-if="Object.keys(report.errors).length" class="mt-3 space-y-1 text-sm text-red-800"><li v-for="(msg, line) in report.errors" :key="line">Row {{ line }}: {{ msg }}</li></ul>
        </div>
    </AppLayout>
</template>
