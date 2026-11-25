<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from '@/Components/Lwe/Dialog.vue';
import Field from '@/Components/Lwe/Field.vue';
import Pagination from '@/Components/Lwe/Pagination.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';

const props = defineProps({ coupons: Object, courses: Array, filters: Object });

const editing = ref(null);
const form = useForm({ code: '', type: 'percent', value: '', course_id: '', max_uses: '', expires_at: '', is_active: true, note: '' });
const open = (c = null) => {
    form.reset(); form.clearErrors();
    if (c) Object.assign(form, { code: c.code, type: c.type, value: Number(c.value), course_id: c.course_id ?? '', max_uses: c.max_uses ?? '', expires_at: c.expires_at ?? '', is_active: c.is_active, note: c.note ?? '' });
    editing.value = c ?? 'new';
};
const save = () => {
    const opts = { preserveScroll: true, onSuccess: () => (editing.value = null) };
    editing.value === 'new' ? form.post(route('admin.coupons.store'), opts) : form.put(route('admin.coupons.update', editing.value.id), opts);
};
const remove = (c) => { if (confirm(`Delete coupon ${c.code}?`)) router.delete(route('admin.coupons.destroy', c.id), { preserveScroll: true }); };
</script>

<template>
    <AppLayout title="Coupons">
        <div class="mb-6 flex items-end justify-between">
            <div><h1 class="page-title">Coupons & scholarship codes</h1><p class="muted">Give a % or fixed discount on course fees.</p></div>
            <button type="button" class="btn-primary" @click="open()">+ New coupon</button>
        </div>

        <EmptyState v-if="!coupons.data.length" title="No coupons yet" />
        <div v-else class="table-wrap">
            <table class="table">
                <thead><tr><th>Code</th><th>Discount</th><th>Course</th><th>Used</th><th>Expires</th><th>Active</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="c in coupons.data" :key="c.id">
                        <td><span class="font-mono font-semibold">{{ c.code }}</span><span v-if="c.note" class="block text-xs text-lwe-muted">{{ c.note }}</span></td>
                        <td>{{ c.type === 'percent' ? Number(c.value) + '%' : 'PKR ' + Number(c.value).toLocaleString() }}</td>
                        <td>{{ c.course }}</td>
                        <td>{{ c.used_count }}<template v-if="c.max_uses"> / {{ c.max_uses }}</template></td>
                        <td :class="{ 'text-red-700': c.expired }">{{ c.expires_at ?? 'Never' }}</td>
                        <td>{{ c.is_active ? 'Yes' : 'No' }}</td>
                        <td class="space-x-2 text-right">
                            <button type="button" class="btn-outline btn-sm" @click="open(c)">Edit</button>
                            <button type="button" class="btn-sm text-red-700 hover:underline" @click="remove(c)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="coupons.links" />

        <Dialog :show="!!editing" :title="editing === 'new' ? 'New coupon' : 'Edit coupon'" @close="editing = null">
            <form class="space-y-4" @submit.prevent="save">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Code" for="cc" :error="form.errors.code"><input id="cc" v-model="form.code" type="text" class="input uppercase" placeholder="SCHOLAR50" required /></Field>
                    <Field label="Course" for="cco" :error="form.errors.course_id">
                        <select id="cco" v-model="form.course_id" class="input"><option value="">All courses</option><option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option></select>
                    </Field>
                    <Field label="Type" for="ct"><select id="ct" v-model="form.type" class="input"><option value="percent">Percent (%)</option><option value="fixed">Fixed (PKR)</option></select></Field>
                    <Field :label="form.type === 'percent' ? 'Discount %' : 'Discount PKR'" for="cv" :error="form.errors.value"><input id="cv" v-model="form.value" type="number" min="1" class="input" required /></Field>
                    <Field label="Max uses (empty = unlimited)" for="cm" :error="form.errors.max_uses"><input id="cm" v-model="form.max_uses" type="number" min="1" class="input" /></Field>
                    <Field label="Expires on" for="ce" :error="form.errors.expires_at"><input id="ce" v-model="form.expires_at" type="date" class="input" /></Field>
                </div>
                <Field label="Note (e.g. which session)" for="cn"><input id="cn" v-model="form.note" type="text" class="input" /></Field>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded" /> Active</label>
                <div class="flex justify-end gap-2 border-t border-lwe-line pt-4">
                    <button type="button" class="btn-outline" @click="editing = null">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">Save</button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
