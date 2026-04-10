<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'

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

const borrowingsList = computed(() => props.itemBorrowings ?? [])

const {
  sortedItems,
  toggleSort,
  sortDirection,
  ariaSortValue,
} = useTableSort(borrowingsList, {
  accessors: {
    title: (borrowing) => borrowing.title ?? '',
    applicant: (borrowing) => borrowing.user?.name ?? '',
    item: (borrowing) => borrowing.item?.name ?? '',
    quantity: (borrowing) => borrowing.quantity ?? 0,
    borrow_date: (borrowing) => (borrowing.borrow_date ? new Date(borrowing.borrow_date) : null),
    status: (borrowing) => (borrowing.status === 'requested' ? 'waiting' : borrowing.status) ?? '',
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

const formatDate = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
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

      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 md:flex-row md:items-center md:justify-between">
          <div class="text-sm font-semibold text-gray-700">Daftar Permintaan Masuk</div>
          <div class="flex items-center justify-end gap-3 text-sm text-gray-600">
            <label class="font-medium text-gray-700" for="admin-item-borrowings-rows">Rows per page</label>
            <select
              id="admin-item-borrowings-rows"
              v-model.number="rowsPerPage"
              class="w-20 appearance-none rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option v-for="option in perPageOptions" :key="option" :value="option">
                {{ option }}
              </option>
            </select>
          </div>
        </div>

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
                <div class="font-medium text-gray-800">{{ borrowing.item?.name ?? '-' }}</div>
                <div class="text-xs text-gray-500">{{ borrowing.item?.code ?? '-' }} • {{ borrowing.item?.category ?? '-' }}</div>
              </td>
              <td class="px-5 py-4 text-sm">{{ borrowing.quantity }}</td>
              <td class="px-5 py-4 text-sm">
                <div>Pinjam: <span class="font-medium text-gray-800">{{ formatDate(borrowing.borrow_date) }}</span></div>
                <div>Kembali: <span class="font-medium text-gray-800">{{ formatDate(borrowing.return_date) }}</span></div>
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

        <div class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} data</span>
            <span v-else>Menampilkan 0 data</span>
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(1)"
              :disabled="currentPage === 1 || !borrowingsList.length"
            >
              First
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1 || !borrowingsList.length"
            >
              Prev
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
              @click="changePage(currentPage + 1)"
              :disabled="currentPage === pages.length || !borrowingsList.length"
            >
              Next
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(pages.length)"
              :disabled="currentPage === pages.length || !borrowingsList.length"
            >
              Last
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
