<script setup>
import { Transition } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarCheck2,
    FileCheck2,
    ShieldCheck,
    Sparkles,
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

const appName = 'Sistem Peminjaman Ruangan dan Barang';
const campusName = 'Universitas Esa Unggul Kampus Bekasi';
const currentYear = new Date().getFullYear();

const heroContent = {
    badge: 'Portal Peminjaman Internal',
    title: 'Sistem Peminjaman Ruangan dan Barang',
    subtitle: 'Ajukan peminjaman ruangan dan barang kampus, pantau proses persetujuan, serta kelola riwayat pengajuan dalam satu sistem.',
};

const features = [
    {
        icon: CalendarCheck2,
        title: 'Peminjaman Ruangan',
        description: 'Ajukan penggunaan ruangan berdasarkan lokasi, kapasitas, dan jadwal yang tersedia.',
    },
    {
        icon: Sparkles,
        title: 'Peminjaman Barang',
        description: 'Pilih barang, jumlah, dan jadwal penggunaan sesuai kebutuhan kegiatan kampus.',
    },
    {
        icon: ShieldCheck,
        title: 'Pemantauan Pengajuan',
        description: 'Lihat status persetujuan dan riwayat setiap pengajuan secara terpusat.',
    },
];

const aboutHighlights = [
    {
        icon: CalendarCheck2,
        title: 'Jadwal lebih terstruktur',
        description: 'Pilih waktu penggunaan sesuai ketersediaan ruangan atau barang.',
    },
    {
        icon: ShieldCheck,
        title: 'Proses mudah dipantau',
        description: 'Periksa perkembangan persetujuan setiap pengajuan dari satu tempat.',
    },
    {
        icon: FileCheck2,
        title: 'Dokumen tersimpan rapi',
        description: 'Akses dokumen pengajuan melalui akun yang telah diverifikasi.',
    },
];
</script>

<template>
    <Head title="Selamat Datang" />

    <div class="relative min-h-screen overflow-hidden bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-white">
        <!-- Background Blur Effects -->
        <div class="absolute inset-0">
            <div class="absolute -left-10 top-16 hidden h-64 w-64 rounded-full bg-indigo-400/10 blur-[120px] sm:block"></div>
            <div class="absolute -right-10 bottom-10 h-80 w-80 rounded-full bg-blue-400/10 blur-[140px]"></div>
            <div class="absolute inset-x-0 top-32 mx-auto h-72 w-[32rem] rounded-full bg-white/40 blur-[160px] dark:bg-white/5"></div>
        </div>

        <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/85 backdrop-blur-sm dark:border-white/10 dark:bg-slate-950/80">
            <div class="container mx-auto flex flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3 self-start">
                    <img
                        src="/images/LOGO_UEU_BY_ASU-06.png"
                        alt="Logo Universitas Esa Unggul"
                        class="h-12 w-auto sm:h-14"
                        loading="lazy"
                    />
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-indigo-100">{{ appName }}</p>
                        <p class="text-xs font-medium text-slate-500 dark:text-indigo-300">{{ campusName }}</p>
                    </div>
                </div>
                <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
                    <DarkModeSwitch />
                    <Link
                        v-if="props.canRegister"
                        :href="route('register')"
                        class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2 text-center text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 dark:border-white/15 dark:bg-white/5 dark:text-white dark:hover:bg-white/10 sm:w-auto"
                    >
                        Registrasi Mahasiswa
                    </Link>
                    <Link
                        v-if="props.canLogin"
                        :href="route('login')"
                        class="w-full rounded-lg bg-indigo-500 px-4 py-2 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 sm:w-auto"
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
                        <div class="relative overflow-hidden rounded-[28px] border border-slate-200 bg-white px-5 py-10 shadow-xl shadow-slate-200/80 backdrop-blur sm:px-8 sm:py-14 dark:border-white/10 dark:bg-white/5 dark:shadow-none">
                            <!-- Decorative Blur -->
                            <div aria-hidden="true" class="pointer-events-none absolute inset-0">
                                <div class="absolute -top-24 left-10 h-56 w-56 rounded-full bg-indigo-200/30 blur-3xl dark:bg-indigo-200/10"></div>
                                <div class="absolute bottom-[-40px] right-0 h-48 w-48 rounded-full bg-purple-200/30 blur-2xl dark:bg-purple-300/10"></div>
                            </div>

                            <div class="relative space-y-6 text-center">
                                <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-4 py-2 text-center text-xs font-semibold text-indigo-600 shadow-sm shadow-indigo-100 sm:text-sm dark:border-indigo-400/20 dark:bg-indigo-500/20 dark:text-indigo-200">
                                    <span class="inline-flex size-2 rounded-full bg-indigo-500"></span>
                                    {{ heroContent.badge }}
                                </div>
                                <h1 class="text-3xl font-semibold leading-tight text-slate-900 sm:text-4xl md:text-5xl dark:text-white">
                                    {{ campusName }}
                                    <span class="mt-2 block text-indigo-600 dark:text-indigo-300">{{ heroContent.title }}</span>
                                </h1>
                                <p class="mx-auto max-w-2xl text-sm leading-relaxed text-slate-600 sm:text-base md:text-lg dark:text-indigo-100/80">
                                    {{ heroContent.subtitle }}
                                </p>
                                <div class="flex flex-col flex-wrap items-center justify-center gap-3 pt-5 sm:flex-row">
                                    <Link
                                        v-if="props.canRegister"
                                        :href="route('register')"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-6 py-3 text-base font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 dark:border-white/15 dark:bg-white/5 dark:text-indigo-100 dark:hover:bg-white/10 sm:w-auto"
                                    >
                                        <Sparkles class="size-4 text-indigo-500 dark:text-indigo-300" />
                                        Registrasi Mahasiswa
                                    </Link>
                                    <Link
                                        v-if="props.canLogin"
                                        :href="route('login')"
                                        class="group relative inline-flex w-full items-center justify-center rounded-lg bg-indigo-500 px-8 py-3 text-base font-semibold text-white shadow-lg shadow-indigo-500/20 transition hover:-translate-y-0.5 hover:bg-indigo-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 sm:w-auto"
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
                                <p v-if="props.canRegister" class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-indigo-300">
                                    Registrasi hanya tersedia untuk email @student.esaunggul.ac.id.
                                </p>
                            </div>
                        </div>
                    </div>
                </Transition>
            </section>

            <!-- Features Section -->
            <section id="features" class="container mx-auto px-4 py-12 md:py-16">
                <div class="mx-auto max-w-6xl">
                    <div class="mb-12 text-center">
                        <h2 class="mb-4 text-2xl font-semibold text-slate-900 dark:text-white">Fitur Utama</h2>
                        <p class="mx-auto max-w-2xl text-base text-slate-600 dark:text-indigo-100/80">
                            Layanan utama untuk mengajukan dan memantau peminjaman fasilitas kampus.
                        </p>
                    </div>
                    <div class="mx-auto grid grid-cols-1 max-w-5xl gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <article
                            v-for="feature in features"
                            :key="feature.title"
                            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:bg-slate-50 hover:shadow-md dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10"
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
            <section class="container mx-auto px-4 pb-12">
                <div class="mx-auto max-w-6xl">
                    <div class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-xl shadow-slate-200/70 dark:border-white/10 dark:bg-white/5 dark:shadow-none">
                        <div class="grid lg:grid-cols-12">
                            <div class="relative min-h-64 overflow-hidden sm:min-h-80 lg:col-span-5 lg:min-h-[34rem]">
                                <img
                                    alt="Kampus Bekasi"
                                    class="absolute inset-0 size-full object-cover transition duration-700 hover:scale-105"
                                    loading="lazy"
                                    src="/images/esa-unggul-kampus-bekasi.webp"
                                />
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/50 via-transparent to-indigo-600/10"></div>
                                <div class="absolute inset-x-0 bottom-0 p-6 text-white sm:p-8">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-100">Universitas Esa Unggul</p>
                                    <p class="mt-2 text-lg font-semibold">Kampus Bekasi</p>
                                </div>
                            </div>
                            <div class="relative flex flex-col justify-center p-6 sm:p-10 lg:col-span-7 lg:p-12">
                                <div aria-hidden="true" class="pointer-events-none absolute right-0 top-0 size-48 rounded-full bg-indigo-200/30 blur-3xl dark:bg-indigo-500/10"></div>

                                <div class="relative">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-300">Tentang platform</p>
                                    <h2 class="mt-3 max-w-xl text-2xl font-semibold leading-tight text-slate-900 sm:text-3xl dark:text-white">
                                        Pengajuan fasilitas kampus dalam satu alur yang jelas
                                    </h2>
                                    <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-600 sm:text-base dark:text-indigo-100/80">
                                        Sistem ini membantu mahasiswa mengajukan peminjaman ruangan dan barang, mengikuti proses persetujuan, serta mengakses dokumen terkait secara terpusat.
                                    </p>

                                    <div class="mt-8 space-y-3">
                                        <article
                                            v-for="highlight in aboutHighlights"
                                            :key="highlight.title"
                                            class="flex gap-4 rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4 transition hover:border-indigo-200 hover:bg-indigo-50/70 dark:border-white/10 dark:bg-white/5 dark:hover:border-indigo-400/20 dark:hover:bg-indigo-500/10"
                                        >
                                            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300">
                                                <component :is="highlight.icon" class="size-5" />
                                            </div>
                                            <div>
                                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ highlight.title }}</h3>
                                                <p class="mt-1 text-xs leading-relaxed text-slate-600 sm:text-sm dark:text-indigo-100/70">
                                                    {{ highlight.description }}
                                                </p>
                                            </div>
                                        </article>
                                    </div>

                                    <div v-if="props.canLogin" class="mt-8">
                                        <Link
                                            :href="route('login')"
                                            class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 transition hover:text-indigo-700 dark:text-indigo-300 dark:hover:text-indigo-200"
                                        >
                                            Mulai ajukan peminjaman
                                            <svg class="size-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewBox="0 0 24 24">
                                                <path d="M5 12h14" />
                                                <path d="m13 6 6 6-6 6" />
                                            </svg>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="mt-4 border-t border-slate-200 bg-slate-50/80 backdrop-blur-sm dark:border-white/10 dark:bg-slate-950/80">
            <div class="container mx-auto max-w-6xl px-4 py-8">
                <div class="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">
                    <!-- Brand Info -->
                    <div class="max-w-2xl space-y-3">
                        <p class="text-sm font-semibold text-indigo-600 dark:text-indigo-200">{{ appName }}</p>
                        <p class="text-xs leading-relaxed text-slate-600 dark:text-indigo-100/80">
                            Sistem terintegrasi untuk pengajuan dan pengelolaan peminjaman ruangan dan barang Universitas Esa Unggul Kampus Bekasi.
                        </p>
                    </div>

                    <!-- Navigation Links -->
                    <nav class="space-y-3 md:text-right" aria-label="Navigasi footer">
                        <p class="text-sm font-semibold text-indigo-600 dark:text-indigo-200">Navigasi</p>
                        <ul class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-600 md:justify-end dark:text-indigo-100/80">
                            <li>
                                <Link href="/" class="transition hover:text-slate-900 dark:hover:text-white">Beranda</Link>
                            </li>
                            <li v-if="props.canLogin">
                                <Link :href="route('login')" class="transition hover:text-slate-900 dark:hover:text-white">Masuk</Link>
                            </li>
                            <li v-if="props.canRegister">
                                <Link :href="route('register')" class="transition hover:text-slate-900 dark:hover:text-white">Daftar</Link>
                            </li>
                            <li>
                                <a href="#features" class="transition hover:text-slate-900 dark:hover:text-white">Fitur</a>
                            </li>
                        </ul>
                    </nav>
                </div>

                <!-- Copyright -->
                <div class="mt-8 border-t border-slate-200 pt-6 text-center dark:border-white/10">
                    <p class="font-medium text-slate-600 dark:text-indigo-100/90">&copy; {{ currentYear }} {{ campusName }}. Hak cipta dilindungi.</p>
                </div>
            </div>
        </footer>
    </div>
</template>
