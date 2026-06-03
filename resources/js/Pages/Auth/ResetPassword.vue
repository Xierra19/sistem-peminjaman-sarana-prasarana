<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertCircle, KeyRound, Lock, Mail, ShieldCheck } from 'lucide-vue-next';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset Password" />

        <div class="space-y-6">
            <div class="space-y-3">
                <div class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.25em] text-indigo-600">
                    <KeyRound class="h-3.5 w-3.5" />
                    Password Baru
                </div>
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">Atur ulang kata sandi</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Masukkan email kampus dan buat kata sandi baru yang aman untuk melanjutkan akses ke sistem reservasi.
                    </p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                <div class="flex items-start gap-3">
                    <ShieldCheck class="mt-0.5 h-5 w-5 flex-shrink-0 text-indigo-500" />
                    <p>Tautan reset hanya berlaku sementara. Setelah password diganti, tautan lama tidak bisa dipakai lagi.</p>
                </div>
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label for="email" class="text-sm font-medium text-slate-700">Email kampus</label>
                    <div class="relative mt-2">
                        <Mail class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                        <TextInput
                            id="email"
                            type="email"
                            class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/70 pl-12 pr-4 text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                        />
                    </div>
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <label for="password" class="text-sm font-medium text-slate-700">Password baru</label>
                    <div class="relative mt-2">
                        <Lock class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                        <TextInput
                            id="password"
                            type="password"
                            class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/70 pl-12 pr-4 text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                            v-model="form.password"
                            required
                            autocomplete="new-password"
                        />
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Gunakan minimal 8 karakter dengan kombinasi huruf dan angka.</p>
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div>
                    <label for="password_confirmation" class="text-sm font-medium text-slate-700">Konfirmasi password baru</label>
                    <div class="relative mt-2">
                        <Lock class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/70 pl-12 pr-4 text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                            v-model="form.password_confirmation"
                            required
                            autocomplete="new-password"
                        />
                    </div>
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-indigo-600 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:bg-indigo-500 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-indigo-200 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <AlertCircle v-if="form.processing" class="h-4 w-4 animate-pulse" />
                    <span>{{ form.processing ? 'Menyimpan password…' : 'Simpan Password Baru' }}</span>
                </button>
            </form>

            <p class="text-center text-sm text-slate-500">
                Sudah siap masuk?
                <Link :href="route('login')" class="font-semibold text-indigo-600 hover:text-indigo-500">
                    Kembali ke halaman login
                </Link>
            </p>
        </div>
    </GuestLayout>
</template>
