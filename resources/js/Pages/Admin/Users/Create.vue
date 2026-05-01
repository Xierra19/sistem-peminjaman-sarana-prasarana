<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'

const props = defineProps({
  roles: {
    type: Array,
    required: true,
  },
})

const form = useForm({
  name: '',
  email: '',
  phone: '',
  role: 'user',
  password: '',
  password_confirmation: '',
})

const submit = () => {
  form.post(route('admin.users.store'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <Head title="Tambah User" />

  <AuthenticatedLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">Tambah User Baru</h1>
          <p class="text-sm text-gray-500 dark:text-gray-400">Buat akun pengguna aplikasi booking ruangan.</p>
        </div>
        <Link
          :href="route('admin.users.index')"
          class="inline-flex w-full items-center justify-center rounded-2xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm transition hover:border-gray-300 hover:text-gray-800 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200 sm:w-auto"
        >
          ← Kembali
        </Link>
      </div>

      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <form @submit.prevent="submit" class="p-6 space-y-6">
          <div class="grid gap-6 md:grid-cols-2">
            <!-- Name -->
            <div>
              <InputLabel for="name" value="Nama Lengkap" />
              <TextInput
                id="name"
                v-model="form.name"
                type="text"
                class="mt-1 block w-full"
                required
                autofocus
              />
              <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <!-- Email -->
            <div>
              <InputLabel for="email" value="Email" />
              <TextInput
                id="email"
                v-model="form.email"
                type="email"
                class="mt-1 block w-full"
                required
              />
              <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <!-- Phone -->
            <div>
              <InputLabel for="phone" value="Nomor Telepon" />
              <TextInput
                id="phone"
                v-model="form.phone"
                type="text"
                class="mt-1 block w-full"
                placeholder="Contoh: 081234567890"
              />
              <InputError class="mt-2" :message="form.errors.phone" />
            </div>

            <!-- Role -->
            <div>
              <InputLabel for="role" value="Role" />
              <select
                id="role"
                v-model="form.role"
                class="mt-1 block w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
              >
                <option v-for="role in props.roles" :key="role.value" :value="role.value">
                  {{ role.label }}
                </option>
              </select>
              <InputError class="mt-2" :message="form.errors.role" />
            </div>

            <!-- Password -->
            <div>
              <InputLabel for="password" value="Password" />
              <TextInput
                id="password"
                v-model="form.password"
                type="password"
                class="mt-1 block w-full"
                required
              />
              <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <!-- Confirm Password -->
            <div>
              <InputLabel for="password_confirmation" value="Konfirmasi Password" />
              <TextInput
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                class="mt-1 block w-full"
                required
              />
              <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>
          </div>

          <div class="flex items-center justify-end gap-4 border-t border-gray-100 pt-6 dark:border-slate-700">
            <Link :href="route('admin.users.index')">
              <SecondaryButton type="button">Batal</SecondaryButton>
            </Link>
            <PrimaryButton :disabled="form.processing">
              {{ form.processing ? 'Menyimpan...' : 'Simpan User' }}
            </PrimaryButton>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>