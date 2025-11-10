<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    User,
    Mail,
    Phone,
    Lock,
    Eye,
    EyeOff,
    ShieldCheck,
    Sparkles,
} from 'lucide-vue-next';

const showPassword = ref(false);
const showConfirmPassword = ref(false);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
});

const checklist = [
    'Gunakan email student.esaunggul.ac.id aktif.',
    'Nama lengkap sesuai kartu identitas kampus.',
    'Password minimal 8 karakter dengan kombinasi huruf dan angka.',
];

const badges = [
    { label: 'Mahasiswa aktif', value: 'Diverifikasi harian' },
    { label: 'Integrasi ruang', value: 'Semua gedung UEU Bekasi' },
];

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Register" />

    <div class="relative min-h-screen overflow-hidden bg-slate-950 text-white">
        <div class="absolute inset-0">
            <div class="absolute -left-10 top-10 hidden h-80 w-80 rounded-full bg-indigo-500/20 blur-[160px] md:block"></div>
            <div class="absolute -right-16 bottom-10 h-96 w-96 rounded-full bg-blue-500/25 blur-[180px]"></div>
        </div>

        <div class="relative z-10 flex min-h-screen items-center justify-center px-4 py-10 sm:px-6 lg:px-10">
            <div class="w-full max-w-6xl">
                <div class="grid gap-8 lg:grid-cols-[1.05fr_0.95fr]">
                    <section class="rounded-[32px] border border-white/10 bg-gradient-to-b from-white/15 to-white/5 p-8 shadow-[0_25px_90px_rgba(15,23,42,0.55)] backdrop-blur">
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-indigo-200">Mulai Booking</p>
                        <h1 class="mt-4 text-4xl font-semibold leading-tight text-white">
                            Buat akun UEU Room Reservation
                        </h1>
                        <p class="mt-3 text-base text-indigo-100">
                            Kelola jadwal ruang kelas, laboratorium, dan ruang kolaborasi melalui satu portal terintegrasi.
                        </p>

                        <div class="mt-10 grid gap-4 sm:grid-cols-2">
                            <div
                                v-for="badge in badges"
                                :key="badge.label"
                                class="rounded-2xl border border-white/10 bg-white/5 p-4"
                            >
                                <p class="text-sm uppercase tracking-wide text-indigo-200">{{ badge.label }}</p>
                                <p class="mt-2 text-2xl font-semibold text-white">{{ badge.value }}</p>
                            </div>
                        </div>

                        <div class="mt-10 space-y-4">
                            <div class="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/5 p-4">
                                <Sparkles class="h-10 w-10 flex-shrink-0 rounded-2xl bg-white/10 p-2 text-indigo-100" />
                                <div>
                                    <p class="font-medium text-white">Registrasi sekali, pakai selamanya.</p>
                                    <p class="text-sm text-indigo-100">Akses cepat ke panel booking dengan riwayat peminjaman lengkap.</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/5 p-4">
                                <ShieldCheck class="h-10 w-10 flex-shrink-0 rounded-2xl bg-white/10 p-2 text-indigo-100" />
                                <div>
                                    <p class="font-medium text-white">Keamanan akun terjamin.</p>
                                    <p class="text-sm text-indigo-100">Verifikasi email kampus dan enkripsi kata sandi bawaan Laravel.</p>
                                </div>
                            </div>
                        </div>

                        <ul class="mt-10 space-y-3 text-sm text-indigo-100">
                            <li
                                v-for="item in checklist"
                                :key="item"
                                class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/5 p-3"
                            >
                                <span class="mt-1 h-2 w-2 rounded-full bg-lime-300"></span>
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </section>

                    <section class="rounded-[32px] bg-white p-8 text-slate-900 shadow-2xl ring-1 ring-slate-100 lg:p-10">
                        <div class="mb-8 space-y-2">
                            <p class="text-sm font-semibold uppercase tracking-[0.35em] text-indigo-500">Daftar akun</p>
                            <h2 class="text-3xl font-semibold text-slate-900">Selangkah lagi untuk booking</h2>
                            <p class="text-sm text-slate-500">
                                Lengkapi data diri menggunakan email kampus resmi Anda.
                            </p>
                        </div>

                        <form class="space-y-5" @submit.prevent="submit">
                            <div>
                                <label for="name" class="text-sm font-medium text-slate-700">Nama lengkap</label>
                                <div class="relative mt-2">
                                    <User class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                                    <input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        autocomplete="name"
                                        placeholder="Nama sesuai data kampus"
                                        class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/60 pl-12 pr-4 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                                        required
                                        autofocus
                                    />
                                </div>
                                <p v-if="form.errors.name" class="mt-2 text-sm text-rose-500">{{ form.errors.name }}</p>
                            </div>

                            <div>
                                <label for="email" class="text-sm font-medium text-slate-700">Email kampus</label>
                                <div class="relative mt-2">
                                    <Mail class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        autocomplete="username"
                                        pattern="^[^@\\s]+@student\\.esaunggul\\.ac\\.id$"
                                        title="Gunakan email student.esaunggul.ac.id"
                                        placeholder="nama@student.esaunggul.ac.id"
                                        class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/60 pl-12 pr-4 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                                        required
                                    />
                                </div>
                                <p class="mt-2 text-xs font-medium text-indigo-600">Hanya alamat @student.esaunggul.ac.id yang dapat melakukan registrasi.</p>
                                <p v-if="form.errors.email" class="mt-2 text-sm text-rose-500">{{ form.errors.email }}</p>
                            </div>

                            <div>
                                <label for="phone" class="text-sm font-medium text-slate-700">Nomor telepon aktif</label>
                                <div class="relative mt-2">
                                    <Phone class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                                    <input
                                        id="phone"
                                        v-model="form.phone"
                                        type="tel"
                                        inputmode="tel"
                                        pattern="^(?:\\+62\\d{8,13}|0\\d{8,13})$"
                                        title="Gunakan nomor Indonesia diawali 0 atau +62"
                                        placeholder="Contoh: 081234567890"
                                        class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/60 pl-12 pr-4 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                                        required
                                    />
                                </div>
                                <p class="mt-2 text-xs text-slate-500">Pastikan nomor dapat dihubungi saat admin memverifikasi.</p>
                                <p v-if="form.errors.phone" class="mt-2 text-sm text-rose-500">{{ form.errors.phone }}</p>
                            </div>

                            <div>
                                <label for="password" class="text-sm font-medium text-slate-700">Password</label>
                                <div class="relative mt-2">
                                    <Lock class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                                    <input
                                        id="password"
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        autocomplete="new-password"
                                        placeholder="Minimal 8 karakter"
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
                                <p class="mt-2 text-xs text-slate-500">Gunakan kombinasi huruf besar, kecil, dan angka.</p>
                                <p v-if="form.errors.password" class="mt-2 text-sm text-rose-500">{{ form.errors.password }}</p>
                            </div>

                            <div>
                                <label for="password_confirmation" class="text-sm font-medium text-slate-700">Konfirmasi password</label>
                                <div class="relative mt-2">
                                    <Lock class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                                    <input
                                        id="password_confirmation"
                                        v-model="form.password_confirmation"
                                        :type="showConfirmPassword ? 'text' : 'password'"
                                        autocomplete="new-password"
                                        placeholder="Ulangi password"
                                        class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/60 pl-12 pr-12 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                                        required
                                    />
                                    <button
                                        type="button"
                                        class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-slate-600"
                                        @click="showConfirmPassword = !showConfirmPassword"
                                    >
                                        <EyeOff v-if="showConfirmPassword" class="h-5 w-5" />
                                        <Eye v-else class="h-5 w-5" />
                                    </button>
                                </div>
                                <p v-if="form.errors.password_confirmation" class="mt-2 text-sm text-rose-500">
                                    {{ form.errors.password_confirmation }}
                                </p>
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="flex h-12 w-full items-center justify-center rounded-2xl bg-indigo-600 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:bg-indigo-500 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-indigo-200 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <span v-if="form.processing">Mendaftarkan…</span>
                                <span v-else>Buat akun</span>
                            </button>
                        </form>

                        <p class="mt-8 text-center text-sm text-slate-500">
                            Sudah punya akun?
                            <Link :href="route('login')" class="font-semibold text-indigo-600 hover:text-indigo-500">
                                Masuk sekarang
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
