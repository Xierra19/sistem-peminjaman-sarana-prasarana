<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { formatDateTimeToDDMMYY } from '@/Composables/useDateFormatter'

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
  super_admin: 'Super Admin',
  admin_bap: 'Admin BAP',
  admin_sarpras: 'Admin Sarpras',
  admin: 'Super Admin',
  user: 'User',
}

const roleBadgeClasses = {
  super_admin: 'bg-indigo-100 text-indigo-700 ring-1 ring-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300 dark:ring-indigo-800',
  admin_bap: 'bg-blue-100 text-blue-700 ring-1 ring-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:ring-blue-800',
  admin_sarpras: 'bg-amber-100 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:ring-amber-800',
  admin: 'bg-indigo-100 text-indigo-700 ring-1 ring-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300 dark:ring-indigo-800',
  user: 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-800',
}

const stats = computed(() => ({
  total: props.stats?.total ?? 0,
  super_admins: props.stats?.super_admins ?? 0,
  admin_bap: props.stats?.admin_bap ?? 0,
  admin_sarpras: props.stats?.admin_sarpras ?? 0,
  members: props.stats?.members ?? 0,
}))

const usersList = computed(() =>
  (props.users ?? []).map((user) => ({
    ...user,
    roleLabel: roleLabels[user.role] ?? user.role,
  })),
)

const {
  sortedItems: sortedUsers,
  toggleSort: toggleUserSort,
  sortDirection: userSortDirection,
  ariaSortValue: userAriaSortValue,
} = useTableSort(usersList, {
  accessors: {
    name: (user) => user.name ?? '',
    email: (user) => user.email ?? '',
    phone: (user) => user.phone ?? '',
    role: (user) => user.roleLabel ?? user.role ?? '',
    created_at: (user) => (user.created_at ? new Date(user.created_at) : null),
  },
})

const {
  paginatedItems: paginatedUsers,
  currentPage,
  rowsPerPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(sortedUsers)

const perPageOptions = [5, 10, 25, 50]

const deleteForm = useForm({})

const isCurrentUser = (userId) => props.currentUserId !== null && userId === props.currentUserId

const canDeleteUser = (user) => {
  if (isCurrentUser(user.id)) {
    return false
  }

  if ((user.role === 'super_admin' || user.role === 'admin') && stats.value.super_admins <= 1) {
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

const formatDate = (value) => formatDateTimeToDDMMYY(value)
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Manajemen User" />

    <div class="space-y-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">Manajemen User</h1>
          <p class="text-sm text-gray-500 dark:text-gray-400">Pantau dan kelola akses pengguna aplikasi booking ruangan.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <Link
            :href="route('admin.users.index')"
            class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-600 shadow-sm transition hover:border-gray-300 hover:text-gray-800 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
            :only="['users', 'stats', 'currentUserId']"
          >
            Refresh
          </Link>
          <Link
            :href="route('admin.users.create')"
            class="inline-flex w-full items-center justify-center rounded-2xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 sm:w-auto"
          >
            + Tambah User
          </Link>
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Total User</p>
          <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ stats.total }}</p>
          <p class="text-xs text-slate-500 dark:text-slate-400">Seluruh pengguna terdaftar</p>
        </div>
        <div class="rounded-xl border border-indigo-200 bg-white p-5 shadow-sm dark:border-indigo-800 dark:bg-slate-800">
          <p class="text-xs font-medium uppercase tracking-wide text-indigo-500 dark:text-indigo-400">Super Admin</p>
          <p class="mt-2 text-2xl font-semibold text-indigo-600 dark:text-indigo-400">{{ stats.super_admins }}</p>
          <p class="text-xs text-indigo-500 dark:text-indigo-400">Akses menu seluruh modul</p>
        </div>
        <div class="rounded-xl border border-blue-200 bg-white p-5 shadow-sm dark:border-blue-800 dark:bg-slate-800">
          <p class="text-xs font-medium uppercase tracking-wide text-blue-500 dark:text-blue-400">Admin BAP</p>
          <p class="mt-2 text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ stats.admin_bap }}</p>
          <p class="text-xs text-blue-500 dark:text-blue-400">Kelola ruangan dan akademik</p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm dark:border-amber-800 dark:bg-slate-800">
          <p class="text-xs font-medium uppercase tracking-wide text-amber-500 dark:text-amber-400">Admin Sarpras</p>
          <p class="mt-2 text-2xl font-semibold text-amber-600 dark:text-amber-400">{{ stats.admin_sarpras }}</p>
          <p class="text-xs text-amber-500 dark:text-amber-400">Kelola barang dan inventaris</p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-white p-5 shadow-sm dark:border-emerald-800 dark:bg-slate-800">
          <p class="text-xs font-medium uppercase tracking-wide text-emerald-500 dark:text-emerald-400">User Biasa</p>
          <p class="mt-2 text-2xl font-semibold text-emerald-600 dark:text-emerald-400">{{ stats.members }}</p>
          <p class="text-xs text-emerald-500 dark:text-emerald-400">Akses standar booking</p>
        </div>
      </div>

      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-700">
          <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar User Terdaftar</div>
          <div class="flex items-center gap-3 text-sm text-gray-600 dark:text-gray-400">
            <label class="font-medium text-gray-700 dark:text-gray-300" for="admin-users-rows">Rows per page</label>
            <div class="relative">
              <select
                id="admin-users-rows"
                v-model.number="rowsPerPage"
                class="w-20 rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
              >
                <option v-for="option in perPageOptions" :key="`rows-${option}`" :value="option">
                  {{ option }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
          <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-slate-700 dark:text-slate-400">
            <tr>
              <SortableTh class="px-5 py-3 text-left" column="name" label="Nama" :direction="userSortDirection('name')" :aria-sort="userAriaSortValue('name')" @toggle="toggleUserSort" />
              <SortableTh class="px-5 py-3 text-left" column="email" label="Email" :direction="userSortDirection('email')" :aria-sort="userAriaSortValue('email')" @toggle="toggleUserSort" />
              <SortableTh class="px-5 py-3 text-left" column="phone" label="No. Telp" :direction="userSortDirection('phone')" :aria-sort="userAriaSortValue('phone')" @toggle="toggleUserSort" />
              <SortableTh class="px-5 py-3 text-left" column="role" label="Role" :direction="userSortDirection('role')" :aria-sort="userAriaSortValue('role')" @toggle="toggleUserSort" />
              <SortableTh class="px-5 py-3 text-left" column="created_at" label="Dibuat" :direction="userSortDirection('created_at')" :aria-sort="userAriaSortValue('created_at')" @toggle="toggleUserSort" />
              <th class="px-5 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-slate-700 dark:text-slate-300">
            <tr v-for="user in paginatedUsers" :key="user.id" class="transition hover:bg-gray-50 dark:hover:bg-slate-700/50" :class="{ 'bg-blue-50/50': isCurrentUser(user.id) }">
              <td class="px-5 py-4">
                <div class="font-medium text-gray-900 dark:text-slate-100">{{ user.name }}</div>
                <div v-if="isCurrentUser(user.id)" class="text-xs font-semibold uppercase text-blue-500 dark:text-blue-400">Anda</div>
              </td>
              <td class="px-5 py-4 text-sm text-gray-700 dark:text-slate-300">{{ user.email }}</td>
              <td class="px-5 py-4 text-sm text-gray-700 dark:text-slate-300">{{ user.phone || '-' }}</td>
              <td class="px-5 py-4">
                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold" :class="roleBadgeClasses[user.role] ?? 'bg-gray-100 text-gray-600 border-gray-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600'">
                  {{ user.roleLabel }}
                </span>
              </td>
              <td class="px-5 py-4 text-sm text-gray-700 dark:text-slate-300">{{ formatDate(user.created_at) }}</td>
              <td class="px-5 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <Link
                    :href="route('admin.users.edit', user.id)"
                    class="inline-flex items-center rounded-md border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-700 transition hover:border-blue-300 hover:text-blue-800 dark:border-blue-800 dark:text-blue-300 dark:hover:border-blue-700 dark:hover:text-blue-200"
                  >
                    Edit
                  </Link>
                  <button
                    type="button"
                    class="inline-flex items-center rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-rose-800 dark:text-rose-300 dark:hover:border-rose-700 dark:hover:bg-rose-900/30"
                    :disabled="!canDeleteUser(user) || deleteForm.processing"
                    :title="isCurrentUser(user.id) ? 'Tidak dapat menghapus akun sendiri.' : (user.role === 'super_admin' || user.role === 'admin') && stats.super_admins <= 1 ? 'Minimal harus ada satu super admin.' : ''"
                    @click="handleDelete(user)"
                  >
                    {{ deleteForm.processing && deleteForm.data.id === user.id ? 'Menghapus...' : 'Hapus' }}
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!usersList.length">
              <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-400 dark:text-slate-500">
                Belum ada user terdaftar.
              </td>
            </tr>
          </tbody>
        </table>

        <div class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:text-slate-400">
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} user</span>
            <span v-else>Menampilkan 0 data</span>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(1)" :disabled="currentPage === 1 || !usersList.length">«</button>
            <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(currentPage - 1)" :disabled="currentPage === 1 || !usersList.length">‹</button>
            <template v-if="usersList.length">
              <button
                v-for="page in pages"
                :key="`user-page-${page}`"
                type="button"
                class="rounded border px-3 py-1 text-sm transition"
                :class="currentPage === page ? 'border-blue-500 bg-blue-500 text-white' : 'border-gray-300 text-gray-600 hover:border-blue-400 hover:text-blue-600 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400'"
                @click="changePage(page)"
              >
                {{ page }}
              </button>
            </template>
            <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(currentPage + 1)" :disabled="currentPage === pages.length || !usersList.length">›</button>
            <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(pages.length)" :disabled="currentPage === pages.length || !usersList.length">»</button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>