<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Lwe/Icon.vue';

const props = defineProps({ course: Object, batches: Array, enrollment: Object });

const form = useForm({ batch_id: props.batches.find((b) => !b.full)?.id ?? null, coupon: '' });
const couponInfo = ref(null);
const couponError = ref('');
const checking = ref(false);

const applyCoupon = async () => {
    couponError.value = '';
    couponInfo.value = null;
    if (!form.coupon) return;
    checking.value = true;
    try {
        const { data } = await axios.post(route('student.courses.coupon', props.course.slug), { code: form.coupon });
        couponInfo.value = data;
    } catch (e) {
        couponError.value = e.response?.data?.errors?.coupon?.[0] ?? 'Could not check this code.';
    } finally {
        checking.value = false;
    }
};

const price = computed(() => couponInfo.value ? couponInfo.value.total : props.course.fee);
const enroll = () => form.post(route('student.courses.enroll', props.course.slug));
const typeLabel = { live_class: 'Live class', recorded_video: 'Video', reading: 'Reading', task: 'Task', quiz: 'Quiz' };
</script>

<template>
    <AppLayout :title="course.title">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <section class="overflow-hidden rounded-2xl bg-lwe-navy text-white">
                    <div class="p-6 sm:p-8">
                        <div class="flex flex-wrap gap-2 text-[11px] font-bold">
                            <span v-if="course.is_vip" class="rounded-full bg-lwe-orange px-2.5 py-1 text-lwe-navy">VIP</span>
                            <span v-if="course.is_masterclass" class="rounded-full bg-white/20 px-2.5 py-1">MASTERCLASS</span>
                            <span class="rounded-full bg-white/20 px-2.5 py-1 uppercase">{{ course.level }}</span>
                        </div>
                        <h1 class="mt-3 text-3xl font-semibold">{{ course.title }}</h1>
                        <p class="mt-2 text-slate-300">{{ course.short_description }}</p>
                        <p v-if="course.mentors.length" class="mt-4 text-sm text-slate-300">Mentor: <span class="font-semibold text-white">{{ course.mentors.join(', ') }}</span></p>
                    </div>
                </section>

                <section v-if="course.what_you_learn?.length" class="card">
                    <h2 class="mb-3 text-lg font-semibold text-lwe-navy">What you will learn</h2>
                    <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <li v-for="item in course.what_you_learn" :key="item" class="flex gap-2 text-sm"><Icon name="check" class="mt-0.5 flex-none text-lwe-green" /> {{ item }}</li>
                    </ul>
                </section>

                <section v-if="course.description" class="card">
                    <h2 class="mb-3 text-lg font-semibold text-lwe-navy">About this course</h2>
                    <p class="whitespace-pre-line text-sm leading-7 text-slate-700">{{ course.description }}</p>
                </section>

                <section class="card">
                    <h2 class="mb-3 text-lg font-semibold text-lwe-navy">Curriculum</h2>
                    <div v-for="m in course.modules" :key="m.title" class="mb-4">
                        <h3 class="mb-2 text-sm font-semibold text-slate-800">{{ m.title }}</h3>
                        <ul class="divide-y divide-slate-100 rounded-xl border border-lwe-line">
                            <li v-for="l in m.lessons" :key="l.title" class="flex items-center justify-between px-4 py-3 text-sm">
                                <span>{{ l.title }}</span>
                                <span class="flex items-center gap-2 text-xs text-lwe-muted">
                                    <span v-if="l.is_preview" class="rounded bg-lwe-greenSoft px-1.5 py-0.5 font-semibold text-green-900">Preview</span>
                                    {{ typeLabel[l.type] }}<template v-if="l.duration_minutes"> · {{ l.duration_minutes }} min</template>
                                </span>
                            </li>
                        </ul>
                    </div>
                </section>

                <section v-if="course.requirements?.length" class="card">
                    <h2 class="mb-3 text-lg font-semibold text-lwe-navy">Requirements</h2>
                    <ul class="list-disc space-y-1 pl-5 text-sm text-slate-700"><li v-for="r in course.requirements" :key="r">{{ r }}</li></ul>
                </section>

                <section v-if="course.faqs?.length" class="card">
                    <h2 class="mb-3 text-lg font-semibold text-lwe-navy">FAQs</h2>
                    <details v-for="f in course.faqs" :key="f.q" class="mb-2 rounded-lg border border-lwe-line px-4 py-3">
                        <summary class="cursor-pointer text-sm font-semibold">{{ f.q }}</summary>
                        <p class="mt-2 text-sm text-slate-700">{{ f.a }}</p>
                    </details>
                </section>
            </div>

            <!-- Enroll box -->
            <aside class="lg:sticky lg:top-24 lg:self-start">
                <div class="card space-y-4">
                    <p class="text-3xl font-bold" :class="course.is_free ? 'text-lwe-green' : 'text-lwe-navy'">
                        {{ course.is_free ? 'Free' : 'PKR ' + Number(price).toLocaleString() }}
                        <span v-if="couponInfo" class="ml-2 text-base font-normal text-slate-400 line-through">PKR {{ course.fee.toLocaleString() }}</span>
                    </p>

                    <template v-if="enrollment && enrollment.status !== 'cancelled'">
                        <Link v-if="enrollment.status === 'pending_payment'" :href="route('student.payments.create', enrollment.id)" class="btn-primary w-full">Upload payment</Link>
                        <Link v-else :href="route('student.my-courses.show', course.slug)" class="btn-primary w-full">Go to course</Link>
                    </template>

                    <form v-else class="space-y-4" @submit.prevent="enroll">
                        <fieldset v-if="batches.length">
                            <legend class="label">Choose a batch</legend>
                            <div class="space-y-2">
                                <label v-for="b in batches" :key="b.id" class="flex cursor-pointer gap-3 rounded-lg border p-3 text-sm"
                                    :class="[form.batch_id === b.id ? 'border-lwe-navy bg-slate-50' : 'border-lwe-line', b.full ? 'cursor-not-allowed opacity-50' : '']">
                                    <input v-model="form.batch_id" type="radio" :value="b.id" :disabled="b.full" class="mt-1" />
                                    <span>
                                        <span class="block font-semibold">{{ b.name }}</span>
                                        <span class="block text-xs text-lwe-muted">Starts {{ b.starts_on }}<template v-if="b.days"> · {{ b.days }}</template><template v-if="b.time"> · {{ b.time }}</template></span>
                                        <span v-if="b.full" class="text-xs font-semibold text-red-700">Full</span>
                                        <span v-else-if="b.seats_left !== null" class="text-xs text-lwe-muted">{{ b.seats_left }} seats left</span>
                                    </span>
                                </label>
                            </div>
                            <p v-if="form.errors.batch_id" class="mt-1 text-xs text-red-700">{{ form.errors.batch_id }}</p>
                        </fieldset>

                        <div v-if="!course.is_free && course.coupons_allowed">
                            <label for="coupon" class="label">Coupon / scholarship code</label>
                            <div class="flex gap-2">
                                <input id="coupon" v-model="form.coupon" type="text" class="input uppercase" @keydown.enter.prevent="applyCoupon" />
                                <button type="button" class="btn-outline" :disabled="checking" @click="applyCoupon">Apply</button>
                            </div>
                            <p v-if="couponInfo" class="mt-1 text-xs font-semibold text-lwe-green">You save PKR {{ couponInfo.discount.toLocaleString() }}</p>
                            <p v-if="couponError || form.errors.coupon" class="mt-1 text-xs text-red-700">{{ couponError || form.errors.coupon }}</p>
                        </div>

                        <p v-if="form.errors.course" class="text-xs text-red-700">{{ form.errors.course }}</p>
                        <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                            {{ course.is_free || price === 0 ? 'Enroll for free' : 'Enroll now' }}
                        </button>
                        <p v-if="!course.is_free" class="text-center text-xs text-lwe-muted">After enrolling, upload your payment screenshot. The course opens when it is approved.</p>
                    </form>

                    <div v-if="course.skills.length" class="border-t border-lwe-line pt-4">
                        <p class="label">Skills on your passport</p>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="s in course.skills" :key="s" class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-lwe-navy">{{ s }}</span>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
