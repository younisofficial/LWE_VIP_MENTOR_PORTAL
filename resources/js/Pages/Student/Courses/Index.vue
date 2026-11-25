<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/Lwe/EmptyState.vue';

const props = defineProps({ courses: Array, filters: Object });
const search = ref(props.filters.search ?? '');
const filter = ref(props.filters.filter ?? '');

let t;
watch([search, filter], () => {
    clearTimeout(t);
    t = setTimeout(() => router.get(route('student.courses.index'), { search: search.value || undefined, filter: filter.value || undefined }, { preserveState: true, replace: true }), 300);
});
</script>

<template>
    <AppLayout title="Courses">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="page-title">Courses</h1>
                <p class="muted">Free and premium courses from Learning with Earning mentors.</p>
            </div>
            <div class="flex gap-2">
                <label class="sr-only" for="search">Search courses</label>
                <input id="search" v-model="search" type="search" class="input w-56" placeholder="Search courses…" />
                <label class="sr-only" for="filter">Filter</label>
                <select id="filter" v-model="filter" class="input w-32">
                    <option value="">All</option>
                    <option value="free">Free</option>
                    <option value="vip">VIP</option>
                </select>
            </div>
        </div>

        <EmptyState v-if="!courses.length" title="No courses found" text="Try another search." />

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <Link v-for="c in courses" :key="c.id" :href="route('student.courses.show', c.slug)"
                class="group flex flex-col overflow-hidden rounded-2xl border border-lwe-line bg-white transition hover:shadow-md">
                <div class="aspect-video bg-lwe-navy2">
                    <img v-if="c.thumbnail_url" :src="c.thumbnail_url" alt="" class="h-full w-full object-cover" />
                </div>
                <div class="flex flex-1 flex-col gap-2 p-5">
                    <div class="flex flex-wrap gap-2">
                        <span v-if="c.is_vip" class="rounded-full bg-lwe-orangeSoft px-2 py-0.5 text-[11px] font-bold text-amber-900">VIP</span>
                        <span v-if="c.is_masterclass" class="rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-bold text-blue-900">MASTERCLASS</span>
                        <span v-if="c.enrolled" class="rounded-full bg-lwe-greenSoft px-2 py-0.5 text-[11px] font-bold text-green-900">ENROLLED</span>
                    </div>
                    <h2 class="text-lg font-semibold text-lwe-navy group-hover:underline">{{ c.title }}</h2>
                    <p class="line-clamp-2 text-sm text-slate-600">{{ c.short_description }}</p>
                    <p v-if="c.mentors.length" class="text-xs text-lwe-muted">With {{ c.mentors.join(', ') }} · {{ c.lessons_count }} lessons</p>
                    <p class="mt-auto pt-2 text-lg font-bold" :class="c.is_free ? 'text-lwe-green' : 'text-lwe-navy'">
                        {{ c.is_free ? 'Free' : 'PKR ' + c.fee.toLocaleString() }}
                    </p>
                </div>
            </Link>
        </div>
    </AppLayout>
</template>
