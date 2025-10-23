<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Modal from '@/Components/Modal.vue'
import { usePagination } from '@/Composables/usePagination'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Swal from 'sweetalert2'                           // ⬅️ panggil Swal di halaman

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

const submitCreate = () => {
  createForm.post(route('admin.rooms.store'), {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      closeCreateModal()
      // popup sukses bisa via flash global dari layout; kalau backend belum set flash, bisa aktifkan baris di bawah:
      // Swal.fire({ icon:'success', title:'Berhasil', timer:1800, showConfirmButton:false, target:getSwalTarget(), heightAuto:false })
    },
    onError: (errors) => {
      // tampilkan pesan validasi pertama
      const msg = errors.name || errors.building_id || errors.capacity || 'Periksa kembali input Anda.'
      Swal.fire({ icon: 'error', title: 'Problem', text: msg, target: getSwalTarget(), heightAuto: false, zIndex: 2147483647 })
      ensureBuildingsLoaded()
    },
    onFinish: () => {
      ensureBuildingsLoaded()
    }
  })
}

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

const submitEdit = () => {
  if (!selectedRoom.value) return
  editForm.put(route('admin.rooms.update', selectedRoom.value.id), {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      closeEditModal()
      // Optional success swal seperti di atas
    },
    onError: (errors) => {
      const msg = errors.name || errors.building_id || errors.capacity || 'Periksa kembali input Anda.'
      Swal.fire({ icon: 'error', title: 'Problem', text: msg, target: getSwalTarget(), heightAuto: false, zIndex: 2147483647 })
      ensureBuildingsLoaded()
    },
    onFinish: () => {
      ensureBuildingsLoaded()
    }
  })
}

// aman kalau buildings belum ada
const buildingOptions = computed(() => {
  const list = props.buildings ?? []
  return list.map(b => ({
    id: b.id,
    label: `${b.name} - ${b.campus?.name ?? '-'}`
  }))
})

const roomsList = computed(() => props.rooms ?? [])

const {
  paginatedItems: paginatedRooms,
  rowsPerPage,
  currentPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(roomsList)

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
            <span>Rows per page</span>
            <div class="inline-flex items-center gap-1 rounded border border-gray-300 bg-white px-2 py-1">
              <select
                v-model.number="rowsPerPage"
                class="border-none bg-transparent pr-4 text-sm text-gray-700 focus:outline-none focus:ring-0"
              >
                <option v-for="option in perPageOptions" :key="option" :value="option">
                  {{ option }}
                </option>
              </select>
              <svg class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                <path
                  fill-rule="evenodd"
                  d="M5.23 7.21a.75.75 0 011.06.02L10 10.939l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"
                  clip-rule="evenodd"
                />
              </svg>
            </div>
          </div>
        </div>
        
        <table class="min-w-full border border-gray-200 divide-y divide-gray-200">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">#</th>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Nama Ruangan</th>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Gedung</th>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Campus</th>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Kapasitas</th>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Ketersediaan</th>              
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
                  <Link
                    :href="route('admin.rooms.destroy', room.id)"
                    method="delete"
                    as="button"
                    class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700"
                  >
                    🗑 Hapus
                  </Link>
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

    <!-- CREATE MODAL -->
    <Modal :show="showCreateModal" max-width="lg" @close="closeCreateModal">
      <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">➕ Tambah Ruangan</h2>

        <form @submit.prevent="submitCreate" class="space-y-4">
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

    <!-- EDIT MODAL -->
    <Modal :show="showEditModal" max-width="lg" @close="closeEditModal">
      <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">✏️ Edit Ruangan</h2>

        <form @submit.prevent="submitEdit" class="space-y-4">
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
