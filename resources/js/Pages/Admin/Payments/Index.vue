<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from '@/Components/Lwe/Dialog.vue';
import Field from '@/Components/Lwe/Field.vue';
import Pagination from '@/Components/Lwe/Pagination.vue';
import StatusBadge from '@/Components/Lwe/StatusBadge.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';
import AiButton from '@/Components/Lwe/AiButton.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({ payments: Object, status: String, counts: Object });
const tabs = [['pending', 'To review'], ['approved', 'Approved'], ['rejected', 'Rejected'], ['refunded', 'Refunded'], ['all', 'All']];
const methodLabel = { bank: 'Bank', jazzcash: 'JazzCash', easypaisa: 'Easypaisa', cash: 'Cash', other: 'Other' };

const viewing = ref(null);
const reviewForm = useForm({ note: '' });
const open = (p) => { reviewForm.reset(); reviewForm.clearErrors(); viewing.value = p; };
const approve = () => reviewForm.post(route('admin.payments.approve', viewing.value.id), { preserveScroll: true, onSuccess: () => (viewing.value = null) });
const reject = () => reviewForm.post(route('admin.payments.reject', viewing.value.id), { preserveScroll: true, onSuccess: () => (viewing.value = null) });

const aiChecking = ref(false);
const runAi = () => {
    aiChecking.value = true;
    router.post(route('admin.ai.payment-check', viewing.value.id), {}, {
        preserveScroll: true,
        onSuccess: (page) => { viewing.value = page.props.payments.data.find((p) => p.id === viewing.value.id) ?? viewing.value; },
        onFinish: () => (aiChecking.value = false),
    });
};
const verdictText = { looks_ok: 'AI: looks OK', check_carefully: 'AI: check carefully', suspicious: 'AI: suspicious' };
const verdictClass = { looks_ok: 'bg-lwe-greenSoft text-green-900', check_carefully: 'bg-lwe-orangeSoft text-amber-900', suspicious: 'bg-red-100 text-red-900' };

const refunding = ref(null);
const refundForm = useForm({ amount: '', reason: '', suspend_access: true });
const openRefund = (p) => { refundForm.reset(); refundForm.amount = p.amount; refunding.value = p; };
const refund = () => refundForm.post(route('admin.payments.refund', refunding.value.id), { preserveScroll: true, onSuccess: () => (refunding.value = null) });
</script>

<template>
    <AppLayout title="Payments">
        <h1 class="page-title mb-4">Payments</h1>
        <nav class="mb-4 flex flex-wrap gap-2" aria-label="Payment status">
            <Link v-for="[key, text] in tabs" :key="key" :href="route('admin.payments.index', { status: key })" preserve-state
                class="rounded-full px-4 py-2 text-sm font-semibold" :class="status === key ? 'bg-lwe-navy text-white' : 'bg-white text-slate-700 ring-1 ring-lwe-line'">
                {{ text }}<template v-if="counts[key]"> ({{ counts[key] }})</template>
            </Link>
        </nav>

        <EmptyState v-if="!payments.data.length" :title="status === 'pending' ? 'No payments waiting' : 'Nothing here'" />
        <div v-else class="table-wrap">
            <table class="table">
                <thead><tr><th>Student</th><th>Course</th><th>Amount</th><th>Method</th><th>Transaction ID</th><th>Date</th><th>Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="p in payments.data" :key="p.id">
                        <td><span class="font-semibold">{{ p.student }}</span><span class="block text-xs text-lwe-muted">{{ p.code }} · {{ p.phone }}</span></td>
                        <td>{{ p.course }}</td>
                        <td class="font-semibold">PKR {{ p.amount.toLocaleString() }}</td>
                        <td>{{ methodLabel[p.method] }}</td>
                        <td class="font-mono text-xs">{{ p.transaction_id }}</td>
                        <td class="text-xs">{{ p.date }}</td>
                        <td>
                            <StatusBadge :status="p.status" />
                            <span v-if="p.ai" class="mt-1 block w-fit rounded-full px-2 py-0.5 text-[11px] font-bold" :class="verdictClass[p.ai.verdict]">{{ verdictText[p.ai.verdict] }}</span>
                            <span v-if="p.reviewer" class="block text-[11px] text-lwe-muted">by {{ p.reviewer }}</span>
                        </td>
                        <td class="space-x-2 text-right">
                            <button v-if="p.status === 'pending'" type="button" class="btn-dark btn-sm" @click="open(p)">Review</button>
                            <button v-else-if="p.status === 'approved'" type="button" class="btn-outline btn-sm" @click="openRefund(p)">Refund</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="payments.links" />

        <Dialog :show="!!viewing" title="Review payment" wide @close="viewing = null">
            <div v-if="viewing" class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <a v-if="viewing.has_screenshot" :href="route('admin.payments.screenshot', viewing.id)" target="_blank" rel="noopener" class="block">
                    <img :src="route('admin.payments.screenshot', viewing.id)" alt="Payment screenshot" class="max-h-[60vh] w-full rounded-xl border border-lwe-line object-contain" />
                    <span class="mt-1 block text-xs text-lwe-navy2 underline">Open full size</span>
                </a>
                <div class="space-y-3 text-sm">
                    <p><span class="text-lwe-muted">Student:</span> <b>{{ viewing.student }}</b> ({{ viewing.code }})</p>
                    <p><span class="text-lwe-muted">Course:</span> {{ viewing.course }}</p>
                    <p><span class="text-lwe-muted">Amount:</span> <b>PKR {{ viewing.amount.toLocaleString() }}</b> of PKR {{ viewing.amount_due.toLocaleString() }} due</p>
                    <p><span class="text-lwe-muted">Method:</span> {{ methodLabel[viewing.method] }} · <span class="font-mono">{{ viewing.transaction_id }}</span></p>
                    <div class="rounded-xl border border-violet-200 bg-violet-50 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-violet-900">AI Payment Checker</span>
                            <AiButton :loading="aiChecking" :label="viewing.ai ? 'Check again' : 'Check with AI'" @click="runAi" />
                        </div>
                        <template v-if="viewing.ai">
                            <span class="mt-2 inline-block rounded-full px-2.5 py-1 text-xs font-bold" :class="verdictClass[viewing.ai.verdict]">{{ verdictText[viewing.ai.verdict] }}</span>
                            <ul v-if="viewing.ai.warnings.length" class="mt-2 list-disc space-y-1 pl-5 text-xs text-red-800">
                                <li v-for="w in viewing.ai.warnings" :key="w">{{ w }}</li>
                            </ul>
                            <p class="mt-2 text-xs text-slate-700">
                                Read from screenshot: PKR {{ viewing.ai.read.amount ?? '?' }} · ID {{ viewing.ai.read.transaction_id ?? '?' }} · {{ viewing.ai.read.method }}
                                <template v-if="viewing.ai.read.date"> · {{ viewing.ai.read.date }}</template>
                                <template v-if="viewing.ai.read.receiver_name"> · to {{ viewing.ai.read.receiver_name }}</template>
                            </p>
                            <p v-if="viewing.ai.notes" class="mt-1 text-xs italic text-slate-600">{{ viewing.ai.notes }}</p>
                            <button v-if="viewing.ai.suggested_note" type="button" class="mt-2 text-xs font-semibold text-violet-900 underline" @click="reviewForm.note = viewing.ai.suggested_note">Use AI note for the student</button>
                        </template>
                        <p v-else class="mt-1 text-xs text-violet-800">Not checked yet.</p>
                        <p class="mt-2 text-[11px] text-slate-500">AI can make mistakes. You make the final decision.</p>
                    </div>
                    <Field label="Note to student (required when rejecting)" for="rn" :error="reviewForm.errors.note">
                        <textarea id="rn" v-model="reviewForm.note" rows="3" class="textarea" placeholder="e.g. Amount does not match / screenshot unclear" />
                    </Field>
                    <div class="flex gap-2 pt-2">
                        <button type="button" class="btn-primary flex-1" :disabled="reviewForm.processing" @click="approve">Approve</button>
                        <button type="button" class="btn-danger flex-1" :disabled="reviewForm.processing" @click="reject">Reject</button>
                    </div>
                    <p class="text-xs text-lwe-muted">The student gets a WhatsApp message either way. Approving the full amount unlocks the course.</p>
                </div>
            </div>
        </Dialog>

        <Dialog :show="!!refunding" title="Record a refund" @close="refunding = null">
            <form class="space-y-4" @submit.prevent="refund">
                <p class="muted">Send the money back outside the portal first, then record it here.</p>
                <Field label="Refund amount (PKR)" for="ra" :error="refundForm.errors.amount"><input id="ra" v-model="refundForm.amount" type="number" min="1" class="input" required /></Field>
                <Field label="Reason" for="rr" :error="refundForm.errors.reason"><input id="rr" v-model="refundForm.reason" type="text" class="input" required /></Field>
                <label class="flex items-center gap-2 text-sm"><input v-model="refundForm.suspend_access" type="checkbox" class="rounded" /> Remove course access</label>
                <div class="flex justify-end gap-2 border-t border-lwe-line pt-4">
                    <button type="button" class="btn-outline" @click="refunding = null">Cancel</button>
                    <button type="submit" class="btn-danger" :disabled="refundForm.processing">Record refund</button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
