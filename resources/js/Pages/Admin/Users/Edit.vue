<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import { computed, ref } from 'vue'

const props = defineProps({
  user: {
    type: Object,
    default: () => ({}),
  },
  roles: {
    type: Array,
    default: () => [],
  },
})

const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

const form = useForm({
  name: props.user?.name ?? '',
  email: props.user?.email ?? '',
  phone: props.user?.phone ?? '',
  role: props.user?.role ?? 'user',
  password: '',
  password_confirmation: '',
})

const submit = () => {
  form.put(route('admin.users.update', props.user.id), {
    preserveScroll: true,
    onSuccess: () => form.reset('password', 'password_confirmation'),
  })
}

const resetForm = () => {
  form.reset()
  form.name  = props.user?.name  ?? ''
  form.email = props.user?.email ?? ''
  form.phone = props.user?.phone ?? ''
  form.role  = props.user?.role  ?? 'user'
}

const formatDate = (value) => {
  if (!value) return '-'
  try {
    return new Date(value).toLocaleString('id-ID', {
      day: '2-digit', month: 'short', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    })
  } catch {
    return value
  }
}

const metadata = computed(() => ({
  createdAt: formatDate(props.user?.created_at),
  updatedAt: formatDate(props.user?.updated_at),
}))

const currentRoleLabel = computed(
  () => props.roles.find((r) => r.value === form.role)?.label ?? form.role
)

// Initials avatar
const initials = computed(() => {
  const parts = (props.user?.name ?? '').trim().split(' ')
  return parts.length >= 2
    ? parts[0][0] + parts[1][0]
    : parts[0]?.[0] ?? '?'
})
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Edit User" />

    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">Edit Data User</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400">
            Perbarui identitas, email, dan hak akses untuk pengguna terpilih.
          </p>
        </div>
        <Link
          :href="route('admin.users.index')"
          class="inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-800 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200 sm:w-auto"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Kembali
        </Link>
      </div>

      <div class="grid gap-6 lg:grid-cols-[1fr_320px]">

        <!-- ── Main Form ── -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
          <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-700">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Informasi Akun</h2>
          </div>

          <form @submit.prevent="submit" class="p-6 space-y-5">
            <div class="grid gap-5 md:grid-cols-2">

              <!-- Name -->
              <div class="space-y-1.5">
                <label for="name" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  Nama Lengkap <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                  <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-slate-400 dark:text-slate-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                  </span>
                  <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    placeholder="Masukkan nama lengkap"
                    required
                    autofocus
                    class="w-full rounded-xl border bg-slate-50 py-2.5 pl-10 pr-4 text-sm text-slate-800 placeholder-slate-400 transition duration-150
                           border-slate-200 focus:border-blue-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-400/20
                           dark:border-slate-600 dark:bg-slate-700/60 dark:text-white dark:placeholder-slate-500 dark:focus:border-blue-500 dark:focus:bg-slate-700 dark:focus:ring-blue-500/20"
                    :class="{ 'border-red-400 dark:border-red-500': form.errors.name }"
                  />
                </div>
                <InputError :message="form.errors.name" />
              </div>

              <!-- Email -->
              <div class="space-y-1.5">
                <label for="email" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  Email <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                  <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-slate-400 dark:text-slate-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                    </svg>
                  </span>
                  <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    placeholder="nama@domain.com"
                    required
                    class="w-full rounded-xl border bg-slate-50 py-2.5 pl-10 pr-4 text-sm text-slate-800 placeholder-slate-400 transition duration-150
                           border-slate-200 focus:border-blue-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-400/20
                           dark:border-slate-600 dark:bg-slate-700/60 dark:text-white dark:placeholder-slate-500 dark:focus:border-blue-500 dark:focus:bg-slate-700 dark:focus:ring-blue-500/20"
                    :class="{ 'border-red-400 dark:border-red-500': form.errors.email }"
                  />
                </div>
                <InputError :message="form.errors.email" />
              </div>

              <!-- Phone -->
              <div class="space-y-1.5">
                <label for="phone" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  Nomor Telepon <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                  <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-slate-400 dark:text-slate-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                    </svg>
                  </span>
                  <input
                    id="phone"
                    v-model="form.phone"
                    type="tel"
                    inputmode="tel"
                    placeholder="081234567890"
                    required
                    class="w-full rounded-xl border bg-slate-50 py-2.5 pl-10 pr-4 text-sm text-slate-800 placeholder-slate-400 transition duration-150
                           border-slate-200 focus:border-blue-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-400/20
                           dark:border-slate-600 dark:bg-slate-700/60 dark:text-white dark:placeholder-slate-500 dark:focus:border-blue-500 dark:focus:bg-slate-700 dark:focus:ring-blue-500/20"
                    :class="{ 'border-red-400 dark:border-red-500': form.errors.phone }"
                  />
                </div>
                <InputError :message="form.errors.phone" />
              </div>

              <!-- Role -->
              <div class="space-y-1.5">
                <label for="role" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  Role <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                  <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-slate-400 dark:text-slate-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                  </span>
                  <select
                    id="role"
                    v-model="form.role"
                    required
                    class="w-full appearance-none rounded-xl border bg-slate-50 py-2.5 pl-10 pr-9 text-sm text-slate-800 transition duration-150
                           border-slate-200 focus:border-blue-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-400/20
                           dark:border-slate-600 dark:bg-slate-700/60 dark:text-white dark:focus:border-blue-500 dark:focus:bg-slate-700 dark:focus:ring-blue-500/20"
                    :class="{ 'border-red-400 dark:border-red-500': form.errors.role }"
                  >
                    <option v-for="roleOption in roles" :key="roleOption.value" :value="roleOption.value">
                      {{ roleOption.label }}
                    </option>
                  </select>
                  <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400 dark:text-slate-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                  </span>
                </div>
                <InputError :message="form.errors.role" />
              </div>

            </div>

            <!-- Password section -->
            <div class="space-y-4 rounded-xl border border-slate-100 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-700/30">
              <div class="flex items-start gap-3">
                <div class="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                  </svg>
                </div>
                <div>
                  <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Ubah Password</p>
                  <p class="text-xs text-slate-500 dark:text-slate-400">Biarkan kosong apabila tidak ingin mengubah kata sandi.</p>
                </div>
              </div>

              <div class="grid gap-4 sm:grid-cols-2">
                <!-- Password -->
                <div class="space-y-1.5">
                  <label for="password" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Password Baru
                  </label>
                  <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-slate-400 dark:text-slate-500">
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                      </svg>
                    </span>
                    <input
                      id="password"
                      v-model="form.password"
                      :type="showPassword ? 'text' : 'password'"
                      placeholder="Minimal 8 karakter"
                      minlength="8"
                      autocomplete="new-password"
                      class="w-full rounded-xl border bg-white py-2.5 pl-10 pr-10 text-sm text-slate-800 placeholder-slate-400 transition duration-150
                             border-slate-200 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-400/20
                             dark:border-slate-600 dark:bg-slate-700/60 dark:text-white dark:placeholder-slate-500 dark:focus:border-blue-500 dark:focus:bg-slate-700 dark:focus:ring-blue-500/20"
                      :class="{ 'border-red-400 dark:border-red-500': form.errors.password }"
                    />
                    <button
                      type="button"
                      class="absolute inset-y-0 right-3 flex items-center text-slate-400 transition hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300"
                      @click="showPassword = !showPassword"
                      :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                    >
                      <svg v-if="!showPassword" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                      </svg>
                      <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                      </svg>
                    </button>
                  </div>
                  <InputError :message="form.errors.password" />
                </div>

                <!-- Confirm Password -->
                <div class="space-y-1.5">
                  <label for="password_confirmation" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Konfirmasi Password
                  </label>
                  <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-slate-400 dark:text-slate-500">
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                      </svg>
                    </span>
                    <input
                      id="password_confirmation"
                      v-model="form.password_confirmation"
                      :type="showPasswordConfirmation ? 'text' : 'password'"
                      placeholder="Ulangi password"
                      minlength="8"
                      autocomplete="new-password"
                      class="w-full rounded-xl border bg-white py-2.5 pl-10 pr-10 text-sm text-slate-800 placeholder-slate-400 transition duration-150
                             border-slate-200 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-400/20
                             dark:border-slate-600 dark:bg-slate-700/60 dark:text-white dark:placeholder-slate-500 dark:focus:border-blue-500 dark:focus:bg-slate-700 dark:focus:ring-blue-500/20"
                      :class="{ 'border-red-400 dark:border-red-500': form.errors.password_confirmation }"
                    />
                    <button
                      type="button"
                      class="absolute inset-y-0 right-3 flex items-center text-slate-400 transition hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300"
                      @click="showPasswordConfirmation = !showPasswordConfirmation"
                      :aria-label="showPasswordConfirmation ? 'Sembunyikan password' : 'Tampilkan password'"
                    >
                      <svg v-if="!showPasswordConfirmation" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                      </svg>
                      <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                      </svg>
                    </button>
                  </div>
                  <InputError :message="form.errors.password_confirmation" />
                </div>
              </div>
            </div>

            <!-- Footer Actions -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-5 dark:border-slate-700">
              <span v-if="form.hasErrors" class="flex items-center gap-1.5 text-xs font-medium text-red-500 dark:text-red-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                Periksa kembali data yang dimasukkan.
              </span>
              <span v-else class="flex-1" />

              <div class="flex items-center gap-3">
                <button
                  type="button"
                  class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:bg-slate-600 dark:hover:text-slate-200"
                  :disabled="form.processing"
                  @click="resetForm"
                >
                  Reset
                </button>
                <button
                  type="submit"
                  :disabled="form.processing"
                  class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:focus:ring-offset-slate-800"
                >
                  <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                  <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                  </svg>
                  {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                </button>
              </div>
            </div>
          </form>
        </div>

        <!-- ── Sidebar ── -->
        <aside class="space-y-4">

          <!-- User summary card -->
          <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-700">
              <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Ringkasan User</h2>
            </div>
            <div class="p-5">
              <!-- Avatar + name -->
              <div class="mb-5 flex items-center gap-3">
                <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm font-bold uppercase text-white dark:bg-blue-500">
                  {{ initials }}
                </div>
                <div class="min-w-0">
                  <p class="truncate font-semibold text-slate-800 dark:text-white">{{ user?.name ?? '-' }}</p>
                  <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ user?.email ?? '-' }}</p>
                </div>
              </div>
              <dl class="space-y-3 text-sm">
                <div class="flex items-center justify-between gap-2">
                  <dt class="text-slate-500 dark:text-slate-400">Role saat ini</dt>
                  <dd>
                    <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-600 dark:bg-blue-900/30 dark:text-blue-300">
                      {{ currentRoleLabel }}
                    </span>
                  </dd>
                </div>
                <div class="flex items-center justify-between gap-2">
                  <dt class="text-slate-500 dark:text-slate-400">Dibuat</dt>
                  <dd class="text-right text-xs font-medium text-slate-700 dark:text-slate-300">{{ metadata.createdAt }}</dd>
                </div>
                <div class="flex items-center justify-between gap-2">
                  <dt class="text-slate-500 dark:text-slate-400">Diperbarui</dt>
                  <dd class="text-right text-xs font-medium text-slate-700 dark:text-slate-300">{{ metadata.updatedAt }}</dd>
                </div>
              </dl>
            </div>
          </div>

          <!-- Security note -->
          <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-700/50 dark:bg-amber-900/20">
            <div class="flex items-center gap-2">
              <svg class="h-4 w-4 flex-shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
              </svg>
              <h3 class="text-sm font-semibold text-amber-800 dark:text-amber-300">Catatan Keamanan</h3>
            </div>
            <ul class="mt-3 space-y-2 pl-1 text-xs text-amber-700 dark:text-amber-400 list-none">
              <li class="flex items-start gap-2">
                <span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-amber-500"></span>
                Pastikan email yang digunakan aktif dan terverifikasi.
              </li>
              <li class="flex items-start gap-2">
                <span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-amber-500"></span>
                Super Admin memiliki akses penuh, Admin lainnya hanya mengelola modul masing-masing.
              </li>
              <li class="flex items-start gap-2">
                <span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-amber-500"></span>
                Untuk reset password, isi kolom password baru dan konfirmasi.
              </li>
            </ul>
          </div>

        </aside>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
/* Sembunyikan native password reveal button bawaan Edge/Chromium */
input[type="password"]::-ms-reveal,
input[type="password"]::-ms-clear {
  display: none;
}
</style>