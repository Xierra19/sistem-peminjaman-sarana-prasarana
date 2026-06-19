<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import Modal from '@/Components/Modal.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { Head, Link, useForm } from '@inertiajs/vue3'
import {
  AlertTriangle,
  Ban,
  Check,
  Filter,
  Mail,
  RotateCcw,
  Search,
  ShieldAlert,
  UserRound,
  X,
} from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
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
  inactive_members: props.stats?.inactive_members ?? 0,
}))

const usersList = computed(() =>
  (props.users ?? []).map((user) => ({
    ...user,
    roleLabel: roleLabels[user.role] ?? user.role,
  })),
)

const searchQuery = ref('')
const roleFilter = ref('')
const statusFilter = ref('')

const filteredUsers = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return usersList.value.filter((user) => {
    if (query) {
      const searchable = [
        user.name,
        user.nim,
        user.email,
        user.phone,
        user.roleLabel,
        user.is_active ? 'aktif' : 'nonaktif',
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()

      if (!searchable.includes(query)) {
        return false
      }
    }

    if (roleFilter.value === 'super_admin') {
      if (!['super_admin', 'admin'].includes(user.role)) {
        return false
      }
    } else if (roleFilter.value && user.role !== roleFilter.value) {
      return false
    }

    if (statusFilter.value === 'active' && !user.is_active) {
      return false
    }

    if (statusFilter.value === 'inactive' && user.is_active) {
      return false
    }

    return true
  })
})

const hasActiveFilters = computed(() =>
  Boolean(searchQuery.value.trim() || roleFilter.value || statusFilter.value),
)

const isCardActive = (role = '', status = '') => {
  const matchesCategory = roleFilter.value === role && statusFilter.value === status

  if (role === '' && status === '') {
    return matchesCategory && !searchQuery.value.trim()
  }

  return matchesCategory
}

const applyCardFilter = (role = '', status = '') => {
  searchQuery.value = ''
  roleFilter.value = role
  statusFilter.value = status
}

const resetFilters = () => {
  searchQuery.value = ''
  roleFilter.value = ''
  statusFilter.value = ''
}

const {
  sortedItems: sortedUsers,
  toggleSort: toggleUserSort,
  sortDirection: userSortDirection,
  ariaSortValue: userAriaSortValue,
} = useTableSort(filteredUsers, {
  accessors: {
    name: (user) => user.name ?? '',
    nim: (user) => user.nim ?? '',
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

const canGoToPreviousPage = computed(() => currentPage.value > 1 && filteredUsers.value.length > 0)
const canGoToNextPage = computed(() => currentPage.value < pages.value.length && filteredUsers.value.length > 0)

watch([searchQuery, roleFilter, statusFilter], () => {
  currentPage.value = 1
})

const perPageOptions = [5, 10, 25, 50]

const isCurrentUser = (userId) => props.currentUserId !== null && userId === props.currentUserId

const selectedUser = ref(null)
const deactivationForm = useForm({
  reason: '',
})
const activationForm = useForm({})
const deactivationReasonSuggestions = [
  'Spam pengajuan berulang kali',
  'Penyalahgunaan sistem',
  'Pelanggaran kebijakan peminjaman',
]
const deactivationReasonLength = computed(() => deactivationForm.reason.length)

const openDeactivationModal = (user) => {
  selectedUser.value = user
  deactivationForm.reset()
  deactivationForm.clearErrors()
}

const closeDeactivationModal = () => {
  if (deactivationForm.processing) {
    return
  }

  selectedUser.value = null
  deactivationForm.reset()
  deactivationForm.clearErrors()
}

const deactivateUser = () => {
  if (!selectedUser.value) {
    return
  }

  deactivationForm.patch(route('admin.users.deactivate', selectedUser.value.id), {
    preserveScroll: true,
    onSuccess: closeDeactivationModal,
  })
}

const useReasonSuggestion = (reason) => {
  deactivationForm.reason = reason
  deactivationForm.clearErrors('reason')
}

const activateUser = (user) => {
  if (!confirm(`Aktifkan kembali akun ${user.name}?`)) {
    return
  }

  activationForm.patch(route('admin.users.activate', user.id), {
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
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
          <Link
            :href="route('admin.users.index')"
            class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-600 shadow-sm transition hover:border-gray-300 hover:text-gray-800 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200 sm:w-auto"
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

      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <button
          type="button"
          class="group rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-slate-200 dark:bg-slate-800 dark:focus:ring-slate-700"
          :class="isCardActive('', '') ? 'border-slate-500 ring-2 ring-slate-300 dark:border-slate-400 dark:ring-slate-600' : 'border-slate-200 dark:border-slate-700'"
          :aria-pressed="isCardActive('', '')"
          @click="applyCardFilter('', '')"
        >
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Total User</p>
          <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ stats.total }}</p>
          <p class="text-xs text-slate-500 dark:text-slate-400">Klik untuk melihat seluruh user</p>
        </button>
        <button
          type="button"
          class="group rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-indigo-100 dark:bg-slate-800 dark:focus:ring-indigo-950"
          :class="isCardActive('super_admin', '') ? 'border-indigo-500 ring-2 ring-indigo-200 dark:border-indigo-400 dark:ring-indigo-900' : 'border-indigo-200 dark:border-indigo-800'"
          :aria-pressed="isCardActive('super_admin', '')"
          @click="applyCardFilter('super_admin', '')"
        >
          <p class="text-xs font-medium uppercase tracking-wide text-indigo-500 dark:text-indigo-400">Super Admin</p>
          <p class="mt-2 text-2xl font-semibold text-indigo-600 dark:text-indigo-400">{{ stats.super_admins }}</p>
          <p class="text-xs text-indigo-500 dark:text-indigo-400">Klik untuk memfilter data</p>
        </button>
        <button
          type="button"
          class="group rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-blue-100 dark:bg-slate-800 dark:focus:ring-blue-950"
          :class="isCardActive('admin_bap', '') ? 'border-blue-500 ring-2 ring-blue-200 dark:border-blue-400 dark:ring-blue-900' : 'border-blue-200 dark:border-blue-800'"
          :aria-pressed="isCardActive('admin_bap', '')"
          @click="applyCardFilter('admin_bap', '')"
        >
          <p class="text-xs font-medium uppercase tracking-wide text-blue-500 dark:text-blue-400">Admin BAP</p>
          <p class="mt-2 text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ stats.admin_bap }}</p>
          <p class="text-xs text-blue-500 dark:text-blue-400">Klik untuk memfilter data</p>
        </button>
        <button
          type="button"
          class="group rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-amber-100 dark:bg-slate-800 dark:focus:ring-amber-950"
          :class="isCardActive('admin_sarpras', '') ? 'border-amber-500 ring-2 ring-amber-200 dark:border-amber-400 dark:ring-amber-900' : 'border-amber-200 dark:border-amber-800'"
          :aria-pressed="isCardActive('admin_sarpras', '')"
          @click="applyCardFilter('admin_sarpras', '')"
        >
          <p class="text-xs font-medium uppercase tracking-wide text-amber-500 dark:text-amber-400">Admin Sarpras</p>
          <p class="mt-2 text-2xl font-semibold text-amber-600 dark:text-amber-400">{{ stats.admin_sarpras }}</p>
          <p class="text-xs text-amber-500 dark:text-amber-400">Klik untuk memfilter data</p>
        </button>
        <button
          type="button"
          class="group rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-emerald-100 dark:bg-slate-800 dark:focus:ring-emerald-950"
          :class="isCardActive('user', '') ? 'border-emerald-500 ring-2 ring-emerald-200 dark:border-emerald-400 dark:ring-emerald-900' : 'border-emerald-200 dark:border-emerald-800'"
          :aria-pressed="isCardActive('user', '')"
          @click="applyCardFilter('user', '')"
        >
          <p class="text-xs font-medium uppercase tracking-wide text-emerald-500 dark:text-emerald-400">User Biasa</p>
          <p class="mt-2 text-2xl font-semibold text-emerald-600 dark:text-emerald-400">{{ stats.members }}</p>
          <p class="text-xs text-emerald-500 dark:text-emerald-400">Klik untuk memfilter data</p>
        </button>
        <button
          type="button"
          class="group rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-rose-100 dark:bg-slate-800 dark:focus:ring-rose-950"
          :class="isCardActive('user', 'inactive') ? 'border-rose-500 ring-2 ring-rose-200 dark:border-rose-400 dark:ring-rose-900' : 'border-rose-200 dark:border-rose-900/60'"
          :aria-pressed="isCardActive('user', 'inactive')"
          @click="applyCardFilter('user', 'inactive')"
        >
          <p class="text-xs font-medium uppercase tracking-wide text-rose-500 dark:text-rose-400">Akun Nonaktif</p>
          <p class="mt-2 text-2xl font-semibold text-rose-600 dark:text-rose-400">{{ stats.inactive_members }}</p>
          <p class="text-xs text-rose-500 dark:text-rose-400">Klik untuk melihat akun nonaktif</p>
        </button>
      </div>

      <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="border-b border-gray-100 px-5 py-4 dark:border-slate-700">
          <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar User Terdaftar</div>
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Menampilkan {{ filteredUsers.length }} dari {{ usersList.length }} user.
              </p>
            </div>
            <button
              v-if="hasActiveFilters"
              type="button"
              class="mt-2 inline-flex items-center gap-2 self-start rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white sm:mt-0"
              @click="resetFilters"
            >
              <RotateCcw class="h-4 w-4" />
              Reset filter
            </button>
          </div>

          <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(260px,1fr)_220px_180px_auto]">
            <label class="relative block">
              <span class="sr-only">Cari user</span>
              <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <input
                v-model="searchQuery"
                type="search"
                class="h-11 w-full rounded-xl border border-slate-300 bg-white pl-10 pr-10 text-sm text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:focus:border-blue-400 dark:focus:ring-blue-950"
                placeholder="Cari nama, NIM, email, atau nomor telepon..."
              />
              <button
                v-if="searchQuery"
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-600 dark:hover:text-white"
                aria-label="Hapus pencarian"
                @click="searchQuery = ''"
              >
                <X class="h-4 w-4" />
              </button>
            </label>

            <label class="relative block">
              <span class="sr-only">Filter role</span>
              <Filter class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <select
                v-model="roleFilter"
                class="h-11 w-full appearance-none rounded-xl border border-slate-300 bg-white pl-10 pr-9 text-sm text-slate-700 shadow-sm transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 dark:focus:border-blue-400 dark:focus:ring-blue-950"
              >
                <option value="">Semua role</option>
                <option value="super_admin">Super Admin</option>
                <option value="admin_bap">Admin BAP</option>
                <option value="admin_sarpras">Admin Sarpras</option>
                <option value="user">User Mahasiswa</option>
              </select>
            </label>

            <select
              v-model="statusFilter"
              class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 shadow-sm transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 dark:focus:border-blue-400 dark:focus:ring-blue-950"
              aria-label="Filter status akun"
            >
              <option value="">Semua status</option>
              <option value="active">Aktif</option>
              <option value="inactive">Nonaktif</option>
            </select>

            <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
            <label class="font-medium text-gray-700 dark:text-gray-300" for="admin-users-rows">Rows per page</label>
              <select
                  id="admin-users-rows"
                  v-model.number="rowsPerPage"
                  class="h-11 w-20 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 dark:focus:border-blue-400 dark:focus:ring-blue-950"
                >
                  <option v-for="option in perPageOptions" :key="`rows-${option}`" :value="option">
                    {{ option }}
                  </option>
                </select>
              </div>
          </div>
        </div>

        <table class="master-mobile-table mobile-friendly-table min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
          <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-slate-700 dark:text-slate-400">
            <tr>
              <SortableTh class="px-5 py-3 text-left" column="name" label="Nama" :direction="userSortDirection('name')" :aria-sort="userAriaSortValue('name')" @toggle="toggleUserSort" />
              <SortableTh class="px-5 py-3 text-left" column="nim" label="NIM" :direction="userSortDirection('nim')" :aria-sort="userAriaSortValue('nim')" @toggle="toggleUserSort" />
              <SortableTh class="px-5 py-3 text-left" column="email" label="Email" :direction="userSortDirection('email')" :aria-sort="userAriaSortValue('email')" @toggle="toggleUserSort" />
              <SortableTh class="px-5 py-3 text-left" column="phone" label="No. Telp" :direction="userSortDirection('phone')" :aria-sort="userAriaSortValue('phone')" @toggle="toggleUserSort" />
              <SortableTh class="px-5 py-3 text-left" column="role" label="Role" :direction="userSortDirection('role')" :aria-sort="userAriaSortValue('role')" @toggle="toggleUserSort" />
              <th class="px-5 py-3 text-left">Status Akun</th>
              <SortableTh class="px-5 py-3 text-left" column="created_at" label="Dibuat" :direction="userSortDirection('created_at')" :aria-sort="userAriaSortValue('created_at')" @toggle="toggleUserSort" />
              <th class="px-5 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-slate-700 dark:text-slate-300">
            <tr
              v-for="user in paginatedUsers"
              :key="user.id"
              class="transition hover:bg-gray-50 dark:hover:bg-slate-700/50"
              :class="{ 'bg-blue-50/50 shadow-[inset_3px_0_0_#3b82f6] dark:bg-blue-950/35 dark:hover:bg-blue-900/30': isCurrentUser(user.id) }"
            >
              <td class="mobile-primary-cell mobile-span-2 px-5 py-4" data-title="Nama">
                <div class="mobile-primary-label">Nama</div>
                <div class="mobile-primary-title">{{ user.name }}</div>
                <div v-if="isCurrentUser(user.id)" class="text-xs font-semibold uppercase text-blue-500 dark:text-blue-400">Anda</div>
              </td>
              <td class="px-5 py-4 text-sm text-gray-700 dark:text-slate-300 mobile-meta-cell mobile-compact-meta" data-title="NIM">{{ user.nim || '-' }}</td>
              <td class="px-5 py-4 text-sm text-gray-700 dark:text-slate-300 mobile-meta-cell mobile-compact-meta" data-title="Email">{{ user.email }}</td>
              <td class="px-5 py-4 text-sm text-gray-700 dark:text-slate-300 mobile-meta-cell mobile-compact-meta" data-title="No. Telp">{{ user.phone || '-' }}</td>
              <td class="mobile-status-cell px-5 py-4" data-title="Role">
                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold" :class="roleBadgeClasses[user.role] ?? 'bg-gray-100 text-gray-600 border-gray-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600'">
                  {{ user.roleLabel }}
                </span>
              </td>
              <td class="mobile-status-cell px-5 py-4" data-title="Status Akun">
                <div class="flex flex-col items-start gap-1">
                  <span
                    class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
                    :class="user.is_active
                      ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                      : 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-300'"
                  >
                    {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                  </span>
                  <span
                    v-if="!user.is_active && user.deactivated_at"
                    class="text-xs text-slate-500 dark:text-slate-400"
                    :title="user.deactivation_reason || ''"
                  >
                    Sejak {{ formatDate(user.deactivated_at) }}
                  </span>
                </div>
              </td>
              <td class="px-5 py-4 text-sm text-gray-700 dark:text-slate-300 mobile-compact-meta" data-title="Dibuat">{{ formatDate(user.created_at) }}</td>
              <td class="mobile-action-cell px-5 py-4 text-right" data-title="Aksi">
                <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                  <Link
                    :href="route('admin.users.edit', user.id)"
                    class="inline-flex items-center rounded-md border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-700 transition hover:border-blue-300 hover:text-blue-800 dark:border-blue-800 dark:text-blue-300 dark:hover:border-blue-700 dark:hover:text-blue-200"
                  >
                    Edit
                  </Link>
                  <button
                    v-if="user.role === 'user' && user.is_active"
                    type="button"
                    class="inline-flex items-center rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-rose-800 dark:text-rose-300 dark:hover:border-rose-700 dark:hover:bg-rose-900/30"
                    :disabled="deactivationForm.processing || activationForm.processing"
                    @click="openDeactivationModal(user)"
                  >
                    Nonaktifkan
                  </button>
                  <button
                    v-if="user.role === 'user' && !user.is_active"
                    type="button"
                    class="inline-flex items-center rounded-md border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-800 dark:text-emerald-300 dark:hover:border-emerald-700 dark:hover:bg-emerald-900/30"
                    :disabled="activationForm.processing || deactivationForm.processing"
                    @click="activateUser(user)"
                  >
                    {{ activationForm.processing ? 'Memproses...' : 'Aktifkan' }}
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!filteredUsers.length">
              <td colspan="8" class="px-5 py-10 text-center text-sm text-gray-400 dark:text-slate-500">
                {{ hasActiveFilters ? 'Tidak ada user yang sesuai dengan pencarian atau filter.' : 'Belum ada user terdaftar.' }}
              </td>
            </tr>
          </tbody>
        </table>

        <div class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:text-slate-400">
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} user</span>
            <span v-else>Menampilkan 0 data</span>
          </div>
          <div class="w-full sm:w-auto">
            <div class="mobile-pagination-compact sm:hidden">
              <button type="button" class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(currentPage - 1)" :disabled="!canGoToPreviousPage">Sebelumnya</button>
              <button type="button" class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(currentPage + 1)" :disabled="!canGoToNextPage">Berikutnya</button>
            </div>
            <div class="hidden items-center gap-2 sm:flex">
              <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(1)" :disabled="currentPage === 1 || !filteredUsers.length">«</button>
              <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(currentPage - 1)" :disabled="currentPage === 1 || !filteredUsers.length">‹</button>
              <template v-if="filteredUsers.length">
                <button
                  v-for="page in pages"
                  :key="`user-page-${page}`"
                  type="button"
                  class="rounded border px-3 py-1 text-sm transition"
                  :class="currentPage === page ? 'border-blue-500 bg-blue-500 text-white' : 'border-gray-300 text-gray-600 hover:border-blue-400 hover:text-blue-600 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400'"
                  :disabled="typeof page !== 'number'"
                  @click="changePage(page)"
                >
                  {{ page }}
                </button>
              </template>
              <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(currentPage + 1)" :disabled="currentPage === pages.length || !filteredUsers.length">›</button>
              <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(pages.length)" :disabled="currentPage === pages.length || !filteredUsers.length">»</button>
            </div>
          </div>
        </div>
      </div>

      <Modal :show="selectedUser !== null" max-width="xl" @close="closeDeactivationModal">
        <form class="overflow-hidden bg-white dark:bg-slate-900" @submit.prevent="deactivateUser">
          <div class="relative border-b border-rose-100 bg-gradient-to-br from-rose-50 via-white to-orange-50 px-5 py-5 dark:border-rose-900/50 dark:from-rose-950/40 dark:via-slate-900 dark:to-orange-950/20 sm:px-7 sm:py-6">
            <button
              type="button"
              class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-400 transition hover:bg-white/80 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-rose-400 dark:hover:bg-slate-800 dark:hover:text-white"
              aria-label="Tutup modal"
              :disabled="deactivationForm.processing"
              @click="closeDeactivationModal"
            >
              <X class="h-5 w-5" />
            </button>

            <div class="flex items-start gap-4 pr-10">
              <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-rose-100 text-rose-600 ring-8 ring-rose-50 dark:bg-rose-900/50 dark:text-rose-300 dark:ring-rose-950/30">
                <ShieldAlert class="h-6 w-6" />
              </div>
              <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-rose-600 dark:text-rose-300">Tindakan akses</p>
                <h2 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">Nonaktifkan akun mahasiswa?</h2>
                <p class="mt-1.5 text-sm leading-6 text-slate-600 dark:text-slate-300">
                  Akun akan langsung dikeluarkan dari sistem dan tidak dapat masuk kembali sampai diaktifkan oleh super admin.
                </p>
              </div>
            </div>
          </div>

          <div class="space-y-5 px-5 py-5 sm:px-7 sm:py-6">
            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-800/70">
              <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white text-slate-500 shadow-sm ring-1 ring-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:ring-slate-600">
                  <UserRound class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate font-semibold text-slate-900 dark:text-white">{{ selectedUser?.name }}</p>
                  <p class="mt-0.5 flex items-center gap-1.5 truncate text-sm text-slate-500 dark:text-slate-400">
                    <Mail class="h-3.5 w-3.5 shrink-0" />
                    <span class="truncate">{{ selectedUser?.email }}</span>
                  </p>
                </div>
                <span class="hidden rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 sm:inline-flex">
                  Aktif
                </span>
              </div>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 dark:border-amber-900/60 dark:bg-amber-950/20">
              <div class="flex gap-3">
                <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                <div>
                  <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Dampak penonaktifan</p>
                  <ul class="mt-2 space-y-1.5 text-sm text-amber-800 dark:text-amber-300">
                    <li class="flex items-start gap-2">
                      <Ban class="mt-0.5 h-4 w-4 shrink-0" />
                      Semua sesi aktif mahasiswa akan dihentikan.
                    </li>
                    <li class="flex items-start gap-2">
                      <Check class="mt-0.5 h-4 w-4 shrink-0" />
                      Email, booking, dan histori peminjaman tetap tersimpan.
                    </li>
                  </ul>
                </div>
              </div>
            </div>

            <div>
              <div class="flex items-center justify-between gap-3">
                <InputLabel for="deactivation-reason" value="Alasan penonaktifan" />
                <span class="text-xs tabular-nums text-slate-400 dark:text-slate-500">{{ deactivationReasonLength }}/1000</span>
              </div>
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Alasan tersimpan dalam audit log dan hanya dapat dilihat administrator.</p>

              <div class="mt-3 flex flex-wrap gap-2">
                <button
                  v-for="reason in deactivationReasonSuggestions"
                  :key="reason"
                  type="button"
                  class="rounded-full border px-3 py-1.5 text-xs font-medium transition"
                  :class="deactivationForm.reason === reason
                    ? 'border-rose-300 bg-rose-50 text-rose-700 dark:border-rose-700 dark:bg-rose-900/30 dark:text-rose-300'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-rose-200 hover:text-rose-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-rose-800 dark:hover:text-rose-300'"
                  @click="useReasonSuggestion(reason)"
                >
                  {{ reason }}
                </button>
              </div>

              <textarea
                id="deactivation-reason"
                v-model="deactivationForm.reason"
                rows="4"
                maxlength="1000"
                required
                autofocus
                class="mt-3 block w-full resize-none rounded-2xl border bg-white px-4 py-3 text-sm leading-6 text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-rose-400 focus:ring-4 focus:ring-rose-100 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500 dark:focus:border-rose-500 dark:focus:ring-rose-950/60"
                :class="deactivationForm.errors.reason ? 'border-rose-400' : 'border-slate-300 dark:border-slate-600'"
                placeholder="Jelaskan alasan akun ini perlu dinonaktifkan..."
                @input="deactivationForm.clearErrors('reason')"
              />
              <InputError class="mt-2" :message="deactivationForm.errors.reason" />
            </div>
          </div>

          <div class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-800/70 sm:flex-row sm:items-center sm:justify-end sm:px-7">
            <button
              type="button"
              class="inline-flex h-11 w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-200 disabled:opacity-60 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:focus:ring-slate-700 sm:w-auto"
              :disabled="deactivationForm.processing"
              @click="closeDeactivationModal"
            >
              Batal
            </button>
            <button
              type="submit"
              class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-rose-600 px-5 text-sm font-semibold text-white shadow-lg shadow-rose-600/20 transition hover:bg-rose-700 focus:outline-none focus:ring-4 focus:ring-rose-200 disabled:cursor-not-allowed disabled:opacity-60 dark:focus:ring-rose-950 sm:w-auto"
              :disabled="deactivationForm.processing || !deactivationForm.reason.trim()"
            >
              <span v-if="deactivationForm.processing" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
              <Ban v-else class="h-4 w-4" />
              {{ deactivationForm.processing ? 'Menonaktifkan...' : 'Nonaktifkan akun' }}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  </AuthenticatedLayout>
</template>
