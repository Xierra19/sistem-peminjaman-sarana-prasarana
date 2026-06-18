<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profil" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800 dark:text-white"
            >
                Profil Akun
            </h2>
        </template>

        <div class="py-8 sm:py-10 lg:py-12">
            <div class="mx-auto max-w-4xl space-y-5 px-4 sm:px-6 lg:px-8">
                <div class="space-y-2">
                    <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">Pengaturan Akun</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Kelola identitas akun, perbarui password, dan atur keamanan akses Anda.
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:p-8"
                >
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-2xl"
                    />
                </div>

                <div
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:p-8"
                >
                    <UpdatePasswordForm class="max-w-2xl" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
