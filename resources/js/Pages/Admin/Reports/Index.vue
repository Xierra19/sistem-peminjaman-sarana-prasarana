<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Dropdown from '@/Components/Dropdown.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { Head, router } from '@inertiajs/vue3'
import { computed, reactive, watch, ref, onMounted, onBeforeUnmount } from 'vue'
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
import flatpickr from 'flatpickr'
import 'flatpickr/dist/flatpickr.css'
import { Indonesian } from 'flatpickr/dist/l10n/id'

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

// Referensi untuk elemen date range picker
const dateRangePicker = ref(null)
const isMobileViewport = ref(false)

const syncMobileViewport = () => {
  if (typeof window === 'undefined') return
  isMobileViewport.value = window.innerWidth < 640
}

// Inisialisasi Flatpickr untuk Date Range Picker
onMounted(() => {
  syncMobileViewport()
  window.addEventListener('resize', syncMobileViewport)

  if (dateRangePicker.value) {
    flatpickr(dateRangePicker.value, {
      mode: 'range',
      dateFormat: 'Y-m-d',
      locale: Indonesian,
      allowInput: true,
      // Menampilkan dropdown bulan dan tahun untuk navigasi mudah
      showMonths: 1,
      // Inisialisasi dengan nilai awal jika ada
      defaultDate: 
        filterForm.start_date && filterForm.end_date 
          ? [filterForm.start_date, filterForm.end_date] 
          : filterForm.start_date || filterForm.end_date || null,
      // Update filterForm ketika range dipilih
      onChange: (selectedDates) => {
        if (selectedDates.length === 1) {
          // Jika hanya satu tanggal yang dipilih, gunakan untuk start_date
          filterForm.start_date = selectedDates[0].toISOString().split('T')[0]
          filterForm.end_date = ''
        } else if (selectedDates.length === 2) {
          // Jika dua tanggal dipilih, gunakan untuk start_date dan end_date
          filterForm.start_date = selectedDates[0].toISOString().split('T')[0]
          filterForm.end_date = selectedDates[1].toISOString().split('T')[0]
        } else {
          // Jika tidak ada tanggal yang dipilih
          filterForm.start_date = ''
          filterForm.end_date = ''
        }
      },
      // Pastikan nilai tetap sinkron saat input manual
      onClose: (selectedDates) => {
        if (selectedDates.length === 0) {
          filterForm.start_date = ''
          filterForm.end_date = ''
        }
      },
    })
  }
})

onBeforeUnmount(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', syncMobileViewport)
  }
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
  waiting: 'bg-amber-100 text-amber-800 border border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
  pending: 'bg-amber-100 text-amber-800 border border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
  approved: 'bg-emerald-100 text-emerald-700 border border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800',
  rejected: 'bg-rose-100 text-rose-700 border border-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:border-rose-800',
  cancelled: 'bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600',
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

const chartBookings = computed(() => {
  const bookings = props.bookings ?? []
  const today = new Date()
  today.setHours(23, 59, 59, 999)

  const startDate = new Date(today)
  startDate.setDate(today.getDate() - 6)
  startDate.setHours(0, 0, 0, 0)

  return bookings.filter((booking) => {
    if (!booking.created_at) return false

    const createdAt = new Date(booking.created_at)
    if (Number.isNaN(createdAt.getTime())) return false

    return createdAt >= startDate && createdAt <= today
  })
})

// =========================================
// CHART DATA: STATUS DISTRIBUTION (PIE)
// =========================================
const statusChartData = computed(() => {
  const counts = {
    approved: 0,
    waiting: 0,
    rejected: 0,
    cancelled: 0,
  }
   
  chartBookings.value.forEach(b => {
    const s = b.status || ''
    if (counts[s] !== undefined) {
      counts[s]++
    }
  })

  const total = Object.values(counts).reduce((sum, count) => sum + count, 0)
  const toPercentageLabel = (label, count) => {
    const percentage = total > 0 ? ((count / total) * 100).toFixed(1) : '0.0'
    return `${label} (${percentage}%)`
  }

  return {
    labels: [
      toPercentageLabel('Disetujui', counts.approved),
      toPercentageLabel('Menunggu', counts.waiting),
      toPercentageLabel('Ditolak', counts.rejected),
      toPercentageLabel('Dibatalkan', counts.cancelled),
    ],
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

// =========================================
// CHART DATA: WEEKLY BOOKINGS (BAR)
// =========================================
const weeklyChartData = computed(() => {
  const dayCounts = {}
  const dates = []
  const today = new Date()
  today.setHours(0, 0, 0, 0)

  for (let offset = 6; offset >= 0; offset -= 1) {
    const date = new Date(today)
    date.setDate(today.getDate() - offset)

    const key = date.toISOString().split('T')[0]
    dates.push(key)
    dayCounts[key] = 0
  }

  chartBookings.value.forEach((booking) => {
    const createdAt = new Date(booking.created_at)
    if (Number.isNaN(createdAt.getTime())) return

    const key = createdAt.toISOString().split('T')[0]
    if (dayCounts[key] !== undefined) {
      dayCounts[key] += 1
    }
  })

  const labels = dates.map((dateString) => {
    const date = new Date(dateString)
    return date.toLocaleDateString('id-ID', {
      day: '2-digit',
      month: 'short',
    })
  })

  const data = dates.map((dateString) => dayCounts[dateString])

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

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        usePointStyle: true,
        boxWidth: isMobileViewport.value ? 10 : 14,
        padding: isMobileViewport.value ? 10 : 16,
        font: {
          size: isMobileViewport.value ? 10 : 12,
        },
      },
    },
  },
}))

const canGoToPreviousPage = computed(() => currentPage.value > 1 && pageMeta.value.of > 0)
const canGoToNextPage = computed(() => currentPage.value < pages.value.length && pageMeta.value.of > 0)
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Report Peminjaman Ruangan" />

    <div class="space-y-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 sm:text-3xl">Report Peminjaman Ruangan</h1>
          <p class="mt-1 max-w-2xl text-sm leading-relaxed text-gray-500 dark:text-gray-400">
            Rekap lengkap pengajuan ruangan beserta status terbaru dan data pemohon.
          </p>
        </div>
        <Dropdown align="right" width="48">
          <template #trigger>
            <button
              type="button"
              class="inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-medium text-blue-700 transition hover:bg-blue-100 dark:border-blue-800 dark:bg-slate-700 dark:text-blue-300 sm:w-auto"
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
            <div class="flex flex-col gap-1 p-2 text-sm text-slate-700 dark:text-slate-300">
              <button
                type="button"
                class="flex items-center gap-2 rounded-md px-3 py-2 hover:bg-blue-50 dark:hover:bg-slate-700"
                @click="exportExcel"
              >
                <span>Export Excel</span>
              </button>
              <button
                type="button"
                class="flex items-center gap-2 rounded-md px-3 py-2 hover:bg-blue-50 dark:hover:bg-slate-700"
                @click="exportPdf"
              >
                <span>Export PDF</span>
              </button>
            </div>
          </template>
        </Dropdown>
      </div>

      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Total Data</p>
          <div class="mt-3 flex items-end justify-between gap-3">
            <p class="text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ summary.total }}</p>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-500 dark:bg-slate-700 dark:text-slate-300">Aktif</span>
          </div>
          <p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Seluruh hasil sesuai filter aktif</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-white p-4 shadow-sm dark:border-amber-800 dark:bg-slate-800">
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-500 dark:text-amber-400">Menunggu</p>
          <div class="mt-3 flex items-end justify-between gap-3">
            <p class="text-3xl font-semibold text-amber-600 dark:text-amber-400">{{ summary.waiting }}</p>
            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-medium text-amber-600 dark:bg-amber-900/20 dark:text-amber-300">Review</span>
          </div>
          <p class="mt-2 text-xs leading-relaxed text-amber-500 dark:text-amber-400">Booking belum diputuskan</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-white p-4 shadow-sm dark:border-emerald-800 dark:bg-slate-800">
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-500 dark:text-emerald-400">Disetujui</p>
          <div class="mt-3 flex items-end justify-between gap-3">
            <p class="text-3xl font-semibold text-emerald-600 dark:text-emerald-400">{{ summary.approved }}</p>
            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-medium text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-300">Aktif</span>
          </div>
          <p class="mt-2 text-xs leading-relaxed text-emerald-500 dark:text-emerald-400">Booking aktif</p>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-white p-4 shadow-sm dark:border-rose-800 dark:bg-slate-800">
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-rose-500 dark:text-rose-400">Ditolak / Batal</p>
          <div class="mt-3 flex items-end justify-between gap-3">
            <p class="text-3xl font-semibold text-rose-600 dark:text-rose-400">{{ summary.rejected + summary.cancelled }}</p>
            <span class="rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-medium text-rose-600 dark:bg-rose-900/20 dark:text-rose-300">Selesai</span>
          </div>
          <p class="mt-2 text-xs leading-relaxed text-rose-500 dark:text-rose-400">Termasuk pembatalan admin</p>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
          <div class="mb-4 flex items-start justify-between gap-3">
            <div>
              <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Distribusi Status</h3>
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Fokus 7 hari terakhir</p>
            </div>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-500 dark:bg-slate-700 dark:text-slate-300">Pie</span>
          </div>
          <div class="h-48 sm:h-56">
            <Pie :data="statusChartData" :options="chartOptions" />
          </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
          <div class="mb-4 flex items-start justify-between gap-3">
            <div>
              <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Tren Peminjaman</h3>
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Aktivitas 7 hari terakhir</p>
            </div>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-500 dark:bg-slate-700 dark:text-slate-300">Bar</span>
          </div>
          <div class="h-48 sm:h-56">
            <Bar :data="weeklyChartData" :options="chartOptions" />
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="mb-4">
          <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Filter Report</h3>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Saring data sebelum membaca detail tabel.</p>
        </div>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <div class="flex flex-col gap-2">
            <label for="report-search" class="text-sm font-medium text-gray-700 dark:text-gray-300">Pencarian bebas</label>
            <input
              id="report-search"
              v-model="filterForm.search"
              type="text"
              placeholder="Nama pemohon, email, ruangan…"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
            />
          </div>
          <div class="flex flex-col gap-2">
            <label for="report-status" class="text-sm font-medium text-gray-700 dark:text-gray-300">Status peminjaman</label>
            <select
              id="report-status"
              v-model="filterForm.status"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
            >
              <option value="">Semua status</option>
              <option v-for="status in statusOptions" :key="`status-${status}`" :value="status">
                {{ statusLabels[status] ?? status }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-2">
            <label for="report-date-range" class="text-sm font-medium text-gray-700 dark:text-gray-300">Rentang Tanggal Pengajuan</label>
            <input
              id="report-date-range"
              ref="dateRangePicker"
              type="text"
              placeholder="Pilih rentang tanggal..."
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 w-full dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
            />
            <p class="text-xs text-gray-500 mt-1 dark:text-gray-400">
              Dari: {{ filterForm.start_date || '-' }} | Sampai: {{ filterForm.end_date || '-' }}
            </p>
          </div>
        </div>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
          <button
            type="button"
            class="w-full rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:border-gray-300 hover:text-gray-800 dark:border-slate-600 dark:text-slate-400 dark:hover:border-slate-500 dark:hover:text-slate-300 sm:w-auto"
            @click="resetFilters"
          >
            Reset
          </button>
          <button
            type="button"
            class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 sm:w-auto"
            @click="applyFilters"
          >
            Terapkan Filter
          </button>
        </div>
      </div>

      <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-700">
          <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">Hasil Report</div>
          <div class="flex flex-col gap-2 text-sm text-gray-600 dark:text-gray-400 sm:flex-row sm:items-center">
            <label for="report-rows" class="font-medium text-gray-700 dark:text-gray-300">Baris per halaman</label>
            <div class="relative">
              <select
                id="report-rows"
                v-model.number="rowsPerPage"
                class="w-full rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 sm:w-28"
              >
                <option v-for="option in perPageOptions" :key="`rows-${option}`" :value="option">
                  {{ option }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="report-mobile-table mobile-friendly-table min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-slate-700 dark:text-slate-400">
              <tr>
                <SortableTh class="px-5 py-3 text-left" column="id" :direction="reportSortDirection('id')" :aria-sort="reportAriaSortValue('id')" @toggle="toggleReportSort">
                  ID
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="created_at" :direction="reportSortDirection('created_at')" :aria-sort="reportAriaSortValue('created_at')" @toggle="toggleReportSort">
                  Tanggal Pengajuan
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="applicant" :direction="reportSortDirection('applicant')" :aria-sort="reportAriaSortValue('applicant')" @toggle="toggleReportSort">
                  Pemohon
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="status" :direction="reportSortDirection('status')" :aria-sort="reportAriaSortValue('status')" @toggle="toggleReportSort">
                  Status
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="letter_number" :direction="reportSortDirection('letter_number')" :aria-sort="reportAriaSortValue('letter_number')" @toggle="toggleReportSort">
                  No. Surat
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="schedule" :direction="reportSortDirection('schedule')" :aria-sort="reportAriaSortValue('schedule')" @toggle="toggleReportSort">
                  Jadwal
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="location" :direction="reportSortDirection('location')" :aria-sort="reportAriaSortValue('location')" @toggle="toggleReportSort">
                  Lokasi
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="details" :direction="reportSortDirection('details')" :aria-sort="reportAriaSortValue('details')" @toggle="toggleReportSort">
                  Detail Peminjaman
                </SortableTh>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-slate-700 dark:text-slate-300">
              <tr v-for="booking in paginatedBookings" :key="booking.id" class="transition hover:bg-gray-50 dark:hover:bg-slate-700/50">
                <td class="px-5 py-4 font-semibold text-gray-900 dark:text-slate-100" data-title="ID">#{{ booking.id }}</td>
                <td class="px-5 py-4" data-title="Tanggal Pengajuan">{{ formatDateTime(booking.created_at) }}</td>
                <td class="px-5 py-4" data-title="Pemohon">
                  <div class="font-medium text-gray-900 dark:text-slate-100">{{ booking.user?.name ?? '-' }}</div>
                  <div class="text-xs text-gray-500 dark:text-slate-400">{{ booking.user?.email ?? '-' }}</div>
                  <div class="text-xs text-gray-500 dark:text-slate-400">Telp: {{ booking.user?.phone ?? '-' }}</div>
                </td>
                <td class="px-5 py-4" data-title="Status">
                  <span
                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                    :class="statusBadgeClasses[booking.status] ?? 'bg-gray-100 text-gray-600 border border-gray-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600'"
                  >
                    {{ statusLabels[booking.status] ?? booking.status }}
                  </span>
                </td>
                <td class="px-5 py-4" data-title="No. Surat">
                  <div class="font-medium text-gray-900 dark:text-slate-100">
                    {{ booking.letter_number ?? '-' }}
                  </div>
                  <div class="text-xs text-gray-500 dark:text-slate-400" v-if="booking.letter_generated_at">
                    Terbit {{ formatDateTime(booking.letter_generated_at) }}
                  </div>
                </td>
                <td class="px-5 py-4" data-title="Jadwal">
                  <div class="font-medium text-gray-900 dark:text-slate-100">{{ booking.schedule_mode_label }}</div>
                  <div class="text-xs text-gray-500 dark:text-slate-400">{{ booking.schedule_summary }}</div>
                </td>
                <td class="px-5 py-4" data-title="Lokasi">
                  <div class="font-medium text-gray-900 dark:text-slate-100">{{ booking.room?.name ?? '-' }}</div>
                  <div class="text-xs text-gray-500 dark:text-slate-400">
                    {{ booking.room?.building?.name ?? '-' }} - {{ booking.room?.building?.campus?.name ?? '-' }}
                  </div>
                </td>
                <td class="px-5 py-4" data-title="Detail Peminjaman">
                  <div class="font-semibold text-gray-900 dark:text-slate-100">{{ booking.title ?? '-' }}</div>
                  <div class="text-xs text-gray-500 dark:text-slate-400">
                    {{ booking.description ?? 'Tidak ada keterangan tambahan' }}
                  </div>
                </td>
              </tr>
              <tr v-if="!paginatedBookings.length">
                <td colspan="8" class="px-5 py-10 text-center text-sm text-gray-400 dark:text-slate-500">
                  Belum ada data sesuai filter yang dipilih.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:text-slate-400">
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} data</span>
            <span v-else>Menampilkan 0 data</span>
          </div>
          <div class="w-full sm:w-auto">
            <div class="mobile-pagination-compact sm:hidden">
              <button
                type="button"
                class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400"
                @click="changePage(currentPage - 1)"
                :disabled="!canGoToPreviousPage"
              >
                Sebelumnya
              </button>
              <button
                type="button"
                class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400"
                @click="changePage(currentPage + 1)"
                :disabled="!canGoToNextPage"
              >
                Berikutnya
              </button>
            </div>
            <div class="hidden items-center gap-2 sm:flex">
              <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(1)" :disabled="currentPage === 1 || !pageMeta.of">«</button>
              <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(currentPage - 1)" :disabled="currentPage === 1 || !pageMeta.of">‹</button>
              <template v-if="pageMeta.of">
                <button
                  v-for="page in pages"
                  :key="`report-page-${page}`"
                  type="button"
                  class="rounded border px-3 py-1 text-sm transition"
                  :class="currentPage === page ? 'border-blue-500 bg-blue-500 text-white' : 'border-gray-300 text-gray-600 hover:border-blue-400 hover:text-blue-600 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400'"
                  :disabled="typeof page !== 'number'"
                  @click="changePage(page)"
                >
                  {{ page }}
                </button>
              </template>
              <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(currentPage + 1)" :disabled="currentPage === pages.length || !pageMeta.of">›</button>
              <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(pages.length)" :disabled="currentPage === pages.length || !pageMeta.of">»</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
