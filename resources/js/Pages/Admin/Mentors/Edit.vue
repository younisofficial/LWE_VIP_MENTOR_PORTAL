<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import Field from '@/Components/Lwe/Field.vue';
import AiButton from '@/Components/Lwe/AiButton.vue';
import AiMini from '@/Components/Lwe/AiMini.vue';

const props = defineProps({ mentor: Object, profile: Object, isSelf: Boolean });

const socialKeys = ['linkedin', 'youtube', 'facebook', 'instagram', 'tiktok', 'website'];
const form = useForm({
    slug: props.profile.slug,
    headline: props.profile.headline ?? '',
    bio: props.profile.bio ?? '',
    intro_video_url: props.profile.intro_video_url ?? '',
    whatsapp: props.profile.whatsapp ?? '',
    is_published: props.profile.is_published,
    expertise: [...props.profile.expertise],
    highlights: props.profile.highlights.map((h) => ({ ...h })),
    socials: Object.fromEntries(socialKeys.map((k) => [k, props.profile.socials?.[k] ?? ''])),
    success_stories: props.profile.success_stories.map((s) => ({ name: s.name, role: s.role ?? '', story: s.story, result: s.result ?? '', image_path: s.image_path ?? null })),
    story_images: props.profile.success_stories.map(() => null),
    photo: null,
    cover: null,
    // Landing page: up to 3 price packages, review videos, FAQs, SEO and ads
    packages: (props.profile.packages ?? []).map((p) => ({ ...p, price: p.price ?? '', old_price: p.old_price ?? '', features_text: (p.features ?? []).join('\n'), featured: !!p.featured })),
    review_videos: (props.profile.review_videos ?? []).map((v) => ({ ...v })),
    faqs: (props.profile.faqs ?? []).map((f) => ({ ...f })),
    seo_title: props.profile.seo_title ?? '',
    seo_description: props.profile.seo_description ?? '',
    pixel_id: props.profile.pixel_id ?? '',
    cta_note: props.profile.cta_note ?? '',
    // Page v2: journey line, outcomes, platforms, timeline, offer countdown, seats
    journey: (props.profile.journey ?? []).map((j) => ({ title: j.title, points: [...(j.points ?? []), '', '', ''].slice(0, 3) })),
    outcomes: (props.profile.outcomes ?? []).map((o) => ({ ...o, text: o.text ?? '' })),
    platforms_text: (props.profile.platforms ?? []).join(', '),
    timeline: (props.profile.timeline ?? []).map((t) => ({ ...t })),
    offer_ends_at: props.profile.offer_ends_at ?? '',
    seats_total: props.profile.seats_total ?? '',
    seats_left: props.profile.seats_left ?? '',
});
const moveStage = (i, d) => { const j = i + d; if (j < 0 || j >= form.journey.length) return; const a = form.journey; [a[i], a[j]] = [a[j], a[i]]; };
const packageNames = ['Starter', 'Professional', 'VIP Mentorship'];
const addPackage = () => form.packages.push({ name: packageNames[form.packages.length] ?? 'Package', tagline: '', old_price: '', price: '', features_text: '', badge: form.packages.length === 1 ? 'Most popular' : '', link: '', featured: form.packages.length === 1 });
const setFeatured = (i) => form.packages.forEach((p, j) => (p.featured = i === j));
// Prices may be typed as "7,000" or "Rs 7000": keep only the digits.
const num = (v) => { const d = String(v ?? '').replace(/[^0-9]/g, ''); return d === '' ? null : Number(d); };
const off = (p) => { const o = num(p.old_price), n = num(p.price); return o && n !== null && o > n ? Math.round((1 - n / o) * 100) : 0; };
const pkgError = (i, field) => form.errors[`packages.${i}.${field}`];
const storyPreviews = ref(props.profile.success_stories.map((s) => s.image_url));
const photoPreview = ref(props.profile.photo_url);
const coverPreview = ref(props.profile.cover_url);

const newTag = ref('');
const addTag = () => { const t = newTag.value.trim(); if (t && !form.expertise.includes(t)) form.expertise.push(t); newTag.value = ''; };
const addHighlight = () => form.highlights.push({ value: '', label: '' });
const addStory = () => { form.success_stories.push({ name: '', role: '', story: '', result: '', image_path: null }); form.story_images.push(null); storyPreviews.value.push(null); };
const removeStory = (i) => { form.success_stories.splice(i, 1); form.story_images.splice(i, 1); storyPreviews.value.splice(i, 1); };
const pickStoryImage = (i, e) => { const f = e.target.files[0]; form.story_images[i] = f ?? null; storyPreviews.value[i] = f ? URL.createObjectURL(f) : storyPreviews.value[i]; };
const pick = (field, e, preview) => { const f = e.target.files[0]; form[field] = f ?? null; if (f) preview.value = URL.createObjectURL(f); };

const save = () => form
    .transform(({ platforms_text, ...d }) => ({
        ...d,
        socials: Object.fromEntries(Object.entries(d.socials).filter(([, v]) => v)),
        packages: d.packages.map(({ features_text, ...p }) => ({ ...p, price: num(p.price) ?? '', old_price: num(p.old_price) ?? '', features: (features_text ?? '').split('\n').map((x) => x.trim()).filter(Boolean) })),
        platforms: platforms_text.split(',').map((x) => x.trim()).filter(Boolean),
        journey: d.journey.map((j) => ({ title: j.title, points: j.points.filter((x) => x && x.trim()) })),
    }))
    .post(route('manage.mentors.update', props.mentor.id), { preserveScroll: true, forceFormData: true });

// AI page writer
const ai = ref({ notes: '', language: 'english', loading: false, error: '' });
const writePage = async () => {
    ai.value.loading = true; ai.value.error = '';
    try {
        const { data } = await axios.post(route('manage.mentors.ai', props.mentor.id), { notes: ai.value.notes, language: ai.value.language });
        form.headline = data.headline;
        form.bio = data.bio;
        if (data.expertise.length) form.expertise = data.expertise;
        if (data.highlights.length) form.highlights = data.highlights;
    } catch (e) {
        ai.value.error = e.response?.data?.message ?? e.response?.data?.errors?.notes?.[0] ?? 'AI could not write this now.';
    } finally {
        ai.value.loading = false;
    }
};
const storyBusy = ref(null);
const polishStory = async (i) => {
    const s = form.success_stories[i];
    if (!s.story || s.story.length < 15) return;
    storyBusy.value = i;
    try {
        const { data } = await axios.post(route('manage.mentors.story-ai', props.mentor.id), { rough: `${s.name}${s.role ? ' (' + s.role + ')' : ''}: ${s.story} ${s.result}`, language: ai.value.language });
        s.story = data.story;
        if (data.result) s.result = data.result;
    } catch { /* keep text */ } finally {
        storyBusy.value = null;
    }
};
</script>

<template>
    <AppLayout :title="`${mentor.name} — public page`">
        <Link v-if="!isSelf" :href="route('admin.mentors.index')" class="text-sm font-semibold text-lwe-navy2 hover:underline">← Mentors</Link>
        <div class="mt-2 mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div><h1 class="page-title">{{ isSelf ? 'My public page' : mentor.name + ' — public page' }}</h1><p class="muted">{{ profile.public_url }}</p></div>
            <div class="flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="form.is_published" type="checkbox" class="rounded" /> Show this page publicly</label>
                <a :href="profile.public_url" target="_blank" class="btn-outline">Preview</a>
                <button type="button" class="btn-primary" :disabled="form.processing" @click="save">Save</button>
            </div>
        </div>
        <p v-if="Object.keys(form.errors).length" class="mb-4 rounded-xl bg-red-50 p-3 text-sm font-semibold text-red-800" role="alert">{{ Object.values(form.errors)[0] }}</p>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <section class="ai rounded-2xl border border-violet-200 bg-violet-50 p-4">
                    <p class="font-semibold text-violet-900">AI Page Writer</p>
                    <p class="mb-2 text-xs text-violet-900">Write a few facts (experience, results, what they teach). AI writes the headline, bio, expertise and highlight numbers — only from your facts.</p>
                    <textarea v-model="ai.notes" rows="3" class="textarea" aria-label="Notes for AI" placeholder="e.g. 8 saal se Amazon FBA kar rahe hain, 3 apne brands, 2,000+ students train kiye, Best Mentor award 2024…" />
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <select v-model="ai.language" class="input w-36" aria-label="Language"><option value="english">English</option><option value="roman_urdu">Roman Urdu</option><option value="urdu">اردو</option></select>
                        <AiButton :loading="ai.loading" label="Write the page" @click="writePage" />
                        <p v-if="ai.error" class="text-xs font-semibold text-red-700">{{ ai.error }}</p>
                    </div>
                </section>

                <section class="card space-y-4">
                    <Field label="Headline (one line under the name)" for="hl" :error="form.errors.headline"><input id="hl" v-model="form.headline" type="text" class="input" maxlength="200" /></Field>
                    <Field label="About / bio" for="bio" :error="form.errors.bio"><textarea id="bio" v-model="form.bio" rows="7" class="textarea" /></Field>
                    <div>
                        <p class="label">Expertise</p>
                        <div class="flex flex-wrap gap-2"><span v-for="(t, i) in form.expertise" :key="t" class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-3 py-1 text-sm font-semibold text-lwe-navy">{{ t }}<button type="button" :aria-label="`Remove ${t}`" @click="form.expertise.splice(i, 1)">✕</button></span></div>
                        <div class="mt-2 flex gap-2"><input v-model="newTag" type="text" class="input" placeholder="e.g. Amazon PPC" aria-label="New expertise" @keydown.enter.prevent="addTag" /><button type="button" class="btn-outline" @click="addTag">Add</button></div>
                    </div>
                    <div>
                        <p class="label">Highlight numbers <span class="font-normal text-lwe-muted">(only true numbers, e.g. "2,000+" · "students trained")</span></p>
                        <div v-for="(h, i) in form.highlights" :key="i" class="mb-2 flex gap-2">
                            <input v-model="h.value" type="text" class="input w-32" placeholder="2,000+" :aria-label="`Highlight ${i + 1} number`" />
                            <input v-model="h.label" type="text" class="input" placeholder="students trained" :aria-label="`Highlight ${i + 1} label`" />
                            <button type="button" class="btn-outline" aria-label="Remove" @click="form.highlights.splice(i, 1)">✕</button>
                        </div>
                        <button v-if="form.highlights.length < 6" type="button" class="text-sm font-semibold text-lwe-navy2 hover:underline" @click="addHighlight">+ Highlight</button>
                    </div>
                </section>

                <section class="card space-y-4">
                    <div class="flex items-center justify-between"><h2 class="font-semibold text-lwe-navy">Student success stories <span class="text-xs font-normal text-lwe-muted">(not shown on the new page — use review videos)</span></h2><button type="button" class="btn-outline btn-sm" @click="addStory">+ Story</button></div>
                    <p class="text-xs text-lwe-muted">Only real stories, with the student's permission. Write it roughly — the AI button cleans it up without adding anything.</p>
                    <div v-for="(s, i) in form.success_stories" :key="i" class="rounded-xl border border-lwe-line p-4">
                        <div class="flex gap-4">
                            <label class="shrink-0 cursor-pointer">
                                <img v-if="storyPreviews[i]" :src="storyPreviews[i]" alt="Student photo" class="h-20 w-20 rounded-full object-cover" />
                                <span v-else class="flex h-20 w-20 items-center justify-center rounded-full bg-slate-100 text-xs text-lwe-muted">Photo</span>
                                <input type="file" accept="image/*" class="sr-only" @change="pickStoryImage(i, $event)" />
                            </label>
                            <div class="grid flex-1 grid-cols-1 gap-2 sm:grid-cols-2">
                                <input v-model="s.name" type="text" class="input" placeholder="Student name" :aria-label="`Story ${i + 1} name`" />
                                <input v-model="s.role" type="text" class="input" placeholder="e.g. Amazon seller, Multan" :aria-label="`Story ${i + 1} role`" />
                                <div class="relative sm:col-span-2">
                                    <textarea v-model="s.story" rows="3" class="textarea pr-12" placeholder="Their story" :aria-label="`Story ${i + 1}`" />
                                    <span class="absolute right-2 top-2"><AiMini :loading="storyBusy === i" @click="polishStory(i)" /></span>
                                </div>
                                <input v-model="s.result" type="text" class="input sm:col-span-2" placeholder="Result, e.g. First $1,000 sale in 2 months" :aria-label="`Story ${i + 1} result`" />
                            </div>
                        </div>
                        <button type="button" class="mt-2 text-xs font-semibold text-red-700 hover:underline" @click="removeStory(i)">Remove story</button>
                    </div>
                </section>
                <section class="card space-y-4">
                    <div class="flex items-center justify-between">
                        <div><h2 class="font-semibold text-lwe-navy">Packages &amp; prices</h2><p class="text-xs text-lwe-muted">Up to 3. Old price shows crossed out with the % off. The middle one is usually "Most popular".</p></div>
                        <button v-if="form.packages.length < 3" type="button" class="btn-outline btn-sm" @click="addPackage">+ Package</button>
                    </div>
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div v-for="(p, i) in form.packages" :key="i" class="space-y-2 border p-3" :class="p.featured ? 'border-lwe-orange bg-orange-50/40' : 'border-lwe-line'">
                            <input v-model="p.name" type="text" class="input font-semibold" placeholder="Package name" :aria-label="`Package ${i + 1} name`" />
                            <input v-model="p.tagline" type="text" class="input text-sm" placeholder="One line, e.g. For complete beginners" :aria-label="`Package ${i + 1} tagline`" />
                            <div class="grid grid-cols-2 gap-2">
                                <label class="text-xs font-semibold text-lwe-muted">Old price (PKR)<input v-model="p.old_price" type="text" inputmode="numeric" class="input mt-1" placeholder="10000" /></label>
                                <label class="text-xs font-semibold text-lwe-muted">Price now (PKR) *<input v-model="p.price" type="text" inputmode="numeric" class="input mt-1" :class="pkgError(i, 'price') ? 'border-red-600' : ''" placeholder="7000" /></label>
                            </div>
                            <p v-if="pkgError(i, 'price') || pkgError(i, 'name')" class="text-xs font-semibold text-red-700">{{ pkgError(i, 'price') || pkgError(i, 'name') }}</p>
                            <p v-if="off(p)" class="text-xs font-bold text-lwe-green">{{ off(p) }}% off — save PKR {{ (num(p.old_price) - num(p.price)).toLocaleString() }}</p>
                            <label class="block text-xs font-semibold text-lwe-muted">What's included <span class="font-normal">(one per line)</span>
                                <textarea v-model="p.features_text" rows="5" class="textarea mt-1 text-sm" placeholder="12 live classes&#10;Lifetime recordings&#10;Private WhatsApp group" />
                            </label>
                            <input v-model="p.badge" type="text" class="input text-sm" placeholder="Badge, e.g. Most popular" :aria-label="`Package ${i + 1} badge`" />
                            <input v-model="p.link" type="url" class="input text-sm" placeholder="Button link (optional — empty = WhatsApp)" :aria-label="`Package ${i + 1} link`" />
                            <div class="flex items-center justify-between pt-1">
                                <label class="flex items-center gap-2 text-xs font-semibold"><input type="radio" :checked="p.featured" name="featured" @change="setFeatured(i)" /> Highlight</label>
                                <button type="button" class="text-xs font-semibold text-red-700 hover:underline" @click="form.packages.splice(i, 1)">Remove</button>
                            </div>
                        </div>
                    </div>
                    <p v-if="!form.packages.length" class="text-sm text-lwe-muted">No packages yet. Add 3 — e.g. Starter, Professional, VIP Mentorship.</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <Field label="Offer ends (countdown)" for="oe" :error="form.errors.offer_ends_at" hint="Pakistan time. Empty = no countdown."><input id="oe" v-model="form.offer_ends_at" type="datetime-local" class="input" /></Field>
                        <Field label="Total seats" for="stt" :error="form.errors.seats_total"><input id="stt" v-model="form.seats_total" type="number" min="1" class="input" placeholder="40" /></Field>
                        <Field label="Seats left" for="stl" :error="form.errors.seats_left" hint="Shown as “12 of 40”."><input id="stl" v-model="form.seats_left" type="number" min="0" class="input" placeholder="12" /></Field>
                    </div>
                    <Field label="Offer line above the packages (optional)" for="cta" :error="form.errors.cta_note"><input id="cta" v-model="form.cta_note" type="text" class="input" maxlength="150" placeholder="e.g. Next batch starts 1 December — only 40 seats" /></Field>
                </section>

                <section class="card space-y-4">
                    <div class="flex items-center justify-between">
                        <div><h2 class="font-semibold text-lwe-navy">Course roadmap (animated)</h2><p class="text-xs text-lwe-muted">The whole course, stage by stage — 10 to 16 stages work best. Each stage branches out on the page. Short title + up to 3 short points.</p></div>
                        <button v-if="form.journey.length < 16" type="button" class="btn-outline btn-sm" @click="form.journey.push({ title: '', points: ['', '', ''] })">+ Stage</button>
                    </div>
                    <div v-for="(j, i) in form.journey" :key="i" class="grid grid-cols-1 gap-2 border border-lwe-line p-3 sm:grid-cols-[3rem_1fr]">
                        <div class="flex items-center gap-1 sm:flex-col">
                            <span class="flex h-9 w-9 items-center justify-center bg-lwe-navy text-xs font-bold text-white">{{ String(i + 1).padStart(2, '0') }}</span>
                            <button type="button" class="text-xs font-bold text-lwe-navy2" :aria-label="`Move stage ${i + 1} up`" @click="moveStage(i, -1)">↑</button>
                            <button type="button" class="text-xs font-bold text-lwe-navy2" :aria-label="`Move stage ${i + 1} down`" @click="moveStage(i, 1)">↓</button>
                        </div>
                        <div class="space-y-2">
                            <input v-model="j.title" type="text" class="input font-semibold" maxlength="40" placeholder="e.g. Product Research" :aria-label="`Stage ${i + 1} title`" />
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                <input v-for="(pt, k) in j.points" :key="k" v-model="j.points[k]" type="text" class="input text-sm" maxlength="60" :placeholder="['Find demand with data', 'Check competition', 'Pick your first product'][k]" :aria-label="`Stage ${i + 1} point ${k + 1}`" />
                            </div>
                            <button type="button" class="text-xs font-semibold text-red-700 hover:underline" @click="form.journey.splice(i, 1)">Remove stage</button>
                        </div>
                    </div>
                </section>

                <section class="card space-y-3">
                    <h2 class="font-semibold text-lwe-navy">Where students work</h2>
                    <Field label="Students now work on (comma separated)" for="pf" :error="form.errors.platforms" hint="Shown as a strip under the video, e.g. Amazon, Daraz, Shopify, Fiverr"><input id="pf" v-model="form.platforms_text" type="text" class="input" /></Field>
                </section>

                <section class="card space-y-4">
                    <div class="flex items-center justify-between">
                        <div><h2 class="font-semibold text-lwe-navy">Mentor's story (timeline)</h2><p class="text-xs text-lwe-muted">A few real milestones, oldest first.</p></div>
                        <button v-if="form.timeline.length < 8" type="button" class="btn-outline btn-sm" @click="form.timeline.push({ year: '', text: '' })">+ Milestone</button>
                    </div>
                    <div v-for="(t, i) in form.timeline" :key="i" class="flex gap-2">
                        <input v-model="t.year" type="text" class="input w-24" maxlength="12" placeholder="2020" :aria-label="`Milestone ${i + 1} year`" />
                        <input v-model="t.text" type="text" class="input" maxlength="140" placeholder="What happened" :aria-label="`Milestone ${i + 1}`" />
                        <button type="button" class="btn-outline" aria-label="Remove" @click="form.timeline.splice(i, 1)">✕</button>
                    </div>
                </section>

                <section class="card space-y-4">
                    <div class="flex items-center justify-between">
                        <div><h2 class="font-semibold text-lwe-navy">Student review videos</h2><p class="text-xs text-lwe-muted">YouTube links (Unlisted is fine). They load only when tapped, so the page stays fast.</p></div>
                        <button v-if="form.review_videos.length < 12" type="button" class="btn-outline btn-sm" @click="form.review_videos.push({ url: '', name: '', title: '' })">+ Video</button>
                    </div>
                    <div v-for="(v, i) in form.review_videos" :key="i" class="grid grid-cols-1 gap-2 border border-lwe-line p-3 sm:grid-cols-3">
                        <input v-model="v.url" type="url" class="input sm:col-span-3" placeholder="https://youtu.be/…" :aria-label="`Video ${i + 1} link`" />
                        <input v-model="v.name" type="text" class="input" placeholder="Student name" :aria-label="`Video ${i + 1} student`" />
                        <input v-model="v.title" type="text" class="input" placeholder="e.g. First sale in 30 days" :aria-label="`Video ${i + 1} title`" />
                        <button type="button" class="text-left text-xs font-semibold text-red-700 hover:underline" @click="form.review_videos.splice(i, 1)">Remove</button>
                    </div>
                </section>

                <section class="card space-y-4">
                    <div class="flex items-center justify-between">
                        <div><h2 class="font-semibold text-lwe-navy">Questions &amp; answers (FAQ)</h2><p class="text-xs text-lwe-muted">Answer what people ask on WhatsApp. Google can show these in search results.</p></div>
                        <button v-if="form.faqs.length < 12" type="button" class="btn-outline btn-sm" @click="form.faqs.push({ q: '', a: '' })">+ Question</button>
                    </div>
                    <div v-for="(f, i) in form.faqs" :key="i" class="space-y-2 border border-lwe-line p-3">
                        <input v-model="f.q" type="text" class="input font-semibold" placeholder="e.g. Kya beginners join kar sakte hain?" :aria-label="`Question ${i + 1}`" />
                        <textarea v-model="f.a" rows="2" class="textarea" placeholder="Answer" :aria-label="`Answer ${i + 1}`" />
                        <button type="button" class="text-xs font-semibold text-red-700 hover:underline" @click="form.faqs.splice(i, 1)">Remove</button>
                    </div>
                </section>
            </div>

            <div class="space-y-6">
                <section class="card space-y-3">
                    <p class="label">Photo</p>
                    <img v-if="photoPreview" :src="photoPreview" alt="Mentor photo" class="aspect-square w-full rounded-2xl object-cover" />
                    <input type="file" accept="image/*" class="block text-sm" aria-label="Photo" @change="pick('photo', $event, photoPreview)" />
                    <p class="label mt-4">Cover image (optional)</p>
                    <img v-if="coverPreview" :src="coverPreview" alt="Cover" class="aspect-video w-full rounded-xl object-cover" />
                    <input type="file" accept="image/*" class="block text-sm" aria-label="Cover image" @change="pick('cover', $event, coverPreview)" />
                </section>
                <section class="card space-y-3">
                    <Field label="Page address" for="sl" :error="form.errors.slug" hint="learningwithearning.org/mentors/…"><input id="sl" v-model="form.slug" type="text" class="input" /></Field>
                    <Field label="Intro video (YouTube / Vimeo)" for="iv" :error="form.errors.intro_video_url"><input id="iv" v-model="form.intro_video_url" type="url" class="input" placeholder="https://" /></Field>
                    <Field label="WhatsApp for enquiries" for="wa" :error="form.errors.whatsapp"><input id="wa" v-model="form.whatsapp" type="tel" class="input" placeholder="0300 1234567" /></Field>
                    <Field v-for="k in socialKeys" :key="k" :label="k.charAt(0).toUpperCase() + k.slice(1)" :for="`so-${k}`" :error="form.errors[`socials.${k}`]"><input :id="`so-${k}`" v-model="form.socials[k]" type="url" class="input" placeholder="https://" /></Field>
                </section>
                <section class="card space-y-3">
                    <p class="font-semibold text-lwe-navy">Google &amp; ads</p>
                    <Field label="Google title" for="st" :error="form.errors.seo_title" :hint="`${form.seo_title.length}/60 — empty = name + headline`"><input id="st" v-model="form.seo_title" type="text" class="input" maxlength="70" /></Field>
                    <Field label="Google description" for="sd" :error="form.errors.seo_description" :hint="`${form.seo_description.length}/160 — what people see under the link`"><textarea id="sd" v-model="form.seo_description" rows="3" class="textarea" maxlength="170" /></Field>
                    <Field label="Extra Meta Pixel for this page (optional)" for="mp" :error="form.errors.pixel_id" hint="The site-wide pixel in Settings always runs too."><input id="mp" v-model="form.pixel_id" type="text" inputmode="numeric" class="input" /></Field>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
