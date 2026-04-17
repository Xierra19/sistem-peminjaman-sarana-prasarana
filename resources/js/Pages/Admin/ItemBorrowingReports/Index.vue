<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { Head, router } from '@inertiajs/vue3'
import { computed, reactive, watch } from 'vue'

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

const formatDate = (value) => {
  if (!value) return '-'

  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) {
    return value
  }

  return parsed.toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
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
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 shadow-sm transition hover:bg-emerald-100"
          @click="exportExcel"
        >
          <span>Export Excel</span>
        </button>
      </div>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total Data</p>
          <p class="mt-2 text-2xl font-semibold text-slate-900">{{ summary.total }}</p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-amber-500">Menunggu</p>
          <p class="mt-2 text-2xl font-semibold text-amber-600">{{ summary.waiting }}</p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-emerald-500">Disetujui</p>
          <p class="mt-2 text-2xl font-semibold text-emerald-600">{{ summary.approved }}</p>
        </div>
        <div class="rounded-xl border border-blue-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-blue-500">Dikembalikan</p>
          <p class="mt-2 text-2xl font-semibold text-blue-600">{{ summary.returned }}</p>
        </div>
        <div class="rounded-xl border border-rose-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-rose-500">Ditolak / Batal</p>
          <p class="mt-2 text-2xl font-semibold text-rose-600">{{ summary.rejected + summary.cancelled }}</p>
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
            <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50" @click="changePage(1)" :disabled="currentPage === 1 || !itemBorrowings.length">First</button>
            <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50" @click="changePage(currentPage - 1)" :disabled="currentPage === 1 || !itemBorrowings.length">Prev</button>
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
            <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50" @click="changePage(currentPage + 1)" :disabled="currentPage === pages.length || !itemBorrowings.length">Next</button>
            <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50" @click="changePage(pages.length)" :disabled="currentPage === pages.length || !itemBorrowings.length">Last</button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
