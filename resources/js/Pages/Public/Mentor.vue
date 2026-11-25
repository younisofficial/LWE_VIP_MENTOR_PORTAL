<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps({ mentor: Object, courses: Array, portalStudents: Number });

const video = computed(() => {
    const u = props.mentor.intro_video_url || '';
    const yt = u.match(/(?:youtu\.be\/|v=|embed\/|shorts\/)([\w-]{11})/);
    if (yt) return `https://www.youtube-nocookie.com/embed/${yt[1]}?rel=0`;
    const v = u.match(/vimeo\.com\/(\d+)/);
    return v ? `https://player.vimeo.com/video/${v[1]}` : null;
});
const wa = computed(() => (props.mentor.whatsapp ? `https://wa.me/${props.mentor.whatsapp.replace(/\D/g, '')}?text=${encodeURIComponent('Assalam o Alaikum, I saw ' + props.mentor.name + "'s page and want to know about the courses.")}` : null));
const socialNames = { linkedin: 'LinkedIn', youtube: 'YouTube', facebook: 'Facebook', instagram: 'Instagram', tiktok: 'TikTok', website: 'Website' };
</script>

<template>
    <PublicLayout :title="`${mentor.name} — Mentor`">
        <p v-if="mentor.preview" class="mb-4 rounded-xl bg-lwe-orangeSoft p-3 text-sm font-semibold text-amber-900">Preview — this page is hidden from the public until it is published.</p>

        <section class="relative overflow-hidden rounded-3xl bg-lwe-navy text-white">
            <img v-if="mentor.cover" :src="mentor.cover" alt="" class="absolute inset-0 h-full w-full object-cover opacity-25" />
            <div class="relative flex flex-col gap-8 p-8 md:flex-row md:items-center md:p-12">
                <img v-if="mentor.photo" :src="mentor.photo" :alt="mentor.name" class="h-44 w-44 shrink-0 rounded-3xl object-cover ring-4 ring-lwe-orange" />
                <div class="flex-1">
                    <p class="text-sm uppercase tracking-widest text-lwe-orange">Mentor at Learning with Earning</p>
                    <h1 class="mt-1 text-4xl font-bold md:text-5xl">{{ mentor.name }}</h1>
                    <p v-if="mentor.headline" class="mt-3 text-lg text-white/85">{{ mentor.headline }}</p>
                    <div class="mt-4 flex flex-wrap gap-2"><span v-for="e in mentor.expertise" :key="e" class="rounded-full bg-white/10 px-3 py-1 text-sm">{{ e }}</span></div>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a v-if="wa" :href="wa" target="_blank" rel="noopener" class="btn-primary">Talk on WhatsApp</a>
                        <a href="#courses" class="btn border-2 border-white text-white">See courses</a>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="mentor.highlights.length || portalStudents" class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4">
            <div v-for="h in mentor.highlights" :key="h.label" class="card text-center"><p class="text-3xl font-bold text-lwe-navy">{{ h.value }}</p><p class="text-sm text-lwe-muted">{{ h.label }}</p></div>
            <div v-if="portalStudents" class="card text-center"><p class="text-3xl font-bold text-lwe-navy">{{ portalStudents }}</p><p class="text-sm text-lwe-muted">students on the portal</p></div>
        </section>

        <section class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-5">
            <div class="card lg:col-span-3"><h2 class="mb-3 text-xl font-bold text-lwe-navy">About</h2><p class="whitespace-pre-line leading-relaxed text-slate-700">{{ mentor.bio }}</p>
                <div v-if="Object.keys(mentor.socials).length" class="mt-4 flex flex-wrap gap-2"><a v-for="(url, k) in mentor.socials" :key="k" :href="url" target="_blank" rel="noopener" class="btn-outline btn-sm">{{ socialNames[k] ?? k }}</a></div>
            </div>
            <div v-if="video" class="lg:col-span-2"><div class="aspect-video overflow-hidden rounded-2xl bg-black"><iframe :src="video" class="h-full w-full" :title="`${mentor.name} introduction`" allow="fullscreen; picture-in-picture" allowfullscreen /></div></div>
        </section>

        <section v-if="mentor.stories.length" class="mt-10">
            <h2 class="mb-4 text-2xl font-bold text-lwe-navy">Student success stories</h2>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
                <article v-for="s in mentor.stories" :key="s.name + s.story" class="card flex flex-col gap-3">
                    <div class="flex items-center gap-3">
                        <img v-if="s.image_url" :src="s.image_url" :alt="s.name" class="h-14 w-14 rounded-full object-cover" loading="lazy" />
                        <span v-else class="flex h-14 w-14 items-center justify-center rounded-full bg-lwe-orangeSoft text-xl font-bold text-lwe-navy">{{ s.name[0] }}</span>
                        <div><p class="font-semibold">{{ s.name }}</p><p v-if="s.role" class="text-xs text-lwe-muted">{{ s.role }}</p></div>
                    </div>
                    <p class="text-sm leading-relaxed text-slate-700">{{ s.story }}</p>
                    <p v-if="s.result" class="mt-auto rounded-xl bg-lwe-greenSoft p-2 text-sm font-semibold text-green-900">🏆 {{ s.result }}</p>
                </article>
            </div>
        </section>

        <section id="courses" class="mt-10">
            <h2 class="mb-4 text-2xl font-bold text-lwe-navy">Courses by {{ mentor.name }}</h2>
            <p v-if="!courses.length" class="card text-sm text-lwe-muted">New courses coming soon.</p>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
                <Link v-for="c in courses" :key="c.slug" :href="route('student.courses.show', c.slug)" class="card block hover:ring-2 hover:ring-lwe-orange">
                    <img v-if="c.thumbnail" :src="c.thumbnail" :alt="c.title" class="mb-3 aspect-video w-full rounded-xl object-cover" loading="lazy" />
                    <p v-if="c.is_vip" class="text-xs font-bold text-lwe-orange">VIP</p>
                    <h3 class="font-bold text-lwe-navy">{{ c.title }}</h3>
                    <p class="text-sm text-slate-600">{{ c.short_description }}</p>
                    <p class="mt-2 font-semibold">{{ c.fee }}</p>
                </Link>
            </div>
        </section>
    </PublicLayout>
</template>
