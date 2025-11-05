<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

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

const form = useForm({
  name: props.user?.name ?? '',
  email: props.user?.email ?? '',
  role: props.user?.role ?? 'user',
  password: '',
  password_confirmation: '',
})

const submit = () => {
  form.put(route('admin.users.update', props.user.id), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('password', 'password_confirmation')
    },
  })
}

const resetForm = () => {
  form.reset()
  form.name = props.user?.name ?? ''
  form.email = props.user?.email ?? ''
  form.role = props.user?.role ?? 'user'
}

const formatDate = (value) => {
  if (!value) return '-'

  try {
    return new Date(value).toLocaleString('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch (error) {
    return value
  }
}

const metadata = computed(() => ({
  createdAt: formatDate(props.user?.created_at),
  updatedAt: formatDate(props.user?.updated_at),
}))
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Edit User" />

    <div class="space-y-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Edit Data User</h1>
          <p class="text-sm text-gray-500">
            Perbarui identitas, email, dan akses role untuk pengguna terpilih.
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <Link
            :href="route('admin.users.index')"
            class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-600 shadow-sm transition hover:border-gray-300 hover:text-gray-800"
          >
            ← Kembali ke daftar user
          </Link>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
        <form @submit.prevent="submit" class="space-y-5 rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
          <div class="space-y-2">
            <label for="name" class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
            <input
              id="name"
              v-model="form.name"
              type="text"
              class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              required
              autocomplete="name"
              placeholder="Nama pengguna"
            />
            <div v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</div>
          </div>

          <div class="space-y-2">
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              required
              autocomplete="email"
              placeholder="email@kampus.ac.id"
            />
            <div v-if="form.errors.email" class="text-xs text-rose-500">{{ form.errors.email }}</div>
          </div>

          <div class="space-y-2">
            <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
            <div class="relative">
              <select
                id="role"
                v-model="form.role"
                class="w-full appearance-none rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                required
              >
                <option v-for="roleOption in roles" :key="roleOption.value" :value="roleOption.value">
                  {{ roleOption.label }}
                </option>
              </select>
              <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">▾</span>
            </div>
            <div v-if="form.errors.role" class="text-xs text-rose-500">{{ form.errors.role }}</div>
          </div>

          <div class="rounded-lg border border-blue-100 bg-blue-50/80 px-4 py-3 text-xs text-blue-700">
            Biarkan kolom password kosong apabila tidak ingin mengubah kata sandi pengguna.
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <label for="password" class="block text-sm font-medium text-gray-700">Password Baru</label>
              <input
                id="password"
                v-model="form.password"
                type="password"
                class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                minlength="8"
                autocomplete="new-password"
                placeholder="Minimal 8 karakter"
              />
              <div v-if="form.errors.password" class="text-xs text-rose-500">{{ form.errors.password }}</div>
            </div>

            <div class="space-y-2">
              <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Konfirmasi Password</label>
              <input
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                minlength="8"
                autocomplete="new-password"
                placeholder="Ulangi password"
              />
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-3 pt-2">
            <button
              type="submit"
              class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-60"
              :disabled="form.processing"
            >
              {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
              @click="resetForm"
              :disabled="form.processing"
            >
              Reset
            </button>
            <span v-if="form.hasErrors" class="text-xs font-medium text-rose-500">Periksa kembali data yang dimasukkan.</span>
          </div>
        </form>

        <aside class="space-y-5">
          <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-800">Ringkasan User</h2>
            <dl class="mt-4 space-y-3 text-sm text-gray-600">
              <div class="flex items-center justify-between">
                <dt>Dibuat</dt>
                <dd class="font-medium text-gray-800">{{ metadata.createdAt }}</dd>
              </div>
              <div class="flex items-center justify-between">
                <dt>Terakhir diperbarui</dt>
                <dd class="font-medium text-gray-800">{{ metadata.updatedAt }}</dd>
              </div>
              <div class="flex items-center justify-between">
                <dt>Role saat ini</dt>
                <dd class="font-semibold text-blue-600">
                  {{ roles.find((role) => role.value === form.role)?.label ?? form.role }}
                </dd>
              </div>
            </dl>
          </div>

          <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-700">
            <h3 class="font-semibold text-amber-800">Catatan Keamanan</h3>
            <ul class="mt-3 space-y-2 list-disc pl-5">
              <li>Pastikan email yang digunakan aktif dan terverifikasi.</li>
              <li>Role <strong>Admin</strong> memiliki akses penuh terhadap data.</li>
              <li>Untuk mengatur ulang password, isi kolom password baru dan konfirmasi.</li>
            </ul>
          </div>
        </aside>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

