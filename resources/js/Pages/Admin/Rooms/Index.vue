<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Modal from '@/Components/Modal.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
  rooms: Array,
  buildings: Array
})

const showCreateModal = ref(false)
const showEditModal = ref(false)
const selectedRoom = ref(null)

const createForm = useForm({ name: '', building_id: '', capacity: 1, is_available: true })
const editForm   = useForm({ name: '', building_id: '', capacity: 1, is_available: true })

const getSwalTarget = () => document.querySelector('dialog[open]') || document.body

const ensureBuildingsLoaded = () => {
  if (!props.buildings || props.buildings.length === 0) {
    router.reload({ only: ['buildings'] })
  }
}

// ==========================================
// LOGIKA MODAL CREATE
// ==========================================
const openCreateModal = () => {
  createForm.reset()
  createForm.is_available = true
  createForm.clearErrors()
  showCreateModal.value = true
  ensureBuildingsLoaded()
}

const closeCreateModal = () => {
  showCreateModal.value = false
  createForm.reset(); createForm.clearErrors()
}

const confirmCreate = () => {
  Swal.fire({
    title: 'Konfirmasi Simpan',
    text: "Apakah Anda yakin data ruangan yang dimasukkan sudah benar?",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#2563eb',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Simpan',
    cancelButtonText: 'Periksa Lagi',
    target: getSwalTarget(), // Agar muncul di atas modal
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
  createForm.post(route('admin.rooms.store'), {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      closeCreateModal()
    },
    onError: (errors) => {
      const msg = errors.name || errors.building_id || errors.capacity || 'Periksa kembali input Anda.'
      Swal.fire({ icon: 'error', title: 'Problem', text: msg, target: getSwalTarget(), heightAuto: false, customClass: { container: 'swal-z-top-force' } })
      ensureBuildingsLoaded()
    },
    onFinish: () => {
      ensureBuildingsLoaded()
    }
  })
}

// ==========================================
// LOGIKA MODAL EDIT (UPDATE)
// ==========================================
const openEditModal = (room) => {
  selectedRoom.value = room
  editForm.name = room.name ?? ''
  editForm.building_id = room.building_id ?? room.building?.id ?? ''
  editForm.capacity = room.capacity ?? ''
  editForm.is_available = Boolean(room.is_available)
  editForm.clearErrors()
  showEditModal.value = true
  ensureBuildingsLoaded()
}

const closeEditModal = () => {
  showEditModal.value = false
  selectedRoom.value = null
  editForm.reset(); editForm.clearErrors()
}

const confirmEdit = () => {
  Swal.fire({
    title: 'Konfirmasi Update',
    text: "Apakah Anda yakin ingin menyimpan perubahan pada ruangan ini?",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#eab308',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Update',
    cancelButtonText: 'Batal',
    target: getSwalTarget(), // Agar muncul di atas modal
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
  if (!selectedRoom.value) return
  editForm.put(route('admin.rooms.update', selectedRoom.value.id), {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      closeEditModal()
    },
    onError: (errors) => {
      const msg = errors.name || errors.building_id || errors.capacity || 'Periksa kembali input Anda.'
      Swal.fire({ icon: 'error', title: 'Problem', text: msg, target: getSwalTarget(), heightAuto: false, customClass: { container: 'swal-z-top-force' } })
      ensureBuildingsLoaded()
    },
    onFinish: () => {
      ensureBuildingsLoaded()
    }
  })
}

// ==========================================
// LOGIKA DELETE
// ==========================================
const confirmDelete = (id, name) => {
  Swal.fire({
    title: 'Yakin ingin menghapus?',
    text: `Data ruangan "${name}" akan dihapus permanen dan tidak dapat dikembalikan!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.rooms.destroy', id))
    }
  })
}

// ==========================================
// LOGIKA TABEL & LAINNYA
// ==========================================
const buildingOptions = computed(() => {
  const list = props.buildings ?? []
  return list.map(b => ({
    id: b.id,
    label: `${b.name} - ${b.campus?.name ?? '-'}`
  }))
})

const roomsList = computed(() => props.rooms ?? [])

const {
  sortedItems: sortedRooms,
  toggleSort: toggleRoomSort,
  sortDirection: roomSortDirection,
  ariaSortValue: roomAriaSortValue,
} = useTableSort(roomsList, {
  accessors: {
    number: (room) => room.id ?? 0,
    name: (room) => room.name ?? '',
    building: (room) => room.building?.name ?? '',
    campus: (room) => room.building?.campus?.name ?? '',
    capacity: (room) => Number(room.capacity) || 0,
    availability: (room) => (room.is_available ? 1 : 0),
  },
})

const {
  paginatedItems: paginatedRooms,
  rowsPerPage,
  currentPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(sortedRooms)

const perPageOptions = [5, 10, 25, 50]
</script>

<template>
  <Head title="Master Room" />

  <AuthenticatedLayout>
    <div class="bg-white p-6 rounded-lg shadow-md">
      <div class="flex justify-between items-center mb-4">
        <div>
          <h1 class="text-xl font-semibold text-gray-800">🚪 Master Room</h1>
          <p class="text-sm text-gray-500">Kelola ruangan berdasarkan gedung dan campus.</p>
        </div>

        <button
          type="button"
          class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700"
          @click="openCreateModal"
        >
          ➕ Tambah Ruangan
        </button>
      </div>

      <div class="overflow-x-auto">
        <div class="flex flex-col gap-3 pb-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="text-sm font-semibold text-gray-700">Daftar Ruangan</div>
          <div class="flex items-center justify-end gap-3 text-sm text-gray-600">
            <label class="font-medium text-gray-700" for="admin-rooms-rows">Rows per page</label>
            <div class="relative">
              <select
                id="admin-rooms-rows"
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
                class="px-4 py-2 text-sm font-semibold text-gray-600"
                column="number"
                label="#"
                :direction="roomSortDirection('number')"
                :aria-sort="roomAriaSortValue('number')"
                @toggle="toggleRoomSort"
              />
              <SortableTh
                class="px-4 py-2 text-sm font-semibold text-gray-600"
                column="name"
                label="Nama Ruangan"
                :direction="roomSortDirection('name')"
                :aria-sort="roomAriaSortValue('name')"
                @toggle="toggleRoomSort"
              />
              <SortableTh
                class="px-4 py-2 text-sm font-semibold text-gray-600"
                column="building"
                label="Gedung"
                :direction="roomSortDirection('building')"
                :aria-sort="roomAriaSortValue('building')"
                @toggle="toggleRoomSort"
              />
              <SortableTh
                class="px-4 py-2 text-sm font-semibold text-gray-600"
                column="campus"
                label="Campus"
                :direction="roomSortDirection('campus')"
                :aria-sort="roomAriaSortValue('campus')"
                @toggle="toggleRoomSort"
              />
              <SortableTh
                class="px-4 py-2 text-sm font-semibold text-gray-600"
                column="capacity"
                label="Kapasitas"
                :direction="roomSortDirection('capacity')"
                :aria-sort="roomAriaSortValue('capacity')"
                @toggle="toggleRoomSort"
              />
              <SortableTh
                class="px-4 py-2 text-sm font-semibold text-gray-600"
                column="availability"
                label="Ketersediaan"
                :direction="roomSortDirection('availability')"
                :aria-sort="roomAriaSortValue('availability')"
                @toggle="toggleRoomSort"
              />
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(room, index) in paginatedRooms" :key="room.id">
              <td class="px-4 py-2 text-sm text-gray-700">{{ pageMeta.from ? pageMeta.from + index : index + 1 }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ room.name }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ room.building?.name ?? '-' }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ room.building?.campus?.name ?? '-' }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ room.capacity }}</td>
              <td class="px-4 py-2 text-sm">
                <span
                  class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="room.is_available ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : 'bg-rose-100 text-rose-700 border border-rose-200'"
                >
                  {{ room.is_available ? 'Tersedia' : 'Tidak Tersedia' }}
                </span>
              </td>
              <td class="px-4 py-2 text-sm">
                <div class="flex gap-2">
                  <button
                    type="button"
                    class="px-3 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500"
                    @click="openEditModal(room)"
                  >
                    ✏️ Edit
                  </button>
                  <button
                    type="button"
                    class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700"
                    @click="confirmDelete(room.id, room.name)"
                  >
                    🗑 Hapus
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="rooms.length === 0">
              <td colspan="6" class="px-4 py-4 text-center text-gray-500">
                Belum ada data ruangan.
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
              :disabled="currentPage === 1 || !roomsList.length"
            >
              «
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1 || !roomsList.length"
            >
              ‹
            </button>
            <template v-if="roomsList.length">
              <button
                v-for="page in pages"
                :key="`rooms-page-${page}`"
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
              :disabled="currentPage === pages.length || !roomsList.length"
            >
              ›
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(pages.length)"
              :disabled="currentPage === pages.length || !roomsList.length"
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
        <h2 class="text-lg font-semibold text-gray-800 mb-4">➕ Tambah Ruangan</h2>

        <form @submit.prevent="confirmCreate" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Nama Ruangan</label>
            <input
              v-model="createForm.name"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1"
            />
            <div v-if="createForm.errors.name" class="text-red-500 text-sm">{{ createForm.errors.name }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Gedung</label>
            <select
              v-model="createForm.building_id"
              class="w-full border rounded px-3 py-2 mt-1"
              :disabled="buildingOptions.length === 0"
            >
              <option value="" disabled>
                {{ buildingOptions.length === 0 ? 'Memuat daftar gedung…' : 'Pilih gedung' }}
              </option>
              <option v-for="option in buildingOptions" :key="option.id" :value="option.id">
                {{ option.label }}
              </option>
            </select>
            <div v-if="createForm.errors.building_id" class="text-red-500 text-sm">{{ createForm.errors.building_id }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Kapasitas</label>
            <input
              v-model.number="createForm.capacity"
              type="number"
              min="1"
              class="w-full border rounded px-3 py-2 mt-1"
            />
            <div v-if="createForm.errors.capacity" class="text-red-500 text-sm">{{ createForm.errors.capacity }}</div>
          </div>
          <div>
            <span class="block text-sm font-medium text-gray-700">Ketersediaan</span>
            <label class="mt-1 inline-flex items-center gap-2 text-sm text-gray-600">
              <input
                type="checkbox"
                v-model="createForm.is_available"
                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              Ruangan dapat dipilih oleh pengguna
            </label>
            <p class="mt-1 text-xs text-gray-400">Nonaktifkan bila ruangan tidak dapat digunakan sementara.</p>
            <div v-if="createForm.errors.is_available" class="text-red-500 text-sm">{{ createForm.errors.is_available }}</div>
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

    <!-- EDIT MODAL (Diperbesar ke 3xl) -->
    <Modal :show="showEditModal" maxWidth="3xl" @close="closeEditModal">
      <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">✏️ Edit Ruangan</h2>

        <form @submit.prevent="confirmEdit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Nama Ruangan</label>
            <input
              v-model="editForm.name"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1"
            />
            <div v-if="editForm.errors.name" class="text-red-500 text-sm">{{ editForm.errors.name }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Gedung</label>
            <select
              v-model="editForm.building_id"
              class="w-full border rounded px-3 py-2 mt-1"
              :disabled="buildingOptions.length === 0"
            >
              <option value="" disabled>
                {{ buildingOptions.length === 0 ? 'Memuat daftar gedung…' : 'Pilih gedung' }}
              </option>
              <option v-for="option in buildingOptions" :key="option.id" :value="option.id">
                {{ option.label }}
              </option>
            </select>
            <div v-if="editForm.errors.building_id" class="text-red-500 text-sm">{{ editForm.errors.building_id }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Kapasitas</label>
            <input
              v-model.number="editForm.capacity"
              type="number"
              min="1"
              class="w-full border rounded px-3 py-2 mt-1"
            />
            <div v-if="editForm.errors.capacity" class="text-red-500 text-sm">{{ editForm.errors.capacity }}</div>
          </div>
          <div>
            <span class="block text-sm font-medium text-gray-700">Ketersediaan</span>
            <label class="mt-1 inline-flex items-center gap-2 text-sm text-gray-600">
              <input
                type="checkbox"
                v-model="editForm.is_available"
                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              Ruangan dapat dipilih oleh pengguna
            </label>
            <p class="mt-1 text-xs text-gray-400">Matikan ketika ruangan dinonaktifkan atau sedang perawatan.</p>
            <div v-if="editForm.errors.is_available" class="text-red-500 text-sm">{{ editForm.errors.is_available }}</div>
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