<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Dropdown from '@/Components/Dropdown.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { Head, router } from '@inertiajs/vue3'
import { computed, reactive, watch } from 'vue'
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

const props = defineProps({
  bookings: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  statusSummary: {
    type: Object,
    default: () => ({}),
  },
  statusOptions: {
    type: Array,
    default: () => [],
  },
})

const filterForm = reactive({
  search: props.filters?.search ?? '',
  status: props.filters?.status ?? '',
  start_date: props.filters?.start_date ?? '',
  end_date: props.filters?.end_date ?? '',
})

watch(
  () => props.filters,
  (filters) => {
    filterForm.search = filters?.search ?? ''
    filterForm.status = filters?.status ?? ''
    filterForm.start_date = filters?.start_date ?? ''
    filterForm.end_date = filters?.end_date ?? ''
  },
  { deep: true },
)

const bookings = computed(() => props.bookings ?? [])

const {
  sortedItems: sortedBookings,
  toggleSort: toggleReportSort,
  sortDirection: reportSortDirection,
  ariaSortValue: reportAriaSortValue,
} = useTableSort(bookings, {
  accessors: {
    id: (booking) => booking.id ?? 0,
    created_at: (booking) => (booking.created_at ? new Date(booking.created_at) : null),
    applicant: (booking) => booking.user?.name ?? '',
    status: (booking) => booking.status ?? '',
    letter_number: (booking) => booking.letter_number ?? '',
    schedule: (booking) => (booking.start_time ? new Date(booking.start_time) : null),
    location: (booking) => booking.room?.name ?? '',
    details: (booking) => booking.title ?? '',
  },
})

const {
  paginatedItems: paginatedBookings,
  rowsPerPage,
  currentPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(sortedBookings, { perPage: 10 })

const perPageOptions = [10, 25, 50, 100]

const summary = computed(() => ({
  total: props.statusSummary?.total ?? bookings.value.length ?? 0,
  approved: props.statusSummary?.approved ?? 0,
  waiting: props.statusSummary?.waiting ?? 0,
  rejected: props.statusSummary?.rejected ?? 0,
  cancelled: props.statusSummary?.cancelled ?? 0,
}))

const statusLabels = {
  waiting: 'Menunggu',
  pending: 'Menunggu',
  approved: 'Disetujui',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan',
}

const statusBadgeClasses = {
  waiting: 'bg-amber-100 text-amber-800 border border-amber-200',
  pending: 'bg-amber-100 text-amber-800 border border-amber-200',
  approved: 'bg-emerald-100 text-emerald-700 border border-emerald-200',
  rejected: 'bg-rose-100 text-rose-700 border border-rose-200',
  cancelled: 'bg-slate-100 text-slate-700 border border-slate-200',
}

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

const buildQuery = () => {
  const payload = {}
  Object.entries(filterForm).forEach(([key, value]) => {
    if (value) {
      payload[key] = value
    }
  })
  return payload
}

const applyFilters = () => {
  changePage(1)
  router.get(route('admin.reports.index'), buildQuery(), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const resetFilters = () => {
  filterForm.search = ''
  filterForm.status = ''
  filterForm.start_date = ''
  filterForm.end_date = ''
  applyFilters()
}

const exportExcel = () => {
  const url = route('admin.reports.export', buildQuery())
  window.open(url, '_blank')
}

const exportPdf = () => {
  const url = route('admin.reports.export.pdf', buildQuery())
  window.open(url, '_blank')
}

// ==========================================
// CHART DATA: STATUS DISTRIBUTION (PIE)
// ==========================================
const statusChartData = computed(() => {
  const bookings = props.bookings ?? []
  const counts = {
    approved: 0,
    waiting: 0,
    rejected: 0,
    cancelled: 0,
  }
  
  bookings.forEach(b => {
    const s = b.status || ''
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
  const bookings = props.bookings ?? []
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
    <Head title="Report Booking Ruangan" />

    <div class="space-y-6">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Report Booking Ruangan</h1>
          <p class="text-sm text-gray-500">
            Rekap lengkap pengajuan ruangan beserta status terbaru dan data pemohon.
          </p>
        </div>
        <Dropdown align="right" width="48">
          <template #trigger>
            <button
              type="button"
              class="inline-flex items-center justify-center gap-2 rounded-2xl border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-100"
            >
              <span>Export</span>
              <svg class="h-4 w-4" fill="none" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                <path
                  d="M5.25 7.5 10 12.5l4.75-5"
                  stroke="currentColor"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="1.5"
                />
              </svg>
            </button>
          </template>
          <template #content>
            <div class="flex flex-col gap-1 p-2 text-sm text-slate-700">
              <button
                type="button"
                class="flex items-center gap-2 rounded-md px-3 py-2 hover:bg-blue-50"
                @click="exportExcel"
              >
                <span>Export Excel</span>
              </button>
              <button
                type="button"
                class="flex items-center gap-2 rounded-md px-3 py-2 hover:bg-blue-50"
                @click="exportPdf"
              >
                <span>Export PDF</span>
              </button>
            </div>
          </template>
        </Dropdown>
      </div>

      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total Data</p>
          <p class="mt-2 text-2xl font-semibold text-slate-900">{{ summary.total }}</p>
          <p class="text-xs text-slate-500">Seluruh hasil sesuai filter aktif</p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-amber-500">Menunggu</p>
          <p class="mt-2 text-2xl font-semibold text-amber-600">{{ summary.waiting }}</p>
          <p class="text-xs text-amber-500">Booking belum diputuskan</p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-emerald-500">Disetujui</p>
          <p class="mt-2 text-2xl font-semibold text-emerald-600">{{ summary.approved }}</p>
          <p class="text-xs text-emerald-500">Booking aktif</p>
        </div>
        <div class="rounded-xl border border-rose-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-rose-500">Ditolak / Batal</p>
          <p class="mt-2 text-2xl font-semibold text-rose-600">{{ summary.rejected + summary.cancelled }}</p>
          <p class="text-xs text-rose-500">Termasuk pembatalan admin</p>
        </div>
      </div>

      <!-- Charts Section -->
      <div class="grid gap-6 md:grid-cols-2 mb-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Distribusi Status Booking</h3>
          <div class="h-64">
            <Pie :data="statusChartData" :options="chartOptions" />
          </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Tren Peminjaman 6 Bulan Terakhir</h3>
          <div class="h-64">
            <Bar :data="monthlyChartData" :options="chartOptions" />
          </div>
        </div>
      </div>

      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <div class="flex flex-col gap-2">
            <label for="report-search" class="text-sm font-medium text-gray-700">Pencarian bebas</label>
            <input
              id="report-search"
              v-model="filterForm.search"
              type="text"
              placeholder="Nama pemohon, email, ruangan…"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
          </div>
          <div class="flex flex-col gap-2">
            <label for="report-status" class="text-sm font-medium text-gray-700">Status peminjaman</label>
            <select
              id="report-status"
              v-model="filterForm.status"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="">Semua status</option>
              <option v-for="status in statusOptions" :key="`status-${status}`" :value="status">
                {{ statusLabels[status] ?? status }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-2">
            <label for="report-start-date" class="text-sm font-medium text-gray-700">Tanggal pengajuan (dari)</label>
            <input
              id="report-start-date"
              v-model="filterForm.start_date"
              type="date"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
          </div>
          <div class="flex flex-col gap-2">
            <label for="report-end-date" class="text-sm font-medium text-gray-700">Tanggal pengajuan (sampai)</label>
            <input
              id="report-end-date"
              v-model="filterForm.end_date"
              type="date"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
          </div>
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:border-gray-300 hover:text-gray-800"
            @click="resetFilters"
          >
            Reset
          </button>
          <button
            type="button"
            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
            @click="applyFilters"
          >
            Terapkan Filter
          </button>
        </div>
      </div>

      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div
          class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 md:flex-row md:items-center md:justify-between"
        >
          <div class="text-sm font-semibold text-gray-700">Hasil Report</div>
          <div class="flex items-center gap-3 text-sm text-gray-600">
            <label for="report-rows" class="font-medium text-gray-700">Baris per halaman</label>
              <div class="relative">
                <select
                  id="report-rows"
                  v-model.number="rowsPerPage"
                  class="w-28 rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                  <option v-for="option in perPageOptions" :key="`rows-${option}`" :value="option">
                    {{ option }}
                  </option>
                </select>
              </div>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
              <tr>
                <SortableTh
                  class="px-5 py-3 text-left"
                  column="id"
                  :direction="reportSortDirection('id')"
                  :aria-sort="reportAriaSortValue('id')"
                  @toggle="toggleReportSort"
                >
                  ID
                </SortableTh>
                <SortableTh
                  class="px-5 py-3 text-left"
                  column="created_at"
                  :direction="reportSortDirection('created_at')"
                  :aria-sort="reportAriaSortValue('created_at')"
                  @toggle="toggleReportSort"
                >
                  Tanggal Pengajuan
                </SortableTh>
                <SortableTh
                  class="px-5 py-3 text-left"
                  column="applicant"
                  :direction="reportSortDirection('applicant')"
                  :aria-sort="reportAriaSortValue('applicant')"
                  @toggle="toggleReportSort"
                >
                  Pemohon
                </SortableTh>
                <SortableTh
                  class="px-5 py-3 text-left"
                  column="status"
                  :direction="reportSortDirection('status')"
                  :aria-sort="reportAriaSortValue('status')"
                  @toggle="toggleReportSort"
                >
                  Status
                </SortableTh>
                <SortableTh
                  class="px-5 py-3 text-left"
                  column="letter_number"
                  :direction="reportSortDirection('letter_number')"
                  :aria-sort="reportAriaSortValue('letter_number')"
                  @toggle="toggleReportSort"
                >
                  No. Surat
                </SortableTh>
                <SortableTh
                  class="px-5 py-3 text-left"
                  column="schedule"
                  :direction="reportSortDirection('schedule')"
                  :aria-sort="reportAriaSortValue('schedule')"
                  @toggle="toggleReportSort"
                >
                  Jadwal
                </SortableTh>
                <SortableTh
                  class="px-5 py-3 text-left"
                  column="location"
                  :direction="reportSortDirection('location')"
                  :aria-sort="reportAriaSortValue('location')"
                  @toggle="toggleReportSort"
                >
                  Lokasi
                </SortableTh>
                <SortableTh
                  class="px-5 py-3 text-left"
                  column="details"
                  :direction="reportSortDirection('details')"
                  :aria-sort="reportAriaSortValue('details')"
                  @toggle="toggleReportSort"
                >
                  Detail Peminjaman
                </SortableTh>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-gray-700">
              <tr v-for="booking in paginatedBookings" :key="booking.id" class="transition hover:bg-gray-50">
                <td class="px-5 py-4 font-semibold text-gray-900">#{{ booking.id }}</td>
                <td class="px-5 py-4">
                  <div class="font-medium text-gray-900">{{ formatDateTime(booking.created_at) }}</div>
                </td>
                <td class="px-5 py-4">
                  <div class="font-semibold text-gray-900">{{ booking.user?.name ?? '-' }}</div>
                  <div class="text-xs text-gray-500">{{ booking.user?.email ?? '-' }}</div>
                  <div class="text-xs text-gray-500">Telp: {{ booking.user?.phone ?? '-' }}</div>
                </td>
                <td class="px-5 py-4">
                  <span
                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                    :class="statusBadgeClasses[booking.status] ?? 'bg-gray-100 text-gray-700'"
                  >
                    {{ statusLabels[booking.status] ?? booking.status }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <div class="text-sm font-semibold text-gray-900">
                    {{ booking.letter_number ?? '-' }}
                  </div>
                  <div class="text-xs text-gray-500" v-if="booking.letter_generated_at">
                    Terbit {{ formatDateTime(booking.letter_generated_at) }}
                  </div>
                </td>
                <td class="px-5 py-4">
                  <div class="font-medium text-gray-900">{{ formatDateTime(booking.start_time) }}</div>
                  <div class="text-xs text-gray-500">s.d {{ formatDateTime(booking.end_time) }}</div>
                </td>
                <td class="px-5 py-4">
                  <div class="font-semibold text-gray-900">{{ booking.room?.name ?? '-' }}</div>
                  <div class="text-xs text-gray-500">
                    {{ booking.room?.building?.name ?? '-' }} - {{ booking.room?.building?.campus?.name ?? '-' }}
                  </div>
                </td>
                <td class="px-5 py-4">
                  <div class="font-semibold text-gray-900">{{ booking.title ?? '-' }}</div>
                  <div class="text-xs text-gray-500">
                    {{ booking.description ?? 'Tidak ada keterangan tambahan' }}
                  </div>
                </td>
              </tr>
              <tr v-if="!paginatedBookings.length">
                <td colspan="8" class="px-5 py-10 text-center text-sm text-gray-400">
                  Belum ada data sesuai filter yang dipilih.
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
              :disabled="currentPage === 1 || !pageMeta.of"
            >
              «
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1 || !pageMeta.of"
            >
              ‹
            </button>
            <template v-if="pageMeta.of">
              <button
                v-for="page in pages"
                :key="`report-page-${page}`"
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
              :disabled="currentPage === pages.length || !pageMeta.of"
            >
              ›
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(pages.length)"
              :disabled="currentPage === pages.length || !pageMeta.of"
            >
              »
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
