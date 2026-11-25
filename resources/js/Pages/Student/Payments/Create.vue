<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Field from '@/Components/Lwe/Field.vue';
import StatusBadge from '@/Components/Lwe/StatusBadge.vue';

const props = defineProps({ enrollment: Object, payments: Array, instructions: String, methods: Array });

const form = useForm({ amount: props.enrollment.amount_due, method: 'jazzcash', transaction_id: '', screenshot: null });
const hasPending = computed(() => props.payments.some((p) => p.status === 'pending'));
const methodLabel = { bank: 'Bank transfer', jazzcash: 'JazzCash', easypaisa: 'Easypaisa', cash: 'Cash at campus', other: 'Other' };
const submit = () => form.post(route('student.payments.store', props.enrollment.id), { forceFormData: true, onSuccess: () => form.reset('transaction_id', 'screenshot') });
</script>

<template>
    <AppLayout title="Payment">
        <h1 class="page-title">Payment for {{ enrollment.course }}</h1>
        <p class="muted">{{ enrollment.batch }}</p>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-5">
            <section class="card space-y-3 lg:col-span-2" aria-label="Amount">
                <div class="flex justify-between text-sm"><span>Course fee</span><span>PKR {{ enrollment.fee.toLocaleString() }}</span></div>
                <div v-if="enrollment.discount" class="flex justify-between text-sm text-lwe-green"><span>Discount</span><span>− PKR {{ enrollment.discount.toLocaleString() }}</span></div>
                <div class="flex justify-between border-t border-lwe-line pt-3 text-lg font-bold text-lwe-navy"><span>Amount due</span><span>PKR {{ enrollment.amount_due.toLocaleString() }}</span></div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="label">How to pay</p>
                    <p class="whitespace-pre-line text-sm text-slate-700">{{ instructions }}</p>
                </div>
                <Link v-if="enrollment.status === 'active'" :href="route('student.my-courses.show', enrollment.slug)" class="btn-primary w-full">Course unlocked — start learning</Link>
            </section>

            <section class="card lg:col-span-3" aria-label="Upload payment">
                <h2 class="mb-4 text-lg font-semibold text-lwe-navy">Upload your payment</h2>
                <div v-if="hasPending" class="rounded-xl bg-lwe-orangeSoft p-4 text-sm text-amber-900">Your payment is being checked. You will get a WhatsApp message when it is approved.</div>
                <div v-else-if="enrollment.amount_due <= 0" class="rounded-xl bg-lwe-greenSoft p-4 text-sm text-green-900">Nothing to pay.</div>
                <form v-else class="space-y-4" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Amount paid (PKR)" for="amount" :error="form.errors.amount">
                            <input id="amount" v-model="form.amount" type="number" min="1" :max="enrollment.amount_due" class="input" required />
                        </Field>
                        <Field label="Paid by" for="method" :error="form.errors.method">
                            <select id="method" v-model="form.method" class="input">
                                <option v-for="m in methods" :key="m" :value="m">{{ methodLabel[m] }}</option>
                            </select>
                        </Field>
                    </div>
                    <Field label="Transaction ID / reference" for="tid" :error="form.errors.transaction_id">
                        <input id="tid" v-model="form.transaction_id" type="text" class="input" required />
                    </Field>
                    <Field label="Screenshot of payment" for="shot" :error="form.errors.screenshot" hint="JPG or PNG, up to 4 MB">
                        <input id="shot" type="file" accept="image/*" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-lwe-navy file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-white"
                            required @input="form.screenshot = $event.target.files[0]" />
                    </Field>
                    <button type="submit" class="btn-primary w-full" :disabled="form.processing">Submit payment</button>
                </form>

                <div v-if="payments.length" class="mt-6 border-t border-lwe-line pt-4">
                    <p class="label">Your payments</p>
                    <ul class="divide-y divide-slate-100">
                        <li v-for="p in payments" :key="p.id" class="flex items-center justify-between gap-3 py-2 text-sm">
                            <span>PKR {{ p.amount.toLocaleString() }} · {{ methodLabel[p.method] }} · {{ p.date }}
                                <span v-if="p.note" class="block text-xs text-slate-500">{{ p.note }}</span>
                            </span>
                            <StatusBadge :status="p.status" />
                        </li>
                    </ul>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
