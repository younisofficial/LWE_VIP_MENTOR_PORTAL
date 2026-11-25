<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Lwe/Pagination.vue';

const props = defineProps({ logs: Object, filters: Object, logNames: Array });
const log = ref(props.filters.log ?? '');
watch(log, () => router.get(route('admin.activity.index'), { log: log.value || undefined }, { preserveState: true, replace: true }));

const changes = (c) => {
    const attrs = c.attributes ?? {};
    const old = c.old ?? {};
    const keys = Object.keys(attrs);
    if (!keys.length) return Object.keys(c).length ? JSON.stringify(c) : '';
    return keys.map((k) => (k in old ? `${k}: ${old[k] ?? '—'} → ${attrs[k] ?? '—'}` : `${k}: ${attrs[k] ?? '—'}`)).join(' · ');
};
</script>

<template>
    <AppLayout title="Audit log">
        <div class="mb-6 flex items-end justify-between">
            <div><h1 class="page-title">Audit log</h1><p class="muted">Who changed what: fees, payments, enrollments, students, settings.</p></div>
            <select v-model="log" class="input w-48" aria-label="Area"><option value="">Everything</option><option v-for="n in logNames" :key="n" :value="n">{{ n }}</option></select>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Time</th><th>By</th><th>Area</th><th>What</th><th>Changes</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="a in logs.data" :key="a.id">
                        <td class="text-xs">{{ a.time }}</td>
                        <td>{{ a.by }}</td>
                        <td class="text-xs capitalize">{{ a.log }}</td>
                        <td class="text-xs">{{ a.event }} · {{ a.subject }}</td>
                        <td class="max-w-md whitespace-normal text-xs text-slate-600">{{ changes(a.changes) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="logs.links" />
    </AppLayout>
</template>
