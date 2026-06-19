<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import RowsPerPageSelect from '@/Components/RowsPerPageSelect.vue'
import SortableTh from '@/Components/SortableTh.vue'
import TablePagination from '@/Components/TablePagination.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { isDateWithinRange, useAppliedFilters } from '@/Composables/useAppliedFilters'
import { useDateRangePickers } from '@/Composables/useDateRangePickers'
import {
  getItemBorrowingStatusClasses,
  getItemBorrowingStatusLabel,
  itemBorrowingStatusLabels,
  normalizeItemBorrowingStatus,
} from '@/Composables/useItemBorrowingStatus'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { formatDateTimeToDDMMYY, formatToDDMMYY } from '@/Composables/useDateFormatter'
import { distinctItemNames, totalDistinctItemQuantity } from '@/Composables/useItemBorrowingSchedules'

const props = defineProps({
  itemBorrowings: {
    type: Array,
    default: () => [],
  },
})

const statusOptions = ['waiting', 'needs_revision', 'approved', 'completed', 'rejected', 'cancelled']

const {
  filterForm,
  appliedFilters,
  hasActiveFilters,
  activeFilterBadges,
  applyFilters: applyBaseFilters,
  resetFilters: resetBaseFilters,
} = useAppliedFilters(itemBorrowingStatusLabels)

const { startInput, endInput } = useDateRangePickers(filterForm)
const summaryCardFilter = ref('')

// ── Helpers ───────────────────────────────────────────────────────────
const getItemNames = (borrowing) => {
  if (borrowing.items && borrowing.items.length > 0) {
    return distinctItemNames(borrowing.items).join(', ')
  }
  return borrowing.singleItem?.name ?? '-'
}

const getItemCodes = (borrowing) => {
  if (borrowing.items && borrowing.items.length > 0) {
    return [...new Set(borrowing.items.map((pivot) => pivot.item?.code).filter(Boolean))].join(', ')
  }
  return borrowing.singleItem?.code ?? '-'
}

const getItemCategories = (borrowing) => {
  if (borrowing.items && borrowing.items.length > 0) {
    return [...new Set(borrowing.items.map((pivot) => pivot.item?.category).filter(Boolean))].join(', ')
  }
  return borrowing.singleItem?.category ?? '-'
}

const getTotalQuantity = (borrowing) => {
  if (borrowing.items && borrowing.items.length > 0) {
    return totalDistinctItemQuantity(borrowing.items)
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
    list = list.filter((b) => normalizeItemBorrowingStatus(b.effective_status ?? b.status) === appliedFilters.status)
  }

  if (summaryCardFilter.value === 'waiting') {
    list = list.filter((b) =>
      ['waiting', 'needs_revision'].includes(
        normalizeItemBorrowingStatus(b.effective_status ?? b.status),
      ),
    )
  }

  if (summaryCardFilter.value === 'approved') {
    list = list.filter((b) => normalizeItemBorrowingStatus(b.effective_status ?? b.status) === 'approved')
  }

  if (summaryCardFilter.value === 'completed') {
    list = list.filter((b) => normalizeItemBorrowingStatus(b.effective_status ?? b.status) === 'completed')
  }

  if (summaryCardFilter.value === 'rejected_cancelled') {
    list = list.filter((b) =>
      ['rejected', 'cancelled'].includes(
        normalizeItemBorrowingStatus(b.effective_status ?? b.status),
      ),
    )
  }

  if (appliedFilters.start_date || appliedFilters.end_date) {
    list = list.filter((b) =>
      isDateWithinRange(b.created_at, appliedFilters.start_date, appliedFilters.end_date),
    )
  }

  return list
})

// ── Summary cards (based on filtered list) ───────────────────────────────────
const summary = computed(() => {
  const filtered = props.itemBorrowings ?? []

  const count = (arr, fn) => arr.filter(fn).length

  return {
    total: filtered.length,
    waiting:   count(filtered, (b) => ['waiting', 'requested', 'needs_revision'].includes(b.status)),
    approved:  count(filtered, (b) => b.effective_status === 'approved'),
    completed: count(filtered, (b) => b.effective_status === 'completed'),
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
  defaultColumn: 'created_at',
  defaultDirection: 'desc',
  accessors: {
    title:      (b) => b.title ?? '',
    applicant:  (b) => b.user?.name ?? '',
    item:       (b) => getItemNames(b),
    quantity:   (b) => getTotalQuantity(b),
    borrow_date: (b) => {
      const d = getBorrowDates(b)
      return d ? new Date(d) : null
    },
    created_at: (b) => (b.created_at ? new Date(b.created_at) : null),
    status: (b) => normalizeItemBorrowingStatus(b.effective_status ?? b.status),
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

const isSummaryCardActive = (filter) => {
  if (filter === '') {
    return summaryCardFilter.value === '' && !appliedFilters.status
  }

  return summaryCardFilter.value === filter
}

const applySummaryCardFilter = (filter) => {
  summaryCardFilter.value = filter
  filterForm.status = ''
  appliedFilters.status = ''
  currentPage.value = 1
}

const applyFilters = () => {
  summaryCardFilter.value = ''
  applyBaseFilters()
  currentPage.value = 1
}

const resetFilters = () => {
  summaryCardFilter.value = ''
  resetBaseFilters()
  currentPage.value = 1
}

const formatDate = (value) => formatToDDMMYY(value)
const formatSchedule = (value) => formatDateTimeToDDMMYY(value)

const formatTimeHHMM = (value) => {
  if (!value) return '-'
  const d = new Date(value)
  if (isNaN(d.getTime())) return '-'
  const hours = String(d.getHours()).padStart(2, '0')
  const minutes = String(d.getMinutes()).padStart(2, '0')
  return `${hours}:${minutes}`
}

</script>

<template>
  <AuthenticatedLayout>
    <Head title="Persetujuan Peminjaman Barang" />

    <div class="space-y-6">
      <div class="space-y-1">
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">Persetujuan Peminjaman Barang</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Kelola permintaan peminjaman barang yang masuk.</p>
        <p class="text-xs text-slate-500 dark:text-slate-400">
          {{ hasActiveFilters || summaryCardFilter ? `Menampilkan ${borrowingsList.length} hasil sesuai filter aktif.` : `Menampilkan seluruh ${summary.total} pengajuan yang tersedia.` }}
        </p>
      </div>

      <!-- Summary Cards -->
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <button
          type="button"
          class="rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-slate-200 dark:bg-slate-800 dark:focus:ring-slate-700"
          :class="isSummaryCardActive('') ? 'border-slate-500 ring-2 ring-slate-300 dark:border-slate-400 dark:ring-slate-600' : 'border-slate-200 dark:border-slate-700'"
          :aria-pressed="isSummaryCardActive('')"
          @click="applySummaryCardFilter('')"
        >
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Total Data</p>
          <p class="mt-3 text-3xl font-semibold text-slate-900 dark:text-white">{{ summary.total }}</p>
          <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Klik untuk menampilkan semua status</p>
        </button>
        <button
          type="button"
          class="rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-amber-100 dark:bg-amber-900/30 dark:focus:ring-amber-950"
          :class="isSummaryCardActive('waiting') ? 'border-amber-500 ring-2 ring-amber-200 dark:border-amber-400 dark:ring-amber-900' : 'border-amber-200 dark:border-amber-800'"
          :aria-pressed="isSummaryCardActive('waiting')"
          @click="applySummaryCardFilter('waiting')"
        >
          <p class="text-xs font-medium uppercase tracking-wide text-amber-500 dark:text-amber-300">Menunggu</p>
          <p class="mt-3 text-3xl font-semibold text-amber-600 dark:text-amber-400">{{ summary.waiting }}</p>
          <p class="mt-2 text-xs text-amber-500 dark:text-amber-400">Klik untuk melihat antrean tinjauan</p>
        </button>
        <button
          type="button"
          class="rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-emerald-100 dark:bg-emerald-900/30 dark:focus:ring-emerald-950"
          :class="isSummaryCardActive('approved') ? 'border-emerald-500 ring-2 ring-emerald-200 dark:border-emerald-400 dark:ring-emerald-900' : 'border-emerald-200 dark:border-emerald-800'"
          :aria-pressed="isSummaryCardActive('approved')"
          @click="applySummaryCardFilter('approved')"
        >
          <p class="text-xs font-medium uppercase tracking-wide text-emerald-500 dark:text-emerald-300">Disetujui</p>
          <p class="mt-3 text-3xl font-semibold text-emerald-600 dark:text-emerald-400">{{ summary.approved }}</p>
          <p class="mt-2 text-xs text-emerald-500 dark:text-emerald-400">Klik untuk melihat data disetujui</p>
        </button>
        <button
          type="button"
          class="rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-blue-100 dark:bg-blue-900/30 dark:focus:ring-blue-950"
          :class="isSummaryCardActive('completed') ? 'border-blue-500 ring-2 ring-blue-200 dark:border-blue-400 dark:ring-blue-900' : 'border-blue-200 dark:border-blue-800'"
          :aria-pressed="isSummaryCardActive('completed')"
          @click="applySummaryCardFilter('completed')"
        >
          <p class="text-xs font-medium uppercase tracking-wide text-blue-500 dark:text-blue-300">Selesai</p>
          <p class="mt-3 text-3xl font-semibold text-blue-600 dark:text-blue-400">{{ summary.completed }}</p>
          <p class="mt-2 text-xs text-blue-500 dark:text-blue-400">Klik untuk melihat data selesai</p>
        </button>
        <button
          type="button"
          class="rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-rose-100 dark:bg-rose-900/30 dark:focus:ring-rose-950"
          :class="isSummaryCardActive('rejected_cancelled') ? 'border-rose-500 ring-2 ring-rose-200 dark:border-rose-400 dark:ring-rose-900' : 'border-rose-200 dark:border-rose-800'"
          :aria-pressed="isSummaryCardActive('rejected_cancelled')"
          @click="applySummaryCardFilter('rejected_cancelled')"
        >
          <p class="text-xs font-medium uppercase tracking-wide text-rose-500 dark:text-rose-300">Ditolak / Batal</p>
          <p class="mt-3 text-3xl font-semibold text-rose-600 dark:text-rose-400">{{ summary.rejected + summary.cancelled }}</p>
          <p class="mt-2 text-xs text-rose-500 dark:text-rose-400">Klik untuk melihat ditolak atau batal</p>
        </button>
      </div>
      <!-- End Summary Cards -->

      <!-- Filter Panel -->
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div v-if="hasActiveFilters" class="mb-4 flex flex-wrap gap-2">
          <span
            v-for="badge in activeFilterBadges"
            :key="badge"
            class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-300"
          >
            {{ badge }}
          </span>
        </div>
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
                {{ getItemBorrowingStatusLabel(status) }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200" for="item-report-start-date">Tanggal pengajuan (dari)</label>
            <input
              id="item-report-start-date"
              ref="startInput"
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
              ref="endInput"
              v-model="filterForm.end_date"
              type="text"
              readonly
              placeholder="Pilih tanggal selesai"
              class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white cursor-pointer"
            />
          </div>
        </div>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
          <button
            type="button"
            class="w-full rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:text-slate-800 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200 sm:w-auto"
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
      <!-- End Filter Panel -->

      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">

        <!-- Card Header -->
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-700">
          <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">Daftar Permintaan Masuk</div>
          <RowsPerPageSelect
            v-model="rowsPerPage"
            input-id="admin-item-borrowings-rows"
            :options="perPageOptions"
          />
        </div>
        <!-- End Card Header -->

        <!-- Table -->
        <table class="approval-mobile-table mobile-friendly-table min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
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
              <SortableTh
                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"
                column="created_at"
                label="Dibuat"
                :direction="sortDirection('created_at')"
                :aria-sort="ariaSortValue('created_at')"
                @toggle="toggleSort"
              />
              <th class="px-5 py-3 text-center text-slate-500 dark:text-slate-300">Status</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-700 dark:text-slate-300">
            <tr v-for="borrowing in paginatedItems" :key="borrowing.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
              <td class="mobile-primary-cell mobile-span-2 px-5 py-4" data-title="Keperluan">
                <div class="mobile-primary-label">Keperluan</div>
                <div class="mobile-primary-title">{{ borrowing.title }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ borrowing.description || 'Tidak ada deskripsi.' }}</div>
              </td>
              <td class="px-5 py-4" data-title="Pemohon">
                <div class="font-medium text-slate-800 dark:text-slate-200">{{ borrowing.user?.name ?? '-' }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ borrowing.user?.email ?? '-' }}</div>
              </td>
              <td class="px-5 py-4 mobile-compact-meta" data-title="Barang">
                <div class="font-medium text-slate-800 dark:text-slate-200">{{ getItemNames(borrowing) }}</div>
                <div v-if="borrowing.items && borrowing.items.length > 1" class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                  {{ borrowing.items.length }} jenis barang
                </div>
              </td>
              <td class="px-5 py-4 text-sm" data-title="Qty">{{ getTotalQuantity(borrowing) }}</td>
              <td class="mobile-span-2 px-5 py-4 text-sm" data-title="Periode">
                <div>Pinjam: <span class="font-medium text-slate-800 dark:text-slate-200">{{ formatSchedule(getBorrowDates(borrowing)) }}</span></div>
                <div>Kembali: <span class="font-medium text-slate-800 dark:text-slate-200">{{ formatSchedule(getReturnDates(borrowing)) }}</span></div>
              </td>
              <td class="px-5 py-4 text-sm mobile-compact-meta" data-title="Dibuat">
                <div>
                  Dibuat:
                  <span class="font-medium text-slate-800 dark:text-slate-200">
                    {{ borrowing.created_at ? formatDate(new Date(borrowing.created_at)) : '-' }}
                  </span>
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                  Jam:
                  <span class="font-medium text-slate-800 dark:text-slate-200">
                    {{ borrowing.created_at ? formatTimeHHMM(borrowing.created_at) : '-' }}
                  </span>
                </div>
              </td>
              <td class="mobile-status-cell px-5 py-4 text-center" data-title="Status">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                  :class="getItemBorrowingStatusClasses(borrowing.effective_status ?? borrowing.status)"
                >
                  {{ getItemBorrowingStatusLabel(borrowing.effective_status ?? borrowing.status) }}
                </span>
              </td>
              <td class="mobile-action-cell px-5 py-4 text-right" data-title="Aksi">
                <Link
                  :href="route('admin.item-borrowings.show', borrowing.id)"
                  class="inline-flex items-center rounded-md border border-blue-200 px-3 py-1.5 text-xs font-medium text-blue-600 hover:bg-blue-50 dark:border-slate-600 dark:text-blue-300 dark:hover:border-slate-500 dark:hover:bg-slate-600"
                >
                  Lihat Detail
                </Link>
              </td>
            </tr>
            <tr v-if="!borrowingsList.length">
              <td colspan="8" class="px-5 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
                Belum ada data peminjaman barang.
              </td>
            </tr>
          </tbody>
        </table>
        <!-- End Table -->

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
