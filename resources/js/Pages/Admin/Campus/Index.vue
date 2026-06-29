<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Modal from '@/Components/Modal.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
// PENTING: Tambahkan 'router' di sini untuk fungsi delete
import { Head, Link, useForm, router } from '@inertiajs/vue3' 
import { computed, ref } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
  campuses: Array
})

const searchQuery = ref('')

const filteredCampuses = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return props.campuses ?? []
  
  return (props.campuses ?? []).filter(campus => {
    const searchable = [
      campus.name,
      campus.address,
      campus.phone,
    ].filter(Boolean).join(' ').toLowerCase()
    
    return searchable.includes(q)
  })
})

const showCreateModal = ref(false)
const showEditModal = ref(false)
const selectedCampus = ref(null)

const createForm = useForm({
  name: '',
  address: '',
  phone: ''
})

const editForm = useForm({
  name: '',
  address: '',
  phone: ''
})

const getSwalTarget = () => document.querySelector('dialog[open]') || document.body

const getCampusDependencyCount = (campus) => Number(campus?.buildings_count) || 0

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
    text: "Apakah Anda yakin data campus yang dimasukkan sudah benar?",
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
  createForm.post(route('admin.campus.store'), {
    onSuccess: () => {
      closeCreateModal()
    }
  })
}

// ==========================================
// LOGIKA MODAL EDIT (UPDATE)
// ==========================================
const openEditModal = (campus) => {
  selectedCampus.value = campus
  editForm.name = campus.name ?? ''
  editForm.address = campus.address ?? ''
  editForm.phone = campus.phone ?? ''
  editForm.clearErrors()
  showEditModal.value = true
}

const closeEditModal = () => {
  showEditModal.value = false
  selectedCampus.value = null
  editForm.reset()
  editForm.clearErrors()
}

// Konfirmasi Update sudah ada di sini
const confirmEdit = () => {
  Swal.fire({
    title: 'Konfirmasi Update',
    text: "Apakah Anda yakin ingin menyimpan perubahan pada campus ini?",
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
  if (!selectedCampus.value) return
  
  editForm.put(route('admin.campus.update', selectedCampus.value.id), {
    onSuccess: () => {
      closeEditModal()
    }
  })
}

// ==========================================
// LOGIKA DELETE
// ==========================================
const confirmDelete = (campus) => {
  const dependencyCount = getCampusDependencyCount(campus)

  if (dependencyCount > 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Data masih digunakan',
      text: `Campus "${campus.name}" masih memiliki ${dependencyCount} gedung. Hapus gedung terkait terlebih dahulu sebelum menghapus campus ini.`,
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
    text: `Data campus "${campus.name}" akan dihapus permanen dan tidak dapat dikembalikan!`,
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
      router.delete(route('admin.campus.destroy', campus.id))
    }
  })
}

// ==========================================
// LOGIKA TABEL & PAGINASI
// ==========================================
const {
  sortedItems: sortedCampuses,
  toggleSort: toggleCampusSort,
  sortDirection: campusSortDirection,
  ariaSortValue: campusAriaSortValue,
} = useTableSort(filteredCampuses, {
  accessors: {
    number: (campus) => campus.id ?? 0,
    name: (campus) => campus.name ?? '',
    address: (campus) => campus.address ?? '',
    phone: (campus) => campus.phone ?? '',
  },
})

const {
  paginatedItems: paginatedCampuses,
  rowsPerPage,
  currentPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(sortedCampuses)

const perPageOptions = [5, 10, 25, 50]
const canGoToPreviousPage = computed(() => currentPage.value > 1 && filteredCampuses.value.length > 0)
const canGoToNextPage = computed(() => currentPage.value < pages.value.length && filteredCampuses.value.length > 0)
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Master Campus" />

    <div class="space-y-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">Master Campus</h1>
          <p class="text-sm text-gray-500 dark:text-gray-400">Kelola data kampus yang tersedia dalam sistem.</p>
        </div>

        <button
          type="button"
          class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
          @click="openCreateModal"
        >
          + Tambah Campus
        </button>
      </div>

      <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex flex-col gap-4 px-5 py-4 md:flex-row md:items-end md:justify-between">
          <div class="flex flex-1 flex-col gap-3 md:flex-row md:items-center">
            <div class="w-full md:max-w-sm">
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" for="admin-campuses-search">Pencarian</label>
              <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10 18a8 8 0 0 1-1.06-1.06L12.94 10l3.71-3.71a.75.75 0 0 1 1.06-1.06Z" />
                  </svg>
                </span>
                <input
                  id="admin-campuses-search"
                  v-model="searchQuery"
                  type="text"
                  placeholder="Cari nama campus, alamat, telepon..."
                  class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-700 placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
                />
              </div>
            </div>
          </div>
          <div class="flex flex-col gap-2 text-sm text-gray-600 dark:text-gray-400 sm:flex-row sm:items-center">
            <label class="font-medium text-gray-700 dark:text-gray-300" for="admin-campuses-rows">Rows per page</label>
              <div class="relative">
                <select
                  id="admin-campuses-rows"
                  v-model.number="rowsPerPage"
                  class="w-full rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 sm:w-20"
                >
                  <option v-for="option in perPageOptions" :key="`campuses-rows-${option}`" :value="option">
                    {{ option }}
                  </option>
                </select>
              </div>
          </div>
        </div>

        <table class="master-mobile-table mobile-friendly-table min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
          <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-slate-700 dark:text-slate-400">
            <tr>
              <SortableTh class="px-4 py-2 text-left" column="number" label="#" :direction="campusSortDirection('number')" :aria-sort="campusAriaSortValue('number')" @toggle="toggleCampusSort" />
              <SortableTh class="px-4 py-2 text-left" column="name" label="Nama Campus" :direction="campusSortDirection('name')" :aria-sort="campusAriaSortValue('name')" @toggle="toggleCampusSort" />
              <SortableTh class="px-4 py-2 text-left" column="address" label="Alamat" :direction="campusSortDirection('address')" :aria-sort="campusAriaSortValue('address')" @toggle="toggleCampusSort" />
              <SortableTh class="px-4 py-2 text-left" column="phone" label="Telepon" :direction="campusSortDirection('phone')" :aria-sort="campusAriaSortValue('phone')" @toggle="toggleCampusSort" />
              <th class="px-4 py-2 text-left">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-slate-700 dark:text-slate-300">
            <tr v-for="(campus, index) in paginatedCampuses" :key="campus.id" class="transition hover:bg-gray-50 dark:hover:bg-slate-700/50">
              <td class="mobile-id-cell px-4 py-2 text-sm text-gray-500 dark:text-gray-400" data-title="#">{{ pageMeta.from ? pageMeta.from + index : index + 1 }}</td>
              <td class="mobile-primary-cell mobile-span-2 px-4 py-2" data-title="Nama Campus">
                <div class="mobile-primary-label">Nama Campus</div>
                <div class="mobile-primary-title">{{ campus.name }}</div>
              </td>
              <td class="px-4 py-2 text-sm mobile-meta-cell mobile-compact-meta" data-title="Alamat">{{ campus.address }}</td>
              <td class="px-4 py-2 text-sm mobile-meta-cell mobile-compact-meta" data-title="Telepon">{{ campus.phone }}</td>
              <td class="mobile-action-cell px-4 py-2 text-sm" data-title="Aksi">
                <div class="flex flex-col gap-2 sm:flex-row">
                  <button
                    type="button"
                    class="rounded-lg bg-yellow-400 px-3 py-2 text-sm font-semibold text-white hover:bg-yellow-500"
                    @click="openEditModal(campus)"
                  >
                    Edit
                  </button>

                  <button
                    type="button"
                    class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700"
                    @click="confirmDelete(campus)"
                  >
                    Hapus
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!filteredCampuses.length">
              <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400 dark:text-slate-500">
                Tidak ada data campus.
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
              <button type="button" class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(1)" :disabled="currentPage === 1 || !filteredCampuses.length">«</button>
              <button type="button" class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(currentPage - 1)" :disabled="currentPage === 1 || !filteredCampuses.length">‹</button>
              <template v-if="filteredCampuses.length">
                <button
                  v-for="page in pages"
                  :key="`campus-page-${page}`"
                  type="button"
                  class="rounded border px-2 py-1 text-sm transition"
                  :class="currentPage === page ? 'border-blue-500 bg-blue-500 text-white' : 'border-gray-300 text-gray-600 hover:border-blue-400 hover:text-blue-600 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400'"
                  :disabled="typeof page !== 'number'"
                  @click="changePage(page)"
                >
                  {{ page }}
                </button>
              </template>
              <button type="button" class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(currentPage + 1)" :disabled="currentPage === pages.length || !filteredCampuses.length">›</button>
              <button type="button" class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(pages.length)" :disabled="currentPage === pages.length || !filteredCampuses.length">»</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL CREATE -->
    <Modal :show="showCreateModal" maxWidth="3xl" @close="closeCreateModal">
      <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">➕ Tambah Campus</h2>

        <form @submit.prevent="confirmCreate" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Campus</label>
            <input
              v-model="createForm.name"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="createForm.errors.name" class="text-red-500 text-sm mt-1">{{ createForm.errors.name }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Alamat</label>
            <input
              v-model="createForm.address"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="createForm.errors.address" class="text-red-500 text-sm mt-1">{{ createForm.errors.address }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Telepon</label>
            <input
              v-model="createForm.phone"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="createForm.errors.phone" class="text-red-500 text-sm mt-1">{{ createForm.errors.phone }}</div>
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
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">✏️ Edit Campus</h2>

        <form @submit.prevent="confirmEdit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Campus</label>
            <input
              v-model="editForm.name"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="editForm.errors.name" class="text-red-500 text-sm mt-1">{{ editForm.errors.name }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Alamat</label>
            <input
              v-model="editForm.address"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="editForm.errors.address" class="text-red-500 text-sm mt-1">{{ editForm.errors.address }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Telepon</label>
            <input
              v-model="editForm.phone"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="editForm.errors.phone" class="text-red-500 text-sm mt-1">{{ editForm.errors.phone }}</div>
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
