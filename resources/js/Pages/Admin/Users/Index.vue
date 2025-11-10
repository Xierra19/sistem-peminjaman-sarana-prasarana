<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { usePagination } from '@/Composables/usePagination'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  users: {
    type: Array,
    default: () => [],
  },
  stats: {
    type: Object,
    default: () => ({}),
  },
  currentUserId: {
    type: Number,
    default: null,
  },
})

const roleLabels = {
  admin: 'Admin',
  user: 'User',
}

const roleBadgeClasses = {
  admin: 'bg-indigo-100 text-indigo-700 ring-1 ring-indigo-200',
  user: 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200',
}

const stats = computed(() => ({
  total: props.stats?.total ?? 0,
  admins: props.stats?.admins ?? 0,
  members: props.stats?.members ?? 0,
}))

const usersList = computed(() =>
  (props.users ?? []).map((user) => ({
    ...user,
    roleLabel: roleLabels[user.role] ?? user.role,
  })),
)

const {
  paginatedItems: paginatedUsers,
  currentPage,
  rowsPerPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(usersList)

const perPageOptions = [5, 10, 25, 50]

const deleteForm = useForm({})

const isCurrentUser = (userId) => props.currentUserId !== null && userId === props.currentUserId

const canDeleteUser = (user) => {
  if (isCurrentUser(user.id)) {
    return false
  }

  if (user.role === 'admin' && stats.value.admins <= 1) {
    return false
  }

  return true
}

const handleDelete = (user) => {
  if (!canDeleteUser(user)) {
    return
  }

  if (!confirm(`Hapus user ${user.name}? Tindakan ini tidak dapat dibatalkan.`)) {
    return
  }

  deleteForm.delete(route('admin.users.destroy', user.id), {
    preserveScroll: true,
  })
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
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Manajemen User" />

    <div class="space-y-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Manajemen User</h1>
          <p class="text-sm text-gray-500">Pantau dan kelola akses pengguna aplikasi booking ruangan.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <Link
            :href="route('admin.users.index')"
            class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-600 shadow-sm transition hover:border-gray-300 hover:text-gray-800"
            :only="['users', 'stats', 'currentUserId']"
          >
            Refresh
          </Link>
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total User</p>
          <p class="mt-2 text-2xl font-semibold text-slate-900">{{ stats.total }}</p>
          <p class="text-xs text-slate-500">Seluruh pengguna terdaftar</p>
        </div>
        <div class="rounded-xl border border-indigo-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-indigo-500">Admin</p>
          <p class="mt-2 text-2xl font-semibold text-indigo-600">{{ stats.admins }}</p>
          <p class="text-xs text-indigo-500">Pengguna dengan akses penuh</p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-emerald-500">User Biasa</p>
          <p class="mt-2 text-2xl font-semibold text-emerald-600">{{ stats.members }}</p>
          <p class="text-xs text-emerald-500">Akses standar booking</p>
        </div>
      </div>

      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div
          class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 md:flex-row md:items-center md:justify-between"
        >
          <div class="text-sm font-semibold text-gray-700">Daftar User Terdaftar</div>
          <div class="flex items-center justify-end gap-3 text-sm text-gray-600">
            <label class="font-medium text-gray-700" for="admin-users-rows">Rows per page</label>
            <div class="relative">
              <select
                id="admin-users-rows"
                v-model.number="rowsPerPage"
                class="w-20 appearance-none rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              >
                <option v-for="option in perPageOptions" :key="option" :value="option">
                  {{ option }}
                </option>
              </select>
              <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-gray-400">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                  <path
                    fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 011.06.02L10 10.939l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"
                    clip-rule="evenodd"
                  />
                </svg>
              </span>
            </div>
          </div>
        </div>

        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
            <tr>
              <th class="px-5 py-3 text-left">Nama</th>
              <th class="px-5 py-3 text-left">Email</th>
              <th class="px-5 py-3 text-left">No. Telp</th>
              <th class="px-5 py-3 text-left">Role</th>
              <th class="px-5 py-3 text-left">Dibuat</th>
              <th class="px-5 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-gray-700">
            <tr
              v-for="user in paginatedUsers"
              :key="user.id"
              class="transition hover:bg-gray-50"
              :class="{ 'bg-blue-50/50': isCurrentUser(user.id) }"
            >
              <td class="px-5 py-4">
                <div class="font-medium text-gray-900">{{ user.name }}</div>
                <div v-if="isCurrentUser(user.id)" class="text-xs font-semibold uppercase text-blue-500">Anda</div>
              </td>
              <td class="px-5 py-4">
                <div class="text-sm text-gray-700">{{ user.email }}</div>
              </td>
              <td class="px-5 py-4">
                <div class="text-sm text-gray-700">{{ user.phone || '-' }}</div>
              </td>
              <td class="px-5 py-4">
                <span
                  class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                  :class="roleBadgeClasses[user.role] ?? 'bg-gray-100 text-gray-600'"
                >
                  {{ user.roleLabel }}
                </span>
              </td>
              <td class="px-5 py-4 text-sm text-gray-600">
                {{ formatDate(user.created_at) }}
              </td>
              <td class="px-5 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <Link
                    :href="route('admin.users.edit', user.id)"
                    class="inline-flex items-center rounded-md border border-blue-200 px-3 py-1.5 text-xs font-medium text-blue-600 hover:bg-blue-50"
                  >
                    Edit
                  </Link>
                  <button
                    type="button"
                    class="inline-flex items-center rounded-md border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-600 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="!canDeleteUser(user) || deleteForm.processing"
                    :title="isCurrentUser(user.id) ? 'Tidak dapat menghapus akun sendiri.' : user.role === 'admin' && stats.admins <= 1 ? 'Minimal harus ada satu admin.' : ''"
                    @click="handleDelete(user)"
                  >
                    Hapus
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!usersList.length">
              <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-400">
                Belum ada user terdaftar.
              </td>
            </tr>
          </tbody>
        </table>

        <div
          class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between"
        >
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} user</span>
            <span v-else>Menampilkan 0 user</span>
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(1)"
              :disabled="currentPage === 1 || !usersList.length"
            >
              First
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1 || !usersList.length"
            >
              Prev
            </button>
            <template v-if="usersList.length">
              <button
                v-for="page in pages"
                :key="`page-${page}`"
                type="button"
                class="rounded border px-3 py-1 text-sm transition"
                :class="
                  currentPage === page
                    ? 'border-blue-500 bg-blue-500 text-white'
                    : 'border-gray-300 text-gray-600 hover:border-blue-400 hover:text-blue-600'
                "
                @click="changePage(page)"
              >
                {{ page }}
              </button>
            </template>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage + 1)"
              :disabled="currentPage === pages.length || !usersList.length"
            >
              Next
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(pages.length)"
              :disabled="currentPage === pages.length || !usersList.length"
            >
              Last
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
