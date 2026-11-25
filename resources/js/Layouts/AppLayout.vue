<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Lwe/Icon.vue';
import InstallApp from '@/Components/Lwe/InstallApp.vue';

const props = defineProps({ title: { type: String, default: '' } });

const page = usePage();
const user = computed(() => page.props.auth.user);
const roles = computed(() => user.value?.roles ?? []);
const notifications = computed(() => page.props.notifications);

const menuOpen = ref(false);
const bellOpen = ref(false);

const studentNav = [
    { label: 'Today', route: 'student.today', icon: 'home', match: 'student.today' },
    { label: 'Courses', route: 'student.courses.index', icon: 'book', match: 'student.courses.*' },
    { label: 'Live Classes', route: 'student.classes.index', icon: 'video', match: 'student.classes.*' },
    { label: 'Tasks', route: 'student.tasks.index', icon: 'tasks', match: 'student.tasks.*' },
    { label: 'Quizzes', route: 'student.quizzes.index', icon: 'check', match: 'student.quizzes.*' },
    { label: 'Q&A Community', route: 'qa.index', icon: 'chat', match: 'qa.*' },
    { label: 'Attendance', route: 'student.attendance.index', icon: 'calendar', match: 'student.attendance.*' },
    { label: 'Skill Passport', route: 'student.passport.index', icon: 'passport', match: 'student.passport.*' },
];
const mentorNav = [
    { label: 'My Batches', route: 'mentor.dashboard', icon: 'home', match: 'mentor.*' },
    { label: 'Lessons', route: 'manage.lessons.index', icon: 'book', match: 'manage.lessons.*' },
    { label: 'Tasks', route: 'manage.tasks.index', icon: 'tasks', match: 'manage.tasks.*' },
    { label: 'Quizzes', route: 'manage.quizzes.index', icon: 'check', match: 'manage.quizzes.*' },
    { label: 'Q&A', route: 'qa.index', icon: 'chat', match: 'qa.*' },
    { label: 'Daily Attendance', route: 'manage.attendance.index', icon: 'calendar', match: 'manage.attendance.*' },
    { label: 'Results', route: 'manage.progress.index', icon: 'passport', match: 'manage.progress.*' },
    { label: 'My Public Page', route: 'manage.mentors.mine', icon: 'users', match: 'manage.mentors.*' },
];
const adminNav = [
    { label: 'Dashboard', route: 'admin.dashboard', icon: 'home', match: 'admin.dashboard' },
    { label: 'Reports', route: 'admin.reports.index', icon: 'log', match: 'admin.reports.*' },
    { label: 'Courses', route: 'admin.courses.index', icon: 'book', match: 'admin.courses.*' },
    { label: 'Lessons', route: 'admin.lessons.index', icon: 'book', match: 'admin.lessons.index' },
    { label: 'Batches', route: 'admin.batches.index', icon: 'layers', match: 'admin.batches.*' },
    { label: 'Live Classes', route: 'admin.classes.index', icon: 'video', match: 'admin.classes.*' },
    { label: 'Tasks', route: 'manage.tasks.index', icon: 'tasks', match: 'manage.tasks.*' },
    { label: 'Quizzes', route: 'manage.quizzes.index', icon: 'check', match: 'manage.quizzes.*' },
    { label: 'Q&A', route: 'qa.index', icon: 'chat', match: 'qa.*' },
    { label: 'Daily Attendance', route: 'manage.attendance.index', icon: 'calendar', match: 'manage.attendance.*' },
    { label: 'Results & Certificates', route: 'manage.progress.index', icon: 'passport', match: 'manage.progress.*' },
    { label: 'Mentors', route: 'admin.mentors.index', icon: 'users', match: 'admin.mentors.*' },
    { label: 'Franchise', route: 'admin.franchise.index', icon: 'layers', match: 'admin.franchise.*' },
    { label: 'Enrollments', route: 'admin.enrollments.index', icon: 'users', match: 'admin.enrollments.*' },
    { label: 'Payments', route: 'admin.payments.index', icon: 'money', match: 'admin.payments.*' },
    { label: 'Coupons', route: 'admin.coupons.index', icon: 'tag', match: 'admin.coupons.*' },
    { label: 'Students', route: 'admin.students.index', icon: 'users', match: 'admin.students.*' },
    { label: 'Audit Log', route: 'admin.activity.index', icon: 'log', match: 'admin.activity.*' },
];

const partnerNav = [
    { label: 'My Students', route: 'partner.dashboard', icon: 'users', match: 'partner.*' },
];

const nav = computed(() => {
    if (user.value?.is_staff) {
        const items = [...adminNav];
        if (roles.value.includes('super-admin')) {
            items.push({ label: 'Zoom Accounts', route: 'admin.zoom.index', icon: 'video', match: 'admin.zoom.*' });
            items.push({ label: 'Old Passports', route: 'admin.passports.index', icon: 'passport', match: 'admin.passports.*' });
            items.push({ label: 'Settings', route: 'admin.settings.edit', icon: 'settings', match: 'admin.settings.*' });
        }
        return items;
    }
    if (roles.value.includes('mentor')) return mentorNav;
    if (roles.value.includes('franchiser')) return partnerNav;
    return studentNav;
});

const panelName = computed(() => (user.value?.is_staff ? 'Admin Panel' : roles.value.includes('mentor') ? 'Mentor Panel' : roles.value.includes('franchiser') ? 'Partner Panel' : 'Learning Portal'));

// Flash message toast
const toast = ref(null);
watch(() => page.props.flash, (f) => {
    const msg = f?.success || f?.error || f?.status;
    if (!msg) return;
    toast.value = { text: msg, error: !!f.error };
    setTimeout(() => (toast.value = null), 5000);
}, { immediate: true, deep: true });

// On every page change: close menus and make sure the page can scroll (a popup may have locked it).
router.on('navigate', () => { menuOpen.value = false; bellOpen.value = false; document.body.style.overflow = ''; });

const initials = computed(() => (user.value?.name ?? '?').split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase());
</script>

<template>
    <Head :title="title" />
    <div class="min-h-screen bg-lwe-bg lg:flex">
        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full transform bg-lwe-navy text-white transition lg:static lg:translate-x-0"
            :class="{ 'translate-x-0': menuOpen }" aria-label="Main menu">
            <div class="flex h-full flex-col gap-1 overflow-y-auto px-4 py-6">
                <Link :href="route('dashboard')" class="mb-6 flex items-center gap-3 px-2">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-lwe-orange text-sm font-bold text-lwe-navy">LWE</span>
                    <span class="flex flex-col leading-tight">
                        <span class="font-semibold">{{ panelName }}</span>
                        <span class="text-[11px] text-slate-300">Learning with Earning</span>
                    </span>
                </Link>

                <template v-for="item in nav" :key="item.label">
                    <Link v-if="item.route" :href="route(item.route)"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm"
                        :class="route().current(item.match) ? 'bg-lwe-navy2 font-medium text-white' : 'text-slate-300 hover:bg-lwe-navy2/60 hover:text-white'">
                        <Icon :name="item.icon" /> {{ item.label }}
                    </Link>
                    <span v-else class="flex cursor-default items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-400" :title="'Coming in ' + item.soon">
                        <Icon :name="item.icon" /> {{ item.label }}
                        <span class="ml-auto rounded bg-lwe-navy2 px-1.5 py-0.5 text-[10px] text-slate-300">{{ item.soon }}</span>
                    </span>
                </template>

                <div class="mt-auto space-y-1 pt-6">
                    <Link :href="route('profile.edit')" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-300 hover:bg-lwe-navy2/60 hover:text-white">
                        <Icon name="settings" /> My profile
                    </Link>
                    <Link :href="route('logout')" method="post" as="button" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-300 hover:bg-lwe-navy2/60 hover:text-white">
                        <Icon name="logout" /> Log out
                    </Link>
                </div>
            </div>
        </aside>
        <div v-if="menuOpen" class="fixed inset-0 z-30 bg-black/40 lg:hidden" @click="menuOpen = false" />

        <!-- Main -->
        <div class="min-w-0 flex-1">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-lwe-line bg-white/95 px-4 backdrop-blur sm:px-8">
                <button type="button" class="rounded-lg p-2 hover:bg-slate-100 lg:hidden" aria-label="Open menu" @click="menuOpen = true">
                    <Icon name="menu" />
                </button>
                <div class="min-w-0 flex-1 truncate text-sm font-medium text-slate-600">{{ title }}</div>

                <InstallApp />

                <!-- Bell -->
                <div class="relative">
                    <button type="button" class="relative flex h-11 w-11 items-center justify-center rounded-lg border border-lwe-line hover:bg-slate-50"
                        :aria-label="`Notifications, ${notifications?.unread ?? 0} unread`" @click="bellOpen = !bellOpen">
                        <Icon name="bell" />
                        <span v-if="notifications?.unread" class="absolute -right-1 -top-1 rounded-full bg-red-600 px-1.5 text-[10px] font-bold text-white">
                            {{ notifications.unread > 9 ? '9+' : notifications.unread }}
                        </span>
                    </button>
                    <div v-if="bellOpen" class="absolute right-0 mt-2 w-80 overflow-hidden rounded-xl border border-lwe-line bg-white shadow-lg">
                        <div class="flex items-center justify-between border-b border-lwe-line px-4 py-3">
                            <span class="text-sm font-semibold text-lwe-navy">Notifications</span>
                            <Link :href="route('notifications.read-all')" method="post" as="button" class="text-xs font-semibold text-lwe-navy2 hover:underline">Mark all read</Link>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            <a v-for="n in notifications?.latest ?? []" :key="n.id" :href="route('notifications.open', n.id)"
                                class="block border-b border-slate-100 px-4 py-3 hover:bg-slate-50" :class="{ 'bg-amber-50/60': !n.read }">
                                <span class="block text-sm font-semibold text-slate-800">{{ n.title }}</span>
                                <span class="block text-xs text-slate-600">{{ n.body }}</span>
                                <span class="mt-1 block text-[11px] text-slate-400">{{ n.time }}</span>
                            </a>
                            <p v-if="!notifications?.latest?.length" class="px-4 py-6 text-center text-sm text-slate-500">No notifications yet.</p>
                        </div>
                        <Link :href="route('notifications.index')" class="block px-4 py-3 text-center text-xs font-semibold text-lwe-navy2 hover:bg-slate-50">See all</Link>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-lwe-navy2 text-sm font-semibold text-white">
                        <img v-if="user?.avatar_url" :src="user.avatar_url" alt="" class="h-full w-full object-cover" />
                        <template v-else>{{ initials }}</template>
                    </span>
                    <span class="hidden flex-col leading-tight sm:flex">
                        <span class="text-sm font-semibold">{{ user?.name }}</span>
                        <span class="text-[11px] text-lwe-muted">{{ user?.student_code }}</span>
                    </span>
                </div>
            </header>

            <main class="mx-auto max-w-7xl px-4 py-6 sm:px-8 sm:py-8">
                <slot />
            </main>
        </div>

        <!-- Toast -->
        <div v-if="toast" role="status" class="fixed bottom-5 right-5 z-50 max-w-sm rounded-xl px-5 py-4 text-sm font-medium shadow-lg"
            :class="toast.error ? 'bg-red-700 text-white' : 'bg-lwe-navy text-white'">
            {{ toast.text }}
        </div>
    </div>
</template>
