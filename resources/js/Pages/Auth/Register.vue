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
    AlertCircle,
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
    { label: 'Integrasi Ruangan & Barang', value: 'Semua gedung UEU Bekasi' },
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
                    <section class="rounded-[32px] border border-white/10 bg-gradient-to-b from-white/15 to-white/5 p-6 shadow-[0_25px_90px_rgba(15,23,42,0.55)] backdrop-blur sm:p-8">
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-indigo-200">Mulai Reservasi</p>
                        <h1 class="mt-4 text-4xl font-semibold leading-tight text-white md:text-5xl">
                            Buat Akun UEU Reservasi Ruangan & Barang
                        </h1>
                        <p class="mt-3 text-base text-indigo-100 md:text-lg">
                            Kelola jadwal ruangan dan peminjaman barang kampus melalui satu portal terintegrasi.
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
                                <Sparkles class="h-10 w-10 flex-shrink-0 rounded-2xl bg-white/10 p-2 text-indigo-100" aria-hidden="true" />
                                <div>
                                    <p class="font-medium text-white">Registrasi satu kali, gunakan selamanya.</p>
                                    <p class="text-sm text-indigo-100">Akses cepat ke panel reservasi dengan riwayat peminjaman lengkap.</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/5 p-4">
                                <ShieldCheck class="h-10 w-10 flex-shrink-0 rounded-2xl bg-white/10 p-2 text-indigo-100" aria-hidden="true" />
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

                    <section class="rounded-[32px] bg-white p-6 sm:p-8 text-slate-900 shadow-2xl ring-1 ring-slate-100 lg:p-10">
                        <div class="mb-8 space-y-2">
                            <p class="text-sm font-semibold uppercase tracking-[0.35em] text-indigo-500">Daftar akun</p>
                            <h2 class="text-3xl font-semibold text-slate-900">Selangkah lagi untuk reservasi</h2>
                            <p class="text-sm text-slate-500">
                                Lengkapi data diri menggunakan email kampus resmi Anda.
                            </p>
                        </div>

                        <form class="space-y-5" @submit.prevent="submit">
                            <div>
                                <label for="name" class="text-sm font-medium text-slate-700">Nama lengkap</label>
                                <div class="relative mt-2">
                                    <User class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                    <input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        autocomplete="name"
                                        placeholder="Nama sesuai data kampus"
                                        class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/60 pl-12 pr-4 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                                        :class="{ 'border-rose-500': form.errors.name }"
                                        :aria-invalid="!!form.errors.name"
                                        :aria-describedby="form.errors.name ? 'name-error' : undefined"
                                        required
                                        autofocus
                                    />
                                </div>
                                <p v-if="form.errors.name" id="name-error" class="mt-2 flex items-center gap-2 text-sm text-rose-500">
                                    <AlertCircle class="h-4 w-4 flex-shrink-0" />
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <div>
                                <label for="email" class="text-sm font-medium text-slate-700">Email kampus</label>
                                <div class="relative mt-2">
                                    <Mail class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        autocomplete="username"
                                        pattern="^[^@\s]+@student\.esaunggul\.ac\.id$"
                                        title="Gunakan email student.esaunggul.ac.id"
                                        placeholder="nama@student.esaunggul.ac.id"
                                        class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/60 pl-12 pr-4 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                                        :class="{ 'border-rose-500': form.errors.email }"
                                        :aria-invalid="!!form.errors.email"
                                        :aria-describedby="form.errors.email ? 'email-error' : undefined"
                                        required
                                    />
                                </div>
                                <p class="mt-2 text-xs font-medium text-indigo-600">Hanya alamat @student.esaunggul.ac.id yang dapat melakukan registrasi.</p>
                                <p v-if="form.errors.email" id="email-error" class="mt-2 flex items-center gap-2 text-sm text-rose-500">
                                    <AlertCircle class="h-4 w-4 flex-shrink-0" />
                                    {{ form.errors.email }}
                                </p>
                            </div>

                            <div>
                                <label for="phone" class="text-sm font-medium text-slate-700">Nomor telepon aktif</label>
                                <div class="relative mt-2">
                                    <Phone class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                    <input
                                        id="phone"
                                        v-model="form.phone"
                                        type="tel"
                                        inputmode="tel"
                                        pattern="^(?:\+62\d{8,13}|0\d{8,13})$"
                                        title="Gunakan nomor Indonesia diawali 0 atau +62"
                                        placeholder="Contoh: 081234567890"
                                        class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/60 pl-12 pr-4 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                                        :class="{ 'border-rose-500': form.errors.phone }"
                                        :aria-invalid="!!form.errors.phone"
                                        :aria-describedby="form.errors.phone ? 'phone-error' : undefined"
                                        required
                                    />
                                </div>
                                <p class="mt-2 text-xs text-slate-500">Pastikan nomor dapat dihubungi saat admin memverifikasi.</p>
                                <p v-if="form.errors.phone" id="phone-error" class="mt-2 flex items-center gap-2 text-sm text-rose-500">
                                    <AlertCircle class="h-4 w-4 flex-shrink-0" />
                                    {{ form.errors.phone }}
                                </p>
                            </div>

                            <div>
                                <label for="password" class="text-sm font-medium text-slate-700">Password</label>
                                <div class="relative mt-2">
                                    <Lock class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                    <input
                                        id="password"
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        autocomplete="new-password"
                                        placeholder="Minimal 8 karakter"
                                        class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/60 pl-12 pr-12 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                                        :class="{ 'border-rose-500': form.errors.password }"
                                        :aria-invalid="!!form.errors.password"
                                        :aria-describedby="form.errors.password ? 'password-error' : undefined"
                                        required
                                    />
                                    <button
                                        type="button"
                                        class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-slate-600"
                                        :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                        @click="showPassword = !showPassword"
                                    >
                                        <EyeOff v-if="showPassword" class="h-5 w-5" />
                                        <Eye v-else class="h-5 w-5" />
                                    </button>
                                </div>
                                <p class="mt-2 text-xs text-slate-500">Gunakan kombinasi huruf besar, kecil, dan angka.</p>
                                <p v-if="form.errors.password" id="password-error" class="mt-2 flex items-center gap-2 text-sm text-rose-500">
                                    <AlertCircle class="h-4 w-4 flex-shrink-0" />
                                    {{ form.errors.password }}
                                </p>
                            </div>

                            <div>
                                <label for="password_confirmation" class="text-sm font-medium text-slate-700">Konfirmasi password</label>
                                <div class="relative mt-2">
                                    <Lock class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                    <input
                                        id="password_confirmation"
                                        v-model="form.password_confirmation"
                                        :type="showConfirmPassword ? 'text' : 'password'"
                                        autocomplete="new-password"
                                        placeholder="Ulangi password"
                                        class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/60 pl-12 pr-12 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                                        :class="{ 'border-rose-500': form.errors.password_confirmation }"
                                        :aria-invalid="!!form.errors.password_confirmation"
                                        :aria-describedby="form.errors.password_confirmation ? 'password-confirmation-error' : undefined"
                                        required
                                    />
                                    <button
                                        type="button"
                                        class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-slate-600"
                                        :aria-label="showConfirmPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                        @click="showConfirmPassword = !showConfirmPassword"
                                    >
                                        <EyeOff v-if="showConfirmPassword" class="h-5 w-5" />
                                        <Eye v-else class="h-5 w-5" />
                                    </button>
                                </div>
                                <p v-if="form.errors.password_confirmation" id="password-confirmation-error" class="mt-2 flex items-center gap-2 text-sm text-rose-500">
                                    <AlertCircle class="h-4 w-4 flex-shrink-0" />
                                    {{ form.errors.password_confirmation }}
                                </p>
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-indigo-600 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:bg-indigo-500 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-indigo-200 disabled:cursor-not-allowed disabled:opacity-60 disabled:pointer-events-none"
                            >
                                <svg v-if="form.processing" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span v-if="form.processing">Mendaftarkan…</span>
                                <span v-else>Buat Akun</span>
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
