<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed, reactive, ref, onMounted, onBeforeUnmount } from 'vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { formatToDDMMYY } from '@/Composables/useDateFormatter'
import flatpickr from 'flatpickr'
import 'flatpickr/dist/flatpickr.css'

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

// ── Filter state ──────────────────────────────────────────────────────
const filterForm = reactive({
  search: '',
  status: '',
  start_date: '',
  end_date: '',
})

const datePickers = ref({})

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

// ── Helpers ───────────────────────────────────────────────────────────
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

// ── Sort & pagination ─────────────────────────────────────────────────
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

// Inisialisasi Flatpickr
onMounted(() => {
  // Flatpickr untuk Tanggal Pengajuan (Dari)
  const startInput = document.getElementById('item-report-start-date')
  if (startInput) {
    datePickers.value.start = flatpickr(startInput, {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'd-m-y',
      onChange: (selectedDates, dateStr) => {
        filterForm.start_date = dateStr
      }
    })
  }

  // Flatpickr untuk Tanggal Pengajuan (Sampai)
  const endInput = document.getElementById('item-report-end-date')
  if (endInput) {
    datePickers.value.end = flatpickr(endInput, {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'd-m-y',
      onChange: (selectedDates, dateStr) => {
        filterForm.end_date = dateStr
      }
    })
  }
})

onBeforeUnmount(() => {
  Object.values(datePickers.value).forEach(picker => picker?.destroy())
})
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Persetujuan Peminjaman Barang" />

    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">Persetujuan Peminjaman Barang</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Kelola permintaan peminjaman barang yang masuk.</p>
      </div>

      <!-- Summary Cards -->
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Total Data</p>
          <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ summary.total }}</p>
          <p class="text-xs text-slate-500 dark:text-slate-400">Seluruh hasil sesuai filter aktif</p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm dark:border-amber-800 dark:bg-amber-900/30">
          <p class="text-xs font-medium uppercase tracking-wide text-amber-500 dark:text-amber-300">Menunggu</p>
          <p class="mt-2 text-2xl font-semibold text-amber-600 dark:text-amber-400">{{ summary.waiting }}</p>
          <p class="text-xs text-amber-500 dark:text-amber-400">Booking belum diputuskan</p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-white p-5 shadow-sm dark:border-emerald-800 dark:bg-emerald-900/30">
          <p class="text-xs font-medium uppercase tracking-wide text-emerald-500 dark:text-emerald-300">Disetujui</p>
          <p class="mt-2 text-2xl font-semibold text-emerald-600 dark:text-emerald-400">{{ summary.approved }}</p>
          <p class="text-xs text-emerald-500 dark:text-emerald-400">Booking aktif</p>
        </div>
        <div class="rounded-xl border border-blue-200 bg-white p-5 shadow-sm dark:border-blue-800 dark:bg-blue-900/30">
          <p class="text-xs font-medium uppercase tracking-wide text-blue-500 dark:text-blue-300">Dikembalikan</p>
          <p class="mt-2 text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ summary.returned }}</p>
          <p class="text-xs text-blue-500 dark:text-blue-400">Barang yang sudah dikembalikan</p>
        </div>
        <div class="rounded-xl border border-rose-200 bg-white p-5 shadow-sm dark:border-rose-800 dark:bg-rose-900/30">
          <p class="text-xs font-medium uppercase tracking-wide text-rose-500 dark:text-rose-300">Ditolak / Batal</p>
          <p class="mt-2 text-2xl font-semibold text-rose-600 dark:text-rose-400">{{ summary.rejected + summary.cancelled }}</p>
          <p class="text-xs text-rose-500 dark:text-rose-400">Termasuk pembatalan admin</p>
        </div>
      </div>
      <!-- End Summary Cards -->

      <!-- Filter Panel -->
      <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200" for="item-report-search">Pencarian bebas</label>
            <input
              id="item-report-search"
              v-model="filterForm.search"
              type="text"
              placeholder="Nama pemohon, barang, kode…"
              class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
            />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200" for="item-report-status">Status peminjaman</label>
            <select
              id="item-report-status"
              v-model="filterForm.status"
              class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
            >
              <option value="">Semua status</option>
              <option v-for="status in statusOptions" :key="`item-status-${status}`" :value="status">
                {{ statusLabels[status] ?? status }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200" for="item-report-start-date">Tanggal pengajuan (dari)</label>
            <input
              id="item-report-start-date"
              v-model="filterForm.start_date"
              type="text"
              readonly
              placeholder="Pilih tanggal mulai"
              class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white cursor-pointer"
            />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200" for="item-report-end-date">Tanggal pengajuan (sampai)</label>
            <input
              id="item-report-end-date"
              v-model="filterForm.end_date"
              type="text"
              readonly
              placeholder="Pilih tanggal selesai"
              class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white cursor-pointer"
            />
          </div>
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:text-slate-800 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
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

      <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">

        <!-- Card Header -->
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-700">
          <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">Daftar Permintaan Masuk</div>
          <div class="flex items-center justify-end gap-3 text-sm text-slate-600 dark:text-slate-300">
            <label class="font-medium text-slate-700 dark:text-slate-200" for="admin-item-borrowings-rows">Rows per page</label>
            <select
              id="admin-item-borrowings-rows"
              v-model.number="rowsPerPage"
              class="w-20 rounded border border-slate-300 bg-white px-3 py-1.5 pr-8 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
            >
              <option v-for="option in perPageOptions" :key="option" :value="option">
                {{ option }}
              </option>
            </select>
          </div>
        </div>
        <!-- End Card Header -->

        <!-- Table -->
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
          <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-700 dark:text-slate-300">
            <tr>
              <SortableTh
                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"
                column="title"
                label="Keperluan"
                :direction="sortDirection('title')"
                :aria-sort="ariaSortValue('title')"
                @toggle="toggleSort"
              />
              <SortableTh
                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"
                column="applicant"
                label="Pemohon"
                :direction="sortDirection('applicant')"
                :aria-sort="ariaSortValue('applicant')"
                @toggle="toggleSort"
              />
              <SortableTh
                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"
                column="item"
                label="Barang"
                :direction="sortDirection('item')"
                :aria-sort="ariaSortValue('item')"
                @toggle="toggleSort"
              />
              <SortableTh
                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"
                column="quantity"
                label="Qty"
                :direction="sortDirection('quantity')"
                :aria-sort="ariaSortValue('quantity')"
                @toggle="toggleSort"
              />
              <SortableTh
                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"
                column="borrow_date"
                label="Periode"
                :direction="sortDirection('borrow_date')"
                :aria-sort="ariaSortValue('borrow_date')"
                @toggle="toggleSort"
              />
              <th class="px-5 py-3 text-center text-slate-500 dark:text-slate-300">Status</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-700 dark:text-slate-300">
            <tr v-for="borrowing in paginatedItems" :key="borrowing.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
              <td class="px-5 py-4">
                <div class="font-medium text-slate-900 dark:text-white">{{ borrowing.title }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ borrowing.description || 'Tidak ada deskripsi.' }}</div>
              </td>
              <td class="px-5 py-4">
                <div class="font-medium text-slate-800 dark:text-slate-200">{{ borrowing.user?.name ?? '-' }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ borrowing.user?.email ?? '-' }}</div>
              </td>
              <td class="px-5 py-4">
                <div class="font-medium text-slate-800 dark:text-slate-200">{{ getItemNames(borrowing) }}</div>
                <div v-if="borrowing.items && borrowing.items.length > 1" class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                  {{ borrowing.items.length }} jenis barang
                </div>
              </td>
              <td class="px-5 py-4 text-sm">{{ getTotalQuantity(borrowing) }}</td>
              <td class="px-5 py-4 text-sm">
                <div>Pinjam: <span class="font-medium text-slate-800 dark:text-slate-200">{{ formatDate(getBorrowDates(borrowing)) }}</span></div>
                <div>Kembali: <span class="font-medium text-slate-800 dark:text-slate-200">{{ formatDate(getReturnDates(borrowing)) }}</span></div>
              </td>
              <td class="px-5 py-4 text-center">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                  :class="badgeClasses[borrowing.status] ?? 'bg-slate-100 text-slate-600 border-slate-200'"
                >
                  {{ statusLabels[borrowing.status] ?? borrowing.status }}
                </span>
              </td>
              <td class="px-5 py-4 text-right">
                <Link
                  :href="route('admin.item-borrowings.show', borrowing.id)"
                  class="inline-flex items-center rounded-md border border-blue-200 px-3 py-1.5 text-xs font-medium text-blue-600 hover:bg-blue-50 dark:border-slate-600 dark:text-blue-300 dark:hover:border-slate-500 dark:hover:bg-slate-600"
                >
                  Lihat Detail
                </Link>
              </td>
            </tr>
            <tr v-if="!borrowingsList.length">
              <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
                Belum ada data peminjaman barang.
              </td>
            </tr>
          </tbody>
        </table>
        <!-- End Table -->

        <!-- Pagination -->
        <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:text-slate-300">
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} data</span>
            <span v-else>Menampilkan 0 data</span>
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-slate-400 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
              @click="changePage(1)"
              :disabled="currentPage === 1 || !borrowingsList.length"
            >
              «
            </button>
            <button
              type="button"
              class="rounded border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-slate-400 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1 || !borrowingsList.length"
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
                    : 'border-slate-300 text-slate-600 hover:border-slate-400 hover:text-slate-600 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200'"
                @click="changePage(page)"
              >
                {{ page }}
              </button>
            </template>
            <button
              type="button"
              class="rounded border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-slate-400 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
              @click="changePage(currentPage + 1)"
              :disabled="currentPage === pages.length || !borrowingsList.length"
            >
              ›
            </button>
            <button
              type="button"
              class="rounded border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-slate-400 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
              @click="changePage(pages.length)"
              :disabled="currentPage === pages.length || !borrowingsList.length"
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