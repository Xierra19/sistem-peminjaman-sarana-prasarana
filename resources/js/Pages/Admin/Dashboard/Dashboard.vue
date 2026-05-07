<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { formatDateTimeToDDMMYY } from '@/Composables/useDateFormatter'
import { Bar, Pie } from 'vue-chartjs'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
  ArcElement,
} from 'chart.js'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement)

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
      cancelled: 0,
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
  cancelled: 'Dibatalkan',
}

const statusBadgeClasses = {
  approved: 'bg-emerald-100 text-emerald-700 border border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-100 dark:border-emerald-800',
  waiting: 'bg-amber-100 text-amber-700 border border-amber-200 dark:bg-amber-900/30 dark:text-amber-100 dark:border-amber-800',
  rejected: 'bg-rose-100 text-rose-700 border border-rose-200 dark:bg-rose-900/30 dark:text-rose-100 dark:border-rose-800',
  cancelled: 'bg-violet-100 text-violet-700 border border-violet-200 dark:bg-violet-900/30 dark:text-violet-100 dark:border-violet-800',
}

const isUser = computed(() => page.props.auth.user.role === 'user')
const isAdmin = computed(() => Boolean(page.props.auth?.permissions?.is_admin))

const createEmptyFilters = () => ({
  title: '',
  applicant: '',
  room: '',
  campus: '',
  status: '',
})

const filterDrafts = ref(createEmptyFilters())
const activeFilters = ref(createEmptyFilters())

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

  if (status === 'cancelled') {
    return normalizedStatus === 'cancelled' || rawStatus === 'cancelled'
  }

  return normalizedStatus === status || rawStatus === status
}

const filteredBookings = computed(() => {
  const titleQuery = activeFilters.value.title.trim().toLowerCase()
  const applicantQuery = activeFilters.value.applicant.trim().toLowerCase()
  const status = activeFilters.value.status
  const roomQuery = activeFilters.value.room.trim().toLowerCase()
  const campusQuery = activeFilters.value.campus.trim().toLowerCase()

  return normalizedBookings.value.filter((booking) => {
    if (!matchesStatusFilter(booking, status)) {
      return false
    }

    if (titleQuery) {
      const titleSearchable = [booking.title, booking.description]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()

      if (!titleSearchable.includes(titleQuery)) {
        return false
      }
    }

    if (applicantQuery) {
      const userSearchable = [booking.user?.name, booking.user?.email].filter(Boolean).join(' ').toLowerCase()

      if (!userSearchable.includes(applicantQuery)) {
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

    if (campusQuery) {
      const campusName = booking.room?.building?.campus?.name?.toLowerCase() ?? ''

      if (!campusName.includes(campusQuery)) {
        return false
      }
    }

    return true
  })
})

const perPageOptions = [5, 10, 25, 50]

const {
  sortedItems: sortedBookings,
  toggleSort: toggleDashboardSort,
  sortDirection: dashboardSortDirection,
  ariaSortValue: dashboardAriaSortValue,
} = useTableSort(filteredBookings, {
  accessors: {
    title: (booking) => booking.title ?? '',
    applicant: (booking) => booking.user?.name ?? '',
    room: (booking) => booking.room?.name ?? '',
    start_time: (booking) => (booking.start_time ? new Date(booking.start_time) : null),
    end_time: (booking) => (booking.end_time ? new Date(booking.end_time) : null),
    status: (booking) => booking.normalizedStatus ?? '',
  },
})

const {
  paginatedItems: paginatedBookings,
  currentPage,
  rowsPerPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(sortedBookings)

const applyFilters = () => {
  activeFilters.value = { ...filterDrafts.value }
  changePage(1)
}

const resetFilters = () => {
  const defaults = createEmptyFilters()
  filterDrafts.value = { ...defaults }
  activeFilters.value = { ...defaults }
  changePage(1)
}

const summary = computed(() => ({
  total: props.statusSummary?.total ?? normalizedBookings.value.length,
  approved: props.statusSummary?.approved ?? 0,
  waiting: props.statusSummary?.waiting ?? 0,
  rejected: props.statusSummary?.rejected ?? 0,
  cancelled: props.statusSummary?.cancelled ?? 0,
}))

const formatDateTime = (value) => formatDateTimeToDDMMYY(value)

// ==========================================
// CHART DATA: STATUS DISTRIBUTION (PIE)
// ==========================================
const statusChartData = computed(() => {
  const bookings = filteredBookings.value
  const counts = {
    approved: 0,
    waiting: 0,
    rejected: 0,
    cancelled: 0,
  }
  
  bookings.forEach(b => {
    const s = b.normalizedStatus || ''
    if (counts[s] !== undefined) {
      counts[s]++
    }
  })

  return {
    labels: ['Disetujui', 'Menunggu', 'Ditolak', 'Dibatalkan'],
    datasets: [{
      data: [counts.approved, counts.waiting, counts.rejected, counts.cancelled],
      backgroundColor: [
        '#10b981', // emerald-500
        '#f59e0b', // amber-500
        '#f43f5e', // rose-500
        '#8b5cf6', // violet-500
      ],
      borderWidth: 1,
    }],
  }
})

// ==========================================
// CHART DATA: MONTHLY BOOKINGS (BAR)
// ==========================================
const monthlyChartData = computed(() => {
  const bookings = filteredBookings.value
  const monthCounts = {}

  bookings.forEach(b => {
    if (!b.created_at) return
    const d = new Date(b.created_at)
    if (isNaN(d.getTime())) return
    const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    monthCounts[key] = (monthCounts[key] || 0) + 1
  })

  // Sort months and take last 6
  const sortedMonths = Object.keys(monthCounts).sort()
  const last6 = sortedMonths.slice(-6)

  const labels = last6.map(m => {
    const [year, month] = m.split('-')
    const date = new Date(year, month - 1, 1)
    return date.toLocaleString('id-ID', { month: 'short', year: 'numeric' })
  })

  const data = last6.map(m => monthCounts[m])

  return {
    labels,
    datasets: [{
      label: 'Jumlah Peminjaman',
      data,
      backgroundColor: '#3b82f6', // blue-500
      borderColor: '#2563eb', // blue-600
      borderWidth: 1,
    }],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
    },
  },
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Dashboard" />

    <div class="py-12">
      <div class="mx-auto space-y-8 px-4 sm:px-6 lg:max-w-7xl lg:px-8">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 class="text-3xl font-semibold text-gray-900 dark:text-white">Dashboard</h1>
<p class="text-sm text-gray-500 dark:text-slate-400">
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

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
<div class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-blue-800 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-100">
  <div class="text-sm font-medium">Total Pengajuan</div>
  <div class="mt-2 text-3xl font-semibold">{{ summary.total }}</div>
</div>
<div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-100">
  <div class="text-sm font-medium">Disetujui</div>
  <div class="mt-2 text-3xl font-semibold">{{ summary.approved }}</div>
</div>
<div class="rounded-xl border border-amber-100 bg-amber-50 p-4 text-amber-700 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-100">
  <div class="text-sm font-medium">Menunggu</div>
  <div class="mt-2 text-3xl font-semibold">{{ summary.waiting }}</div>
</div>
<div class="rounded-xl border border-rose-100 bg-rose-50 p-4 text-rose-700 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-100">
  <div class="text-sm font-medium">Ditolak</div>
  <div class="mt-2 text-3xl font-semibold">{{ summary.rejected }}</div>
</div>
<div class="rounded-xl border border-slate-100 bg-slate-50 p-4 text-slate-700 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-200">
  <div class="text-sm font-medium">Dibatalkan</div>
  <div class="mt-2 text-3xl font-semibold">{{ summary.cancelled }}</div>
</div>
        </div>

        <!-- Charts Section -->
        <div class="grid gap-6 md:grid-cols-2 mb-6">
<div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
  <h3 class="text-lg font-semibold text-gray-800 mb-4 dark:text-white">Distribusi Status Booking</h3>
            <div class="h-64">
              <Pie :data="statusChartData" :options="chartOptions" />
            </div>
          </div>
<div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
  <h3 class="text-lg font-semibold text-gray-800 mb-4 dark:text-white">Tren Peminjaman 6 Bulan Terakhir</h3>
            <div class="h-64">
              <Bar :data="monthlyChartData" :options="chartOptions" />
            </div>
          </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
          <div class="border-b border-gray-100 px-5 py-4">
            <form class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6" @submit.prevent="applyFilters">
              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-gray-700" for="dashboard-title">Judul</label>
                <input
                  id="dashboard-title"
                  v-model="filterDrafts.title"
                  type="text"
                  placeholder="Masukkan judul kegiatan"
                  class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-gray-700" for="dashboard-applicant">Pemohon</label>
                <input
                  id="dashboard-applicant"
                  v-model="filterDrafts.applicant"
                  type="text"
                  placeholder="Nama atau email pemohon"
                  class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-gray-700" for="dashboard-room">Ruangan</label>
                <input
                  id="dashboard-room"
                  v-model="filterDrafts.room"
                  type="text"
                  placeholder="Nama ruangan atau gedung"
                  class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-gray-700" for="dashboard-campus">Kampus</label>
                <input
                  id="dashboard-campus"
                  v-model="filterDrafts.campus"
                  type="text"
                  placeholder="Nama kampus"
                  class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-gray-700" for="dashboard-status">Status</label>
                <div class="relative">
                  <select
                    id="dashboard-status"
                    v-model="filterDrafts.status"
                    class="w-full appearance-none rounded-md border border-gray-300 px-3 py-2 pr-9 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  >
                    <option value="">Semua Status</option>
                    <option value="approved">Disetujui</option>
                    <option value="waiting">Menunggu</option>
                    <option value="rejected">Ditolak</option>
                    <option value="cancelled">Dibatalkan</option>
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
              <div class="flex items-center justify-end gap-2 sm:col-span-2 xl:col-span-1">
                <button
                  type="button"
                  class="inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                  @click="resetFilters"
                >
                  Reset
                </button>
                <button
                  type="submit"
                  class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                >
                  Cari
                </button>
              </div>
            </form>
          </div>

          <div class="border-b border-gray-100 px-5 py-4">
            <div class="flex items-center justify-end gap-3 text-sm text-gray-600">
              <label class="font-medium text-gray-700" for="dashboard-rows">Rows per page</label>
              <div class="relative">
                <select
                  id="dashboard-rows"
                  v-model.number="rowsPerPage"
                  class="w-20 rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                  <option v-for="option in perPageOptions" :key="option" :value="option">
                    {{ option }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm text-gray-700 dark:divide-slate-700 dark:text-slate-300">
              <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                  <SortableTh
                    class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                    column="title"
                    label="Judul"
                    :direction="dashboardSortDirection('title')"
                    :aria-sort="dashboardAriaSortValue('title')"
                    @toggle="toggleDashboardSort"
                  />
                  <SortableTh
                    v-if="isAdmin"
                    class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                    column="applicant"
                    label="Pemohon"
                    :direction="dashboardSortDirection('applicant')"
                    :aria-sort="dashboardAriaSortValue('applicant')"
                    @toggle="toggleDashboardSort"
                  />
                  <SortableTh
                    class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                    column="room"
                    label="Ruangan"
                    :direction="dashboardSortDirection('room')"
                    :aria-sort="dashboardAriaSortValue('room')"
                    @toggle="toggleDashboardSort"
                  />
                  <SortableTh
                    class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                    column="start_time"
                    label="Mulai"
                    :direction="dashboardSortDirection('start_time')"
                    :aria-sort="dashboardAriaSortValue('start_time')"
                    @toggle="toggleDashboardSort"
                  />
                  <SortableTh
                    class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                    column="end_time"
                    label="Selesai"
                    :direction="dashboardSortDirection('end_time')"
                    :aria-sort="dashboardAriaSortValue('end_time')"
                    @toggle="toggleDashboardSort"
                  />
                  <SortableTh
                    class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                    column="status"
                    label="Status"
                    :direction="dashboardSortDirection('status')"
                    :aria-sort="dashboardAriaSortValue('status')"
                    @toggle="toggleDashboardSort"
                  />
                  <th v-if="isAdmin" class="px-5 py-3 text-left">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                <tr v-for="booking in paginatedBookings" :key="booking.id" class="hover:bg-gray-50 dark:hover:bg-slate-700">
                  <td class="px-5 py-4">
                    <div class="font-semibold text-gray-900 dark:text-white">{{ booking.title }}</div>
                    <div class="text-xs text-gray-500 dark:text-slate-400">
                      {{ booking.description || 'Tidak ada deskripsi tambahan.' }}
                    </div>
                  </td>
                  <td v-if="isAdmin" class="px-5 py-4">
                    <div class="font-medium text-gray-900 dark:text-white">{{ booking.user?.name ?? '-' }}</div>
                    <div class="text-xs text-gray-500 dark:text-slate-400">{{ booking.user?.email ?? '-' }}</div>
                  </td>
                  <td class="px-5 py-4">
                    <div class="font-medium text-gray-900 dark:text-white">{{ booking.room?.name ?? '-' }}</div>
                    <div class="text-xs text-gray-500 dark:text-slate-400">
                      {{ booking.room?.building?.name ?? '-' }} - {{ booking.room?.building?.campus?.name ?? '-' }}
                    </div>
                  </td>
                  <td class="px-5 py-4 text-gray-700">{{ formatDateTime(booking.start_time) }}</td>
                  <td class="px-5 py-4 text-gray-700">{{ formatDateTime(booking.end_time) }}</td>
                  <td class="px-5 py-4">
                    <span
                      class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold capitalize"
                      :class="statusBadgeClasses[booking.normalizedStatus] ?? 'bg-gray-100 text-gray-600 border border-gray-200'"
                    >
                      {{ statusLabels[booking.normalizedStatus] ?? (booking.normalizedStatus || booking.status) }}
                    </span>
                  </td>
                  <td v-if="isAdmin" class="px-5 py-4">
                    <Link
                      :href="route('admin.bookings.show', booking.id)"
                      class="inline-flex items-center rounded-md border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50"
                    >
                      Lihat Detail
                    </Link>
                  </td>
                </tr>
                <tr v-if="!filteredBookings.length">
                  <td :colspan="isAdmin ? 7 : 6" class="px-5 py-10 text-center text-sm text-gray-500">
                    Tidak ada data booking yang cocok dengan filter saat ini.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between"
          >
            <div>
              <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} data</span>
              <span v-else>Menampilkan 0 data</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <button
                type="button"
                class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
                @click="changePage(1)"
                :disabled="currentPage === 1 || !filteredBookings.length"
              >
                First
              </button>
              <button
                type="button"
                class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
                @click="changePage(currentPage - 1)"
                :disabled="currentPage === 1 || !filteredBookings.length"
              >
                Prev
              </button>
              <template v-if="filteredBookings.length">
                <button
                  v-for="page in pages"
                  :key="`dashboard-page-${page}`"
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
                :disabled="currentPage === pages.length || !filteredBookings.length"
              >
                Next
              </button>
              <button
                type="button"
                class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
                @click="changePage(pages.length)"
                :disabled="currentPage === pages.length || !filteredBookings.length"
              >
                Last
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
