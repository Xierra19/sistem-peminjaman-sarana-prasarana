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

const searchQuery = ref('')

const filteredRooms = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return props.rooms ?? []
  
  return (props.rooms ?? []).filter(room => {
    const searchable = [
      room.name,
      room.building?.name,
      room.building?.campus?.name,
    ].filter(Boolean).join(' ').toLowerCase()
    
    return searchable.includes(q)
  })
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

const {
  sortedItems: sortedRooms,
  toggleSort: toggleRoomSort,
  sortDirection: roomSortDirection,
  ariaSortValue: roomAriaSortValue,
} = useTableSort(filteredRooms, {
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
const canGoToPreviousPage = computed(() => currentPage.value > 1 && filteredRooms.value.length > 0)
const canGoToNextPage = computed(() => currentPage.value < pages.value.length && filteredRooms.value.length > 0)
</script>

<template>
  <Head title="Master Room" />

  <AuthenticatedLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">Master Room</h1>
          <p class="text-sm text-gray-500 dark:text-gray-400">Kelola ruangan berdasarkan gedung dan kampus.</p>
        </div>

        <button
          type="button"
          class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
          @click="openCreateModal"
        >
          + Tambah Ruangan
        </button>
      </div>

      <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex flex-col gap-4 px-5 py-4 md:flex-row md:items-end md:justify-between">
          <div class="flex flex-1 flex-col gap-3 md:flex-row md:items-center">
            <div class="w-full md:max-w-sm">
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" for="admin-rooms-search">Pencarian</label>
              <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z" />
                  </svg>
                </span>
                <input
                  id="admin-rooms-search"
                  v-model="searchQuery"
                  type="text"
                  placeholder="Cari nama ruangan, gedung, atau kampus..."
                  class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-700 placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
                />
              </div>
            </div>
          </div>
          <div class="flex flex-col gap-2 text-sm text-gray-600 dark:text-gray-400 sm:flex-row sm:items-center">
            <label class="font-medium text-gray-700 dark:text-gray-300" for="admin-rooms-rows">Rows per page</label>
              <div class="relative">
                <select
                  id="admin-rooms-rows"
                  v-model.number="rowsPerPage"
                  class="w-full rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 sm:w-20"
                >
                  <option v-for="option in perPageOptions" :key="`rooms-rows-${option}`" :value="option">
                    {{ option }}
                  </option>
                </select>
              </div>
          </div>
        </div>

        <table class="master-mobile-table mobile-friendly-table min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
          <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-slate-700 dark:text-slate-400">
            <tr>
              <SortableTh class="px-4 py-2 text-left" column="number" label="#" :direction="roomSortDirection('number')" :aria-sort="roomAriaSortValue('number')" @toggle="toggleRoomSort" />
              <SortableTh class="px-4 py-2 text-left" column="name" label="Nama Ruangan" :direction="roomSortDirection('name')" :aria-sort="roomAriaSortValue('name')" @toggle="toggleRoomSort" />
              <SortableTh class="px-4 py-2 text-left" column="building" label="Gedung" :direction="roomSortDirection('building')" :aria-sort="roomAriaSortValue('building')" @toggle="toggleRoomSort" />
              <SortableTh class="px-4 py-2 text-left" column="campus" label="Campus" :direction="roomSortDirection('campus')" :aria-sort="roomAriaSortValue('campus')" @toggle="toggleRoomSort" />
              <SortableTh class="px-4 py-2 text-left" column="capacity" label="Kapasitas" :direction="roomSortDirection('capacity')" :aria-sort="roomAriaSortValue('capacity')" @toggle="toggleRoomSort" />
              <SortableTh class="px-4 py-2 text-left" column="availability" label="Ketersediaan" :direction="roomSortDirection('availability')" :aria-sort="roomAriaSortValue('availability')" @toggle="toggleRoomSort" />
              <th class="px-4 py-2 text-left">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-slate-700 dark:text-slate-300">
            <tr v-for="(room, index) in paginatedRooms" :key="room.id" class="transition hover:bg-gray-50 dark:hover:bg-slate-700/50">
              <td class="mobile-id-cell px-4 py-2 text-sm text-gray-500 dark:text-gray-400" data-title="#">{{ pageMeta.from ? pageMeta.from + index : index + 1 }}</td>
              <td class="mobile-primary-cell mobile-span-2 px-4 py-2" data-title="Nama Ruangan">
                <div class="mobile-primary-label">Nama Ruangan</div>
                <div class="mobile-primary-title">{{ room.name }}</div>
              </td>
              <td class="px-4 py-2 text-sm mobile-meta-cell mobile-compact-meta" data-title="Gedung">{{ room.building?.name ?? '-' }}</td>
              <td class="px-4 py-2 text-sm mobile-meta-cell mobile-compact-meta" data-title="Campus">{{ room.building?.campus?.name ?? '-' }}</td>
              <td class="px-4 py-2 text-sm" data-title="Kapasitas">{{ room.capacity }}</td>
              <td class="mobile-status-cell px-4 py-2 text-sm" data-title="Ketersediaan">
                <span
                  class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="room.is_available 
                    ? 'bg-emerald-100 text-emerald-700 border border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800' 
                    : 'bg-rose-100 text-rose-700 border border-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:border-rose-800'"
                >
                  {{ room.is_available ? 'Tersedia' : 'Tidak Tersedia' }}
                </span>
              </td>
              <td class="mobile-action-cell px-4 py-2 text-sm" data-title="Aksi">
                <div class="flex flex-col gap-2 sm:flex-row">
                  <button
                    type="button"
                    class="rounded-lg bg-yellow-400 px-3 py-2 text-sm font-semibold text-white hover:bg-yellow-500"
                    @click="openEditModal(room)"
                  >
                    Edit
                  </button>
                  <button
                    type="button"
                    class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700"
                    @click="confirmDelete(room.id, room.name)"
                  >
                    Hapus
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="filteredRooms.length === 0">
              <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-400 dark:text-slate-500">
                Tidak ada data ruangan.
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
            <button type="button" class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(1)" :disabled="currentPage === 1 || !filteredRooms.length">«</button>
            <button type="button" class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(currentPage - 1)" :disabled="currentPage === 1 || !filteredRooms.length">‹</button>
            <template v-if="filteredRooms.length">
              <button
                v-for="page in pages"
                :key="`room-page-${page}`"
                type="button"
                class="rounded border px-2 py-1 text-sm transition"
                :class="currentPage === page 
                  ? 'border-blue-500 bg-blue-500 text-white' 
                  : 'border-gray-300 text-gray-600 hover:border-blue-400 hover:text-blue-600 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400'"
                :disabled="typeof page !== 'number'"
                @click="changePage(page)"
              >
                {{ page }}
              </button>
            </template>
            <button type="button" class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(currentPage + 1)" :disabled="currentPage === pages.length || !filteredRooms.length">›</button>
            <button type="button" class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400" @click="changePage(pages.length)" :disabled="currentPage === pages.length || !filteredRooms.length">»</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- CREATE MODAL -->
    <Modal :show="showCreateModal" maxWidth="3xl" @close="closeCreateModal">
      <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">➕ Tambah Ruangan</h2>

        <form @submit.prevent="confirmCreate" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Ruangan</label>
            <input
              v-model="createForm.name"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="createForm.errors.name" class="text-red-500 text-sm mt-1">{{ createForm.errors.name }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gedung</label>
            <select
              v-model="createForm.building_id"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
              :disabled="buildingOptions.length === 0"
            >
              <option value="" disabled>
                {{ buildingOptions.length === 0 ? 'Memuat daftar gedung…' : 'Pilih gedung' }}
              </option>
              <option v-for="option in buildingOptions" :key="option.id" :value="option.id">
                {{ option.label }}
              </option>
            </select>
            <div v-if="createForm.errors.building_id" class="text-red-500 text-sm mt-1">{{ createForm.errors.building_id }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Kapasitas</label>
            <input
              v-model.number="createForm.capacity"
              type="number"
              min="1"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="createForm.errors.capacity" class="text-red-500 text-sm mt-1">{{ createForm.errors.capacity }}</div>
          </div>

          <div>
            <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ketersediaan</span>
            <label class="mt-1 inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
              <input
                type="checkbox"
                v-model="createForm.is_available"
                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700"
              />
              Ruangan dapat dipilih oleh pengguna
            </label>
            <p class="mt-1 text-xs text-gray-400 dark:text-slate-500">Nonaktifkan bila ruangan tidak dapat digunakan sementara.</p>
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

    <!-- EDIT MODAL -->
    <Modal :show="showEditModal" maxWidth="3xl" @close="closeEditModal">
      <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">✏️ Edit Ruangan</h2>

        <form @submit.prevent="confirmEdit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Ruangan</label>
            <input
              v-model="editForm.name"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="editForm.errors.name" class="text-red-500 text-sm mt-1">{{ editForm.errors.name }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gedung</label>
            <select
              v-model="editForm.building_id"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
              :disabled="buildingOptions.length === 0"
            >
              <option value="" disabled>
                {{ buildingOptions.length === 0 ? 'Memuat daftar gedung…' : 'Pilih gedung' }}
              </option>
              <option v-for="option in buildingOptions" :key="option.id" :value="option.id">
                {{ option.label }}
              </option>
            </select>
            <div v-if="editForm.errors.building_id" class="text-red-500 text-sm mt-1">{{ editForm.errors.building_id }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Kapasitas</label>
            <input
              v-model.number="editForm.capacity"
              type="number"
              min="1"
              class="w-full border rounded px-3 py-2 mt-1 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
            />
            <div v-if="editForm.errors.capacity" class="text-red-500 text-sm mt-1">{{ editForm.errors.capacity }}</div>
          </div>

          <div>
            <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ketersediaan</span>
            <label class="mt-1 inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
              <input
                type="checkbox"
                v-model="editForm.is_available"
                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700"
              />
              Ruangan dapat dipilih oleh pengguna
            </label>
            <p class="mt-1 text-xs text-gray-400 dark:text-slate-500">Matikan ketika ruangan dinonaktifkan atau sedang perawatan.</p>
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
