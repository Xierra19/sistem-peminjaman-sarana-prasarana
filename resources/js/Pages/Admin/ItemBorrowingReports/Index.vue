<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Dropdown from '@/Components/Dropdown.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { Head, router } from '@inertiajs/vue3'
import { computed, reactive, watch } from 'vue'
import { formatToDDMMYY, formatDateTimeToDDMMYY } from '@/Composables/useDateFormatter'
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
    status: (row) => (row.status === 'requested' ? 'waiting' : row.status) ?? '',
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

const statusLabels = {
  waiting: 'Menunggu',
  requested: 'Menunggu',
  approved: 'Disetujui',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan',
  returned: 'Dikembalikan',
}

const statusBadgeClasses = {
  waiting: 'bg-amber-100 text-amber-800 border border-amber-200',
  requested: 'bg-amber-100 text-amber-800 border border-amber-200',
  approved: 'bg-emerald-100 text-emerald-700 border border-emerald-200',
  rejected: 'bg-rose-100 text-rose-700 border border-rose-200',
  cancelled: 'bg-slate-100 text-slate-700 border border-slate-200',
  returned: 'bg-blue-100 text-blue-700 border border-blue-200',
}

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

// ==========================================
// CHART DATA: STATUS DISTRIBUTION (PIE) - Distribusi Status Peminjaman Barang
// ==========================================
const statusChartData = computed(() => {
  const borrowings = props.itemBorrowings ?? []
  const counts = {
    waiting: 0,
    requested: 0,
    approved: 0,
    rejected: 0,
    cancelled: 0,
    returned: 0,
  }
  
  borrowings.forEach(b => {
    const s = b.status || ''
    // Normalize status (requested -> waiting)
    const normalizedStatus = s === 'requested' ? 'waiting' : s
    if (counts[normalizedStatus] !== undefined) {
      counts[normalizedStatus]++
    }
  })

  return {
    labels: ['Menunggu', 'Disetujui', 'Dikembalikan', 'Ditolak', 'Dibatalkan'],
    datasets: [{
      data: [counts.waiting + counts.requested, counts.approved, counts.returned, counts.rejected, counts.cancelled],
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

// ==========================================
// CHART DATA: MONTHLY TREND (BAR) - Tren Peminjaman 6 Bulan Terakhir
// ==========================================
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
    <Head title="Report Peminjaman Barang" />

    <div class="space-y-6">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Report Peminjaman Barang</h1>
          <p class="text-sm text-gray-500">
            Rekap lengkap pengajuan barang beserta status terbaru dan data pemohon.
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

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
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
        <div class="rounded-xl border border-blue-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-blue-500">Dikembalikan</p>
          <p class="mt-2 text-2xl font-semibold text-blue-600">{{ summary.returned }}</p>
          <p class="text-xs text-blue-500">Barang yang sudah dikembalikan</p>
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
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Distribusi Status Peminjaman Barang</h3>
          <div class="h-64">
            <Pie :data="statusChartData" :options="chartOptions" />
          </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Tren Peminjaman Barang 6 Bulan Terakhir</h3>
          <div class="h-64">
            <Bar :data="monthlyChartData" :options="chartOptions" />
          </div>
        </div>
      </div>

      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-gray-700" for="item-report-search">Pencarian bebas</label>
            <input
              id="item-report-search"
              v-model="filterForm.search"
              type="text"
              placeholder="Nama pemohon, barang, kode…"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-gray-700" for="item-report-status">Status peminjaman</label>
            <select
              id="item-report-status"
              v-model="filterForm.status"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="">Semua status</option>
              <option v-for="status in statusOptions" :key="`item-status-${status}`" :value="status">
                {{ statusLabels[status] ?? status }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-gray-700" for="item-report-start-date">Tanggal pengajuan (dari)</label>
            <input
              id="item-report-start-date"
              v-model="filterForm.start_date"
              type="date"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-gray-700" for="item-report-end-date">Tanggal pengajuan (sampai)</label>
            <input
              id="item-report-end-date"
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
        <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 md:flex-row md:items-center md:justify-between">
          <div class="text-sm font-semibold text-gray-700">Hasil Report</div>
          <div class="flex items-center gap-3 text-sm text-gray-600">
            <label class="font-medium text-gray-700" for="item-report-rows">Baris per halaman</label>
            <select
              id="item-report-rows"
              v-model.number="rowsPerPage"
              class="w-28 appearance-none rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option v-for="option in perPageOptions" :key="`item-report-rows-${option}`" :value="option">
                {{ option }}
              </option>
            </select>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
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
            <tbody class="divide-y divide-gray-100 text-gray-700">
              <tr v-for="borrowing in paginatedItems" :key="borrowing.id" class="hover:bg-gray-50">
                <td class="px-5 py-4">{{ borrowing.id }}</td>
                <td class="px-5 py-4">{{ formatDateTime(borrowing.created_at) }}</td>
                <td class="px-5 py-4">
                  <div class="font-medium text-gray-900">{{ borrowing.user?.name ?? '-' }}</div>
                  <div class="text-xs text-gray-500">{{ borrowing.user?.email ?? '-' }}</div>
                </td>
                <td class="px-5 py-4">
                  <div class="font-medium text-gray-900">{{ borrowing.title }}</div>
                  <div class="text-xs text-gray-500">{{ borrowing.description || 'Tidak ada deskripsi.' }}</div>
                </td>
                <td class="px-5 py-4">
                  <div class="font-medium text-gray-900">{{ borrowing.item?.name ?? '-' }}</div>
                  <div class="text-xs text-gray-500">{{ borrowing.item?.code ?? '-' }} • {{ borrowing.item?.category ?? '-' }}</div>
                </td>
                <td class="px-5 py-4">
                  <div>Pinjam: <span class="font-medium text-gray-900">{{ formatDate(borrowing.borrow_date) }}</span></div>
                  <div>Kembali: <span class="font-medium text-gray-900">{{ formatDate(borrowing.return_date) }}</span></div>
                </td>
                <td class="px-5 py-4">
                  <span
                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                    :class="statusBadgeClasses[borrowing.status] ?? 'bg-gray-100 text-gray-600 border border-gray-200'"
                  >
                    {{ statusLabels[borrowing.status] ?? borrowing.status }}
                  </span>
                </td>
              </tr>
              <tr v-if="!itemBorrowings.length">
                <td colspan="7" class="px-5 py-10 text-center text-sm text-gray-400">
                  Belum ada data peminjaman barang.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} data</span>
            <span v-else>Menampilkan 0 data</span>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50" @click="changePage(1)" :disabled="currentPage === 1 || !itemBorrowings.length">«</button>
            <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50" @click="changePage(currentPage - 1)" :disabled="currentPage === 1 || !itemBorrowings.length">‹</button>
            <template v-if="itemBorrowings.length">
              <button
                v-for="page in pages"
                :key="`item-report-page-${page}`"
                type="button"
                class="rounded border px-3 py-1 text-sm transition"
                :class="currentPage === page ? 'border-blue-500 bg-blue-500 text-white' : 'border-gray-300 text-gray-600 hover:border-blue-400 hover:text-blue-600'"
                @click="changePage(page)"
              >
                {{ page }}
              </button>
            </template>
            <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50" @click="changePage(currentPage + 1)" :disabled="currentPage === pages.length || !itemBorrowings.length">›</button>
            <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50" @click="changePage(pages.length)" :disabled="currentPage === pages.length || !itemBorrowings.length">»</button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
