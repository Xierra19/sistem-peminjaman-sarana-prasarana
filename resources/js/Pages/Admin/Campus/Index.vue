<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Modal from '@/Components/Modal.vue'
import { usePagination } from '@/Composables/usePagination'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
  campuses: Array
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

const submitCreate = () => {
  createForm.post(route('admin.campus.store'), {
    onSuccess: () => {
      closeCreateModal()
    }
  })
}

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

const submitEdit = () => {
  if (!selectedCampus.value) return

  editForm.put(route('admin.campus.update', selectedCampus.value.id), {
    onSuccess: () => {
      closeEditModal()
    }
  })
}

const campusesList = computed(() => props.campuses ?? [])

const {
  paginatedItems: paginatedCampuses,
  rowsPerPage,
  currentPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(campusesList)

const perPageOptions = [5, 10, 25, 50]
</script>

<template>
  <Head title="Master Campus" />

  <AuthenticatedLayout>
    <div class="bg-white p-6 rounded-lg shadow-md">
      <div class="flex justify-between items-center mb-4">
        <h1 class="text-xl font-semibold text-gray-800">🏫 Master Campus</h1>

        <button
          type="button"
          class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700"
          @click="openCreateModal"
        >
          ➕ Tambah Campus
        </button>
      </div>

      <div class="overflow-x-auto">
        <div class="flex flex-col gap-3 pb-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="text-sm font-semibold text-gray-700">Daftar Campus</div>
          <div class="flex items-center justify-end gap-3 text-sm text-gray-600">
            <label class="font-medium text-gray-700" for="admin-campus-rows">Rows per page</label>
            <div class="relative">
              <select
                id="admin-campus-rows"
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
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">#</th>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Nama Campus</th>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Alamat</th>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Telepon</th>
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

                <button
                  type="button"
                  class="px-3 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500 mr-2"
                  @click="openEditModal(campus)"
                >
                  ✏️ Edit
                </button>
                <Link
                  :href="route('admin.campus.destroy', campus.id)"
                  method="delete"
                  as="button"
                  class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700"
                >
                  🗑 Hapus
                </Link>
              </td>
            </tr>
            <tr v-if="!campusesList.length">
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
              :disabled="currentPage === 1 || !campusesList.length"
            >
              «
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1 || !campusesList.length"
            >
              ‹
            </button>
            <template v-if="campusesList.length">
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
              :disabled="currentPage === pages.length || !campusesList.length"
            >
              ›
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(pages.length)"
              :disabled="currentPage === pages.length || !campusesList.length"
            >
              »
            </button>
          </div>
        </div>
      </div>
    </div>
    <Modal :show="showCreateModal" max-width="lg" @close="closeCreateModal">
      <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">➕ Tambah Campus</h2>

        <form @submit.prevent="submitCreate" class="space-y-4">
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

    <Modal :show="showEditModal" max-width="lg" @close="closeEditModal">
      <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">✏️ Edit Campus</h2>

        <form @submit.prevent="submitEdit" class="space-y-4">
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
