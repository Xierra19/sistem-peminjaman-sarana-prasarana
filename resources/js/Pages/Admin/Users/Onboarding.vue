<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps({
  roles: {
    type: Array,
    default: () => [],
  },
})

const onboardingSteps = [
  {
    title: 'Isi Identitas',
    description: 'Lengkapi nama dan email resmi institusi.',
    icon: '🪪',
  },
  {
    title: 'Pilih Role',
    description: 'Sesuaikan tingkat akses antara admin ataupun user biasa.',
    icon: '🧭',
  },
  {
    title: 'Amankan Password',
    description: 'Gunakan minimal 8 karakter dengan kombinasi angka dan huruf.',
    icon: '🔒',
  },
]

const securityReminders = [
  {
    title: 'Aktifkan Verifikasi',
    body: 'Pastikan user baru melakukan verifikasi email agar akses penuh tersedia.',
  },
  {
    title: 'Pantau Aktivitas',
    body: 'Tinjau riwayat login berkala untuk memastikan tidak ada aktivitas mencurigakan.',
  },
]

const form = useForm({
  name: '',
  email: '',
  role: props.roles?.[0]?.value ?? 'user',
  password: '',
  password_confirmation: '',
})

const submit = () => {
  form.post(route('admin.users.onboarding.store'), {
    onSuccess: () => {
      form.reset('name', 'email', 'password', 'password_confirmation')
    },
  })
}
</script>

<template>
  <Head title="Onboarding User" />

  <AuthenticatedLayout>
    <div class="relative overflow-hidden rounded-3xl border border-blue-100 bg-gradient-to-br from-sky-50 via-white to-indigo-100 p-10 shadow-xl">
      <div class="pointer-events-none absolute -top-16 -right-20 h-56 w-56 rounded-full bg-blue-200/40 blur-3xl" />
      <div class="pointer-events-none absolute bottom-10 -left-12 h-48 w-48 rounded-full bg-indigo-200/30 blur-3xl" />

      <div class="relative z-10 space-y-10">
        <header class="max-w-3xl space-y-3">
          <span class="inline-flex items-center gap-2 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-blue-700">
            ✨ New Team Member
          </span>
          <h1 class="text-3xl font-bold text-gray-900 sm:text-4xl">Onboarding User Baru</h1>
          <p class="text-base text-gray-600 sm:text-lg">
            Lengkapi data pengguna dan berikan akses sesuai kebutuhan. Halaman ini membantu admin memastikan setiap user siap menggunakan sistem booking ruangan.
          </p>
        </header>

        <div class="grid gap-8 lg:grid-cols-[1.45fr_1fr]">
          <div class="rounded-3xl border border-white/60 bg-white/90 shadow-lg backdrop-blur-sm">
            <div class="border-b border-slate-100 px-8 py-6">
              <h2 class="text-lg font-semibold text-gray-900">Formulir Data Pengguna</h2>
              <p class="text-sm text-gray-500">
                Isi informasi di bawah ini. Setelah disimpan, user baru akan menerima akses sesuai role yang dipilih.
              </p>
            </div>

            <form @submit.prevent="submit" class="space-y-5 px-8 py-6">
              <div class="space-y-2">
                <label for="name" class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                <input
                  id="name"
                  v-model="form.name"
                  type="text"
                  class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm shadow-inner focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                  required
                  autocomplete="name"
                  placeholder="Contoh: Sarah Oktavia"
                />
                <div v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</div>
              </div>

              <div class="space-y-2">
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input
                  id="email"
                  v-model="form.email"
                  type="email"
                  class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm shadow-inner focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                  required
                  autocomplete="email"
                  placeholder="nama@kampus.ac.id"
                />
                <div v-if="form.errors.email" class="text-xs text-red-500">{{ form.errors.email }}</div>
              </div>

              <div class="space-y-2">
                <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                <div class="relative">
                  <select
                    id="role"
                    v-model="form.role"
                    class="w-full appearance-none rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium shadow-inner focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    required
                  >
                    <option v-for="roleOption in props.roles" :key="roleOption.value" :value="roleOption.value">
                      {{ roleOption.label }}
                    </option>
                  </select>
                  <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">▾</span>
                </div>
                <div v-if="form.errors.role" class="text-xs text-red-500">{{ form.errors.role }}</div>
              </div>

              <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                  <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                  <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm shadow-inner focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    required
                    autocomplete="new-password"
                    minlength="8"
                    placeholder="Minimal 8 karakter"
                  />
                  <div v-if="form.errors.password" class="text-xs text-red-500">{{ form.errors.password }}</div>
                </div>

                <div class="space-y-2">
                  <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Konfirmasi Password</label>
                  <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm shadow-inner focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    required
                    autocomplete="new-password"
                    placeholder="Ulangi password"
                  />
                </div>
              </div>

              <div class="flex flex-wrap items-center gap-3 pt-2">
                <button
                  type="submit"
                  class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/30 transition hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 disabled:opacity-60"
                  :disabled="form.processing"
                >
                  <span>{{ form.processing ? 'Menyimpan...' : 'Tambah User' }}</span>
                </button>
                <span v-if="form.hasErrors" class="text-xs font-medium text-red-500">Periksa kembali data yang dimasukkan.</span>
              </div>
            </form>
          </div>

          <aside class="space-y-5">
            <div class="rounded-3xl border border-blue-100 bg-white/70 p-6 shadow-md backdrop-blur">
              <h2 class="text-base font-semibold text-gray-900">Alur Tambah Pengguna</h2>
              <ul class="mt-4 space-y-3">
                <li
                  v-for="step in onboardingSteps"
                  :key="step.title"
                  class="flex gap-3 rounded-2xl border border-white/40 bg-white/80 px-4 py-3 shadow-sm"
                >
                  <div class="text-xl leading-none">{{ step.icon }}</div>
                  <div>
                    <p class="text-sm font-semibold text-gray-800">{{ step.title }}</p>
                    <p class="text-xs text-gray-500">{{ step.description }}</p>
                  </div>
                </li>
              </ul>
            </div>

            <div class="rounded-3xl border border-indigo-100 bg-indigo-50/70 p-6 shadow-inner">
              <h3 class="text-sm font-semibold uppercase tracking-wide text-indigo-700">Tips Keamanan</h3>
              <div class="mt-4 space-y-4">
                <div
                  v-for="reminder in securityReminders"
                  :key="reminder.title"
                  class="rounded-2xl border border-indigo-100 bg-white/70 px-4 py-3 shadow"
                >
                  <p class="text-sm font-semibold text-gray-800">{{ reminder.title }}</p>
                  <p class="text-xs text-gray-500">{{ reminder.body }}</p>
                </div>
              </div>

              <div class="mt-5 flex items-center gap-2 text-xs text-indigo-800">
                <span class="text-lg">💡</span>
                <p>Berikan panduan singkat kepada user baru untuk memanfaatkan fitur booking secara maksimal.</p>
              </div>
            </div>
          </aside>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
