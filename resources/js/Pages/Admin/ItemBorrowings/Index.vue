<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { formatToDDMMYY } from '@/Composables/useDateFormatter'
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
})

const statusLabels = {
  requested: 'Menunggu Persetujuan',
  waiting: 'Menunggu Persetujuan',
  approved: 'Disetujui',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan',
  returned: 'Dikembalikan',
}

const badgeClasses = {
  requested: 'bg-amber-100 text-amber-700',
  waiting: 'bg-amber-100 text-amber-700',
  approved: 'bg-emerald-100 text-emerald-700',
  rejected: 'bg-rose-100 text-rose-700',
  cancelled: 'bg-slate-100 text-slate-700',
  returned: 'bg-blue-100 text-blue-700',
}

const statusOptions = ['waiting', 'approved', 'returned', 'rejected', 'cancelled']

// ── Filter state ──────────────────────────────────────────────────────────────
const filterForm = reactive({
  search: '',
  status: '',
  start_date: '',
  end_date: '',
})

// Applied filters (only updated when user clicks "Terapkan Filter")
const appliedFilters = reactive({
  search: '',
  status: '',
  start_date: '',
  end_date: '',
})

const applyFilters = () => {
  Object.assign(appliedFilters, { ...filterForm })
}

const resetFilters = () => {
  Object.assign(filterForm, { search: '', status: '', start_date: '', end_date: '' })
  Object.assign(appliedFilters, { search: '', status: '', start_date: '', end_date: '' })
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const getItemNames = (borrowing) => {
  if (borrowing.items && borrowing.items.length > 0) {
    return borrowing.items.map((pivot) => pivot.item?.name).filter(Boolean).join(', ')
  }
  return borrowing.singleItem?.name ?? '-'
}

const getItemCodes = (borrowing) => {
  if (borrowing.items && borrowing.items.length > 0) {
    return borrowing.items.map((pivot) => pivot.item?.code).filter(Boolean).join(', ')
  }
  return borrowing.singleItem?.code ?? '-'
}

const getItemCategories = (borrowing) => {
  if (borrowing.items && borrowing.items.length > 0) {
    return borrowing.items.map((pivot) => pivot.item?.category).filter(Boolean).join(', ')
  }
  return borrowing.singleItem?.category ?? '-'
}

const getTotalQuantity = (borrowing) => {
  if (borrowing.items && borrowing.items.length > 0) {
    return borrowing.items.reduce((sum, pivot) => sum + (pivot.quantity || 0), 0)
  }
  return borrowing.quantity ?? 0
}

const getBorrowDates = (borrowing) => {
  if (borrowing.items && borrowing.items.length > 0) {
    const dates = borrowing.items.map((pivot) => pivot.borrow_date).filter(Boolean)
    if (dates.length === 0) return null
    return dates.sort()[0]
  }
  return borrowing.borrow_date
}

const getReturnDates = (borrowing) => {
  if (borrowing.items && borrowing.items.length > 0) {
    const dates = borrowing.items.map((pivot) => pivot.return_date).filter(Boolean)
    if (dates.length === 0) return null
    return dates.sort()[dates.length - 1]
  }
  return borrowing.return_date
}

const normalizeStatus = (status) => (status === 'requested' ? 'waiting' : status)

// ── Filtered list (respects appliedFilters) ───────────────────────────────────
const borrowingsList = computed(() => {
  let list = props.itemBorrowings ?? []

  if (appliedFilters.search) {
    const q = appliedFilters.search.toLowerCase()
    list = list.filter((b) => {
      return (
        (b.title ?? '').toLowerCase().includes(q) ||
        (b.user?.name ?? '').toLowerCase().includes(q) ||
        getItemNames(b).toLowerCase().includes(q) ||
        getItemCodes(b).toLowerCase().includes(q)
      )
    })
  }

  if (appliedFilters.status) {
    list = list.filter((b) => normalizeStatus(b.status) === appliedFilters.status)
  }

  if (appliedFilters.start_date) {
    const from = new Date(appliedFilters.start_date)
    list = list.filter((b) => {
      const d = b.created_at ? new Date(b.created_at) : null
      return d && d >= from
    })
  }

  if (appliedFilters.end_date) {
    const to = new Date(appliedFilters.end_date)
    to.setHours(23, 59, 59, 999)
    list = list.filter((b) => {
      const d = b.created_at ? new Date(b.created_at) : null
      return d && d <= to
    })
  }

  return list
})

// ── Summary cards (based on filtered list) ───────────────────────────────────
const summary = computed(() => {
  const all = props.itemBorrowings ?? []
  const filtered = borrowingsList.value

  const count = (arr, fn) => arr.filter(fn).length

  return {
    total: filtered.length,
    waiting:   count(filtered, (b) => ['waiting', 'requested'].includes(b.status)),
    approved:  count(filtered, (b) => b.status === 'approved'),
    returned:  count(filtered, (b) => b.status === 'returned'),
    rejected:  count(filtered, (b) => b.status === 'rejected'),
    cancelled: count(filtered, (b) => b.status === 'cancelled'),
  }
})

// ── Sort & pagination ─────────────────────────────────────────────────────────
const {
  sortedItems,
  toggleSort,
  sortDirection,
  ariaSortValue,
} = useTableSort(borrowingsList, {
  accessors: {
    title:      (b) => b.title ?? '',
    applicant:  (b) => b.user?.name ?? '',
    item:       (b) => getItemNames(b),
    quantity:   (b) => getTotalQuantity(b),
    borrow_date: (b) => {
      const d = getBorrowDates(b)
      return d ? new Date(d) : null
    },
    status: (b) => normalizeStatus(b.status) ?? '',
  },
})

const {
  paginatedItems,
  currentPage,
  rowsPerPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(sortedItems)

const perPageOptions = [5, 10, 25, 50]

const formatDate = (value) => formatToDDMMYY(value)

// ==========================================
// CHART DATA: STATUS DISTRIBUTION (PIE)
// ==========================================
const statusChartData = computed(() => {
  const borrowings = props.itemBorrowings ?? []
  const counts = {
    requested: 0,
    waiting: 0,
    approved: 0,
    rejected: 0,
    cancelled: 0,
    returned: 0,
  }
  
  borrowings.forEach(b => {
    const s = normalizeStatus(b.status) || ''
    if (counts[s] !== undefined) {
      counts[s]++
    }
  })

  return {
    labels: ['Menunggu', 'Disetujui', 'Dikembalikan', 'Ditolak', 'Dibatalkan'],
    datasets: [{
      data: [counts.waiting + counts.requested, counts.approved, counts.returned, counts.rejected, counts.cancelled],
      backgroundColor: [
        '#f59e0b', // amber-500
        '#10b981', // emerald-500
        '#3b82f6', // blue-500
        '#f43f5e', // rose-500
        '#8b5cf6', // violet-500
      ],
      borderWidth: 1,
    }],
  }
})

// ==========================================
// CHART DATA: ITEMS BY CATEGORY (PIE)
// ==========================================
const categoryChartData = computed(() => {
  const borrowings = props.itemBorrowings ?? []
  const categoryCounts = {}
  
  borrowings.forEach(b => {
    let categories = []
    if (b.items && b.items.length > 0) {
      categories = b.items.map(p => p.item?.category).filter(Boolean)
    } else if (b.singleItem?.category) {
      categories = [b.singleItem.category]
    }
    
    if (categories.length === 0) {
      categoryCounts['Uncategorized'] = (categoryCounts['Uncategorized'] || 0) + 1
    } else {
      categories.forEach(cat => {
        categoryCounts[cat] = (categoryCounts[cat] || 0) + 1
      })
    }
  })
  
  const colors = [
    '#3b82f6', // blue-500
    '#10b981', // emerald-500
    '#f59e0b', // amber-500
    '#f43f5e', // rose-500
    '#8b5cf6', // violet-500
    '#06b6d4', // cyan-500
  ]
  
  const labels = Object.keys(categoryCounts)
  const data = labels.map(cat => categoryCounts[cat])
  
  return {
    labels,
    datasets: [{
      data,
      backgroundColor: colors.slice(0, labels.length),
      borderWidth: 1,
    }],
  }
})

// ==========================================
// CHART DATA: MONTHLY TREND (BAR)
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
    <Head title="Approval Peminjaman Barang" />

    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-800">Approval Peminjaman Barang</h1>
        <p class="text-sm text-gray-500">Kelola permintaan peminjaman barang yang masuk.</p>
      </div>

      <!-- Summary Cards -->
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
      <!-- End Summary Cards -->

      <!-- Charts Section -->
      <div class="grid gap-6 md:grid-cols-2 mb-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Distribusi Status Peminjaman</h3>
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
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Distribusi Kategori Barang</h3>
          <div class="h-64">
            <Pie :data="categoryChartData" :options="chartOptions" />
          </div>
        </div>
      </div>

      <!-- Filter Panel -->
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
      <!-- End Filter Panel -->

      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

        <!-- Card Header -->
        <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 md:flex-row md:items-center md:justify-between">
          <div class="text-sm font-semibold text-gray-700">Daftar Permintaan Masuk</div>
          <div class="flex items-center justify-end gap-3 text-sm text-gray-600">
            <label class="font-medium text-gray-700" for="admin-item-borrowings-rows">Rows per page</label>
              <select
                id="admin-item-borrowings-rows"
                v-model.number="rowsPerPage"
                class="w-20 rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              >
                <option v-for="option in perPageOptions" :key="option" :value="option">
                  {{ option }}
                </option>
              </select>
          </div>
        </div>
        <!-- End Card Header -->

        <!-- Table -->
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
            <tr>
              <SortableTh
                class="px-5 py-3 text-left"
                column="title"
                label="Keperluan"
                :direction="sortDirection('title')"
                :aria-sort="ariaSortValue('title')"
                @toggle="toggleSort"
              />
              <SortableTh
                class="px-5 py-3 text-left"
                column="applicant"
                label="Pemohon"
                :direction="sortDirection('applicant')"
                :aria-sort="ariaSortValue('applicant')"
                @toggle="toggleSort"
              />
              <SortableTh
                class="px-5 py-3 text-left"
                column="item"
                label="Barang"
                :direction="sortDirection('item')"
                :aria-sort="ariaSortValue('item')"
                @toggle="toggleSort"
              />
              <SortableTh
                class="px-5 py-3 text-left"
                column="quantity"
                label="Qty"
                :direction="sortDirection('quantity')"
                :aria-sort="ariaSortValue('quantity')"
                @toggle="toggleSort"
              />
              <SortableTh
                class="px-5 py-3 text-left"
                column="borrow_date"
                label="Periode"
                :direction="sortDirection('borrow_date')"
                :aria-sort="ariaSortValue('borrow_date')"
                @toggle="toggleSort"
              />
              <th class="px-5 py-3 text-center">Status</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-gray-700">
            <tr v-for="borrowing in paginatedItems" :key="borrowing.id" class="hover:bg-gray-50">
              <td class="px-5 py-4">
                <div class="font-medium text-gray-900">{{ borrowing.title }}</div>
                <div class="text-xs text-gray-500">{{ borrowing.description || 'Tidak ada deskripsi.' }}</div>
              </td>
              <td class="px-5 py-4">
                <div class="font-medium text-gray-800">{{ borrowing.user?.name ?? '-' }}</div>
                <div class="text-xs text-gray-500">{{ borrowing.user?.email ?? '-' }}</div>
              </td>
              <td class="px-5 py-4">
                <div class="font-medium text-gray-800">{{ getItemNames(borrowing) }}</div>
                <div class="text-xs text-gray-500">{{ getItemCodes(borrowing) }} • {{ getItemCategories(borrowing) }}</div>
                <div v-if="borrowing.items && borrowing.items.length > 1" class="mt-1 text-xs text-gray-400">
                  {{ borrowing.items.length }} jenis barang
                </div>
              </td>
              <td class="px-5 py-4 text-sm">{{ getTotalQuantity(borrowing) }}</td>
              <td class="px-5 py-4 text-sm">
                <div>Pinjam: <span class="font-medium text-gray-800">{{ formatDate(getBorrowDates(borrowing)) }}</span></div>
                <div>Kembali: <span class="font-medium text-gray-800">{{ formatDate(getReturnDates(borrowing)) }}</span></div>
              </td>
              <td class="px-5 py-4 text-center">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                  :class="badgeClasses[borrowing.status] ?? 'bg-gray-100 text-gray-600'"
                >
                  {{ statusLabels[borrowing.status] ?? borrowing.status }}
                </span>
              </td>
              <td class="px-5 py-4 text-right">
                <Link
                  :href="route('admin.item-borrowings.show', borrowing.id)"
                  class="inline-flex items-center rounded-md border border-blue-200 px-3 py-1.5 text-xs font-medium text-blue-600 hover:bg-blue-50"
                >
                  Lihat Detail
                </Link>
              </td>
            </tr>
            <tr v-if="!borrowingsList.length">
              <td colspan="7" class="px-5 py-10 text-center text-sm text-gray-400">
                Belum ada data peminjaman barang.
              </td>
            </tr>
          </tbody>
        </table>
        <!-- End Table -->

        <!-- Pagination -->
        <div class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} data</span>
            <span v-else>Menampilkan 0 data</span>
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="currentPage === 1 || !borrowingsList.length"
              @click="changePage(1)"
            >
              «
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="currentPage === 1 || !borrowingsList.length"
              @click="changePage(currentPage - 1)"
            >
              ‹
            </button>
            <template v-if="borrowingsList.length">
              <button
                v-for="page in pages"
                :key="`admin-item-borrowings-page-${page}`"
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
              :disabled="currentPage === pages.length || !borrowingsList.length"
              @click="changePage(currentPage + 1)"
            >
              ›
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="currentPage === pages.length || !borrowingsList.length"
              @click="changePage(pages.length)"
            >
              »
            </button>
          </div>
        </div>
        <!-- End Pagination -->

      </div>
    </div>

  </AuthenticatedLayout>
</template>