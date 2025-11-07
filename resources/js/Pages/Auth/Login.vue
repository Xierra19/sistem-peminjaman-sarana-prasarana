<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Mail,
    Lock,
    Eye,
    EyeOff,
    CalendarCheck2,
    ShieldCheck,
    Sparkles,
} from 'lucide-vue-next';

const showPassword = ref(false);

defineProps({
    canResetPassword: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    email: '',
    password: '',
});

const featureHighlights = [
    {
        title: 'Smart availability',
        description: 'View real-time schedules and avoid double booking with live sync across campuses.',
        icon: CalendarCheck2,
    },
    {
        title: 'Secure access',
        description: 'Only verified university accounts can access booking and approval workflows.',
        icon: ShieldCheck,
    },
    {
        title: 'Frictionless experience',
        description: 'Clean interface, keyboard shortcuts, and instant confirmations keep you moving.',
        icon: Sparkles,
    },
];

const stats = [
    { label: 'Advance notice', value: 'Submit requests ≥3 days ahead' },
    { label: 'Live booking status', value: 'Transparent in real time' },
];

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

    <div class="relative min-h-screen overflow-hidden bg-slate-950 text-white">
        <div class="absolute inset-0">
            <div class="absolute -left-10 top-16 hidden h-64 w-64 rounded-full bg-indigo-500/30 blur-[120px] sm:block"></div>
            <div class="absolute -right-10 bottom-10 h-80 w-80 rounded-full bg-blue-500/20 blur-[140px]"></div>
            <div class="absolute inset-x-0 top-32 mx-auto h-72 w-[32rem] rounded-full bg-white/5 blur-[160px]"></div>
        </div>

        <div class="relative z-10 flex min-h-screen items-center justify-center px-4 py-10 sm:px-6 lg:px-8">
            <div class="w-full max-w-5xl">
                <div class="grid gap-8 lg:grid-cols-[1.05fr_0.95fr]">
                    <section class="rounded-[32px] border border-white/10 bg-white/5 p-8 shadow-[0_20px_80px_rgba(15,23,42,0.55)] backdrop-blur">
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-indigo-200">Universitas Esa Unggul</p>
                        <h1 class="mt-4 text-4xl font-semibold leading-tight text-white">
                            Room Reservation Platform
                        </h1>
                        <p class="mt-3 text-base text-indigo-100">
                            Reserve lecture halls, labs, and collaboration rooms across the Bekasi campus with a single, beautiful workflow.
                        </p>

                        <dl class="mt-10 grid grid-cols-2 gap-4 text-left text-indigo-100">
                            <div v-for="stat in stats" :key="stat.label" class="rounded-2xl border border-white/5 bg-white/5 p-4">
                                <dt class="text-sm uppercase tracking-wide text-indigo-200">
                                    {{ stat.label }}
                                </dt>
                                <dd class="mt-2 text-3xl font-semibold text-white">
                                    {{ stat.value }}
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-10 space-y-4">
                            <div
                                v-for="feature in featureHighlights"
                                :key="feature.title"
                                class="flex gap-4 rounded-2xl border border-white/10 bg-white/5 p-4"
                            >
                                <component
                                    :is="feature.icon"
                                    class="h-11 w-11 flex-shrink-0 rounded-2xl bg-white/10 p-2 text-indigo-200"
                                />
                                <div>
                                    <p class="font-medium text-white">{{ feature.title }}</p>
                                    <p class="text-sm text-indigo-100">
                                        {{ feature.description }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[32px] bg-white p-8 text-slate-900 shadow-2xl ring-1 ring-slate-100 lg:p-10">
                        <div class="mb-8 space-y-2">
                            <p class="text-sm font-semibold uppercase tracking-[0.25em] text-indigo-500">Sign back in</p>
                            <h2 class="text-3xl font-semibold text-slate-900">
                                Welcome back
                            </h2>
                            <p class="text-sm text-slate-500">
                                Use your university email to access the booking dashboard.
                            </p>
                        </div>

                        <form class="space-y-5" @submit.prevent="submit">
                            <div>
                                <label for="email" class="text-sm font-medium text-slate-700">Email address</label>
                                <div class="relative mt-2">
                                    <Mail class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        autocomplete="username"
                                        placeholder="nama@student.esaunggul.ac.id"
                                        class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/60 pl-12 pr-4 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                                        required
                                    />
                                </div>
                                <p v-if="form.errors.email" class="mt-2 text-sm text-rose-500">
                                    {{ form.errors.email }}
                                </p>
                            </div>

                            <div>
                                <label for="password" class="text-sm font-medium text-slate-700">Password</label>
                                <div class="relative mt-2">
                                    <Lock class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                                    <input
                                        id="password"
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        autocomplete="current-password"
                                        placeholder="●●●●●●●●"
                                        class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/60 pl-12 pr-12 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                                        required
                                    />
                                    <button
                                        type="button"
                                        class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-slate-600"
                                        @click="showPassword = !showPassword"
                                    >
                                        <EyeOff v-if="showPassword" class="h-5 w-5" />
                                        <Eye v-else class="h-5 w-5" />
                                    </button>
                                </div>
                                <p v-if="form.errors.password" class="mt-2 text-sm text-rose-500">
                                    {{ form.errors.password }}
                                </p>
                            </div>

                            <div class="flex items-center justify-between text-sm text-slate-500">
                                <p>Protected by UEU single sign on.</p>
                                <Link
                                    v-if="canResetPassword"
                                    :href="route('password.request')"
                                    class="font-medium text-indigo-600 transition hover:text-indigo-500"
                                >
                                    Forgot password?
                                </Link>
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="flex h-12 w-full items-center justify-center rounded-2xl bg-indigo-600 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:bg-indigo-500 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-indigo-200 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <span v-if="form.processing">Signing in…</span>
                                <span v-else>Sign in</span>
                            </button>
                        </form>

                        <p class="mt-8 text-center text-sm text-slate-500">
                            Need an account?
                            <Link :href="route('register')" class="font-semibold text-indigo-600 hover:text-indigo-500">
                                Create one here
                            </Link>
                        </p>

                        <p class="mt-4 text-center text-xs text-slate-400">
                            <Link href="/" class="font-semibold text-indigo-500 hover:text-indigo-400">
                                Kembali ke beranda
                            </Link>
                        </p>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>
