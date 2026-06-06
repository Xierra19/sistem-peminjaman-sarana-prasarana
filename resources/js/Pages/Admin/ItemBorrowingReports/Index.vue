<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Dropdown from '@/Components/Dropdown.vue'
import RowsPerPageSelect from '@/Components/RowsPerPageSelect.vue'
import SortableTh from '@/Components/SortableTh.vue'
import TablePagination from '@/Components/TablePagination.vue'
import {
  getItemBorrowingStatusClasses,
  getItemBorrowingStatusLabel,
  normalizeItemBorrowingStatus,
} from '@/Composables/useItemBorrowingStatus'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { Head, router } from '@inertiajs/vue3'
import { computed, reactive, watch, ref, onMounted, onBeforeUnmount } from 'vue'
import {
  formatDateToYMD,
  formatToDDMMYY,
  formatDateTimeToDDMMYY,
} from '@/Composables/useDateFormatter'
import flatpickr from 'flatpickr'
import 'flatpickr/dist/flatpickr.css'
import { Indonesian } from 'flatpickr/dist/l10n/id'
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
  itemBorrowings: {
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

// Referensi untuk elemen date range picker
const dateRangePicker = ref(null)
const flatpickrInstance = ref(null)
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
    flatpickrInstance.value = flatpickr(dateRangePicker.value, {
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
          filterForm.start_date = formatDateToYMD(selectedDates[0])
          filterForm.end_date = ''
        } else if (selectedDates.length === 2) {
          // Jika dua tanggal dipilih, gunakan untuk start_date dan end_date
          filterForm.start_date = formatDateToYMD(selectedDates[0])
          filterForm.end_date = formatDateToYMD(selectedDates[1])
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
  flatpickrInstance.value?.destroy()

  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', syncMobileViewport)
  }
})

const itemBorrowings = computed(() => props.itemBorrowings ?? [])

const {
  sortedItems,
  toggleSort,
  sortDirection,
  ariaSortValue,
} = useTableSort(itemBorrowings, {
  accessors: {
    id: (row) => row.id ?? 0,
    created_at: (row) => (row.created_at ? new Date(row.created_at) : null),
    applicant: (row) => row.user?.name ?? '',
    status: (row) => normalizeItemBorrowingStatus(row.status),
    borrow_date: (row) => (row.borrow_date ? new Date(row.borrow_date) : null),
    return_date: (row) => (row.return_date ? new Date(row.return_date) : null),
    item: (row) => row.item?.name ?? '',
    title: (row) => row.title ?? '',
  },
})

const {
  paginatedItems,
  rowsPerPage,
  currentPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(sortedItems, { perPage: 10 })

const perPageOptions = [10, 25, 50, 100]

const summary = computed(() => ({
  total: props.statusSummary?.total ?? itemBorrowings.value.length ?? 0,
  approved: props.statusSummary?.approved ?? 0,
  waiting: props.statusSummary?.waiting ?? 0,
  rejected: props.statusSummary?.rejected ?? 0,
  cancelled: props.statusSummary?.cancelled ?? 0,
  returned: props.statusSummary?.returned ?? 0,
}))

const formatDate = (value) => formatToDDMMYY(value)

const formatDateTime = (value) => formatDateTimeToDDMMYY(value)

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
  router.get(route('admin.item-borrowing-reports.index'), buildQuery(), {
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
  // Bersihkan input Flatpickr secara programatik
  if (flatpickrInstance.value) {
    flatpickrInstance.value.clear()
  }
  applyFilters()
}

const exportExcel = () => {
  const url = route('admin.item-borrowing-reports.export', buildQuery())
  window.open(url, '_blank')
}

const exportPdf = () => {
  const url = route('admin.item-borrowing-reports.export.pdf', buildQuery())
  window.open(url, '_blank')
}

// =========================================
// CHART DATA: STATUS DISTRIBUTION (PIE) - Distribusi Status Peminjaman Barang
// =========================================
const statusChartData = computed(() => {
  const borrowings = props.itemBorrowings ?? []
  const counts = {
    waiting: 0,
    approved: 0,
    rejected: 0,
    cancelled: 0,
    returned: 0,
  }
  
  borrowings.forEach(b => {
    const normalizedStatus = normalizeItemBorrowingStatus(b.status)
    if (counts[normalizedStatus] !== undefined) {
      counts[normalizedStatus]++
    }
  })

  return {
    labels: ['Menunggu', 'Disetujui', 'Dikembalikan', 'Ditolak', 'Dibatalkan'],
    datasets: [{
      data: [counts.waiting, counts.approved, counts.returned, counts.rejected, counts.cancelled],
      backgroundColor: [
        '#f59e0b', // amber-500 (Menunggu)
        '#10b981', // emerald-500 (Disetujui)
        '#3b82f6', // blue-500 (Dikembalikan)
        '#f43f5e', // rose-500 (Ditolak)
        '#8b5cf6', // violet-500 (Dibatalkan)
      ],
      borderWidth: 1,
    }],
  }
})

// =========================================
// CHART DATA: MONTHLY TREND (BAR) - Tren Peminjaman 6 Bulan Terakhir
// =========================================
const monthlyChartData = computed(() => {
  const borrowings = props.itemBorrowings ?? []
  const monthCounts = {}
  
  borrowings.forEach(b => {
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
      label: 'Jumlah Peminjaman Barang',
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

</script>

<template>
  <AuthenticatedLayout>
    <Head title="Report Peminjaman Barang" />

    <div class="space-y-4 sm:space-y-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200 sm:text-3xl">Report Peminjaman Barang</h1>
          <p class="mt-1 max-w-2xl text-sm leading-relaxed text-gray-500 dark:text-gray-400">
            Rekap lengkap pengajuan barang beserta status terbaru dan data pemohon.
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

      <div class="grid grid-cols-2 gap-3 xl:grid-cols-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Total Data</p>
          <p class="mt-3 text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ summary.total }}</p>
          <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Seluruh hasil sesuai filter aktif</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-white p-4 shadow-sm dark:border-amber-800 dark:bg-slate-800">
          <p class="text-xs font-medium uppercase tracking-wide text-amber-500 dark:text-amber-400">Menunggu</p>
          <p class="mt-2 text-2xl font-semibold text-amber-600 dark:text-amber-400">{{ summary.waiting }}</p>
          <p class="text-xs text-amber-500 dark:text-amber-400">Booking belum diputuskan</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-white p-4 shadow-sm dark:border-emerald-800 dark:bg-slate-800">
          <p class="text-xs font-medium uppercase tracking-wide text-emerald-500 dark:text-emerald-400">Disetujui</p>
          <p class="mt-2 text-2xl font-semibold text-emerald-600 dark:text-emerald-400">{{ summary.approved }}</p>
          <p class="text-xs text-emerald-500 dark:text-emerald-400">Booking aktif</p>
        </div>
        <div class="rounded-2xl border border-blue-200 bg-white p-4 shadow-sm dark:border-blue-800 dark:bg-slate-800">
          <p class="text-xs font-medium uppercase tracking-wide text-blue-500 dark:text-blue-400">Dikembalikan</p>
          <p class="mt-2 text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ summary.returned }}</p>
          <p class="text-xs text-blue-500 dark:text-blue-400">Barang yang sudah dikembalikan</p>
        </div>
        <div class="col-span-2 rounded-2xl border border-rose-200 bg-white p-4 shadow-sm dark:border-rose-800 dark:bg-slate-800 sm:col-span-1">
          <p class="text-xs font-medium uppercase tracking-wide text-rose-500 dark:text-rose-400">Ditolak / Batal</p>
          <p class="mt-2 text-2xl font-semibold text-rose-600 dark:text-rose-400">{{ summary.rejected + summary.cancelled }}</p>
          <p class="text-xs text-rose-500 dark:text-rose-400">Termasuk pembatalan admin</p>
        </div>
      </div>

      <!-- Charts Section -->
      <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:p-5">
          <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Distribusi Status Peminjaman Barang</h3>
          <div class="h-48 sm:h-56">
            <Pie :data="statusChartData" :options="chartOptions" />
          </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:p-5">
          <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Tren Peminjaman Barang 6 Bulan Terakhir</h3>
          <div class="h-48 sm:h-56">
            <Bar :data="monthlyChartData" :options="chartOptions" />
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:p-5">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300" for="item-report-search">Pencarian bebas</label>
            <input
              id="item-report-search"
              v-model="filterForm.search"
              type="text"
              placeholder="Nama pemohon, barang, kode…"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
            />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300" for="item-report-status">Status peminjaman</label>
            <select
              id="item-report-status"
              v-model="filterForm.status"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
            >
              <option value="">Semua status</option>
              <option v-for="status in statusOptions" :key="`item-status-${status}`" :value="status">
                {{ getItemBorrowingStatusLabel(status) }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-2">
            <label for="item-report-date-range" class="text-sm font-medium text-gray-700 dark:text-gray-300">Rentang Tanggal Pengajuan</label>
            <input
              id="item-report-date-range"
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
          <RowsPerPageSelect
            v-model="rowsPerPage"
            input-id="item-report-rows"
            label="Baris per halaman"
            :options="perPageOptions"
            wide
          />
        </div>

        <div class="overflow-x-auto">
          <table class="report-mobile-table mobile-friendly-table min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-slate-700 dark:text-slate-400">
              <tr>
                <SortableTh class="px-5 py-3 text-left" column="id" :direction="sortDirection('id')" :aria-sort="ariaSortValue('id')" @toggle="toggleSort">
                  ID
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="created_at" :direction="sortDirection('created_at')" :aria-sort="ariaSortValue('created_at')" @toggle="toggleSort">
                  Tanggal Pengajuan
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="applicant" :direction="sortDirection('applicant')" :aria-sort="ariaSortValue('applicant')" @toggle="toggleSort">
                  Pemohon
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="title" :direction="sortDirection('title')" :aria-sort="ariaSortValue('title')" @toggle="toggleSort">
                  Keperluan
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="item" :direction="sortDirection('item')" :aria-sort="ariaSortValue('item')" @toggle="toggleSort">
                  Barang
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="borrow_date" :direction="sortDirection('borrow_date')" :aria-sort="ariaSortValue('borrow_date')" @toggle="toggleSort">
                  Periode
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="status" :direction="sortDirection('status')" :aria-sort="ariaSortValue('status')" @toggle="toggleSort">
                  Status
                </SortableTh>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-slate-700 dark:text-slate-300">
              <tr v-for="borrowing in paginatedItems" :key="borrowing.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/50">
                <td class="mobile-report-muted px-5 py-4" data-title="ID">#{{ borrowing.id }}</td>
                <td class="mobile-report-muted px-5 py-4" data-title="Tanggal Pengajuan">{{ formatDateTime(borrowing.created_at) }}</td>
                <td class="mobile-report-applicant mobile-report-span-2 px-5 py-4" data-title="Pemohon">
                  <div class="font-medium text-gray-900 dark:text-slate-100">{{ borrowing.user?.name ?? '-' }}</div>
                  <div class="text-xs text-gray-500 dark:text-slate-400">{{ borrowing.user?.email ?? '-' }}</div>
                </td>
                <td class="mobile-report-primary mobile-report-span-2 px-5 py-4" data-title="Keperluan">
                  <div class="font-medium text-gray-900 dark:text-slate-100">{{ borrowing.title }}</div>
                  <div class="text-xs text-gray-500 dark:text-slate-400">{{ borrowing.description || 'Tidak ada deskripsi.' }}</div>
                </td>
                <td class="mobile-report-span-2 px-5 py-4" data-title="Barang">
                  <div class="font-medium text-gray-900 dark:text-slate-100">{{ borrowing.item?.name ?? '-' }}</div>
                  <div class="text-xs text-gray-500 dark:text-slate-400">{{ borrowing.item?.code ?? '-' }} • {{ borrowing.item?.category ?? '-' }}</div>
                </td>
                <td class="mobile-report-span-2 px-5 py-4" data-title="Periode">
                  <div>Pinjam: <span class="font-medium text-gray-900 dark:text-slate-100">{{ formatDate(borrowing.borrow_date) }}</span></div>
                  <div>Kembali: <span class="font-medium text-gray-900 dark:text-slate-100">{{ formatDate(borrowing.return_date) }}</span></div>
                </td>
                <td class="mobile-report-status mobile-report-span-2 px-5 py-4" data-title="Status">
                  <span
                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                    :class="getItemBorrowingStatusClasses(borrowing.status)"
                  >
                    {{ getItemBorrowingStatusLabel(borrowing.status) }}
                  </span>
                </td>
              </tr>
              <tr v-if="!itemBorrowings.length">
                <td colspan="7" class="px-5 py-10 text-center text-sm text-gray-400 dark:text-slate-500">
                  Belum ada data peminjaman barang.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <TablePagination
          :page-meta="pageMeta"
          :pages="pages"
          :current-page="currentPage"
          @change="changePage"
        />
      </div>
    </div>
  </AuthenticatedLayout>
</template>
