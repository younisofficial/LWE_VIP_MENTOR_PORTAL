<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Field from '@/Components/Lwe/Field.vue';
import AiButton from '@/Components/Lwe/AiButton.vue';
import AiMini from '@/Components/Lwe/AiMini.vue';
import axios from 'axios';

const props = defineProps({ course: Object, step: String, options: Object });

const c = props.course ?? {};
const labels = { basic: 'Basic info', details: 'Details', skills: 'Skills', fee: 'Fee', completion: 'Completion rules', publish: 'Publish' };
const stepIndex = computed(() => props.options.steps.indexOf(props.step));

// One form object holds every field; each step only sends its own fields.
const form = useForm({
    step: props.step,
    // basic
    title: c.title ?? '', short_description: c.short_description ?? '', category: c.category ?? '', level: c.level ?? 'beginner',
    is_vip: !!c.is_vip, is_masterclass: !!c.is_masterclass, mentor_ids: c.mentor_ids ?? [], thumbnail: null,
    // details
    description: c.description ?? '', what_you_learn: c.what_you_learn?.length ? [...c.what_you_learn] : [''],
    requirements: c.requirements?.length ? [...c.requirements] : [''], intro_video_url: c.intro_video_url ?? '',
    faqs: c.faqs?.length ? c.faqs.map((f) => ({ ...f })) : [{ q: '', a: '' }],
    // skills
    skill_names: c.skill_names ? [...c.skill_names] : [],
    // fee
    is_free: props.course ? c.is_free : false, fee: c.fee ? Number(c.fee) : '', coupons_allowed: c.coupons_allowed ?? true,
    // completion
    min_attendance_percent: c.min_attendance_percent ?? 75, require_compulsory_tasks: c.require_compulsory_tasks ?? true,
    min_quiz_percent: c.min_quiz_percent ?? 50, weight_attendance: c.weight_attendance ?? 30, weight_tasks: c.weight_tasks ?? 50, weight_quiz: c.weight_quiz ?? 20,
    // publish
    status: c.status ?? 'draft',
    batch_ids: c.batch_ids ? [...c.batch_ids] : [], enroll_existing: true,
});

const fieldsFor = {
    basic: ['title', 'short_description', 'category', 'level', 'is_vip', 'is_masterclass', 'mentor_ids', 'thumbnail'],
    details: ['description', 'what_you_learn', 'requirements', 'intro_video_url', 'faqs'],
    skills: ['skill_names'],
    fee: ['is_free', 'fee', 'coupons_allowed'],
    completion: ['min_attendance_percent', 'require_compulsory_tasks', 'min_quiz_percent', 'weight_attendance', 'weight_tasks', 'weight_quiz'],
    publish: ['status', 'batch_ids', 'enroll_existing'],
};

const weightTotal = computed(() => Number(form.weight_attendance) + Number(form.weight_tasks) + Number(form.weight_quiz));
const newSkill = ref('');
const addSkill = () => {
    const s = newSkill.value.trim();
    if (s && !form.skill_names.includes(s)) form.skill_names.push(s);
    newSkill.value = '';
};

// ---- AI Course Builder ----
// One AI call drafts every field of the wizard. The draft is kept for this browser tab,
// so the next steps fill instantly without paying for another AI call.
const ai = ref({ notes: '', language: 'roman_urdu', loading: false, error: '', done: '', draft: null });
const stepFields = {
    basic: ['short_description', 'category', 'level'],
    details: ['description', 'what_you_learn', 'requirements', 'faqs'],
    skills: ['skills'],
};
const cacheKey = () => 'lwe-ai-draft:' + form.title.trim().toLowerCase() + ':' + ai.value.language;
const loadCached = () => {
    try { return JSON.parse(sessionStorage.getItem(cacheKey()) || 'null'); } catch { return null; }
};
ai.value.draft = form.title ? loadCached() : null;

const getDraft = async (fresh = false) => {
    if (!form.title.trim()) {
        ai.value.error = 'Write the course title first — AI uses it.';
        return null;
    }
    if (!fresh) {
        const cached = ai.value.draft ?? loadCached();
        if (cached) return (ai.value.draft = cached);
    }
    ai.value.loading = true;
    ai.value.error = '';
    try {
        const { data } = await axios.post(route('admin.ai.course-draft'), {
            title: form.title, level: form.level, short_description: form.short_description,
            notes: ai.value.notes, language: ai.value.language,
        });
        ai.value.draft = data;
        try { sessionStorage.setItem(cacheKey(), JSON.stringify(data)); } catch { /* storage full or blocked: fine */ }
        return data;
    } catch (e) {
        ai.value.error = e.response?.data?.message ?? e.response?.data?.errors?.title?.[0] ?? 'AI could not write this right now.';
        return null;
    } finally {
        ai.value.loading = false;
    }
};

const applyField = (d, field) => {
    switch (field) {
        case 'short_description': form.short_description = d.short_description; break;
        case 'category': form.category = d.category; break;
        case 'level': form.level = d.level; break;
        case 'description': form.description = d.description; break;
        case 'what_you_learn': form.what_you_learn = d.what_you_learn.length ? [...d.what_you_learn] : ['']; break;
        case 'requirements': form.requirements = d.requirements.length ? [...d.requirements] : ['']; break;
        case 'faqs': form.faqs = d.faqs.length ? d.faqs.map((f) => ({ ...f })) : [{ q: '', a: '' }]; break;
        case 'skills': d.skills.forEach((x) => { if (!form.skill_names.includes(x)) form.skill_names.push(x); }); break;
    }
};

/** Fill one field. */
const aiFill = async (field) => {
    ai.value.done = '';
    const d = await getDraft();
    if (d) applyField(d, field);
};

/** Fill every field of this step. `fresh` = ask AI again for a new version. */
const aiFillStep = async (fresh = false) => {
    ai.value.done = '';
    const d = await getDraft(fresh);
    if (!d) return;
    (stepFields[props.step] ?? []).forEach((f) => applyField(d, f));
    ai.value.done = 'AI filled this step. Read it, change anything you like, then press the orange button to save.';
};

const submit = () => {
    // Inertia keeps this page alive between steps, so the step must come from the current props,
    // not from the form (which still holds the step the page was first opened on).
    const step = props.step;
    form.step = step;
    const keys = fieldsFor[step];
    const payload = (data) => ({ ...Object.fromEntries(Object.entries(data).filter(([k]) => keys.includes(k))), step });
    if (!props.course) {
        form.transform(payload).post(route('admin.courses.store'), { forceFormData: true });
    } else if (step === 'basic') {
        // POST + _method=PUT so the thumbnail upload works
        form.transform((d) => ({ ...payload(d), _method: 'put' })).post(route('admin.courses.update', props.course.id), {
            forceFormData: true, preserveScroll: true,
            onError: () => setTimeout(() => document.getElementById('wizard-errors')?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 50),
        });
    } else {
        form.transform(payload).put(route('admin.courses.update', props.course.id), {
            preserveScroll: true,
            onError: () => setTimeout(() => document.getElementById('wizard-errors')?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 50),
        });
    }
};
</script>

<template>
    <AppLayout :title="course ? 'Edit course' : 'New course'">
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <Link :href="route('admin.courses.index')" class="text-sm font-semibold text-lwe-navy2 hover:underline">← Courses</Link>
                <h1 class="page-title mt-1">{{ course ? course.title : 'Create a course' }}</h1>
            </div>
            <Link v-if="course" :href="route('admin.courses.curriculum', course.id)" class="btn-dark">Lessons ({{ course.lessons_count }})</Link>
        </div>

        <!-- Stepper -->
        <ol class="mb-6 flex flex-wrap gap-2" aria-label="Steps">
            <li v-for="(s, i) in options.steps" :key="s">
                <component :is="course && (i <= course.wizard_step - 1) ? Link : 'span'"
                    :href="course ? route('admin.courses.edit', { course: course.id, step: s }) : undefined"
                    class="flex items-center gap-2 rounded-full px-3 py-2 text-xs font-semibold"
                    :class="s === step ? 'bg-lwe-orangeSoft text-amber-900 ring-2 ring-lwe-orange' : i < stepIndex || (course && i < course.wizard_step - 1) ? 'bg-lwe-navy text-white' : 'bg-slate-200 text-slate-600'"
                    :aria-current="s === step ? 'step' : undefined">
                    <span>{{ i + 1 }}</span> {{ labels[s] }}
                </component>
            </li>
        </ol>

        <form class="card max-w-4xl space-y-5" @submit.prevent="submit">
            <div v-if="Object.keys(form.errors).length" id="wizard-errors" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold">Save nahi hua — ye theek karein:</p>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="(msg, key) in form.errors" :key="key">{{ msg }}</li>
                </ul>
            </div>

            <!-- AI helper: basic, details and skills steps (also before the course is saved) -->
            <div v-if="stepFields[step]" class="rounded-xl border border-violet-200 bg-violet-50 p-4">
                <p class="text-sm font-semibold text-violet-900">AI Course Builder</p>
                <p class="mb-3 text-xs text-violet-800">
                    Course title likhein (aur chahein to 1–2 lines notes). Phir <b>Fill this step with AI</b> dabayein —
                    ya kisi bhi field ke saath wala chhota <b>AI</b> button dabayein. AI sirf draft likhta hai; save aap karte hain.
                </p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <label class="sr-only" for="ainotes">Notes for AI</label>
                    <textarea id="ainotes" v-model="ai.notes" rows="2" class="textarea sm:col-span-3"
                        placeholder="e.g. Beginners ke liye, Pakistan se Amazon par selling, 8 hafte, live classes + practical tasks" />
                    <div class="flex flex-col gap-2">
                        <label class="sr-only" for="ailang">Language</label>
                        <select id="ailang" v-model="ai.language" class="input">
                            <option value="roman_urdu">Roman Urdu</option><option value="english">English</option><option value="urdu">اردو</option>
                        </select>
                        <AiButton :loading="ai.loading" label="Fill this step with AI" @click="aiFillStep(false)" />
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                    <p v-if="ai.done" class="text-xs font-semibold text-green-800">{{ ai.done }}</p>
                    <p v-if="ai.error" class="text-xs font-semibold text-red-700">{{ ai.error }}</p>
                    <button v-if="ai.draft" type="button" class="ml-auto text-xs font-semibold text-violet-900 underline" :disabled="ai.loading" @click="aiFillStep(true)">
                        Not happy? Write a new version
                    </button>
                </div>
            </div>

            <!-- 1. Basic -->
            <template v-if="step === 'basic'">
                <Field label="Course title" for="title" :error="form.errors.title">
                    <input id="title" v-model="form.title" type="text" class="input" required />
                </Field>
                <Field label="Short description (shown on course cards)" for="short" :error="form.errors.short_description" ai :ai-loading="ai.loading" @ai="aiFill('short_description')">
                    <input id="short" v-model="form.short_description" type="text" maxlength="300" class="input" />
                </Field>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Category" for="category" :error="form.errors.category" ai :ai-loading="ai.loading" @ai="aiFill('category')">
                        <input id="category" v-model="form.category" list="categories" type="text" class="input" placeholder="e.g. E-commerce" />
                        <datalist id="categories"><option v-for="cat in options.categories" :key="cat" :value="cat" /></datalist>
                    </Field>
                    <Field label="Level" for="level" :error="form.errors.level" ai :ai-loading="ai.loading" @ai="aiFill('level')">
                        <select id="level" v-model="form.level" class="input"><option value="beginner">Beginner</option><option value="intermediate">Intermediate</option><option value="advanced">Advanced</option></select>
                    </Field>
                </div>
                <fieldset>
                    <legend class="label">Mentor(s)</legend>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="m in options.mentors" :key="m.id" class="flex cursor-pointer items-center gap-2 rounded-lg border border-lwe-line px-3 py-2 text-sm">
                            <input v-model="form.mentor_ids" type="checkbox" :value="m.id" class="rounded" /> {{ m.name }}
                        </label>
                        <p v-if="!options.mentors.length" class="text-sm text-lwe-muted">No mentors yet — give a user the "mentor" role first.</p>
                    </div>
                    <p v-if="form.errors.mentor_ids" class="mt-1 text-xs text-red-700">{{ form.errors.mentor_ids }}</p>
                </fieldset>
                <div class="flex flex-wrap gap-6">
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.is_vip" type="checkbox" class="rounded" /> VIP course</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.is_masterclass" type="checkbox" class="rounded" /> Masterclass / workshop</label>
                </div>
                <Field label="Thumbnail image" for="thumb" :error="form.errors.thumbnail" hint="16:9 image, up to 4 MB">
                    <img v-if="course?.thumbnail_url" :src="course.thumbnail_url" alt="Current thumbnail" class="mb-2 h-24 rounded-lg object-cover" />
                    <input id="thumb" type="file" accept="image/*" class="block text-sm" @input="form.thumbnail = $event.target.files[0]" />
                </Field>
            </template>

            <!-- 2. Details -->
            <template v-if="step === 'details'">
                <Field label="Full description" for="desc" :error="form.errors.description" ai :ai-loading="ai.loading" @ai="aiFill('description')">
                    <textarea id="desc" v-model="form.description" rows="8" class="textarea" />
                </Field>
                <fieldset>
                    <legend class="label flex w-full items-center justify-between">What students will learn <AiMini :loading="ai.loading" @click="aiFill('what_you_learn')" /></legend>
                    <div v-for="(item, i) in form.what_you_learn" :key="'w' + i" class="mb-2 flex gap-2">
                        <input v-model="form.what_you_learn[i]" type="text" class="input" :aria-label="'Point ' + (i + 1)" />
                        <button type="button" class="btn-outline btn-sm" @click="form.what_you_learn.splice(i, 1)">Remove</button>
                    </div>
                    <button type="button" class="text-sm font-semibold text-lwe-navy2 hover:underline" @click="form.what_you_learn.push('')">+ Add point</button>
                </fieldset>
                <fieldset>
                    <legend class="label flex w-full items-center justify-between">Requirements <AiMini :loading="ai.loading" @click="aiFill('requirements')" /></legend>
                    <div v-for="(item, i) in form.requirements" :key="'r' + i" class="mb-2 flex gap-2">
                        <input v-model="form.requirements[i]" type="text" class="input" :aria-label="'Requirement ' + (i + 1)" />
                        <button type="button" class="btn-outline btn-sm" @click="form.requirements.splice(i, 1)">Remove</button>
                    </div>
                    <button type="button" class="text-sm font-semibold text-lwe-navy2 hover:underline" @click="form.requirements.push('')">+ Add requirement</button>
                </fieldset>
                <Field label="Intro video link (YouTube/Vimeo)" for="intro" :error="form.errors.intro_video_url">
                    <input id="intro" v-model="form.intro_video_url" type="url" class="input" />
                </Field>
                <fieldset>
                    <legend class="label flex w-full items-center justify-between">FAQs <AiMini :loading="ai.loading" @click="aiFill('faqs')" /></legend>
                    <div v-for="(f, i) in form.faqs" :key="'f' + i" class="mb-3 space-y-2 rounded-lg border border-lwe-line p-3">
                        <input v-model="f.q" type="text" class="input" placeholder="Question" :aria-label="'Question ' + (i + 1)" />
                        <textarea v-model="f.a" rows="2" class="textarea" placeholder="Answer" :aria-label="'Answer ' + (i + 1)" />
                        <button type="button" class="text-xs text-red-700 hover:underline" @click="form.faqs.splice(i, 1)">Remove</button>
                    </div>
                    <button type="button" class="text-sm font-semibold text-lwe-navy2 hover:underline" @click="form.faqs.push({ q: '', a: '' })">+ Add FAQ</button>
                </fieldset>
            </template>

            <!-- 3. Skills -->
            <template v-if="step === 'skills'">
                <div class="flex items-center justify-between gap-2"><p class="muted">These skills are verified on the student's Skill Passport when they complete the course.</p><AiMini :loading="ai.loading" label="AI skills" @click="aiFill('skills')" /></div>
                <div class="flex flex-wrap gap-2">
                    <span v-for="(s, i) in form.skill_names" :key="s" class="flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1.5 text-sm font-semibold text-lwe-navy">
                        {{ s }} <button type="button" class="text-slate-500 hover:text-red-700" :aria-label="'Remove ' + s" @click="form.skill_names.splice(i, 1)">✕</button>
                    </span>
                </div>
                <div class="flex gap-2">
                    <label for="skill" class="sr-only">Add a skill</label>
                    <input id="skill" v-model="newSkill" list="skills" type="text" class="input" placeholder="Type a skill, e.g. Product Research" @keydown.enter.prevent="addSkill" />
                    <datalist id="skills"><option v-for="s in options.skills" :key="s" :value="s" /></datalist>
                    <button type="button" class="btn-outline" @click="addSkill">Add</button>
                </div>
            </template>

            <!-- 4. Fee -->
            <template v-if="step === 'fee'">
                <label class="flex items-center gap-3 rounded-xl border border-lwe-line p-4">
                    <input v-model="form.is_free" type="checkbox" class="rounded" />
                    <span><span class="block font-semibold">This is a free course</span><span class="text-sm text-lwe-muted">Students are enrolled instantly with no payment.</span></span>
                </label>
                <Field v-if="!form.is_free" label="Fee (PKR)" for="fee" :error="form.errors.fee">
                    <input id="fee" v-model="form.fee" type="number" min="1" class="input max-w-xs" required />
                </Field>
                <label v-if="!form.is_free" class="flex items-center gap-2 text-sm"><input v-model="form.coupons_allowed" type="checkbox" class="rounded" /> Allow coupon / scholarship codes</label>
                <p class="text-xs text-lwe-muted">Installment plans arrive in Module 5.</p>
            </template>

            <!-- 5. Completion -->
            <template v-if="step === 'completion'">
                <p class="muted">A student completes the course (and gets it on their Skill Passport) only when all rules pass.</p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Minimum attendance %" for="att" :error="form.errors.min_attendance_percent"><input id="att" v-model="form.min_attendance_percent" type="number" min="0" max="100" class="input" /></Field>
                    <Field label="Minimum final quiz %" for="quiz" :error="form.errors.min_quiz_percent"><input id="quiz" v-model="form.min_quiz_percent" type="number" min="0" max="100" class="input" /></Field>
                </div>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.require_compulsory_tasks" type="checkbox" class="rounded" /> All compulsory tasks must be approved</label>
                <fieldset>
                    <legend class="label">Score weightage (must total 100)</legend>
                    <div class="grid grid-cols-3 gap-4">
                        <Field label="Attendance" for="wa"><input id="wa" v-model="form.weight_attendance" type="number" min="0" max="100" class="input" /></Field>
                        <Field label="Tasks" for="wt"><input id="wt" v-model="form.weight_tasks" type="number" min="0" max="100" class="input" /></Field>
                        <Field label="Quiz" for="wq"><input id="wq" v-model="form.weight_quiz" type="number" min="0" max="100" class="input" /></Field>
                    </div>
                    <p class="mt-2 text-sm font-semibold" :class="weightTotal === 100 ? 'text-lwe-green' : 'text-red-700'">Total: {{ weightTotal }}</p>
                    <p v-if="form.errors.weight_attendance" class="text-xs text-red-700">{{ form.errors.weight_attendance }}</p>
                </fieldset>
            </template>

            <!-- 6. Publish -->
            <template v-if="step === 'publish'">
                <fieldset class="space-y-3">
                    <legend class="label">Who can see this course?</legend>
                    <label class="flex gap-3 rounded-xl border p-4" :class="form.status === 'draft' ? 'border-lwe-navy bg-slate-50' : 'border-lwe-line'">
                        <input v-model="form.status" type="radio" value="draft" class="mt-1" /><span><b class="block">Draft</b><span class="text-sm text-lwe-muted">Only admins can see it.</span></span>
                    </label>
                    <label class="flex gap-3 rounded-xl border p-4" :class="form.status === 'published' ? 'border-lwe-navy bg-slate-50' : 'border-lwe-line'">
                        <input v-model="form.status" type="radio" value="published" class="mt-1" /><span><b class="block">Published</b><span class="text-sm text-lwe-muted">Every student can see it and enroll.</span></span>
                    </label>
                    <label class="flex gap-3 rounded-xl border p-4" :class="form.status === 'private' ? 'border-lwe-navy bg-slate-50' : 'border-lwe-line'">
                        <input v-model="form.status" type="radio" value="private" class="mt-1" /><span><b class="block">Private</b><span class="text-sm text-lwe-muted">Hidden from the list; only students you enroll can open it.</span></span>
                    </label>
                </fieldset>
                <p v-if="form.errors.status" class="text-sm text-red-700">{{ form.errors.status }}</p>
                <fieldset class="space-y-2">
                    <legend class="label">Add this course to batches <span class="font-normal text-lwe-muted">(optional — one batch can have many courses)</span></legend>
                    <div v-if="options.batches.length" class="grid max-h-56 grid-cols-1 gap-2 overflow-y-auto rounded-xl border border-lwe-line p-3 sm:grid-cols-2">
                        <label v-for="b in options.batches" :key="b.id" class="flex items-center gap-2 text-sm">
                            <input v-model="form.batch_ids" type="checkbox" :value="b.id" class="rounded" />
                            <span>{{ b.name }} <span class="text-xs text-lwe-muted">· {{ b.courses_count }} courses</span></span>
                        </label>
                    </div>
                    <p v-else class="text-sm text-lwe-muted">No running or upcoming batch yet. Create one in <Link :href="route('admin.batches.index')" class="font-semibold underline">Batches</Link> and tick all its courses there.</p>
                    <label v-if="form.batch_ids.length" class="flex items-start gap-2 rounded-xl bg-blue-50 p-3 text-sm">
                        <input v-model="form.enroll_existing" type="checkbox" class="mt-0.5 rounded" />
                        <span>Give this course to the students already in these batches <span class="block text-xs text-lwe-muted">They get it free and active, with no new payment.</span></span>
                    </label>
                    <p v-if="form.errors.batch_ids" class="text-sm text-red-700">{{ form.errors.batch_ids }}</p>
                </fieldset>
            </template>

            <div class="flex items-center justify-between border-t border-lwe-line pt-5">
                <Link v-if="course && stepIndex > 0" :href="route('admin.courses.edit', { course: course.id, step: options.steps[stepIndex - 1] })" class="btn-outline">Back</Link>
                <span v-else />
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ step === 'publish' ? 'Save course' : course ? 'Save and continue' : 'Create course' }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
