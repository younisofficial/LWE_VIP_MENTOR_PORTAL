<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from '@/Components/Lwe/Dialog.vue';
import Field from '@/Components/Lwe/Field.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';

const props = defineProps({ accounts: Array, webhookUrl: String });

const editing = ref(null); // 'new' | account
const form = useForm({ name: '', host_email: '', account_id: '', client_id: '', client_secret: '', sdk_key: '', sdk_secret: '', webhook_secret: '', is_active: true });
const open = (a = null) => {
    form.reset(); form.clearErrors();
    if (a) Object.assign(form, { name: a.name, host_email: a.host_email, is_active: a.is_active });
    editing.value = a ?? 'new';
};
const save = () => {
    const opts = { preserveScroll: true, onSuccess: () => (editing.value = null) };
    editing.value === 'new' ? form.post(route('admin.zoom.store'), opts) : form.put(route('admin.zoom.update', editing.value.id), opts);
};
const test = (a) => router.post(route('admin.zoom.test', a.id), {}, { preserveScroll: true });
const remove = (a) => { if (confirm(`Remove Zoom account "${a.name}"?`)) router.delete(route('admin.zoom.destroy', a.id), { preserveScroll: true }); };
const copied = ref(false);
const copy = async () => { try { await navigator.clipboard.writeText(props.webhookUrl); copied.value = true; setTimeout(() => (copied.value = false), 2000); } catch { /* ignore */ } };
const secretHint = (k) => (editing.value !== 'new' ? 'Leave empty to keep the saved value' : '');
</script>

<template>
    <AppLayout title="Zoom accounts">
        <div class="mb-6 flex items-end justify-between gap-4">
            <div><h1 class="page-title">Zoom accounts</h1><p class="muted">Each account can run one class at a time. Add more accounts to run classes side by side.</p></div>
            <button type="button" class="btn-primary" @click="open()">+ Add Zoom account</button>
        </div>

        <section class="card mb-6 text-sm">
            <p class="font-semibold text-lwe-navy">Webhook URL (for attendance, recordings and AI notes)</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <code class="rounded-lg bg-slate-100 px-3 py-2 text-xs">{{ webhookUrl }}</code>
                <button type="button" class="btn-outline btn-sm" @click="copy">{{ copied ? 'Copied' : 'Copy' }}</button>
            </div>
            <p class="mt-2 text-xs text-lwe-muted">Paste it in your Zoom app → Features → Event Subscriptions. Events: meeting started, meeting ended, participant joined, participant left, recording completed. Zoom needs a public HTTPS address, so this works on the live server (or with a tunnel such as ngrok on your PC).</p>
        </section>

        <EmptyState v-if="!accounts.length" title="No Zoom account yet" text="Add your Zoom Server-to-Server OAuth + Meeting SDK keys to start scheduling classes." />
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div v-for="a in accounts" :key="a.id" class="card">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h2 class="font-semibold text-lwe-navy">{{ a.name }}</h2>
                        <p class="text-xs text-lwe-muted">Host: {{ a.host_email }}</p>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold"
                        :class="!a.is_active ? 'bg-slate-200 text-slate-700' : a.status === 'ok' ? 'bg-lwe-greenSoft text-green-900' : a.status === 'error' ? 'bg-red-100 text-red-900' : 'bg-lwe-orangeSoft text-amber-900'">
                        {{ !a.is_active ? 'Off' : a.status === 'ok' ? 'Connected' : a.status === 'error' ? 'Error' : 'Not tested' }}
                    </span>
                </div>
                <p v-if="a.last_error" class="mt-2 rounded-lg bg-red-50 p-2 text-xs text-red-800">{{ a.last_error }}</p>
                <p class="mt-2 text-xs text-lwe-muted">{{ a.upcoming_count }} upcoming classes · webhook secret {{ a.has_webhook_secret ? 'saved' : 'missing' }}<template v-if="a.last_checked"> · checked {{ a.last_checked }}</template></p>
                <div class="mt-4 flex gap-2">
                    <button type="button" class="btn-dark btn-sm" @click="test(a)">Test connection</button>
                    <button type="button" class="btn-outline btn-sm" @click="open(a)">Edit</button>
                    <button type="button" class="btn-sm text-red-700 hover:underline" @click="remove(a)">Remove</button>
                </div>
            </div>
        </div>

        <Dialog :show="!!editing" :title="editing === 'new' ? 'Add Zoom account' : 'Edit Zoom account'" wide @close="editing = null">
            <form class="space-y-4" @submit.prevent="save">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Name (for you)" for="zn" :error="form.errors.name"><input id="zn" v-model="form.name" type="text" class="input" placeholder="Zoom 1" required /></Field>
                    <Field label="Host email (licensed Zoom user)" for="zh" :error="form.errors.host_email"><input id="zh" v-model="form.host_email" type="email" class="input" required /></Field>
                </div>
                <p class="label">Server-to-Server OAuth app</p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Field label="Account ID" for="za" :error="form.errors.account_id" :hint="secretHint()"><input id="za" v-model="form.account_id" type="password" autocomplete="off" class="input" :required="editing === 'new'" /></Field>
                    <Field label="Client ID" for="zc" :error="form.errors.client_id" :hint="secretHint()"><input id="zc" v-model="form.client_id" type="password" autocomplete="off" class="input" :required="editing === 'new'" /></Field>
                    <Field label="Client Secret" for="zs" :error="form.errors.client_secret" :hint="secretHint()"><input id="zs" v-model="form.client_secret" type="password" autocomplete="off" class="input" :required="editing === 'new'" /></Field>
                </div>
                <p class="label">Meeting SDK app (General app)</p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="SDK Key / Client ID" for="zk" :error="form.errors.sdk_key" :hint="secretHint()"><input id="zk" v-model="form.sdk_key" type="password" autocomplete="off" class="input" :required="editing === 'new'" /></Field>
                    <Field label="SDK Secret / Client Secret" for="zks" :error="form.errors.sdk_secret" :hint="secretHint()"><input id="zks" v-model="form.sdk_secret" type="password" autocomplete="off" class="input" :required="editing === 'new'" /></Field>
                </div>
                <Field label="Webhook Secret Token" for="zw" :error="form.errors.webhook_secret" hint="From the app's Event Subscriptions. Needed for attendance and recordings."><input id="zw" v-model="form.webhook_secret" type="password" autocomplete="off" class="input" /></Field>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded" /> Use this account for new classes</label>
                <p class="text-xs text-lwe-muted">Keys are stored encrypted and are never shown again. Saving runs a connection test.</p>
                <div class="flex justify-end gap-2 border-t border-lwe-line pt-4">
                    <button type="button" class="btn-outline" @click="editing = null">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">Save &amp; test</button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
