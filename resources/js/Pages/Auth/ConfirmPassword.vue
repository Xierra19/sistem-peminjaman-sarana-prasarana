<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head } from '@inertiajs/vue3';
import { AlertCircle, LockKeyhole, ShieldCheck } from 'lucide-vue-next';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Konfirmasi Password" />

        <div class="space-y-6">
            <div class="space-y-3">
                <div class="inline-flex items-center gap-2 rounded-full border border-amber-100 bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.25em] text-amber-600">
                    <LockKeyhole class="h-3.5 w-3.5" />
                    Area Aman
                </div>
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">Konfirmasi kata sandi</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Area ini memerlukan verifikasi ulang password sebelum Anda bisa melanjutkan.
                    </p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                <div class="flex items-start gap-3">
                    <ShieldCheck class="mt-0.5 h-5 w-5 flex-shrink-0 text-indigo-500" />
                    <p>Langkah ini melindungi perubahan sensitif seperti update keamanan akun dan akses administratif.</p>
                </div>
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label for="password" class="text-sm font-medium text-slate-700">Password saat ini</label>
                    <div class="relative mt-2">
                        <LockKeyhole class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                        <TextInput
                            id="password"
                            type="password"
                            class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/70 pl-12 pr-4 text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                            v-model="form.password"
                            required
                            autocomplete="current-password"
                            autofocus
                        />
                    </div>
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-indigo-600 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:bg-indigo-500 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-indigo-200 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <AlertCircle v-if="form.processing" class="h-4 w-4 animate-pulse" />
                    <span>{{ form.processing ? 'Memverifikasi…' : 'Konfirmasi Password' }}</span>
                </button>
            </form>
        </div>
    </GuestLayout>
</template>
