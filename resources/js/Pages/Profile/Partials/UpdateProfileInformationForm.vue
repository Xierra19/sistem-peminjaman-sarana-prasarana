<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    phone: user.phone ?? '',
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                Informasi Profil
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">
                Perbarui nama dan nomor telepon yang dipakai untuk proses booking.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="mt-6 space-y-6"
        >
            <div>
                <InputLabel for="name" value="Nama Lengkap" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-4 text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email Kampus" />

                <input
                    id="email"
                    type="email"
                    :value="user.email"
                    class="mt-1 block h-12 w-full cursor-not-allowed rounded-2xl border border-slate-200 bg-slate-100 px-4 text-sm text-slate-500 shadow-sm dark:border-slate-600 dark:bg-slate-700/70 dark:text-slate-300"
                    readonly
                    aria-describedby="email-help"
                />

                <p id="email-help" class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Email kampus terikat pada akun dan tidak dapat diubah.
                </p>
            </div>

            <div>
                <InputLabel for="phone" value="Nomor Telepon" />

                <TextInput
                    id="phone"
                    type="tel"
                    class="mt-1 block h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-4 text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                    v-model="form.phone"
                    required
                    autocomplete="tel"
                />

                <InputError class="mt-2" :message="form.errors.phone" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                    <p class="mt-2 text-sm text-gray-800 dark:text-slate-200">
                        Email Anda belum terverifikasi.
                        <Link
                            :href="route('verification.send')"
                            method="post"
                            as="button"
                            class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-slate-300 dark:hover:text-white"
                        >
                            Kirim ulang email verifikasi.
                        </Link>
                    </p>

                <div
                    v-show="status === 'verification-link-sent'"
                        class="mt-2 text-sm font-medium text-green-600 dark:text-green-400"
                >
                    Tautan verifikasi baru sudah dikirim ke email Anda.
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <PrimaryButton :disabled="form.processing" class="!flex !h-12 !w-full !justify-center !rounded-2xl !bg-indigo-600 !px-4 !text-sm !font-semibold !normal-case !tracking-normal sm:!w-auto">
                    {{ form.processing ? 'Menyimpan…' : 'Simpan Perubahan' }}
                </PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                        <p
                            v-if="form.recentlySuccessful"
                            class="text-sm text-gray-600 dark:text-slate-300"
                        >
                            Perubahan tersimpan.
                        </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
