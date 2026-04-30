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
const confirmDelete = (id, name) => {
  Swal.fire({
    title: 'Yakin ingin menghapus?',
    text: `Data campus "${name}" akan dihapus permanen dan tidak dapat dikembalikan!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626', // Warna merah untuk tombol hapus
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
    // Tidak butuh target khusus karena pop up ini dipanggil saat tidak ada modal yang terbuka
  }).then((result) => {
    if (result.isConfirmed) {
      // Mengirim request DELETE menggunakan router Inertia
      router.delete(route('admin.campus.destroy', id))
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
</script>

<template>
  <Head title="Master Campus" />

  <AuthenticatedLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Master Campus</h1>
          <p class="text-sm text-gray-500">Kelola data kampus yang tersedia dalam sistem.</p>
        </div>

        <button
          type="button"
          class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
          @click="openCreateModal"
        >
          + Tambah Campus
        </button>
      </div>

      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 px-5 py-4 md:flex-row md:items-end md:justify-between">
          <div class="flex flex-1 flex-col gap-3 md:flex-row md:items-center">
            <div class="w-full md:max-w-sm">
              <label class="mb-1 block text-sm font-medium text-gray-700" for="admin-campus-search">Pencarian</label>
              <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z" />
                  </svg>
                </span>
                <input
                  id="admin-campus-search"
                  v-model="searchQuery"
                  type="text"
                  placeholder="Cari nama campus, alamat, telepon..."
                  class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-700 placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
            </div>
          </div>
          <div class="flex items-center justify-end gap-3 text-sm text-gray-600">
            <label class="font-medium text-gray-700" for="admin-campus-rows">Rows per page</label>
              <div class="relative">
                <select>
                  id="admin-campus-rows"
                  v-model.number="rowsPerPage"
                  class="w-20 rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                  <option v-for="option in perPageOptions" :key="option" :value="option">
                    {{ option }}
                  </option>
                </select>
              </div>
          </div>
        </div>
        
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-100">
            <tr>
              <SortableTh
                class="px-4 py-2 text-left text-sm font-semibold text-gray-600"
                column="number"
                label="#"
                :direction="campusSortDirection('number')"
                :aria-sort="campusAriaSortValue('number')"
                @toggle="toggleCampusSort"
              />
              <SortableTh
                class="px-4 py-2 text-left text-sm font-semibold text-gray-600"
                column="name"
                label="Nama Campus"
                :direction="campusSortDirection('name')"
                :aria-sort="campusAriaSortValue('name')"
                @toggle="toggleCampusSort"
              />
              <SortableTh
                class="px-4 py-2 text-left text-sm font-semibold text-gray-600"
                column="address"
                label="Alamat"
                :direction="campusSortDirection('address')"
                :aria-sort="campusAriaSortValue('address')"
                @toggle="toggleCampusSort"
              />
              <SortableTh
                class="px-4 py-2 text-left text-sm font-semibold text-gray-600"
                column="phone"
                label="Telepon"
                :direction="campusSortDirection('phone')"
                :aria-sort="campusAriaSortValue('phone')"
                @toggle="toggleCampusSort"
              />
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(campus, index) in paginatedCampuses" :key="campus.id">
              <td class="px-4 py-2 text-sm text-gray-700">{{ pageMeta.from ? pageMeta.from + index : index + 1 }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ campus.name }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ campus.address }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ campus.phone }}</td>
              <td class="px-4 py-2 text-sm">

                <!-- Tombol Edit -->
                <button
                  type="button"
                  class="px-3 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500 mr-2"
                  @click="openEditModal(campus)"
                >
                  ✏️ Edit
                </button>

                <!-- Tombol Delete yang sudah diperbarui memanggil confirmDelete -->
                <button
                  type="button"
                  class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700"
                  @click="confirmDelete(campus.id, campus.name)"
                >
                  🗑 Hapus
                </button>

              </td>
            </tr>
            <tr v-if="!filteredCampuses.length">
              <td colspan="5" class="px-4 py-4 text-center text-gray-500">
                Tidak ada data campus.
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
              :disabled="currentPage === 1 || !filteredCampuses.length"
            >
              «
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1 || !filteredCampuses.length"
            >
              ‹
            </button>
            <template v-if="filteredCampuses.length">
              <button
                v-for="page in pages"
                :key="`campus-page-${page}`"
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
              :disabled="currentPage === pages.length || !filteredCampuses.length"
            >
              ›
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(pages.length)"
              :disabled="currentPage === pages.length || !filteredCampuses.length"
            >
              »
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- MODAL CREATE -->
    <Modal :show="showCreateModal" maxWidth="3xl" @close="closeCreateModal">
      <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">➕ Tambah Campus</h2>

        <form @submit.prevent="confirmCreate" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Nama Campus</label>
            <input
              v-model="createForm.name"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1"
            />
            <div v-if="createForm.errors.name" class="text-red-500 text-sm">{{ createForm.errors.name }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Alamat</label>
            <input
              v-model="createForm.address"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1"
            />
            <div v-if="createForm.errors.address" class="text-red-500 text-sm">{{ createForm.errors.address }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Telepon</label>
            <input
              v-model="createForm.phone"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1"
            />
            <div v-if="createForm.errors.phone" class="text-red-500 text-sm">{{ createForm.errors.phone }}</div>
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
        <h2 class="text-lg font-semibold text-gray-800 mb-4">✏️ Edit Campus</h2>

        <form @submit.prevent="confirmEdit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Nama Campus</label>
            <input
              v-model="editForm.name"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1"
            />
            <div v-if="editForm.errors.name" class="text-red-500 text-sm">{{ editForm.errors.name }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Alamat</label>
            <input
              v-model="editForm.address"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1"
            />
            <div v-if="editForm.errors.address" class="text-red-500 text-sm">{{ editForm.errors.address }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Telepon</label>
            <input
              v-model="editForm.phone"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1"
            />
            <div v-if="editForm.errors.phone" class="text-red-500 text-sm">{{ editForm.errors.phone }}</div>
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