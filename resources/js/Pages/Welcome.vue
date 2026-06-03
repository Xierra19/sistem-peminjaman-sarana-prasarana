<script setup>
import { Transition } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarCheck2,
    ShieldCheck,
    Sparkles,
    Mail,
    Phone,
    Eye,
    EyeOff
} from 'lucide-vue-next';
import DarkModeSwitch from '@/Components/DarkModeSwitch.vue';

const props = defineProps({
    canLogin: {
        type: Boolean,
        default: false,
    },
    canRegister: {
        type: Boolean,
        default: false,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});

const appName = 'Sistem Peminjaman Sarana & Prasarana';
const campusName = 'Universitas Esa Unggul Kampus Bekasi';
const currentYear = new Date().getFullYear();

const heroContent = {
    badge: 'Selamat Datang di Portal Peminjaman Internal',
    title: 'Peminjaman Sarana & Prasarana',
    subtitle: 'Kelola peminjaman ruangan kelas, ruang rapat, laboratorium, hingga perangkat pendukung kampus dalam satu platform terintegrasi yang modern dan efisien.',
};

const features = [
    {
        icon: CalendarCheck2,
        title: 'Peminjaman Sarana & Prasarana',
        description: 'Pesan ruang kelas, rapat, laboratorium, serta pinjam perangkat pendukung seperti meja, kursi, proyektor, dan sound system secara real-time.',
    },
    {
        icon: ShieldCheck,
        title: 'Akses Terverifikasi & Aman',
        description: 'Hanya civitas academica dengan email resmi kampus yang dapat mengakses sistem dengan enkripsi keamanan tingkat tinggi.',
    },
    {
        icon: Sparkles,
        title: 'Pengalaman Tanpa Hambatan',
        description: 'Antarmuka minimalis dengan konfirmasi instan, notifikasi otomatis, dan template surat peminjaman siap unduh.',
    },
];

const aboutBadges = [
    {
        text: 'Real-time Availability',
        classes: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-200',
    },
    {
        text: 'Konfirmasi Instan',
        classes: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-200',
    },
    {
        text: 'Akses 24/7',
        classes: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-200',
    },
];
</script>

<template>
    <Head title="Selamat Datang" />

    <div class="relative min-h-screen overflow-hidden bg-white text-slate-900 dark:bg-slate-950 dark:text-white">
        <!-- Background Blur Effects -->
        <div class="absolute inset-0">
            <div class="absolute -left-10 top-16 hidden h-64 w-64 rounded-full bg-indigo-500/20 blur-[120px] sm:block"></div>
            <div class="absolute -right-10 bottom-10 h-80 w-80 rounded-full bg-blue-500/20 blur-[140px]"></div>
            <div class="absolute inset-x-0 top-32 mx-auto h-72 w-[32rem] rounded-full bg-white/5 blur-[160px]"></div>
        </div>

        <header class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/80 backdrop-blur-sm dark:border-white/10 dark:bg-slate-950/80">
            <div class="container mx-auto flex flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3 self-start">
                    <img
                        src="/images/LOGO_UEU_BY_ASU-06.png"
                        alt="Logo Universitas Esa Unggul"
                        class="h-12 w-auto sm:h-14"
                        loading="lazy"
                    />
                    <div>
                        <p class="text-sm font-semibold text-indigo-600 dark:text-indigo-200">{{ appName }}</p>
                        <p class="text-xs font-medium text-indigo-700 dark:text-indigo-300">{{ campusName }}</p>
                    </div>
                </div>
                <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
                    <DarkModeSwitch />
                    <Link
                        v-if="props.canRegister"
                        :href="route('register')"
                        class="w-full rounded-full border border-indigo-200/20 px-4 py-2 text-center text-sm font-semibold text-indigo-200 transition hover:border-indigo-400 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 sm:w-auto"
                    >
                        Daftar Sekarang
                    </Link>
                    <Link
                        v-if="props.canLogin"
                        :href="route('login')"
                        class="w-full rounded-full bg-indigo-600 px-4 py-2 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 sm:w-auto"
                    >
                        Masuk ke Sistem
                    </Link>
                </div>
            </div>
        </header>

        <main class="relative z-10">
            <!-- Hero Section -->
            <section class="container mx-auto px-4 py-16 md:py-24">
                <Transition
                    appear
                    enter-active-class="duration-500 ease-out"
                    enter-from-class="translate-y-8 opacity-0"
                    enter-to-class="translate-y-0 opacity-100"
                >
                    <div class="relative mx-auto max-w-4xl">
                        <div class="relative overflow-hidden rounded-[32px] border border-slate-200 bg-white px-5 py-10 shadow-2xl shadow-indigo-500/20 backdrop-blur sm:rounded-[40px] sm:px-6 sm:py-12 dark:border-white/10 dark:bg-white/5">
                            <!-- Decorative Blur -->
                            <div aria-hidden="true" class="pointer-events-none absolute inset-0">
                                <div class="absolute -top-24 left-10 h-56 w-56 rounded-full bg-indigo-200/20 blur-3xl"></div>
                                <div class="absolute bottom-[-40px] right-0 h-48 w-48 rounded-full bg-purple-300/20 blur-2xl"></div>
                            </div>

                            <div class="relative space-y-6 text-center">
                                <div class="inline-flex items-center gap-2 rounded-full bg-indigo-100 px-4 py-2 text-center text-xs font-semibold text-indigo-600 shadow-sm shadow-indigo-200/60 sm:text-sm dark:bg-indigo-500/20 dark:text-indigo-200">
                                    <span class="inline-flex size-2 rounded-full bg-indigo-500"></span>
                                    {{ heroContent.badge }}
                                </div>
                                <h1 class="text-3xl font-semibold leading-tight text-slate-900 sm:text-4xl md:text-5xl lg:text-6xl dark:text-white">
                                    {{ campusName }}
                                    <span class="block bg-gradient-to-r from-indigo-400 to-purple-400 bg-clip-text text-transparent">{{ heroContent.title }}</span>
                                </h1>
                                <p class="mx-auto max-w-2xl text-sm leading-relaxed text-slate-600 sm:text-base md:text-lg dark:text-indigo-100/80">
                                    {{ heroContent.subtitle }}
                                </p>
                                <div class="flex flex-col flex-wrap items-center justify-center gap-3 pt-5 sm:flex-row">
                                    <Link
                                        v-if="props.canRegister"
                                        :href="route('register')"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-indigo-200/20 px-6 py-3 text-base font-semibold text-indigo-200 transition hover:border-indigo-400 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 sm:w-auto"
                                    >
                                        <Sparkles class="size-4 text-indigo-300" />
                                        Daftar Sekarang
                                    </Link>
                                    <Link
                                        v-if="props.canLogin"
                                        :href="route('login')"
                                        class="group relative inline-flex w-full items-center justify-center rounded-full bg-gradient-to-r from-indigo-600 to-purple-600 px-8 py-3 text-base font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:-translate-y-0.5 hover:from-indigo-700 hover:to-purple-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 sm:w-auto"
                                    >
                                        <span class="absolute inset-0 rounded-full bg-white/20 opacity-0 transition group-hover:opacity-100"></span>
                                        <span class="relative flex items-center gap-2">
                                            <svg class="size-4 text-white/90" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" viewBox="0 0 24 24">
                                                <path d="M5 12h14" />
                                                <path d="m12 5 7 7-7 7" />
                                            </svg>
                                            Masuk ke Sistem
                                        </span>
                                    </Link>
                                </div>
                                <p v-if="props.canRegister" class="text-xs font-semibold uppercase tracking-wide text-indigo-300">
                                    Registrasi hanya tersedia untuk email @student.esaunggul.ac.id.
                                </p>
                            </div>
                        </div>
                    </div>
                </Transition>
            </section>

            <!-- Features Section -->
            <section class="container mx-auto px-4 py-16">
                <div class="mx-auto max-w-6xl">
                    <div class="mb-12 text-center">
                        <h2 class="mb-4 text-2xl font-semibold text-slate-900 dark:text-white">Fitur Utama</h2>
                        <p class="mx-auto max-w-2xl text-base text-slate-600 dark:text-indigo-100/80">
                            Semua yang dibutuhkan untuk mengatur Peminjaman ruangan dan peminjaman barang kampus secara efisien dan terorganisir.
                        </p>
                    </div>
                    <div class="mx-auto grid grid-cols-1 max-w-5xl gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <article
                            v-for="feature in features"
                            :key="feature.title"
                            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:bg-slate-50 hover:shadow-lg dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10"
                        >
                            <div class="mb-4 flex size-12 items-center justify-center rounded-lg bg-indigo-500/20">
                                <component :is="feature.icon" class="size-6 text-indigo-600 dark:text-indigo-300" />
                            </div>
                            <h3 class="mb-2 text-lg font-semibold text-slate-900 dark:text-white">{{ feature.title }}</h3>
                            <p class="text-sm leading-relaxed text-slate-600 dark:text-indigo-100/80">{{ feature.description }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <!-- About Section -->
            <section class="container mx-auto px-4 pb-16">
                <div class="mx-auto max-w-4xl">
                    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-lg dark:border-white/10 dark:bg-white/5">
                        <div class="grid gap-8 md:grid-cols-2">
                            <div class="relative h-48 md:h-full">
                                <img
                                    alt="Kampus Bekasi"
                                    class="size-full object-cover"
                                    loading="lazy"
                                    src="/images/Kampus Bekasi IMG.jpg"
                                />
                                <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/10 via-transparent to-purple-600/20"></div>
                            </div>
                            <div class="flex flex-col justify-center gap-4 p-8">
                                <h2 class="text-2xl font-semibold text-slate-900 dark:text-white">Tentang Sistem Peminjaman</h2>
                                <p class="text-sm leading-relaxed text-slate-600 dark:text-indigo-100/80">
                                    Sistem Peminjaman Ruangan & Barang Internal dirancang khusus untuk civitas academica Universitas Esa Unggul Kampus Bekasi. Platform ini merapikan proses peminjaman dan pengelolaan fasilitas kampus dalam satu alur terpadu.
                                </p>
                                <p class="text-sm leading-relaxed text-slate-600 dark:text-indigo-100/80">
                                    Butuh ruang kelas tambahan, ruangan rapat, atau perangkat pendukung seperti meja, kursi, dan proyektor? Tinggal pilih fasilitasnya, ajukan, dan ikuti status persetujuannya secara real-time.
                                </p>
                                <div class="flex flex-wrap gap-2 pt-2">
                                    <span
                                        v-for="badge in aboutBadges"
                                        :key="badge.text"
                                        class="rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="badge.classes"
                                    >
                                        {{ badge.text }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="mt-16 border-t border-slate-200 bg-slate-50/80 backdrop-blur-sm dark:border-white/10 dark:bg-slate-950/80">
            <div class="container mx-auto px-4 py-8">
                <div class="grid gap-8 md:grid-cols-3">
                    <!-- Brand Info -->
                    <div class="space-y-3">
                        <p class="text-sm font-semibold text-indigo-600 dark:text-indigo-200">{{ appName }}</p>
                        <p class="text-xs leading-relaxed text-slate-600 dark:text-indigo-100/80">
                            Platform Peminjaman ruangan dan peminjaman barang kampus yang terintegrasi untuk civitas academica Universitas Esa Unggul Kampus Bekasi.
                        </p>
                    </div>

                    <!-- Navigation Links -->
                    <div class="space-y-3">
                        <p class="text-sm font-semibold text-indigo-600 dark:text-indigo-200">Navigasi</p>
                        <ul class="space-y-2 text-sm text-slate-600 dark:text-indigo-100/80">
                            <li>
                                <Link href="/" class="transition hover:text-slate-900 dark:hover:text-white">Beranda</Link>
                            </li>
                            <li>
                                <Link :href="route('login')" class="transition hover:text-slate-900 dark:hover:text-white">Masuk</Link>
                            </li>
                            <li>
                                <Link :href="route('register')" class="transition hover:text-slate-900 dark:hover:text-white">Daftar</Link>
                            </li>
                            <li>
                                <a href="#features" class="transition hover:text-slate-900 dark:hover:text-white">Fitur</a>
                            </li>
                        </ul>
                    </div>

                    <!-- Contact & Support -->
                    <div class="space-y-3">
                        <p class="text-sm font-semibold text-indigo-600 dark:text-indigo-200">Bantuan & Kontak</p>
                        <ul class="space-y-2 text-sm text-slate-600 dark:text-indigo-100/80">
                            <li class="flex items-center gap-2">
                                <Mail class="h-4 w-4 text-indigo-600 dark:text-indigo-300" />
                                <a href="mailto:leomarhadi13@gmail.com" class="transition hover:text-slate-900 dark:hover:text-white">leomarhadi13@gmail.com</a>
                            </li>
                            <li class="flex items-center gap-2">
                                <Phone class="h-4 w-4 text-indigo-600 dark:text-indigo-300" />
                                <a href="tel:+6287851327750" class="transition hover:text-slate-900 dark:hover:text-white">+62 878-5132-7750</a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Copyright -->
                <div class="mt-8 border-t border-slate-200 pt-6 text-center dark:border-white/10">
                    <p class="font-medium text-slate-600 dark:text-indigo-100/90">&copy; {{ currentYear }} {{ campusName }}. All rights reserved.</p>
                    <p class="mt-2 text-xs text-indigo-600 dark:text-indigo-300">
                        Sistem Peminjaman Sarana & Prasarana &bull; Laravel v{{ props.laravelVersion }} &middot; PHP v{{ props.phpVersion }}
                    </p>
                    <p class="mt-1 text-xs text-indigo-600 dark:text-indigo-400">
                        Dikembangkan untuk memenuhi tugas skripsi - Universitas Esa Unggul
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
