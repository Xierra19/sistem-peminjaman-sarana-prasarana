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
  buildings: Array,
  campuses: Array // akan diisi via reload lazy prop
})

const searchQuery = ref('')

const filteredBuildings = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return props.buildings ?? []
  
  return (props.buildings ?? []).filter(building => {
    const searchable = [
      building.name,
      building.campus?.name,
    ].filter(Boolean).join(' ').toLowerCase()
    
    return searchable.includes(q)
  })
})

const showCreateModal = ref(false)
const showEditModal = ref(false)
const selectedBuilding = ref(null)

const createForm = useForm({
  name: '',
  campus_id: ''
})

const editForm = useForm({
  name: '',
  campus_id: ''
})

const bulkDeleteForm = useForm({
  ids: []
})

const selectedBuildingIds = ref([])

const getSwalTarget = () => document.querySelector('dialog[open]') || document.body

const getBuildingDependencyCount = (building) => Number(building?.rooms_count) || 0

const selectedBuildings = computed(() => {
  const ids = selectedBuildingIds.value
  return (props.buildings ?? []).filter((building) => ids.includes(building.id))
})

const selectedFilteredBuildingIds = computed(() => (filteredBuildings.value ?? []).map((building) => building.id))

const allFilteredBuildingsSelected = computed(() => {
  const ids = selectedFilteredBuildingIds.value
  return ids.length > 0 && ids.every((id) => selectedBuildingIds.value.includes(id))
})

const clearBuildingSelection = () => {
  selectedBuildingIds.value = []
}

const toggleAllFilteredBuildings = () => {
  const ids = selectedFilteredBuildingIds.value

  if (ids.length === 0) {
    return
  }

  if (allFilteredBuildingsSelected.value) {
    selectedBuildingIds.value = selectedBuildingIds.value.filter((id) => !ids.includes(id))
    return
  }

  selectedBuildingIds.value = Array.from(new Set([...selectedBuildingIds.value, ...ids]))
}

const submitBulkDelete = () => {
  bulkDeleteForm.ids = selectedBuildings.value.map((building) => building.id)

  bulkDeleteForm.post(route('admin.buildings.bulk-destroy'), {
    preserveScroll: true,
    onSuccess: () => {
      clearBuildingSelection()
    },
  })
}

const confirmBulkDelete = () => {
  const items = selectedBuildings.value
  const deletableCount = items.filter((building) => getBuildingDependencyCount(building) === 0).length
  const blockedCount = items.length - deletableCount

  if (deletableCount === 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Tidak ada data yang bisa dihapus',
      text: 'Semua gedung terpilih masih memiliki ruangan terkait.',
      confirmButtonText: 'Mengerti',
      target: getSwalTarget(),
      customClass: {
        container: 'swal-z-top-force'
      }
    })

    return
  }

  Swal.fire({
    title: 'Hapus gedung terpilih?',
    text: blockedCount > 0
      ? `Sebanyak ${deletableCount} gedung akan dihapus. ${blockedCount} gedung lain dilewati karena masih memiliki ruangan terkait.`
      : `Sebanyak ${deletableCount} gedung akan dihapus permanen dan tidak dapat dikembalikan!`,
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

// minta ulang hanya prop 'campuses' jika belum ada / kosong
const ensureCampusesLoaded = () => {
  if (!props.campuses || props.campuses.length === 0) {
    router.reload({ only: ['campuses'] })
  }
}

// ==========================================
// LOGIKA MODAL CREATE
// ==========================================
const openCreateModal = () => {
  createForm.reset()
  createForm.clearErrors()
  showCreateModal.value = true
  ensureCampusesLoaded()
}

const closeCreateModal = () => {
  showCreateModal.value = false
  createForm.reset()
  createForm.clearErrors()
}

const confirmCreate = () => {
  Swal.fire({
    title: 'Konfirmasi Simpan',
    text: "Apakah Anda yakin data gedung yang dimasukkan sudah benar?",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#2563eb', 
    cancelButtonColor: '#6b7280',  
    confirmButtonText: 'Ya, Simpan',
    cancelButtonText: 'Periksa Lagi',
    target: document.querySelector('dialog[open]') || document.body,
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
  createForm.post(route('admin.buildings.store'), {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      closeCreateModal()
    },
    onError: () => {
      ensureCampusesLoaded()
    },
    onFinish: () => {
      ensureCampusesLoaded()
    }
  })
}

// ==========================================
// LOGIKA MODAL EDIT (UPDATE)
// ==========================================
const openEditModal = (building) => {
  selectedBuilding.value = building
  editForm.name = building.name ?? ''
  editForm.campus_id = building.campus_id ?? ''
  editForm.clearErrors()
  showEditModal.value = true
  ensureCampusesLoaded()
}

const closeEditModal = () => {
  showEditModal.value = false
  selectedBuilding.value = null
  editForm.reset()
  editForm.clearErrors()
}

// Konfirmasi Update sudah ada di sini
const confirmEdit = () => {
  Swal.fire({
    title: 'Konfirmasi Update',
    text: "Apakah Anda yakin ingin menyimpan perubahan pada gedung ini?",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#eab308', 
    cancelButtonColor: '#6b7280',  
    confirmButtonText: 'Ya, Update',
    cancelButtonText: 'Batal',
    target: document.querySelector('dialog[open]') || document.body,
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
  if (!selectedBuilding.value) return
  
  editForm.put(route('admin.buildings.update', selectedBuilding.value.id), {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      closeEditModal()
    },
    onError: () => {
      ensureCampusesLoaded()
    },
    onFinish: () => {
      ensureCampusesLoaded()
    }
  })
}

// ==========================================
// LOGIKA DELETE
// ==========================================
const confirmDelete = (building) => {
  const dependencyCount = getBuildingDependencyCount(building)

  if (dependencyCount > 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Data masih digunakan',
      text: `Gedung "${building.name}" masih memiliki ${dependencyCount} ruangan. Hapus ruangan terkait terlebih dahulu sebelum menghapus gedung ini.`,
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
    text: `Data gedung "${building.name}" akan dihapus permanen dan tidak dapat dikembalikan!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626', // Warna merah untuk tombol hapus
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
    target: getSwalTarget(),
    customClass: {
      container: 'swal-z-top-force'
    }
  }).then((result) => {
    if (result.isConfirmed) {
      // Mengirim request DELETE menggunakan router Inertia
      router.delete(route('admin.buildings.destroy', building.id), {
        onSuccess: () => {
          clearBuildingSelection()
        }
      })
    }
  })
}

// ==========================================
// LOGIKA TABEL & PAGINASI
// ==========================================
const {
  sortedItems: sortedBuildings,
  toggleSort: toggleBuildingSort,
  sortDirection: buildingSortDirection,
  ariaSortValue: buildingAriaSortValue,
} = useTableSort(filteredBuildings, {
  accessors: {
    number: (building) => building.id ?? 0,
    name: (building) => building.name ?? '',
    campus: (building) => building.campus?.name ?? '',
    created_at: (building) => (building.created_at ? new Date(building.created_at) : null),
  },
})

const {
  paginatedItems: paginatedBuildings,
  rowsPerPage,
  currentPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(sortedBuildings)

const perPageOptions = [5, 10, 25, 50]
const canGoToPreviousPage = computed(() => currentPage.value > 1 && filteredBuildings.value.length > 0)
const canGoToNextPage = computed(() => currentPage.value < pages.value.length && filteredBuildings.value.length > 0)
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Master Gedung" />

    <div class="space-y-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">Master Gedung</h1>
          <p class="text-sm text-gray-500 dark:text-gray-400">Kelola data gedung untuk setiap kampus.</p>
        </div>

        <button
          type="button"
          class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
          @click="openCreateModal"
        >
          + Tambah Gedung
        </button>
      </div>

      <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex flex-col gap-4 px-5 py-4 md:flex-row md:items-end md:justify-between dark:border-slate-700">
          <div class="flex flex-1 flex-col gap-3 md:flex-row md:items-center">
            <div class="w-full md:max-w-sm">
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" for="admin-buildings-search">Pencarian</label>
              <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10 18a8 8 0 1 1 0-16 8 8 8 0 0 1 0 16Z" />
                  </svg>
                </span>
                <input
                  id="admin-buildings-search"
                  v-model="searchQuery"
                  type="text"
                  placeholder="Cari nama gedung atau kampus..."
                  class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-700 placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
                />
              </div>
            </div>
          </div>
          <div class="flex flex-col gap-2 text-sm text-gray-600 dark:text-gray-400 sm:flex-row sm:items-center">
            <label class="font-medium text-gray-700 dark:text-gray-300" for="admin-buildings-rows">Rows per page</label>
              <div class="relative">
                <select
                  id="admin-buildings-rows"
                  v-model.number="rowsPerPage"
                  class="w-full rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 sm:w-20"
                >
                  <option v-for="option in perPageOptions" :key="`buildings-rows-${option}`" :value="option">
                    {{ option }}
                  </option>
                </select>
              </div>
          </div>
        </div>

        <div v-if="selectedBuildings.length" class="flex flex-col gap-3 border-t border-gray-100 px-5 py-3 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:text-slate-400">
          <span>{{ selectedBuildings.length }} gedung dipilih</span>
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
                  :checked="allFilteredBuildingsSelected"
                  @change="toggleAllFilteredBuildings"
                  aria-label="Pilih semua gedung"
                />
              </th>
              <SortableTh class="px-4 py-2 text-left" column="number" label="#" :direction="buildingSortDirection('number')" :aria-sort="buildingAriaSortValue('number')" @toggle="toggleBuildingSort" />
              <SortableTh class="px-4 py-2 text-left" column="name" label="Nama Gedung" :direction="buildingSortDirection('name')" :aria-sort="buildingAriaSortValue('name')" @toggle="toggleBuildingSort" />
              <SortableTh class="px-4 py-2 text-left" column="campus" label="Campus" :direction="buildingSortDirection('campus')" :aria-sort="buildingAriaSortValue('campus')" @toggle="toggleBuildingSort" />
              <SortableTh class="px-4 py-2 text-left" column="created_at" label="Dibuat" :direction="buildingSortDirection('created_at')" :aria-sort="buildingAriaSortValue('created_at')" @toggle="toggleBuildingSort" />
              <th class="px-4 py-2 text-left">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-slate-700 dark:text-slate-300">
            <tr v-for="(building, index) in paginatedBuildings" :key="building.id" class="transition hover:bg-gray-50 dark:hover:bg-slate-700/50">
              <td class="px-4 py-2 align-top" data-title="Pilih">
                <input
                  v-model="selectedBuildingIds"
                  type="checkbox"
                  :value="building.id"
                  class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700"
                  :aria-label="`Pilih gedung ${building.name}`"
                />
              </td>
              <td class="mobile-id-cell px-4 py-2 text-sm text-gray-500 dark:text-gray-400" data-title="#">{{ pageMeta.from ? pageMeta.from + index : index + 1 }}</td>
              <td class="mobile-primary-cell mobile-span-2 px-4 py-2" data-title="Nama Gedung">
                <div class="mobile-primary-label">Nama Gedung</div>
                <div class="mobile-primary-title">{{ building.name }}</div>
              </td>
              <td class="px-4 py-2 text-sm mobile-meta-cell mobile-compact-meta" data-title="Campus">{{ building.campus?.name ?? '-' }}</td>
              <td class="px-4 py-2 text-sm mobile-meta-cell mobile-compact-meta" data-title="Dibuat">{{ new Date(building.created_at).toLocaleDateString('id-ID') }}</td>
              <td class="mobile-action-cell px-4 py-2 text-sm" data-title="Aksi">
                <div class="flex flex-col gap-2 sm:flex-row">
                  <!-- Tombol Edit -->
                  <button
                    type="button"
                    class="rounded-lg bg-yellow-400 px-3 py-2 text-sm font-semibold text-white hover:bg-yellow-500"
                    @click="openEditModal(building)"
                  >
                    Edit
                  </button>

                  <!-- Tombol Delete yang sudah diperbaiki memanggil confirmDelete -->
                  <button
                    type="button"
                    class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700"
                    @click="confirmDelete(building)"
                  >
                    Hapus
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!filteredBuildings.length">
              <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                Tidak ada data gedung.
              </td>
            </tr>
          </tbody>
        </table>

        <div class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:text-gray-400">
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
              <button type="button" class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(1)" :disabled="currentPage === 1 || !filteredBuildings.length">«</button>
              <button type="button" class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(currentPage - 1)" :disabled="currentPage === 1 || !filteredBuildings.length">‹</button>
              <template v-if="filteredBuildings.length">
                <button
                  v-for="page in pages"
                  :key="`building-page-${page}`"
                  type="button"
                  class="rounded border px-2 py-1 text-sm transition"
                  :class="currentPage === page ? 'border-blue-500 bg-blue-500 text-white' : 'border-gray-300 text-gray-600 hover:border-blue-400 hover:text-blue-600 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400'"
                  :disabled="typeof page !== 'number'"
                  @click="changePage(page)"
                >
                  {{ page }}
                </button>
              </template>
              <button type="button" class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(currentPage + 1)" :disabled="currentPage === pages.length || !filteredBuildings.length">›</button>
              <button type="button" class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(pages.length)" :disabled="currentPage === pages.length || !filteredBuildings.length">»</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL CREATE -->
    <Modal :show="showCreateModal" maxWidth="3xl" @close="closeCreateModal">
      <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">➕ Tambah Gedung</h2>

        <form @submit.prevent="confirmCreate" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Gedung</label>
            <input
              v-model="createForm.name"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="createForm.errors.name" class="text-red-500 text-sm mt-1">{{ createForm.errors.name }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Campus</label>
            <select
              v-model="createForm.campus_id"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
              :disabled="!props.campuses || props.campuses.length === 0"
            >
              <option value="" disabled>
                {{ (!props.campuses || props.campuses.length === 0) ? 'Memuat daftar campus…' : 'Pilih campus' }}
              </option>
              <option v-for="campus in (props.campuses || [])" :key="campus.id" :value="campus.id">
                {{ campus.name }}
              </option>
            </select>
            <div v-if="createForm.errors.campus_id" class="text-red-500 text-sm mt-1">{{ createForm.errors.campus_id }}</div>
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
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">✏️ Edit Gedung</h2>

        <form @submit.prevent="confirmEdit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Gedung</label>
            <input
              v-model="editForm.name"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="editForm.errors.name" class="text-red-500 text-sm mt-1">{{ editForm.errors.name }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Campus</label>
            <select
              v-model="editForm.campus_id"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
              :disabled="!props.campuses || props.campuses.length === 0"
            >
              <option value="" disabled>
                {{ (!props.campuses || props.campuses.length === 0) ? 'Memuat daftar campus…' : 'Pilih campus' }}
              </option>
              <option v-for="campus in (props.campuses || [])" :key="campus.id" :value="campus.id">
                {{ campus.name }}
              </option>
            </select>
            <div v-if="editForm.errors.campus_id" class="text-red-500 text-sm mt-1">{{ editForm.errors.campus_id }}</div>
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
