<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Modal from '@/Components/Modal.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Swal from 'sweetalert2' // <-- Import SweetAlert 

const props = defineProps({
  items: Array
})

const searchQuery = ref('')

const filteredItems = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return props.items ?? []
  
  return (props.items ?? []).filter(item => {
    const searchable = [
      item.code,
      item.name,
      item.category,
    ].filter(Boolean).join(' ').toLowerCase()
    
    return searchable.includes(q)
  })
})

const showCreateModal = ref(false)
const showEditModal = ref(false)
const selectedItem = ref(null)

const createForm = useForm({
  code: '',
  name: '',
  category: '',
  quantity: 0,
  is_available: true,
})

const editForm = useForm({
  code: '',
  name: '',
  category: '',
  quantity: 0,
  is_available: true,
})

const bulkDeleteForm = useForm({
  ids: []
})

const selectedItemIds = ref([])

const getSwalTarget = () => document.querySelector('dialog[open]') || document.body

const getItemDependencyCounts = (item) => ({
  legacy: Number(item?.item_borrowings_count) || 0,
  pivot: Number(item?.borrowing_items_count) || 0,
})

const selectedItems = computed(() => {
  const ids = selectedItemIds.value
  return (props.items ?? []).filter((item) => ids.includes(item.id))
})

const selectedFilteredItemIds = computed(() => (filteredItems.value ?? []).map((item) => item.id))

const allFilteredItemsSelected = computed(() => {
  const ids = selectedFilteredItemIds.value
  return ids.length > 0 && ids.every((id) => selectedItemIds.value.includes(id))
})

const clearItemSelection = () => {
  selectedItemIds.value = []
}

const toggleAllFilteredItems = () => {
  const ids = selectedFilteredItemIds.value

  if (ids.length === 0) {
    return
  }

  if (allFilteredItemsSelected.value) {
    selectedItemIds.value = selectedItemIds.value.filter((id) => !ids.includes(id))
    return
  }

  selectedItemIds.value = Array.from(new Set([...selectedItemIds.value, ...ids]))
}

const submitBulkDelete = () => {
  bulkDeleteForm.ids = selectedItems.value.map((item) => item.id)

  bulkDeleteForm.post(route('admin.items.bulk-destroy'), {
    preserveScroll: true,
    onSuccess: () => {
      clearItemSelection()
    },
  })
}

const confirmBulkDelete = () => {
  const items = selectedItems.value
  const deletableCount = items.filter((item) => {
    const dependencyCounts = getItemDependencyCounts(item)
    return dependencyCounts.legacy === 0 && dependencyCounts.pivot === 0
  }).length
  const blockedCount = items.length - deletableCount

  if (deletableCount === 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Tidak ada data yang bisa dihapus',
      text: 'Semua barang terpilih masih memiliki riwayat peminjaman.',
      confirmButtonText: 'Mengerti',
      target: getSwalTarget(),
      customClass: {
        container: 'swal-z-top-force'
      }
    })

    return
  }

  Swal.fire({
    title: 'Hapus barang terpilih?',
    text: blockedCount > 0
      ? `Sebanyak ${deletableCount} barang akan dihapus. ${blockedCount} barang lain dilewati karena masih memiliki riwayat peminjaman.`
      : `Sebanyak ${deletableCount} barang akan dihapus permanen dan tidak dapat dikembalikan!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
    target: getSwalTarget(),
    customClass: {
      container: 'swal-z-top-force'
    }
  }).then((result) => {
    if (result.isConfirmed) {
      submitBulkDelete()
    }
  })
}

// ==========================================
// LOGIKA MODAL CREATE
// ==========================================
const openCreateModal = () => {
  createForm.reset()
  createForm.clearErrors()
  showCreateModal.value = true
}

const closeCreateModal = () => {
  showCreateModal.value = false
  createForm.reset()
  createForm.clearErrors()
}

const confirmCreate = () => {
  Swal.fire({
    title: 'Konfirmasi Simpan',
    text: "Apakah Anda yakin data barang yang dimasukkan sudah benar?",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#2563eb',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Simpan',
    cancelButtonText: 'Periksa Lagi',
    target: getSwalTarget(),
    customClass: {
      container: 'swal-z-top-force'
    }
  }).then((result) => {
    if (result.isConfirmed) {
      submitCreate()
    }
  })
}

const submitCreate = () => {
  createForm.post(route('admin.items.store'), {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      closeCreateModal()
    },
    onError: (errors) => {
      const msg = errors.code || errors.name || errors.category || errors.quantity || 'Periksa kembali input Anda.'
      Swal.fire({ icon: 'error', title: 'Problem', text: msg, target: getSwalTarget(), heightAuto: false, customClass: { container: 'swal-z-top-force' } })
    }
  })
}

// ==========================================
// LOGIKA MODAL EDIT (UPDATE)
// ==========================================
const openEditModal = (item) => {
  selectedItem.value = item
  editForm.code = item.code ?? ''
  editForm.name = item.name ?? ''
  editForm.category = item.category ?? ''
  editForm.quantity = item.quantity ?? 0
  editForm.is_available = item.is_available ?? true
  editForm.clearErrors()
  showEditModal.value = true
}

const closeEditModal = () => {
  showEditModal.value = false
  selectedItem.value = null
  editForm.reset()
  editForm.clearErrors()
}

const confirmEdit = () => {
  Swal.fire({
    title: 'Konfirmasi Update',
    text: "Apakah Anda yakin ingin menyimpan perubahan pada barang ini?",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#eab308',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Update',
    cancelButtonText: 'Batal',
    target: getSwalTarget(),
    customClass: {
      container: 'swal-z-top-force'
    }
  }).then((result) => {
    if (result.isConfirmed) {
      submitEdit()
    }
  })
}

const submitEdit = () => {
  if (!selectedItem.value) return
  
  editForm.put(route('admin.items.update', selectedItem.value.id), {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      closeEditModal()
    },
    onError: (errors) => {
      const msg = errors.code || errors.name || errors.category || errors.quantity || 'Periksa kembali input Anda.'
      Swal.fire({ icon: 'error', title: 'Problem', text: msg, target: getSwalTarget(), heightAuto: false, customClass: { container: 'swal-z-top-force' } })
    }
  })
}

// ==========================================
// LOGIKA DELETE
// ==========================================
const confirmDelete = (item) => {
  const dependencyCounts = getItemDependencyCounts(item)
  const totalDependencies = dependencyCounts.legacy + dependencyCounts.pivot

  if (totalDependencies > 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Data masih digunakan',
      text: `Barang "${item.name}" masih memiliki ${dependencyCounts.legacy} riwayat peminjaman lama dan ${dependencyCounts.pivot} riwayat peminjaman baru. Selesaikan data peminjaman terlebih dahulu sebelum menghapus barang ini.`,
      confirmButtonText: 'Mengerti',
      target: getSwalTarget(),
      customClass: {
        container: 'swal-z-top-force'
      }
    })

    return
  }

  Swal.fire({
    title: 'Yakin ingin menghapus?',
    text: `Data barang "${item.name}" akan dihapus permanen!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
    target: getSwalTarget(),
    customClass: {
      container: 'swal-z-top-force'
    }
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.items.destroy', item.id), {
        onSuccess: () => {
          clearItemSelection()
        }
      })
    }
  })
}

// ==========================================
// LOGIKA TABEL & PAGINASI
// ==========================================
const {
  sortedItems,
  toggleSort,
  sortDirection,
  ariaSortValue,
} = useTableSort(filteredItems, {
  accessors: {
    number: (item) => item.id ?? 0,
    code: (item) => item.code ?? '',
    name: (item) => item.name ?? '',
    category: (item) => item.category ?? '',
    quantity: (item) => item.quantity ?? 0,
    is_available: (item) => item.is_available,
  },
})

const {
  paginatedItems,
  rowsPerPage,
  currentPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(sortedItems)

const perPageOptions = [5, 10, 25, 50]
const canGoToPreviousPage = computed(() => currentPage.value > 1 && filteredItems.value.length > 0)
const canGoToNextPage = computed(() => currentPage.value < pages.value.length && filteredItems.value.length > 0)
</script>

<template>
  <Head title="Master Barang" />

  <AuthenticatedLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">Master Barang</h1>
          <p class="text-sm text-gray-500 dark:text-gray-400">Kelola data barang inventaris.</p>
        </div>

        <button
          type="button"
          class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
          @click="openCreateModal"
        >
          + Tambah Barang
        </button>
      </div>

      <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex flex-col gap-4 px-5 py-4 md:flex-row md:items-end md:justify-between">
          <div class="flex flex-1 flex-col gap-3 md:flex-row md:items-center">
            <div class="w-full md:max-w-sm">
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" for="admin-items-search">Pencarian</label>
              <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z" />
                  </svg>
                </span>
                <input
                  id="admin-items-search"
                  v-model="searchQuery"
                  type="text"
                  placeholder="Cari kode, nama, atau kategori..."
                  class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-700 placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
                />
              </div>
            </div>
          </div>
          <div class="flex flex-col gap-2 text-sm text-gray-600 dark:text-gray-400 sm:flex-row sm:items-center">
            <label class="font-medium text-gray-700 dark:text-gray-300" for="admin-items-rows">Rows per page</label>
              <div class="relative">
                <select
                  id="admin-items-rows"
                  v-model.number="rowsPerPage"
                  class="w-full rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 sm:w-20"
                >
                  <option v-for="option in perPageOptions" :key="`items-rows-${option}`" :value="option">
                    {{ option }}
                  </option>
                </select>
              </div>
          </div>
        </div>

        <div v-if="selectedItems.length" class="flex flex-col gap-3 border-t border-gray-100 px-5 py-3 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:text-slate-400">
          <span>{{ selectedItems.length }} barang dipilih</span>
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-md bg-red-600 px-4 py-2 font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="bulkDeleteForm.processing"
            @click="confirmBulkDelete"
          >
            Hapus Terpilih
          </button>
        </div>

        <table class="master-mobile-table mobile-friendly-table min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
          <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-slate-700 dark:text-slate-400">
            <tr>
              <th class="px-4 py-2 text-left">
                <input
                  type="checkbox"
                  class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700"
                  :checked="allFilteredItemsSelected"
                  @change="toggleAllFilteredItems"
                  aria-label="Pilih semua barang"
                />
              </th>
              <SortableTh class="px-4 py-2 text-left" column="number" label="#" :direction="sortDirection('number')" :aria-sort="ariaSortValue('number')" @toggle="toggleSort" />
              <SortableTh class="px-4 py-2 text-left" column="code" label="Kode" :direction="sortDirection('code')" :aria-sort="ariaSortValue('code')" @toggle="toggleSort" />
              <SortableTh class="px-4 py-2 text-left" column="name" label="Nama Barang" :direction="sortDirection('name')" :aria-sort="ariaSortValue('name')" @toggle="toggleSort" />
              <SortableTh class="px-4 py-2 text-left" column="category" label="Kategori" :direction="sortDirection('category')" :aria-sort="ariaSortValue('category')" @toggle="toggleSort" />
              <SortableTh class="px-4 py-2 text-left" column="quantity" label="Jumlah" :direction="sortDirection('quantity')" :aria-sort="ariaSortValue('quantity')" @toggle="toggleSort" />
              <SortableTh class="px-4 py-2 text-left" column="is_available" label="Ketersediaan" :direction="sortDirection('is_available')" :aria-sort="ariaSortValue('is_available')" @toggle="toggleSort" />
              <th class="px-4 py-2 text-left">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-slate-700 dark:text-slate-300">
            <tr v-for="(item, index) in paginatedItems" :key="item.id" class="transition hover:bg-gray-50 dark:hover:bg-slate-700/50">
              <td class="px-4 py-2 align-top" data-title="Pilih">
                <input
                  v-model="selectedItemIds"
                  type="checkbox"
                  :value="item.id"
                  class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700"
                  :aria-label="`Pilih barang ${item.name}`"
                />
              </td>
              <td class="mobile-id-cell px-4 py-2 text-sm text-gray-500 dark:text-gray-400" data-title="#">{{ pageMeta.from ? pageMeta.from + index : index + 1 }}</td>
              <td class="px-4 py-2 mobile-meta-cell mobile-compact-meta" data-title="Kode">
                <div class="font-mono text-sm text-gray-900 dark:text-slate-200">{{ item.code }}</div>
              </td>
              <td class="mobile-primary-cell mobile-span-2 px-4 py-2" data-title="Nama Barang">
                <div class="mobile-primary-label">Nama Barang</div>
                <div class="mobile-primary-title">{{ item.name }}</div>
              </td>
              <td class="px-4 py-2 text-sm mobile-compact-meta" data-title="Kategori">{{ item.category }}</td>
              <td class="px-4 py-2 text-sm" data-title="Jumlah">{{ item.quantity }}</td>
              <td class="mobile-status-cell px-4 py-2 text-sm" data-title="Ketersediaan">
                <span :class="item.is_available ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                  {{ item.is_available ? 'Tersedia' : 'Tidak Tersedia' }}
                </span>
              </td>
              <td class="mobile-action-cell px-4 py-2 text-sm" data-title="Aksi">
                <div class="flex flex-col gap-2 sm:flex-row">
                  <button
                    type="button"
                    class="rounded-lg bg-yellow-400 px-3 py-2 text-sm font-semibold text-white hover:bg-yellow-500"
                    @click="openEditModal(item)"
                  >
                    Edit
                  </button>
                  <button
                    type="button"
                    class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700"
                    @click="confirmDelete(item)"
                  >
                    Hapus
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!filteredItems.length">
              <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-400 dark:text-slate-500">
                Tidak ada data barang.
              </td>
            </tr>
          </tbody>
        </table>

        <div class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:text-slate-400">
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} data</span>
            <span v-else>Menampilkan 0 data</span>
          </div>
          <div class="w-full sm:w-auto">
            <div class="mobile-pagination-compact sm:hidden">
              <button type="button" class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(currentPage - 1)" :disabled="!canGoToPreviousPage">Sebelumnya</button>
              <button type="button" class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(currentPage + 1)" :disabled="!canGoToNextPage">Berikutnya</button>
            </div>
            <div class="hidden items-center gap-2 sm:flex">
              <button type="button" class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(1)" :disabled="currentPage === 1 || !filteredItems.length">«</button>
              <button type="button" class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(currentPage - 1)" :disabled="currentPage === 1 || !filteredItems.length">‹</button>
              <template v-if="filteredItems.length">
                <button
                  v-for="page in pages"
                  :key="`item-page-${page}`"
                  type="button"
                  class="rounded border px-2 py-1 text-sm transition"
                  :class="currentPage === page ? 'border-blue-500 bg-blue-500 text-white' : 'border-gray-300 text-gray-600 hover:border-blue-400 hover:text-blue-600 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400'"
                  :disabled="typeof page !== 'number'"
                  @click="changePage(page)"
                >
                  {{ page }}
                </button>
              </template>
              <button type="button" class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(currentPage + 1)" :disabled="currentPage === pages.length || !filteredItems.length">›</button>
              <button type="button" class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(pages.length)" :disabled="currentPage === pages.length || !filteredItems.length">»</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL CREATE -->
    <Modal :show="showCreateModal" maxWidth="3xl" @close="closeCreateModal">
      <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">➕ Tambah Barang</h2>

        <form @submit.prevent="confirmCreate" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Kode Barang</label>
            <input
              v-model="createForm.code"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="createForm.errors.code" class="text-red-500 text-sm mt-1">{{ createForm.errors.code }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Barang</label>
            <input
              v-model="createForm.name"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="createForm.errors.name" class="text-red-500 text-sm mt-1">{{ createForm.errors.name }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Kategori</label>
            <input
              v-model="createForm.category"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="createForm.errors.category" class="text-red-500 text-sm mt-1">{{ createForm.errors.category }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Jumlah</label>
            <input
              v-model.number="createForm.quantity"
              type="number"
              min="0"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="createForm.errors.quantity" class="text-red-500 text-sm mt-1">{{ createForm.errors.quantity }}</div>
          </div>

          <div>
            <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ketersediaan</span>
            <select v-model="createForm.is_available" class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">
                <option :value="true">Tersedia</option>
                <option :value="false">Tidak Tersedia</option>
            </select>
            <div v-if="createForm.errors.is_available" class="text-red-500 text-sm mt-1">{{ createForm.errors.is_available }}</div>
          </div>

          <div class="flex justify-end gap-2">
            <button
              type="button"
              class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600"
              @click="closeCreateModal"
            >
              Batal
            </button>
            <button
              type="submit"
              class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
              :disabled="createForm.processing"
            >
              Simpan
            </button>
          </div>
        </form>
      </div>
    </Modal>

    <!-- MODAL EDIT -->
    <Modal :show="showEditModal" maxWidth="3xl" @close="closeEditModal">
      <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">✏️ Edit Barang</h2>

        <form @submit.prevent="confirmEdit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Kode Barang</label>
            <input
              v-model="editForm.code"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="editForm.errors.code" class="text-red-500 text-sm mt-1">{{ editForm.errors.code }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Barang</label>
            <input
              v-model="editForm.name"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="editForm.errors.name" class="text-red-500 text-sm mt-1">{{ editForm.errors.name }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Kategori</label>
            <input
              v-model="editForm.category"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="editForm.errors.category" class="text-red-500 text-sm mt-1">{{ editForm.errors.category }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Jumlah</label>
            <input
              v-model.number="editForm.quantity"
              type="number"
              min="0"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="editForm.errors.quantity" class="text-red-500 text-sm mt-1">{{ editForm.errors.quantity }}</div>
          </div>

          <div>
            <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ketersediaan</span>
            <select v-model="editForm.is_available" class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">
                <option :value="true">Tersedia</option>
                <option :value="false">Tidak Tersedia</option>
            </select>
            <div v-if="editForm.errors.is_available" class="text-red-500 text-sm mt-1">{{ editForm.errors.is_available }}</div>
          </div>

          <div class="flex justify-end gap-2">
            <button
              type="button"
              class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600"
              @click="closeEditModal"
            >
              Batal
            </button>
            <button
              type="submit"
              class="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600"
              :disabled="editForm.processing"
            >
              Update
            </button>
          </div>
        </form>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>

<style>
/* Class CSS agar modal konfirmasi selalu di depan */
div.swal-z-top-force {
  z-index: 999999 !important;
}
</style>
