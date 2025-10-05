<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'

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

const submitCreate = () => {
  createForm.post(route('admin.buildings.store'), {
   preserveState: true,
   preserveScroll: true,
    onSuccess: () => {
      closeCreateModal()
    },
   onError: () => {
     // setelah validasi gagal, lazy prop campuses biasanya kosong lagi
     ensureCampusesLoaded()
   },
   onFinish: () => {
     // jaga-jaga: kalau masih kosong, muat ulang
     ensureCampusesLoaded()
   }
  })
}

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
        <table class="min-w-full border border-gray-200 divide-y divide-gray-200">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">#</th>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Nama Gedung</th>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Campus</th>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Dibuat</th>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(building, index) in props.buildings" :key="building.id">
              <td class="px-4 py-2 text-sm text-gray-700">{{ index + 1 }}</td>
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
                  <Link
                    :href="route('admin.buildings.destroy', building.id)"
                    method="delete"
                    as="button"
                    class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700"
                  >
                    🗑 Hapus
                  </Link>
                </div>
              </td>
            </tr>
            <tr v-if="props.buildings.length === 0">
              <td colspan="5" class="px-4 py-4 text-center text-gray-500">
                Belum ada data gedung.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- CREATE MODAL -->
    <Modal :show="showCreateModal" max-width="lg" @close="closeCreateModal">
      <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">➕ Tambah Gedung</h2>

        <form @submit.prevent="submitCreate" class="space-y-4">
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

    <!-- EDIT MODAL -->
    <Modal :show="showEditModal" max-width="lg" @close="closeEditModal">
      <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">✏️ Edit Gedung</h2>

        <form @submit.prevent="submitEdit" class="space-y-4">
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
