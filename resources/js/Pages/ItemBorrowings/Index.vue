<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { formatToDDMMYY } from '@/Composables/useDateFormatter'

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

const statusClasses = {
  requested: 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
  waiting: 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
  approved: 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800',
  rejected: 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:border-rose-800',
  cancelled: 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600',
  returned: 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800',
}

const perPageOptions = [5, 10, 25, 50]
const searchQuery = ref('')
const statusFilter = ref('')

/**
 * Mengambil nama barang untuk ditampilkan di tabel.
 * Mendukung multi-item (dari relasi items) dan single-item legacy (dari singleItem).
 */
const getItemNames = (borrowing) => {
  // Cek dulu apakah ada data multi-item
  if (borrowing.items && borrowing.items.length > 0) {
    return borrowing.items
      .map((pivot) => pivot.item?.name)
      .filter(Boolean)
      .join(', ')
  }
  // Fallback ke legacy singleItem
  return borrowing.item?.name ?? '-'
}

/**
 * Mengambil total kuantitas dari semua item.
 */
const getTotalQuantity = (borrowing) => {
  if (borrowing.items && borrowing.items.length > 0) {
    return borrowing.items.reduce((sum, pivot) => sum + (pivot.quantity || 0), 0)
  }
  // Fallback ke legacy quantity
  return borrowing.quantity ?? 0
}

/**
 * Mengambil rentang tanggal pinjam dari semua item.
 */
const getBorrowDates = (borrowing) => {
  if (borrowing.items && borrowing.items.length > 0) {
    const dates = borrowing.items
      .map((pivot) => pivot.borrow_date)
      .filter(Boolean)
    if (dates.length === 0) return null
    return dates.sort()[0] // Tanggal terkecil
  }
  // Fallback ke legacy borrow_date
  return borrowing.borrow_date
}

/**
 * Mengambil rentang tanggal kembali dari semua item.
 */
const getReturnDates = (borrowing) => {
  if (borrowing.items && borrowing.items.length > 0) {
    const dates = borrowing.items
      .map((pivot) => pivot.return_date)
      .filter(Boolean)
    if (dates.length === 0) return null
    return dates.sort()[dates.length - 1] // Tanggal terbesar
  }
  // Fallback ke legacy return_date
  return borrowing.return_date
}

const filteredBorrowings = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  const status = statusFilter.value

  return (props.itemBorrowings ?? []).filter((borrowing) => {
    const itemNames = getItemNames(borrowing)
    const searchable = [
      borrowing.title,
      borrowing.description,
      itemNames,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()

    const matchesSearch = !q || searchable.includes(q)
    const normalizedStatus = borrowing.status === 'requested' ? 'waiting' : borrowing.status

    const matchesStatus = !status || normalizedStatus === status

    return matchesSearch && matchesStatus
  })
})

const {
  sortedItems: sortedBorrowings,
  toggleSort,
  sortDirection,
  ariaSortValue,
} = useTableSort(filteredBorrowings, {
  accessors: {
    number: (borrowing) => borrowing.id ?? 0,
    title: (borrowing) => borrowing.title ?? '',
    item: (borrowing) => borrowing.item?.name ?? '',
    quantity: (borrowing) => borrowing.quantity ?? 0,
    borrow_date: (borrowing) => (borrowing.borrow_date ? new Date(borrowing.borrow_date) : null),
    return_date: (borrowing) => (borrowing.return_date ? new Date(borrowing.return_date) : null),
    status: (borrowing) => (borrowing.status === 'requested' ? 'waiting' : borrowing.status) ?? '',
  },
})

const {
  paginatedItems,
  rowsPerPage,
  currentPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(sortedBorrowings)

const formatDate = (value) => formatToDDMMYY(value)

const cancelForm = useForm({})
const cancellingId = ref(null)

const canCancel = (borrowing) => ['waiting', 'requested'].includes(borrowing.status)

const cancelBorrowing = (borrowing) => {
  if (!canCancel(borrowing)) {
    return
  }

  if (!window.confirm('Yakin ingin membatalkan permintaan peminjaman barang ini?')) {
    return
  }

  cancellingId.value = borrowing.id

  cancelForm.post(route('item-borrowings.cancel', borrowing.id), {
    preserveScroll: true,
    onFinish: () => {
      cancellingId.value = null
    },
  })
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Peminjaman Barang" />

    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Peminjaman Barang</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400">
            Pantau status pengajuan peminjaman barang yang telah kamu ajukan.
          </p>
        </div>
        <Link
          :href="route('item-borrowings.create')"
          class="inline-flex w-full items-center justify-center rounded-2xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 sm:w-auto"
        >
          + Buat Permintaan Baru
        </Link>
      </div>

      <div class="card-surface overflow-hidden">
        <div class="space-y-4 border-b border-slate-100 px-5 py-4 dark:border-slate-700">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex flex-1 flex-col gap-3 md:flex-row md:items-center">
              <div class="w-full md:max-w-sm">
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300" for="item-borrowings-search">Pencarian</label>
                <input
                  id="item-borrowings-search"
                  v-model="searchQuery"
                  type="text"
                  placeholder="Cari judul, barang, atau kategori..."
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
                />
              </div>
              <div class="w-full md:w-60">
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300" for="item-borrowings-status">Status</label>
                <select
                  id="item-borrowings-status"
                  v-model="statusFilter"
                  class="w-full appearance-none rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm leading-5 text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
                >
                  <option value="">Semua Status</option>
                  <option value="waiting">Menunggu Persetujuan</option>
                  <option value="approved">Disetujui</option>
                  <option value="rejected">Ditolak</option>
                  <option value="cancelled">Dibatalkan</option>
                  <option value="returned">Dikembalikan</option>
                </select>
              </div>
            </div>
            <div class="flex items-center gap-3 rounded-2xl bg-slate-50 px-3 py-2 text-sm text-slate-600 dark:bg-slate-700 dark:text-slate-300">
              <span class="font-medium">Total data</span>
              <span class="inline-flex h-8 w-12 items-center justify-center rounded-xl bg-white text-sm font-semibold text-slate-900 shadow-sm dark:bg-slate-600 dark:text-slate-100">
                {{ filteredBorrowings.length }}
              </span>
            </div>
          </div>
          <div class="flex flex-col gap-2 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-end dark:text-slate-400">
            <label class="font-medium text-slate-700 dark:text-slate-300" for="item-borrowings-rows">Rows per page</label>
              <select
                id="item-borrowings-rows"
                v-model.number="rowsPerPage"
                class="w-24 rounded-xl border border-slate-200 bg-white px-3 py-1.5 pr-9 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
              >
                <option v-for="option in perPageOptions" :key="`item-borrowings-rows-${option}`" :value="option">
                  {{ option }}
                </option>
              </select>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="mobile-friendly-table min-w-full divide-y divide-slate-200 text-sm text-slate-700 dark:divide-slate-700 dark:text-slate-300">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-700 dark:text-slate-400">
              <tr>
                <SortableTh
                  class="px-4 py-3 text-left"
                  column="number"
                  label="#"
                  :direction="sortDirection('number')"
                  :aria-sort="ariaSortValue('number')"
                  @toggle="toggleSort"
                />
                <SortableTh
                  class="px-4 py-3 text-left"
                  column="title"
                  label="Keperluan"
                  :direction="sortDirection('title')"
                  :aria-sort="ariaSortValue('title')"
                  @toggle="toggleSort"
                />
                <SortableTh
                  class="px-4 py-3 text-left"
                  column="item"
                  label="Barang"
                  :direction="sortDirection('item')"
                  :aria-sort="ariaSortValue('item')"
                  @toggle="toggleSort"
                />
                <SortableTh
                  class="px-4 py-3 text-left"
                  column="quantity"
                  label="Qty"
                  :direction="sortDirection('quantity')"
                  :aria-sort="ariaSortValue('quantity')"
                  @toggle="toggleSort"
                />
                <SortableTh
                  class="px-4 py-3 text-left"
                  column="borrow_date"
                  label="Pinjam"
                  :direction="sortDirection('borrow_date')"
                  :aria-sort="ariaSortValue('borrow_date')"
                  @toggle="toggleSort"
                />
                <SortableTh
                  class="px-4 py-3 text-left"
                  column="return_date"
                  label="Kembali"
                  :direction="sortDirection('return_date')"
                  :aria-sort="ariaSortValue('return_date')"
                  @toggle="toggleSort"
                />
                <SortableTh
                  class="px-4 py-3 text-left"
                  column="status"
                  label="Status"
                  :direction="sortDirection('status')"
                  :aria-sort="ariaSortValue('status')"
                  @toggle="toggleSort"
                />
                <th class="px-4 py-3 text-left">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
              <tr v-for="(borrowing, index) in paginatedItems" :key="borrowing.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                <td class="px-4 py-3 text-slate-500 dark:text-slate-400" data-title="#">
                  {{ pageMeta.from + index }}
                </td>
                <td class="px-4 py-3" data-title="Keperluan">
                  <div class="font-semibold text-slate-900 dark:text-slate-100">{{ borrowing.title }}</div>
                  <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ borrowing.description || 'Tidak ada deskripsi tambahan.' }}</div>
                </td>
                <td class="px-4 py-3" data-title="Barang">
                  <div class="font-medium text-slate-900 dark:text-slate-100">{{ getItemNames(borrowing) }}</div>
                  <div v-if="borrowing.items && borrowing.items.length > 1" class="text-xs text-slate-500 dark:text-slate-400">
                    {{ borrowing.items.length }} jenis barang
                  </div>
                </td>
                <td class="px-4 py-3 text-slate-700 dark:text-slate-300" data-title="Qty">{{ getTotalQuantity(borrowing) }}</td>
                <td class="px-4 py-3 text-slate-700 dark:text-slate-300" data-title="Pinjam">{{ formatDate(getBorrowDates(borrowing)) }}</td>
                <td class="px-4 py-3 text-slate-700 dark:text-slate-300" data-title="Kembali">{{ formatDate(getReturnDates(borrowing)) }}</td>
                <td class="px-4 py-3" data-title="Status">
                  <span
                    class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
                    :class="statusClasses[borrowing.status] ?? 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600'"
                  >
                    {{ statusLabels[borrowing.status] ?? borrowing.status }}
                  </span>
                </td>
                <td class="px-4 py-3" data-title="Aksi">
                  <div class="flex flex-col gap-2">
                    <Link
                      :href="route('item-borrowings.show', borrowing.id)"
                      class="inline-flex items-center justify-center rounded-xl border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-700 transition hover:border-blue-300 hover:text-blue-800 dark:border-blue-800 dark:text-blue-300 dark:hover:border-blue-700 dark:hover:text-blue-200"
                    >
                      Lihat Detail
                    </Link>
                    <template v-if="borrowing.status === 'approved' && borrowing.signed_letter">
                      <a
                        :href="route('item-borrowings.signed-letter', borrowing.id)"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                      >
                        Download Surat
                      </a>
                    </template>
                    <button
                      v-if="canCancel(borrowing)"
                      type="button"
                      class="inline-flex items-center justify-center rounded-xl border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-rose-800 dark:text-rose-300 dark:hover:border-rose-700 dark:hover:bg-rose-900/30"
                      :disabled="cancellingId === borrowing.id"
                      @click="cancelBorrowing(borrowing)"
                    >
                      {{ cancellingId === borrowing.id ? 'Membatalkan...' : 'Batalkan' }}
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!filteredBorrowings.length">
                <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
                  Belum ada data peminjaman barang.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:text-slate-400">
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} data</span>
            <span v-else>Menampilkan 0 data</span>
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400"
              @click="changePage(1)"
              :disabled="currentPage === 1 || !filteredBorrowings.length"
            >
              «
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400"
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1 || !filteredBorrowings.length"
            >
              ‹
            </button>
            <template v-if="filteredBorrowings.length">
              <button
                v-for="page in pages"
                :key="`item-borrowings-page-${page}`"
                type="button"
                class="rounded border px-3 py-1 text-sm transition"
                :class="
                  currentPage === page
                    ? 'border-blue-500 bg-blue-500 text-white'
                    : 'border-gray-300 text-gray-600 hover:border-blue-400 hover:text-blue-600 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400'
                "
                @click="changePage(page)"
              >
                {{ page }}
              </button>
            </template>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400"
              @click="changePage(currentPage + 1)"
              :disabled="currentPage === pages.length || !filteredBorrowings.length"
            >
              ›
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400"
              @click="changePage(pages.length)"
              :disabled="currentPage === pages.length || !filteredBorrowings.length"
            >
              »
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>