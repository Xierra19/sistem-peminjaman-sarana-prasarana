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

const getSwalTarget = () => document.querySelector('dialog[open]') || document.body

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
const confirmDelete = (id, name) => {
  Swal.fire({
    title: 'Yakin ingin menghapus?',
    text: `Data barang "${name}" akan dihapus permanen!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.items.destroy', id))
    }
  })
}

// ==========================================
// LOGIKA TABEL & PAGINASI
// ==========================================
const itemsList = computed(() => props.items ?? [])

const {
  sortedItems,
  toggleSort,
  sortDirection,
  ariaSortValue,
} = useTableSort(itemsList, {
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
</script>

<template>
  <Head title="Master Barang" />

  <AuthenticatedLayout>
    <div class="bg-white p-6 rounded-lg shadow-md">
      <div class="flex justify-between items-center mb-4">
        <h1 class="text-xl font-semibold text-gray-800">📦 Master Barang</h1>

        <button
          type="button"
          class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700"
          @click="openCreateModal"
        >
          ➕ Tambah Barang
        </button>
      </div>

      <div class="overflow-x-auto">
        <div class="flex flex-col gap-3 pb-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="text-sm font-semibold text-gray-700">Daftar Barang</div>
          <div class="flex items-center justify-end gap-3 text-sm text-gray-600">
            <label class="font-medium text-gray-700" for="admin-items-rows">Rows per page</label>
            <div class="relative">
              <select
                id="admin-items-rows"
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
                :direction="sortDirection('number')"
                :aria-sort="ariaSortValue('number')"
                @toggle="toggleSort"
              />
              <SortableTh
                class="px-4 py-2 text-left text-sm font-semibold text-gray-600"
                column="code"
                label="Kode"
                :direction="sortDirection('code')"
                :aria-sort="ariaSortValue('code')"
                @toggle="toggleSort"
              />
              <SortableTh
                class="px-4 py-2 text-left text-sm font-semibold text-gray-600"
                column="name"
                label="Nama Barang"
                :direction="sortDirection('name')"
                :aria-sort="ariaSortValue('name')"
                @toggle="toggleSort"
              />
              <SortableTh
                class="px-4 py-2 text-left text-sm font-semibold text-gray-600"
                column="category"
                label="Kategori"
                :direction="sortDirection('category')"
                :aria-sort="ariaSortValue('category')"
                @toggle="toggleSort"
              />
              <SortableTh
                class="px-4 py-2 text-left text-sm font-semibold text-gray-600"
                column="quantity"
                label="Jumlah"
                :direction="sortDirection('quantity')"
                :aria-sort="ariaSortValue('quantity')"
                @toggle="toggleSort"
              />
              <SortableTh
                class="px-4 py-2 text-left text-sm font-semibold text-gray-600"
                column="is_available"
                label="Ketersediaan"
                :direction="sortDirection('is_available')"
                :aria-sort="ariaSortValue('is_available')"
                @toggle="toggleSort"
              />
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(item, index) in paginatedItems" :key="item.id">
              <td class="px-4 py-2 text-sm text-gray-700">{{ pageMeta.from ? pageMeta.from + index : index + 1 }}</td>
              <td class="px-4 py-2 text-sm text-gray-700 font-mono">{{ item.code }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ item.name }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ item.category }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ item.quantity }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">
                <span :class="item.is_available ? 'text-green-600' : 'text-red-600'">
                  {{ item.is_available ? 'Tersedia' : 'Tidak Tersedia' }}
                </span>
              </td>
              <td class="px-4 py-2 text-sm">
                <div class="flex gap-2">
                  <button
                    type="button"
                    class="px-3 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500"
                    @click="openEditModal(item)"
                  >
                    ✏️ Edit
                  </button>
                  <button
                    type="button"
                    class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700"
                    @click="confirmDelete(item.id, item.name)"
                  >
                    🗑 Hapus
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!itemsList.length">
              <td colspan="7" class="px-4 py-4 text-center text-gray-500">
                Tidak ada data barang.
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
              :disabled="currentPage === 1 || !itemsList.length"
            >
              «
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1 || !itemsList.length"
            >
              ‹
            </button>
            <template v-if="itemsList.length">
              <button
                v-for="page in pages"
                :key="`item-page-${page}`"
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
              :disabled="currentPage === pages.length || !itemsList.length"
            >
              ›
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(pages.length)"
              :disabled="currentPage === pages.length || !itemsList.length"
            >
              »
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- MODAL CREATE (Diperbesar ke 3xl) -->
    <Modal :show="showCreateModal" maxWidth="3xl" @close="closeCreateModal">
      <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">➕ Tambah Barang</h2>

        <form @submit.prevent="confirmCreate" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Kode Barang</label>
            <input
              v-model="createForm.code"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1"
            />
            <div v-if="createForm.errors.code" class="text-red-500 text-sm">{{ createForm.errors.code }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Nama Barang</label>
            <input
              v-model="createForm.name"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1"
            />
            <div v-if="createForm.errors.name" class="text-red-500 text-sm">{{ createForm.errors.name }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Kategori</label>
            <input
              v-model="createForm.category"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1"
            />
            <div v-if="createForm.errors.category" class="text-red-500 text-sm">{{ createForm.errors.category }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Jumlah</label>
            <input
              v-model.number="createForm.quantity"
              type="number"
              min="0"
              class="w-full border rounded px-3 py-2 mt-1"
            />
            <div v-if="createForm.errors.quantity" class="text-red-500 text-sm">{{ createForm.errors.quantity }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Ketersediaan</label>
            <select v-model="createForm.is_available" class="w-full border rounded px-3 py-2 mt-1">
                <option :value="true">Tersedia</option>
                <option :value="false">Tidak Tersedia</option>
            </select>
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

    <!-- MODAL EDIT (Diperbesar ke 3xl) -->
    <Modal :show="showEditModal" maxWidth="3xl" @close="closeEditModal">
      <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">✏️ Edit Barang</h2>

        <form @submit.prevent="confirmEdit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Kode Barang</label>
            <input
              v-model="editForm.code"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1"
            />
            <div v-if="editForm.errors.code" class="text-red-500 text-sm">{{ editForm.errors.code }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Nama Barang</label>
            <input
              v-model="editForm.name"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1"
            />
            <div v-if="editForm.errors.name" class="text-red-500 text-sm">{{ editForm.errors.name }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Kategori</label>
            <input
              v-model="editForm.category"
              type="text"
              class="w-full border rounded px-3 py-2 mt-1"
            />
            <div v-if="editForm.errors.category" class="text-red-500 text-sm">{{ editForm.errors.category }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Jumlah</label>
            <input
              v-model.number="editForm.quantity"
              type="number"
              min="0"
              class="w-full border rounded px-3 py-2 mt-1"
            />
            <div v-if="editForm.errors.quantity" class="text-red-500 text-sm">{{ editForm.errors.quantity }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Ketersediaan</label>
            <select v-model="editForm.is_available" class="w-full border rounded px-3 py-2 mt-1">
                <option :value="true">Tersedia</option>
                <option :value="false">Tidak Tersedia</option>
            </select>
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
/* Memaksa pop-up SweetAlert tampil di urutan paling depan */
div.swal-z-top-force {
  z-index: 999999 !important;
}
</style>