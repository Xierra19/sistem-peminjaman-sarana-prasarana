<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Mail, RefreshCcw, ShieldCheck } from 'lucide-vue-next';

const props = defineProps({
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({
    email: '',
});

const infoCards = [
    {
        title: 'Tautan berlaku 15 menit',
        description: 'Keamanan tinggi: tautan reset kedaluwarsa otomatis dan dapat diminta kembali kapan saja.',
        icon: RefreshCcw,
    },
    {
        title: 'Email kampus terverifikasi',
        description: 'Kami hanya memproses akun @student.esaunggul.ac.id untuk melindungi akses ruang.',
        icon: ShieldCheck,
    },
];

const steps = [
    'Masukkan email kampus yang terdaftar.',
    'Periksa inbox / folder spam untuk email dari UEU Booking.',
    'Klik tautan reset dan buat kata sandi baru.',
];

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <Head title="Lupa Password" />

    <div class="relative min-h-screen overflow-hidden bg-slate-950 text-white">
        <div class="absolute inset-0">
            <div class="absolute -left-16 top-16 hidden h-72 w-72 rounded-full bg-indigo-500/25 blur-[160px] md:block"></div>
            <div class="absolute -right-16 bottom-10 h-96 w-96 rounded-full bg-blue-500/30 blur-[180px]"></div>
        </div>

        <div class="relative z-10 flex min-h-screen items-center justify-center px-4 py-10 sm:px-6 lg:px-10">
            <div class="w-full max-w-5xl">
                <div class="grid gap-8 lg:grid-cols-[1.05fr_0.95fr]">
                    <section class="rounded-[32px] border border-white/10 bg-gradient-to-b from-white/15 to-white/5 p-6 shadow-[0_25px_90px_rgba(15,23,42,0.55)] backdrop-blur sm:p-8">
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-indigo-200">Pemulihan akun</p>
                        <h1 class="mt-4 text-4xl font-semibold leading-tight text-white">
                            Reset kata sandi dengan aman
                        </h1>
                        <p class="mt-3 text-base text-indigo-100">
                            Kami akan mengirim tautan reset unik ke email kampus Anda. Tautan tersebut hanya aktif selama beberapa menit untuk mencegah penyalahgunaan.
                        </p>

                        <div class="mt-10 space-y-4">
                            <div
                                v-for="card in infoCards"
                                :key="card.title"
                                class="flex gap-4 rounded-2xl border border-white/10 bg-white/5 p-4"
                            >
                                <component
                                    :is="card.icon"
                                    class="h-11 w-11 flex-shrink-0 rounded-2xl bg-white/10 p-2 text-indigo-100"
                                />
                                <div>
                                    <p class="font-medium text-white">{{ card.title }}</p>
                                    <p class="text-sm text-indigo-100">{{ card.description }}</p>
                                </div>
                            </div>
                        </div>

                        <ul class="mt-10 space-y-3 text-sm text-indigo-100">
                            <li
                                v-for="step in steps"
                                :key="step"
                                class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/5 p-3"
                            >
                                <span class="mt-1 h-2 w-2 rounded-full bg-sky-300"></span>
                                <span>{{ step }}</span>
                            </li>
                        </ul>
                    </section>

                    <section class="rounded-[32px] bg-white p-6 text-slate-900 shadow-2xl ring-1 ring-slate-100 sm:p-8 lg:p-10">
                        <div class="mb-8 space-y-2">
                            <p class="text-sm font-semibold uppercase tracking-[0.35em] text-indigo-500">Lupa password</p>
                            <h2 class="text-3xl font-semibold text-slate-900">Kirim ulang tautan reset</h2>
                            <p class="text-sm text-slate-500">
                                Masukkan email kampus Anda dan kami akan kirim instruksi untuk membuat kata sandi baru.
                            </p>
                        </div>

                        <div
                            v-if="props.status"
                            class="mb-6 rounded-2xl border border-emerald-100 bg-emerald-50 p-4 text-sm font-medium text-emerald-800"
                        >
                            {{ props.status }}
                        </div>

                        <form class="space-y-5" @submit.prevent="submit">
                            <div>
                                <label for="email" class="text-sm font-medium text-slate-700">Email kampus</label>
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
                                        autofocus
                                    />
                                </div>
                                <p v-if="form.errors.email" class="mt-2 text-sm text-rose-500">
                                    {{ form.errors.email }}
                                </p>
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="flex h-12 w-full items-center justify-center rounded-2xl bg-indigo-600 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:bg-indigo-500 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-indigo-200 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <span v-if="form.processing">Mengirim tautan…</span>
                                <span v-else>Kirim Tautan Reset Password </span>
                            </button>
                        </form>

                        <p class="mt-8 text-center text-sm text-slate-500">
                            Ingat kata sandi?
                            <Link :href="route('login')" class="font-semibold text-indigo-600 hover:text-indigo-500">
                                Kembali ke halaman masuk
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
