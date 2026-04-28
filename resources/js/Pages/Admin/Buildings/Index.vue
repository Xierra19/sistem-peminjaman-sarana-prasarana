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
const confirmDelete = (id, name) => {
  Swal.fire({
    title: 'Yakin ingin menghapus?',
    text: `Data gedung "${name}" akan dihapus permanen dan tidak dapat dikembalikan!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.buildings.destroy', id))
    }
  })
}

// ==========================================
// LOGIKA TABEL & PAGINASI
// ==========================================
const buildingsList = computed(() => props.buildings ?? [])

const {
  sortedItems: sortedBuildings,
  toggleSort: toggleBuildingSort,
  sortDirection: buildingSortDirection,
  ariaSortValue: buildingAriaSortValue,
} = useTableSort(buildingsList, {
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
</script>

<template>
  <Head title="Master Building" />

  <AuthenticatedLayout>
    <div class="bg-white p-6 rounded-lg shadow-md">
      <div class="flex justify-between items-center mb-4">
        <div>
          <h1 class="text-xl font-semibold text-gray-800">🏢 Master Building</h1>
          <p class="text-sm text-gray-500">Kelola data gedung untuk setiap campus.</p>
        </div>
        <button
          type="button"
          class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700"
          @click="openCreateModal"
        >
          ➕ Tambah Gedung
        </button>
      </div>

      <div class="overflow-x-auto">
        <div class="flex flex-col gap-3 pb-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="text-sm font-semibold text-gray-700">Daftar Gedung</div>
          <div class="flex items-center justify-end gap-3 text-sm text-gray-600">
            <label class="font-medium text-gray-700" for="admin-buildings-rows">Rows per page</label>
            <div class="relative">
              <select
                id="admin-buildings-rows"
                v-model.number="rowsPerPage"
                class="appearance-none w-20 rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              >
                <option v-for="option in perPageOptions" :key="option" :value="option">
                  {{ option }}
                </option>
              </select>
              <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-gray-400">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                  <path
                    fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 011.06.02L10 10.939l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"
                    clip-rule="evenodd"
                  />
                </svg>
              </span>
            </div>
          </div>
        </div>
        <table class="min-w-full border border-gray-200 divide-y divide-gray-200">
          <thead class="bg-gray-100">
            <tr>
              <SortableTh
                class="px-4 py-2 text-left text-sm font-semibold text-gray-600"
                column="number"
                label="#"
                :direction="buildingSortDirection('number')"
                :aria-sort="buildingAriaSortValue('number')"
                @toggle="toggleBuildingSort"
              />
              <SortableTh
                class="px-4 py-2 text-left text-sm font-semibold text-gray-600"
                column="name"
                label="Nama Gedung"
                :direction="buildingSortDirection('name')"
                :aria-sort="buildingAriaSortValue('name')"
                @toggle="toggleBuildingSort"
              />
              <SortableTh
                class="px-4 py-2 text-left text-sm font-semibold text-gray-600"
                column="campus"
                label="Campus"
                :direction="buildingSortDirection('campus')"
                :aria-sort="buildingAriaSortValue('campus')"
                @toggle="toggleBuildingSort"
              />
              <SortableTh
                class="px-4 py-2 text-left text-sm font-semibold text-gray-600"
                column="created_at"
                label="Dibuat"
                :direction="buildingSortDirection('created_at')"
                :aria-sort="buildingAriaSortValue('created_at')"
                @toggle="toggleBuildingSort"
              />
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(building, index) in paginatedBuildings" :key="building.id">
              <td class="px-4 py-2 text-sm text-gray-700">{{ pageMeta.from ? pageMeta.from + index : index + 1 }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ building.name }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ building.campus?.name ?? '-' }}</td>
              <td class="px-4 py-2 text-sm text-gray-500">
                {{ new Date(building.created_at).toLocaleDateString('id-ID') }}
              </td>
              <td class="px-4 py-2 text-sm">
                <div class="flex gap-2">
                  <button
                    type="button"
                    class="px-3 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500"
                    @click="openEditModal(building)"
                  >
                    ✏️ Edit
                  </button>
                  <!-- Ubah Link menjadi button memanggil confirmDelete -->
                  <button
                    type="button"
                    class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700"
                    @click="confirmDelete(building.id, building.name)"
                  >
                    🗑 Hapus
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!buildingsList.length">
              <td colspan="5" class="px-4 py-4 text-center text-gray-500">
                Belum ada data gedung.
              </td>
            </tr>
          </tbody>
        </table>
        <div class="flex flex-col gap-3 pt-4 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} data</span>
            <span v-else>Menampilkan 0 data</span>
          </div>
          <div class="flex items-center gap-1">
            <button
              type="button"
              class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(1)"
              :disabled="currentPage === 1 || !buildingsList.length"
            >
              «
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1 || !buildingsList.length"
            >
              ‹
            </button>
            <template v-if="buildingsList.length">
              <button
                v-for="page in pages"
                :key="`buildings-page-${page}`"
                type="button"
                class="rounded border px-3 py-1 text-sm"
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
              class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage + 1)"
              :disabled="currentPage === pages.length || !buildingsList.length"
            >
              ›
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(pages.length)"
              :disabled="currentPage === pages.length || !buildingsList.length"
            >
              »
            </button>
          </div>
        </div>        
      </div>
    </div>

    <!-- CREATE MODAL (Diperbesar ke 3xl) -->
    <Modal :show="showCreateModal" maxWidth="3xl" @close="closeCreateModal">
      <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">➕ Tambah Gedung</h2>

        <form @submit.prevent="confirmCreate" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Nama Gedung</label>
            <input v-model="createForm.name" type="text" class="w-full border rounded px-3 py-2 mt-1" />
            <div v-if="createForm.errors.name" class="text-red-500 text-sm">{{ createForm.errors.name }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Campus</label>
            <select v-model="createForm.campus_id" class="w-full border rounded px-3 py-2 mt-1"
                    :disabled="!props.campuses || props.campuses.length === 0">
              <option value="" disabled>
                {{ (!props.campuses || props.campuses.length === 0) ? 'Memuat daftar campus…' : 'Pilih campus' }}
              </option>
              <option v-for="campus in (props.campuses || [])" :key="campus.id" :value="campus.id">
                {{ campus.name }}
              </option>
            </select>
            <div v-if="createForm.errors.campus_id" class="text-red-500 text-sm">{{ createForm.errors.campus_id }}</div>
          </div>

          <div class="flex justify-end gap-2">
            <button type="button" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600" @click="closeCreateModal">
              Batal
            </button>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700" :disabled="createForm.processing">
              Simpan
            </button>
          </div>
        </form>
      </div>
    </Modal>

    <!-- EDIT MODAL (Diperbesar ke 3xl) -->
    <Modal :show="showEditModal" maxWidth="3xl" @close="closeEditModal">
      <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">✏️ Edit Gedung</h2>

        <form @submit.prevent="confirmEdit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Nama Gedung</label>
            <input v-model="editForm.name" type="text" class="w-full border rounded px-3 py-2 mt-1" />
            <div v-if="editForm.errors.name" class="text-red-500 text-sm">{{ editForm.errors.name }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Campus</label>
            <select v-model="editForm.campus_id" class="w-full border rounded px-3 py-2 mt-1"
                    :disabled="!props.campuses || props.campuses.length === 0">
              <option value="" disabled>
                {{ (!props.campuses || props.campuses.length === 0) ? 'Memuat daftar campus…' : 'Pilih campus' }}
              </option>
              <option v-for="campus in (props.campuses || [])" :key="campus.id" :value="campus.id">
                {{ campus.name }}
              </option>
            </select>
            <div v-if="editForm.errors.campus_id" class="text-red-500 text-sm">{{ editForm.errors.campus_id }}</div>
          </div>

          <div class="flex justify-end gap-2">
            <button type="button" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600" @click="closeEditModal">
              Batal
            </button>
            <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600" :disabled="editForm.processing">
              Update
            </button>
          </div>
        </form>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>

<style>
/* Memaksa pop-up SweetAlert tampil di urutan paling depan */
div.swal-z-top-force {
  z-index: 999999 !important;
}
</style>