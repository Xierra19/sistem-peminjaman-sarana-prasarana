<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                Hapus Akun
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">
                Jika akun dihapus, seluruh data terkait akses dan riwayat akan dihapus permanen. Pastikan keputusan ini memang final.
            </p>
        </header>

        <DangerButton @click="confirmUserDeletion" class="!flex !h-12 !w-full !justify-center !rounded-2xl !px-4 !text-sm !font-semibold !normal-case !tracking-normal sm:!w-auto">
            Hapus Akun
        </DangerButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="bg-white p-5 dark:border dark:border-slate-700 dark:bg-slate-800 sm:p-6">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-white"
                >
                    Yakin ingin menghapus akun?
                </h2>

                <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">
                    Semua data akun akan dihapus permanen. Masukkan password untuk mengonfirmasi tindakan ini.
                </p>

                <div class="mt-6">
                    <InputLabel
                        for="password"
                        value="Password"
                        class="sr-only"
                    />

                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-4 text-sm text-slate-900 shadow-sm transition focus:border-rose-500 focus:bg-white focus:ring-4 focus:ring-rose-100 sm:w-3/4"
                        placeholder="Masukkan password"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <SecondaryButton @click="closeModal" class="!flex !h-12 !w-full !justify-center !rounded-2xl !px-4 !text-sm !font-semibold !normal-case !tracking-normal sm:!w-auto">
                        Batal
                    </SecondaryButton>

                    <DangerButton
                        class="!flex !h-12 !w-full !justify-center !rounded-2xl !px-4 !text-sm !font-semibold !normal-case !tracking-normal sm:!w-auto"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        {{ form.processing ? 'Menghapus…' : 'Ya, Hapus Akun' }}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
