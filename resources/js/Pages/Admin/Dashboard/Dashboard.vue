<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const page = usePage()

const props = defineProps({
  bookings: {
    type: Array,
    default: () => [],
  },
  statusSummary: {
    type: Object,
    default: () => ({
      total: 0,
      approved: 0,
      waiting: 0,
      rejected: 0,
    }),
  },
})

const normalizeStatus = (status) => {
  if (!status) return ''
  return status === 'pending' || status === 'requested' ? 'waiting' : status
}

const statusLabels = {
  approved: 'Disetujui',
  waiting: 'Menunggu Persetujuan',
  rejected: 'Ditolak',
}

const statusBadgeClasses = {
  approved: 'bg-emerald-100 text-emerald-700 border border-emerald-200',
  waiting: 'bg-amber-100 text-amber-700 border border-amber-200',
  rejected: 'bg-rose-100 text-rose-700 border border-rose-200',
}

const isUser = computed(() => page.props.auth.user.role === 'user')
const isAdmin = computed(() => page.props.auth.user.role === 'admin')

const createEmptyFilters = () => ({
  keyword: '',
  user: '',
  room: '',
  status: '',
})

const filterDrafts = ref(createEmptyFilters())
const activeFilters = ref(createEmptyFilters())

const applyFilters = () => {
  activeFilters.value = { ...filterDrafts.value }
}

const normalizedBookings = computed(() =>
  (props.bookings ?? []).map((booking) => ({
    ...booking,
    normalizedStatus: normalizeStatus(booking.status),
  })),
)

const matchesStatusFilter = (booking, status) => {
  if (!status) {
    return true
  }

  const normalizedStatus = booking.normalizedStatus ?? ''
  const rawStatus = booking.status ?? ''

  if (status === 'requested') {
    return rawStatus === 'requested' || rawStatus === 'pending' || normalizedStatus === 'waiting'
  }

  if (status === 'approved') {
    return normalizedStatus === 'approved' || rawStatus === 'approved'
  }

  if (status === 'rejected') {
    return normalizedStatus === 'rejected' || rawStatus === 'rejected'
  }

  return normalizedStatus === status || rawStatus === status
}

const filteredBookings = computed(() => {
  const keyword = activeFilters.value.keyword.trim().toLowerCase()
  const status = activeFilters.value.status
  const userQuery = activeFilters.value.user.trim().toLowerCase()
  const roomQuery = activeFilters.value.room.trim().toLowerCase()

  return normalizedBookings.value.filter((booking) => {
    if (!matchesStatusFilter(booking, status)) {
      return false
    }

    if (keyword) {
      const searchable = [
        booking.title,
        booking.description,
        booking.user?.name,
        booking.user?.email,
        booking.room?.name,
        booking.room?.building?.name,
        booking.room?.building?.campus?.name,
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()

      if (!searchable.includes(keyword)) {
        return false
      }
    }

    if (userQuery) {
      const userSearchable = [booking.user?.name, booking.user?.email].filter(Boolean).join(' ').toLowerCase()

      if (!userSearchable.includes(userQuery)) {
        return false
      }
    }

    if (roomQuery) {
      const roomSearchable = [
        booking.room?.name,
        booking.room?.building?.name,
        booking.room?.building?.campus?.name,
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()

      if (!roomSearchable.includes(roomQuery)) {
        return false
      }
    }

    return true
  })
})

const summary = computed(() => ({
  total: props.statusSummary?.total ?? normalizedBookings.value.length,
  approved: props.statusSummary?.approved ?? 0,
  waiting: props.statusSummary?.waiting ?? 0,
  rejected: props.statusSummary?.rejected ?? 0,
}))

const formatDateTime = (value) => {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) {
    return value
  }

  return parsed.toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Dashboard" />

    <div class="py-12">
      <div class="mx-auto space-y-8 px-4 sm:px-6 lg:max-w-7xl lg:px-8">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 class="text-3xl font-semibold text-gray-900">Dashboard</h1>
            <p class="text-sm text-gray-500">
              {{ isAdmin ? 'Lihat riwayat peminjaman seluruh pengguna.' : 'Pantau permintaan peminjaman yang telah kamu ajukan.' }}
            </p>
          </div>
          <Link
            v-if="isUser"
            :href="route('bookings.create')"
            class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
          >
            + Buat Booking
          </Link>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-blue-800">
            <div class="text-sm font-medium">Total Pengajuan</div>
            <div class="mt-2 text-3xl font-semibold">{{ summary.total }}</div>
          </div>
          <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 text-emerald-700">
            <div class="text-sm font-medium">Disetujui</div>
            <div class="mt-2 text-3xl font-semibold">{{ summary.approved }}</div>
          </div>
          <div class="rounded-xl border border-amber-100 bg-amber-50 p-4 text-amber-700">
            <div class="text-sm font-medium">Menunggu</div>
            <div class="mt-2 text-3xl font-semibold">{{ summary.waiting }}</div>
          </div>
          <div class="rounded-xl border border-rose-100 bg-rose-50 p-4 text-rose-700">
            <div class="text-sm font-medium">Ditolak</div>
            <div class="mt-2 text-3xl font-semibold">{{ summary.rejected }}</div>
          </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
          <div class="border-b border-gray-100 p-4">
            <div class="grid gap-4 md:grid-cols-3">
              <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-gray-700" for="dashboard-search">Pencarian</label>
                <input
                  id="dashboard-search"
                  v-model="searchQuery"
                  type="text"
                  placeholder="Cari judul, pemohon, ruangan, atau kampus..."
                  class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="dashboard-status">Status</label>
                <div class="relative">
                  <select
                    id="dashboard-status"
                    v-model="statusFilter"
                    class="w-full appearance-none rounded-md border border-gray-300 px-3 py-2 pr-9 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  >
                    <option value="">Semua Status</option>
                    <option value="approved">Disetujui</option>
                    <option value="waiting">Menunggu</option>
                    <option value="rejected">Ditolak</option>
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
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm text-gray-700">
              <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                  <th class="px-4 py-3 text-left">Judul</th>
                  <th v-if="isAdmin" class="px-4 py-3 text-left">Pemohon</th>
                  <th class="px-4 py-3 text-left">Ruangan</th>
                  <th class="px-4 py-3 text-left">Mulai</th>
                  <th class="px-4 py-3 text-left">Selesai</th>
                  <th class="px-4 py-3 text-left">Status</th>
                  <th v-if="isAdmin" class="px-4 py-3 text-left">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="booking in filteredBookings" :key="booking.id" class="hover:bg-gray-50">
                  <td class="px-4 py-3">
                    <div class="font-semibold text-gray-900">{{ booking.title }}</div>
                    <div class="text-xs text-gray-500">
                      {{ booking.description || 'Tidak ada deskripsi tambahan.' }}
                    </div>
                  </td>
                  <td v-if="isAdmin" class="px-4 py-3">
                    <div class="font-medium text-gray-900">{{ booking.user?.name ?? '-' }}</div>
                    <div class="text-xs text-gray-500">{{ booking.user?.email ?? '-' }}</div>
                  </td>
                  <td class="px-4 py-3">
                    <div class="font-medium text-gray-900">{{ booking.room?.name ?? '-' }}</div>
                    <div class="text-xs text-gray-500">
                      {{ booking.room?.building?.name ?? '-' }} - {{ booking.room?.building?.campus?.name ?? '-' }}
                    </div>
                  </td>
                  <td class="px-4 py-3 text-gray-700">{{ formatDateTime(booking.start_time) }}</td>
                  <td class="px-4 py-3 text-gray-700">{{ formatDateTime(booking.end_time) }}</td>
                  <td class="px-4 py-3">
                    <span
                      class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold capitalize"
                      :class="statusBadgeClasses[booking.normalizedStatus] ?? 'bg-gray-100 text-gray-600 border border-gray-200'"
                    >
                      {{ statusLabels[booking.normalizedStatus] ?? (booking.normalizedStatus || booking.status) }}
                    </span>
                  </td>
                  <td v-if="isAdmin" class="px-4 py-3">
                    <Link
                      :href="route('admin.bookings.show', booking.id)"
                      class="inline-flex items-center rounded-md border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50"
                    >
                      Lihat Detail
                    </Link>
                  </td>
                </tr>
                <tr v-if="!filteredBookings.length">
                  <td :colspan="isAdmin ? 7 : 6" class="px-4 py-10 text-center text-sm text-gray-500">
                    Tidak ada data booking yang cocok dengan filter saat ini.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
