<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { BadgeCheck, MailCheck, RefreshCcw, ShieldCheck } from 'lucide-vue-next';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Verifikasi Email" />

        <div class="space-y-6">
            <div class="space-y-3">
                <div class="inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.25em] text-emerald-600">
                    <MailCheck class="h-3.5 w-3.5" />
                    Verifikasi Akun
                </div>
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">Cek email Anda</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Sebelum memakai sistem, klik tautan verifikasi yang baru saja kami kirim ke email kampus Anda.
                    </p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                <div class="flex items-start gap-3">
                    <ShieldCheck class="mt-0.5 h-5 w-5 flex-shrink-0 text-indigo-500" />
                    <p>Belum menerima email? Periksa folder spam atau kirim ulang tautan verifikasi dari halaman ini.</p>
                </div>
            </div>

            <div
                v-if="verificationLinkSent"
                class="rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
            >
                <div class="flex items-start gap-3">
                    <BadgeCheck class="mt-0.5 h-5 w-5 flex-shrink-0" />
                    <p>Tautan verifikasi baru sudah dikirim ke email yang Anda daftarkan.</p>
                </div>
            </div>

            <form class="space-y-3" @submit.prevent="submit">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-indigo-600 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:bg-indigo-500 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-indigo-200 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <RefreshCcw class="h-4 w-4" :class="{ 'animate-spin': form.processing }" />
                    <span>{{ form.processing ? 'Mengirim ulang…' : 'Kirim Ulang Email Verifikasi' }}</span>
                </button>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="inline-flex h-12 w-full items-center justify-center rounded-2xl border border-slate-200 bg-white text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                >
                    Keluar dari Akun
                </Link>
            </form>
        </div>
    </GuestLayout>
</template>
