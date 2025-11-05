<script setup>
import { Transition } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

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

const appName = 'Sistem Booking Ruangan Kampus';
const campusName = 'Universitas Esa Unggul Kampus Bekasi';
const currentYear = new Date().getFullYear();

const features = [
    {
        icon: 'calendar',
        badgeClass: 'bg-blue-100',
        iconClass: 'text-blue-600',
        title: 'Easy Booking',
        description: 'Proses pemesanan ruangan yang cepat dan intuitif dengan informasi ketersediaan real-time.',
    },
    {
        icon: 'clock',
        badgeClass: 'bg-purple-100',
        iconClass: 'text-purple-600',
        title: 'Time Management',
        description: 'Kelola jadwal pemakaian ruangan dengan slot waktu fleksibel sesuai kebutuhan kegiatan.',
    },
    {
        icon: 'users',
        badgeClass: 'bg-orange-100',
        iconClass: 'text-orange-600',
        title: 'User Friendly',
        description: 'Antarmuka sederhana yang dirancang untuk dosen, staf, dan mahasiswa.',
    },
    {
        icon: 'notification',
        badgeClass: 'bg-sky-100',
        iconClass: 'text-sky-600',
        title: 'Notification Email',
        description: 'Pemberitahuan email otomatis menjaga pemohon dan admin selalu mengetahui status terbaru.',
    },
    {
        icon: 'document',
        badgeClass: 'bg-emerald-100',
        iconClass: 'text-emerald-600',
        title: 'Penyuratan Mudah',
        description: 'Template surat dan lampiran siap pakai sehingga dokumen bisa diunduh hanya dalam satu klik.',
    },
];

const aboutBadges = [
    {
        text: 'Real-time Availability',
        classes: 'bg-blue-100 text-blue-700',
    },
    {
        text: 'Instant Confirmation',
        classes: 'bg-purple-100 text-purple-700',
    },
    {
        text: '24/7 Access',
        classes: 'bg-green-100 text-green-700',
    },
];
</script>

<template>
    <Head title="Selamat Datang" />

    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50">
        <header class="sticky top-0 z-10 border-b bg-white/80 backdrop-blur-sm">
            <div class="container mx-auto flex items-center justify-between gap-3 px-4 py-4">
                <div class="flex items-center gap-3">
                    <img
                        src="/images/LOGO_UEU_BY_ASU-06.png"
                        alt="Logo Universitas Esa Unggul"
                        class="h-12 w-auto sm:h-14"
                        loading="lazy"
                    />
                    <div>
                        <p class="text-sm font-semibold text-blue-700">{{ appName }}</p>
                        <p class="text-xs font-medium text-blue-500">{{ campusName }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        v-if="props.canLogin"
                        :href="route('login')"
                        class="rounded-full border border-blue-200 px-4 py-2 text-sm font-semibold text-blue-600 transition hover:border-blue-400 hover:text-blue-700"
                    >
                        Masuk
                    </Link>
                </div>
            </div>
        </header>

        <main>
            <section class="container mx-auto px-4 py-16 md:py-24">
                <Transition
                    appear
                    enter-active-class="duration-500 ease-out"
                    enter-from-class="translate-y-8 opacity-0"
                    enter-to-class="translate-y-0 opacity-100"
                >
                    <div class="relative mx-auto max-w-4xl">
                        <div aria-hidden="true" class="absolute inset-0 -z-10">
                            <div class="absolute -left-16 top-0 h-48 w-48 rounded-full bg-blue-500/20 blur-3xl"></div>
                            <div class="absolute right-[-10%] top-[-20%] h-60 w-60 rounded-full bg-purple-400/25 blur-[120px]"></div>
                            <div class="absolute bottom-[-25%] left-[15%] h-56 w-56 rounded-full bg-cyan-300/20 blur-[120px]"></div>
                        </div>
                        <div class="relative overflow-hidden rounded-[40px] border border-blue-100/70 bg-white/80 px-6 py-12 shadow-2xl shadow-blue-500/20 backdrop-blur">
                            <div aria-hidden="true" class="pointer-events-none absolute inset-0">
                                <div class="absolute -top-24 left-10 h-56 w-56 rounded-full bg-blue-200/20 blur-3xl"></div>
                                <div class="absolute bottom-[-40px] right-0 h-48 w-48 rounded-full bg-purple-300/20 blur-2xl"></div>
                                <div class="absolute left-1/2 top-1/2 h-32 w-32 -translate-x-1/2 -translate-y-1/2 rounded-full bg-white/40 blur-2xl"></div>
                            </div>
                            <div class="relative space-y-6 text-center">
                                <div class="inline-flex items-center gap-2 rounded-full bg-blue-100 px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm shadow-blue-200/60">
                                    <span class="inline-flex size-2 rounded-full bg-blue-500"></span>
                                    Selamat datang di Sistem Booking Ruangan Internal
                                </div>
                                <h1 class="text-4xl font-semibold text-blue-900 md:text-5xl lg:text-6xl">
                                    {{ campusName }}
                                    <span class="block bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Room Reservation</span>
                                </h1>
                                <p class="mx-auto max-w-2xl text-lg leading-relaxed text-blue-700/80">
                                    Permudah pengalaman peminjaman ruangan dengan sistem reservasi internal yang modern dan efisien. Booking ruang kelas, ruang rapat,
                                    dan fasilitas kampus hanya dengan beberapa klik.
                                </p>
                                <div class="flex flex-wrap justify-center gap-3 pt-5">
                                    <Link
                                        v-if="props.canLogin"
                                        :href="route('login')"
                                        class="group relative inline-flex items-center justify-center rounded-full bg-gradient-to-r from-blue-600 to-purple-600 px-8 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 transition hover:-translate-y-0.5 hover:from-blue-700 hover:to-purple-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-400"
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
                            </div>
                        </div>
                    </div>
                </Transition>
            </section>

            <section class="container mx-auto px-4 py-16">
                <div class="mx-auto max-w-6xl">
                    <div class="mb-12 text-center">
                        <h2 class="mb-4 text-2xl font-semibold text-blue-900">Fitur Utama</h2>
                        <p class="mx-auto max-w-2xl text-base text-blue-700/80">
                            Semua yang dibutuhkan untuk mengatur reservasi ruangan kampus secara efisien dan terorganisir.
                        </p>
                    </div>
                    <div class="mx-auto grid max-w-5xl gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <article
                            v-for="feature in features"
                            :key="feature.title"
                            class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm transition hover:shadow-lg"
                        >
                            <div class="mb-4 flex size-12 items-center justify-center rounded-lg" :class="feature.badgeClass">
                                <svg
                                    v-if="feature.icon === 'calendar'"
                                    class="size-6"
                                    :class="feature.iconClass"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.6"
                                    viewBox="0 0 24 24"
                                >
                                    <rect width="18" height="18" x="3" y="4" rx="2" ry="2" />
                                    <path d="M16 2v4" />
                                    <path d="M8 2v4" />
                                    <path d="M3 10h18" />
                                    <path d="M8 14h0.01" />
                                    <path d="M12 14h0.01" />
                                    <path d="M16 14h0.01" />
                                </svg>
                                <svg
                                    v-else-if="feature.icon === 'clock'"
                                    class="size-6"
                                    :class="feature.iconClass"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.6"
                                    viewBox="0 0 24 24"
                                >
                                    <circle cx="12" cy="12" r="10" />
                                    <path d="M12 6v6l4 2" />
                                </svg>
                                <svg
                                    v-else-if="feature.icon === 'users'"
                                    class="size-6"
                                    :class="feature.iconClass"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.6"
                                    viewBox="0 0 24 24"
                                >
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                                <svg
                                    v-else-if="feature.icon === 'notification'"
                                    class="size-6"
                                    :class="feature.iconClass"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.6"
                                    viewBox="0 0 24 24"
                                >
                                    <path d="M18 8a6 6 0 1 0-12 0v5a3 3 0 0 1-1 2.24L4 16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2l-.99-.76A3 3 0 0 1 18 13z" />
                                    <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                                </svg>
                                <svg
                                    v-else-if="feature.icon === 'document'"
                                    class="size-6"
                                    :class="feature.iconClass"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.6"
                                    viewBox="0 0 24 24"
                                >
                                    <path d="M7 2h8l5 5v13a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z" />
                                    <path d="M12 2v6h6" />
                                    <path d="M9 13h6" />
                                    <path d="M9 17h3" />
                                </svg>
                            </div>
                            <h3 class="mb-2 text-lg font-semibold text-blue-900">{{ feature.title }}</h3>
                            <p class="text-sm leading-relaxed text-blue-700/80">{{ feature.description }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="container mx-auto px-4 pb-16">
                <div class="mx-auto max-w-4xl">
                    <div class="overflow-hidden rounded-3xl border border-white bg-white/70 shadow-lg">
                        <div class="grid gap-8 md:grid-cols-2">
                            <div class="relative h-64 md:h-full">
                                <img
                                    alt="Kampus Bekasi"
                                    class="size-full object-cover"
                                    loading="lazy"
                                    src="https://images.unsplash.com/photo-1670284768187-5cc68eada1b3?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxtb2Rlcm4lMjB1bml2ZXJzaXR5JTIwY2FtcHVzJTIwYnVpbGRpbmd8ZW58MXx8fHwxNzYyMzU3NTc0fDA&ixlib=rb-4.1.0&q=80&w=1080"
                                />
                                <div class="absolute inset-0 bg-gradient-to-br from-blue-600/10 via-transparent to-purple-600/20"></div>
                            </div>
                            <div class="flex flex-col justify-center gap-4 p-8">
                                <h2 class="text-2xl font-semibold text-blue-900">Tentang Sistem</h2>
                                <p class="text-sm leading-relaxed text-blue-700/80">
                                    Sistem Booking Ruangan Internal dirancang khusus untuk civitas academica Universitas Esa Unggul Kampus Bekasi. Platform ini
                                    merapikan proses peminjaman dan pengelolaan fasilitas kampus dalam satu alur terpadu.
                                </p>
                                <p class="text-sm leading-relaxed text-blue-700/80">
                                    Butuh ruang kelas tambahan, ruangan rapat, atau fasilitas untuk kegiatan kampus? Tinggal pilih ruangannya, ajukan, dan ikuti
                                    status persetujuannya secara real-time.
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

        <footer class="mt-16 border-t bg-white/80 backdrop-blur-sm">
            <div class="container mx-auto px-4 py-8 text-center text-sm text-blue-600/90">
                <p class="font-medium text-blue-700/90">© {{ currentYear }} {{ campusName }}</p>
                <p class="mt-2 text-xs text-blue-500">Internal Room Reservation System • Laravel v{{ props.laravelVersion }} · PHP v{{ props.phpVersion }}</p>
            </div>
        </footer>
    </div>
</template>
