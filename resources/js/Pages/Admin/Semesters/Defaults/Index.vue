<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Modal from '@/Components/Modal.vue'
import { usePagination } from '@/Composables/usePagination'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  semester: { type: Object, required: true },
  defaults: { type: Array, default: () => [] },
  rooms: { type: Array, default: () => [] },
})

const days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']

const showCreateModal = ref(false)
const showEditModal = ref(false)
const selectedDefault = ref(null)
const roomsLoading = ref(false)

const baseFormState = () => ({
  course_name: '',
  course_code: '',
  day_of_week: '',
  theory_start_time: '',
  theory_end_time: '',
  theory_room_id: '',
  practicum1_start_time: '',
  practicum1_end_time: '',
  practicum1_room_id: '',
  practicum2_start_time: '',
  practicum2_end_time: '',
  practicum2_room_id: '',
})

const createForm = useForm(baseFormState())
const editForm = useForm(baseFormState())

const defaultsList = computed(() => props.defaults ?? [])
const hasRooms = computed(() => Array.isArray(props.rooms) && props.rooms.length > 0)
const roomOptions = computed(() => {
  const list = hasRooms.value ? props.rooms : []
  return list.map((room) => ({ id: room.id, name: room.name }))
})

watch(
  () => props.rooms,
  (value) => {
    if (Array.isArray(value) && value.length) {
      roomsLoading.value = false
    }
  }
)

const {
  paginatedItems: paginatedDefaults,
  rowsPerPage,
  currentPage,
  totalPages,
  pageMeta,
  pages,
  changePage,
} = usePagination(defaultsList)

const perPageOptions = [5, 10, 25, 50]

const formatTime = (value) =>
  value ? new Date(`1970-01-01T${value}`).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : ''
const timeRange = (start, end) => (start && end ? `${formatTime(start)} - ${formatTime(end)}` : '-')
const roomName = (row, key) => {
  const rel = row?.[key]
  return rel && rel.name ? rel.name : 'Tanpa ruang'
}

const ensureRoomsLoaded = () => {
  if (roomsLoading.value || hasRooms.value) {
    return
  }

  roomsLoading.value = true
  router.reload({
    only: ['rooms'],
    preserveScroll: true,
    onFinish: () => {
      roomsLoading.value = false
    },
  })
}

const openCreateModal = () => {
  createForm.reset()
  createForm.clearErrors()
  showCreateModal.value = true
  ensureRoomsLoaded()
}

const closeCreateModal = () => {
  showCreateModal.value = false
  createForm.reset()
  createForm.clearErrors()
}

const submitCreate = () => {
  createForm.post(route('admin.semesters.defaults.store', [props.semester.id]), {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      closeCreateModal()
    },
    onError: ensureRoomsLoaded,
    onFinish: ensureRoomsLoaded,
  })
}

const openEditModal = (row) => {
  selectedDefault.value = row
  editForm.course_name = row.course_name ?? ''
  editForm.course_code = row.course_code ?? ''
  editForm.day_of_week = row.day_of_week ?? ''
  editForm.theory_start_time = row.theory_start_time ?? ''
  editForm.theory_end_time = row.theory_end_time ?? ''
  editForm.theory_room_id = row.theory_room?.id ?? ''
  editForm.practicum1_start_time = row.practicum1_start_time ?? ''
  editForm.practicum1_end_time = row.practicum1_end_time ?? ''
  editForm.practicum1_room_id = row.practicum1_room?.id ?? ''
  editForm.practicum2_start_time = row.practicum2_start_time ?? ''
  editForm.practicum2_end_time = row.practicum2_end_time ?? ''
  editForm.practicum2_room_id = row.practicum2_room?.id ?? ''
  editForm.clearErrors()
  showEditModal.value = true
  ensureRoomsLoaded()
}

const closeEditModal = () => {
  showEditModal.value = false
  selectedDefault.value = null
  editForm.reset()
  editForm.clearErrors()
}

const submitEdit = () => {
  if (!selectedDefault.value) return

  editForm.put(route('admin.semesters.defaults.update', [props.semester.id, selectedDefault.value.id]), {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      closeEditModal()
    },
    onError: ensureRoomsLoaded,
    onFinish: ensureRoomsLoaded,
  })
}

const destroyDefault = (row) => {
  if (confirm('Hapus jadwal ini?')) {
    router.delete(route('admin.semesters.defaults.destroy', [props.semester.id, row.id]), {
      preserveScroll: true,
    })
  }
}
</script>

<template>
  <AuthenticatedLayout>
    <Head :title="`Default Jadwal - ${props.semester.year} ${props.semester.term}`" />

    <div class="space-y-6 py-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-800">Default Jadwal</h1>
        <p class="text-sm text-gray-600">Semester {{ props.semester.term }} {{ props.semester.year }}</p>
      </div>

      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 md:flex-row md:items-center md:justify-between">
          <div class="text-sm font-semibold text-gray-700">Daftar Default Jadwal</div>
          <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-end">
            <div class="flex items-center gap-3 text-sm text-gray-600">
              <span>Rows per page</span>
              <div class="inline-flex items-center gap-1 rounded border border-gray-300 bg-white px-2 py-1">
                <select
                  v-model.number="rowsPerPage"
                  class="border-none bg-transparent pr-4 text-sm text-gray-700 focus:outline-none focus:ring-0"
                >
                  <option v-for="option in perPageOptions" :key="`defaults-rows-${option}`" :value="option">
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
            <div class="flex flex-wrap items-center justify-end gap-2">
              <Link
                :href="route('admin.semesters.defaults.import.form', [props.semester.id])"
                class="inline-flex items-center rounded-md border border-blue-200 px-3 py-2 text-sm font-medium text-blue-600 transition hover:bg-blue-50"
              >
                Import CSV
              </Link>
              <button
                type="button"
                class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                @click="openCreateModal"
              >
                + Tambah Jadwal
              </button>
            </div>
          </div>
        </div>

        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
            <tr>
              <th class="px-5 py-3 text-left">Nama Matkul</th>
              <th class="px-5 py-3 text-left">Kode</th>
              <th class="px-5 py-3 text-left">Hari</th>
              <th class="px-5 py-3 text-left">Teori</th>
              <th class="px-5 py-3 text-left">Praktikum 1</th>
              <th class="px-5 py-3 text-left">Praktikum 2</th>
              <th class="px-5 py-3 text-left">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-gray-700">
            <tr v-for="row in paginatedDefaults" :key="row.id" class="hover:bg-gray-50">
              <td class="px-5 py-3">
                <div class="font-medium text-gray-900">{{ row.course_name }}</div>
              </td>
              <td class="px-5 py-3 text-gray-700">{{ row.course_code }}</td>
              <td class="px-5 py-3 text-gray-700">{{ row.day_of_week }}</td>
              <td class="px-5 py-3 text-gray-700">
                <div>{{ timeRange(row.theory_start_time, row.theory_end_time) }}</div>
                <div class="text-xs text-gray-500">{{ roomName(row, 'theory_room') }}</div>
              </td>
              <td class="px-5 py-3 text-gray-700">
                <template v-if="row.practicum1_start_time && row.practicum1_end_time">
                  <div>{{ timeRange(row.practicum1_start_time, row.practicum1_end_time) }}</div>
                  <div class="text-xs text-gray-500">{{ roomName(row, 'practicum1_room') }}</div>
                </template>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-5 py-3 text-gray-700">
                <template v-if="row.practicum2_start_time && row.practicum2_end_time">
                  <div>{{ timeRange(row.practicum2_start_time, row.practicum2_end_time) }}</div>
                  <div class="text-xs text-gray-500">{{ roomName(row, 'practicum2_room') }}</div>
                </template>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-5 py-3 text-sm">
                <div class="flex flex-wrap gap-2">
                  <button type="button" class="text-amber-600 hover:text-amber-800" @click="openEditModal(row)">
                    Edit
                  </button>
                  <button type="button" class="text-red-600 hover:text-red-800" @click="destroyDefault(row)">
                    Hapus
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!defaultsList.length">
              <td colspan="7" class="px-5 py-10 text-center text-sm text-gray-400">
                Belum ada default jadwal untuk semester ini.
              </td>
            </tr>
          </tbody>
        </table>

        <div class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} data</span>
            <span v-else>Menampilkan 0 data</span>
          </div>
          <div class="flex items-center gap-1">
            <button
              type="button"
              class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(1)"
              :disabled="currentPage === 1 || !defaultsList.length"
            >
              «
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1 || !defaultsList.length"
            >
              ‹
            </button>
            <template v-if="defaultsList.length">
              <button
                v-for="page in pages"
                :key="`defaults-page-${page}`"
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
              :disabled="currentPage === totalPages || !defaultsList.length"
            >
              ›
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(totalPages)"
              :disabled="currentPage === totalPages || !defaultsList.length"
            >
              »
            </button>
          </div>
        </div>
      </div>
    </div>

    <Modal :show="showCreateModal" max-width="3xl" @close="closeCreateModal">
      <form @submit.prevent="submitCreate" class="flex max-h-[80vh] flex-col gap-4 p-6">
        <div>
          <h2 class="text-lg font-semibold text-gray-800">Tambah Default Jadwal</h2>
          <p class="text-sm text-gray-500">Masukkan jadwal mata kuliah untuk semester ini.</p>
        </div>

        <div class="flex-1 space-y-6 overflow-y-auto pr-1">
          <div class="grid gap-6 md:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-700">Nama Mata Kuliah *</label>
              <input
                v-model="createForm.course_name"
                type="text"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <div v-if="createForm.errors.course_name" class="mt-1 text-xs text-red-600">{{ createForm.errors.course_name }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Kode Mata Kuliah *</label>
              <input
                v-model="createForm.course_code"
                type="text"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <div v-if="createForm.errors.course_code" class="mt-1 text-xs text-red-600">{{ createForm.errors.course_code }}</div>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Hari *</label>
            <select
              v-model="createForm.day_of_week"
              class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="">-- Pilih Hari --</option>
              <option v-for="day in days" :key="`create-day-${day}`" :value="day">
                {{ day }}
              </option>
            </select>
            <div v-if="createForm.errors.day_of_week" class="mt-1 text-xs text-red-600">{{ createForm.errors.day_of_week }}</div>
          </div>

          <div class="grid gap-6 md:grid-cols-3">
            <div class="md:col-span-3">
              <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-800">Sesi Teori</h3>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Jam Mulai *</label>
              <input
                v-model="createForm.theory_start_time"
                type="time"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <div v-if="createForm.errors.theory_start_time" class="mt-1 text-xs text-red-600">{{ createForm.errors.theory_start_time }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Jam Selesai *</label>
              <input
                v-model="createForm.theory_end_time"
                type="time"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <div v-if="createForm.errors.theory_end_time" class="mt-1 text-xs text-red-600">{{ createForm.errors.theory_end_time }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Ruang</label>
              <select
                v-model="createForm.theory_room_id"
                :disabled="roomsLoading && !roomOptions.length"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100"
              >
                <option value="">{{ roomsLoading && !roomOptions.length ? 'Memuat daftar ruang...' : '-- Tanpa Ruang --' }}</option>
                <option v-for="room in roomOptions" :key="`create-theory-room-${room.id}`" :value="room.id">
                  {{ room.name }}
                </option>
              </select>
              <div v-if="createForm.errors.theory_room_id" class="mt-1 text-xs text-red-600">{{ createForm.errors.theory_room_id }}</div>
            </div>
          </div>

          <div class="grid gap-6 md:grid-cols-3">
            <div class="md:col-span-3 flex items-center justify-between">
              <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-800">Praktikum 1 (opsional)</h3>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Jam Mulai</label>
              <input
                v-model="createForm.practicum1_start_time"
                type="time"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <div v-if="createForm.errors.practicum1_start_time" class="mt-1 text-xs text-red-600">
                {{ createForm.errors.practicum1_start_time }}
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Jam Selesai</label>
              <input
                v-model="createForm.practicum1_end_time"
                type="time"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <div v-if="createForm.errors.practicum1_end_time" class="mt-1 text-xs text-red-600">
                {{ createForm.errors.practicum1_end_time }}
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Ruang</label>
              <select
                v-model="createForm.practicum1_room_id"
                :disabled="roomsLoading && !roomOptions.length"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100"
              >
                <option value="">{{ roomsLoading && !roomOptions.length ? 'Memuat daftar ruang...' : '-- Tanpa Ruang --' }}</option>
                <option v-for="room in roomOptions" :key="`create-prac1-room-${room.id}`" :value="room.id">
                  {{ room.name }}
                </option>
              </select>
              <div v-if="createForm.errors.practicum1_room_id" class="mt-1 text-xs text-red-600">
                {{ createForm.errors.practicum1_room_id }}
              </div>
            </div>
          </div>

          <div class="grid gap-6 md:grid-cols-3">
            <div class="md:col-span-3 flex items-center justify-between">
              <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-800">Praktikum 2 (opsional)</h3>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Jam Mulai</label>
              <input
                v-model="createForm.practicum2_start_time"
                type="time"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <div v-if="createForm.errors.practicum2_start_time" class="mt-1 text-xs text-red-600">
                {{ createForm.errors.practicum2_start_time }}
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Jam Selesai</label>
              <input
                v-model="createForm.practicum2_end_time"
                type="time"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <div v-if="createForm.errors.practicum2_end_time" class="mt-1 text-xs text-red-600">
                {{ createForm.errors.practicum2_end_time }}
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Ruang</label>
              <select
                v-model="createForm.practicum2_room_id"
                :disabled="roomsLoading && !roomOptions.length"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100"
              >
                <option value="">{{ roomsLoading && !roomOptions.length ? 'Memuat daftar ruang...' : '-- Tanpa Ruang --' }}</option>
                <option v-for="room in roomOptions" :key="`create-prac2-room-${room.id}`" :value="room.id">
                  {{ room.name }}
                </option>
              </select>
              <div v-if="createForm.errors.practicum2_room_id" class="mt-1 text-xs text-red-600">
                {{ createForm.errors.practicum2_room_id }}
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-shrink-0 justify-end gap-2 border-t border-gray-100 pt-4">
          <button
            type="button"
            class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            @click="closeCreateModal"
          >
            Batal
          </button>
          <button
            type="submit"
            class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="createForm.processing"
          >
            Simpan Jadwal
          </button>
        </div>
      </form>
    </Modal>

    <Modal :show="showEditModal" max-width="3xl" @close="closeEditModal">
      <form @submit.prevent="submitEdit" class="flex max-h-[80vh] flex-col gap-4 p-6">
        <div>
          <h2 class="text-lg font-semibold text-gray-800">Ubah Default Jadwal</h2>
          <p class="text-sm text-gray-500">Perbarui jadwal mata kuliah yang dipilih.</p>
        </div>

        <div class="flex-1 space-y-6 overflow-y-auto pr-1">
          <div class="grid gap-6 md:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-700">Nama Mata Kuliah *</label>
              <input
                v-model="editForm.course_name"
                type="text"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <div v-if="editForm.errors.course_name" class="mt-1 text-xs text-red-600">{{ editForm.errors.course_name }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Kode Mata Kuliah *</label>
              <input
                v-model="editForm.course_code"
                type="text"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <div v-if="editForm.errors.course_code" class="mt-1 text-xs text-red-600">{{ editForm.errors.course_code }}</div>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Hari *</label>
            <select
              v-model="editForm.day_of_week"
              class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="">-- Pilih Hari --</option>
              <option v-for="day in days" :key="`edit-day-${day}`" :value="day">
                {{ day }}
              </option>
            </select>
            <div v-if="editForm.errors.day_of_week" class="mt-1 text-xs text-red-600">{{ editForm.errors.day_of_week }}</div>
          </div>

          <div class="grid gap-6 md:grid-cols-3">
            <div class="md:col-span-3">
              <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-800">Sesi Teori</h3>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Jam Mulai *</label>
              <input
                v-model="editForm.theory_start_time"
                type="time"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <div v-if="editForm.errors.theory_start_time" class="mt-1 text-xs text-red-600">{{ editForm.errors.theory_start_time }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Jam Selesai *</label>
              <input
                v-model="editForm.theory_end_time"
                type="time"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <div v-if="editForm.errors.theory_end_time" class="mt-1 text-xs text-red-600">{{ editForm.errors.theory_end_time }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Ruang</label>
              <select
                v-model="editForm.theory_room_id"
                :disabled="roomsLoading && !roomOptions.length"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100"
              >
                <option value="">{{ roomsLoading && !roomOptions.length ? 'Memuat daftar ruang...' : '-- Tanpa Ruang --' }}</option>
                <option v-for="room in roomOptions" :key="`edit-theory-room-${room.id}`" :value="room.id">
                  {{ room.name }}
                </option>
              </select>
              <div v-if="editForm.errors.theory_room_id" class="mt-1 text-xs text-red-600">{{ editForm.errors.theory_room_id }}</div>
            </div>
          </div>

          <div class="grid gap-6 md:grid-cols-3">
            <div class="md:col-span-3 flex items-center justify-between">
              <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-800">Praktikum 1 (opsional)</h3>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Jam Mulai</label>
              <input
                v-model="editForm.practicum1_start_time"
                type="time"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <div v-if="editForm.errors.practicum1_start_time" class="mt-1 text-xs text-red-600">
                {{ editForm.errors.practicum1_start_time }}
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Jam Selesai</label>
              <input
                v-model="editForm.practicum1_end_time"
                type="time"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <div v-if="editForm.errors.practicum1_end_time" class="mt-1 text-xs text-red-600">
                {{ editForm.errors.practicum1_end_time }}
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Ruang</label>
              <select
                v-model="editForm.practicum1_room_id"
                :disabled="roomsLoading && !roomOptions.length"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100"
              >
                <option value="">{{ roomsLoading && !roomOptions.length ? 'Memuat daftar ruang...' : '-- Tanpa Ruang --' }}</option>
                <option v-for="room in roomOptions" :key="`edit-prac1-room-${room.id}`" :value="room.id">
                  {{ room.name }}
                </option>
              </select>
              <div v-if="editForm.errors.practicum1_room_id" class="mt-1 text-xs text-red-600">
                {{ editForm.errors.practicum1_room_id }}
              </div>
            </div>
          </div>

          <div class="grid gap-6 md:grid-cols-3">
            <div class="md:col-span-3 flex items-center justify-between">
              <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-800">Praktikum 2 (opsional)</h3>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Jam Mulai</label>
              <input
                v-model="editForm.practicum2_start_time"
                type="time"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <div v-if="editForm.errors.practicum2_start_time" class="mt-1 text-xs text-red-600">
                {{ editForm.errors.practicum2_start_time }}
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Jam Selesai</label>
              <input
                v-model="editForm.practicum2_end_time"
                type="time"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <div v-if="editForm.errors.practicum2_end_time" class="mt-1 text-xs text-red-600">
                {{ editForm.errors.practicum2_end_time }}
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Ruang</label>
              <select
                v-model="editForm.practicum2_room_id"
                :disabled="roomsLoading && !roomOptions.length"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100"
              >
                <option value="">{{ roomsLoading && !roomOptions.length ? 'Memuat daftar ruang...' : '-- Tanpa Ruang --' }}</option>
                <option v-for="room in roomOptions" :key="`edit-prac2-room-${room.id}`" :value="room.id">
                  {{ room.name }}
                </option>
              </select>
              <div v-if="editForm.errors.practicum2_room_id" class="mt-1 text-xs text-red-600">
                {{ editForm.errors.practicum2_room_id }}
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-shrink-0 justify-end gap-2 border-t border-gray-100 pt-4">
          <button
            type="button"
            class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            @click="closeEditModal"
          >
            Batal
          </button>
          <button
            type="submit"
            class="inline-flex items-center rounded-md bg-amber-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="editForm.processing"
          >
            Simpan Perubahan
          </button>
        </div>
      </form>
    </Modal>
  </AuthenticatedLayout>
</template>
