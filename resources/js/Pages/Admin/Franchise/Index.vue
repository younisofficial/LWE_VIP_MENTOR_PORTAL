<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from '@/Components/Lwe/Dialog.vue';
import Field from '@/Components/Lwe/Field.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';
import { fmt } from '@/Components/Charts/format';

const props = defineProps({ partners: Array, range: String, label: String });
const show = ref(false);
const form = useForm({ name: '', phone: '', email: '', city: '', password: '' });
const save = () => form.post(route('admin.franchise.store'), { onSuccess: () => { show.value = false; form.reset(); } });
const setRange = (e) => router.get(route('admin.franchise.index'), { range: e.target.value }, { preserveScroll: true });
</script>

<template>
    <AppLayout title="Franchise partners">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><h1 class="page-title">Franchise partners</h1><p class="muted">Partners log in to see only the students they brought. When you add students (one by one or CSV), choose Source "Franchise" and the partner.</p></div>
            <div class="flex gap-2">
                <select :value="range" class="input w-40" aria-label="Period" @change="setRange"><option value="month">This month</option><option value="last_month">Last month</option><option value="90d">Last 90 days</option><option value="year">This year</option></select>
                <button type="button" class="btn-primary" @click="show = true">+ Add partner</button>
            </div>
        </div>
        <EmptyState v-if="!partners.length" title="No partners yet" text="Add a franchise partner to start tracking their students." />
        <div v-else class="table-wrap">
            <table class="table">
                <thead><tr><th>Partner</th><th>Contact</th><th class="text-right">Students</th><th class="text-right">New ({{ label }})</th><th class="text-right">Active</th><th class="text-right">Payment pending</th><th class="text-right">Completed</th><th class="text-right">Revenue ({{ label }})</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="p in partners" :key="p.id">
                        <td><b>{{ p.name }}</b><p class="text-xs text-lwe-muted">{{ p.city }}</p></td>
                        <td class="text-xs">{{ p.phone }}<br />{{ p.email }}</td>
                        <td class="text-right tabular-nums">{{ p.students }}</td><td class="text-right tabular-nums">{{ p.new }}</td><td class="text-right tabular-nums">{{ p.active }}</td>
                        <td class="text-right tabular-nums">{{ p.pending }}</td><td class="text-right tabular-nums">{{ p.completed }}</td><td class="text-right tabular-nums">{{ fmt(p.revenue, 'pkr') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Dialog :show="show" title="Add franchise partner" @close="show = false">
            <form class="space-y-4" @submit.prevent="save">
                <Field label="Name" for="fn" :error="form.errors.name"><input id="fn" v-model="form.name" type="text" class="input" required /></Field>
                <div class="grid grid-cols-2 gap-3">
                    <Field label="Mobile (login)" for="fp" :error="form.errors.phone"><input id="fp" v-model="form.phone" type="tel" class="input" required /></Field>
                    <Field label="City" for="fc" :error="form.errors.city"><input id="fc" v-model="form.city" type="text" class="input" /></Field>
                </div>
                <Field label="Email (optional)" for="fe" :error="form.errors.email"><input id="fe" v-model="form.email" type="email" class="input" /></Field>
                <Field label="First password (share privately)" for="fpw" :error="form.errors.password"><input id="fpw" v-model="form.password" type="text" class="input" required autocomplete="new-password" /></Field>
                <div class="flex justify-end gap-2 border-t border-lwe-line pt-4"><button type="button" class="btn-outline" @click="show = false">Cancel</button><button type="submit" class="btn-primary" :disabled="form.processing">Add partner</button></div>
            </form>
        </Dialog>
    </AppLayout>
</template>
