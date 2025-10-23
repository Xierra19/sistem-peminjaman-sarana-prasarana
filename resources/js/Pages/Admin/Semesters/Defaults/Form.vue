<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
  semester: { type: Object, required: true },
  defaultItem: { type: Object, default: () => ({}) },
  rooms: { type: Array, default: () => [] },
  mode: { type: String, default: 'create' },
})

const isEdit = props.mode === 'edit' && !!props.defaultItem?.id

const days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']

const form = useForm({
  course_name: props.defaultItem?.course_name ?? '',
  course_code: props.defaultItem?.course_code ?? '',
  day_of_week: props.defaultItem?.day_of_week ?? '',
  theory_start_time: props.defaultItem?.theory_start_time ?? '',
  theory_end_time: props.defaultItem?.theory_end_time ?? '',
  theory_room_id: props.defaultItem?.theory_room_id ?? '',
  practicum1_start_time: props.defaultItem?.practicum1_start_time ?? '',
  practicum1_end_time: props.defaultItem?.practicum1_end_time ?? '',
  practicum1_room_id: props.defaultItem?.practicum1_room_id ?? '',
  practicum2_start_time: props.defaultItem?.practicum2_start_time ?? '',
  practicum2_end_time: props.defaultItem?.practicum2_end_time ?? '',
  practicum2_room_id: props.defaultItem?.practicum2_room_id ?? '',
})

const submit = () => {
  if (isEdit) {
    form.put(route('admin.semesters.defaults.update', { semester: props.semester.id, default: props.defaultItem.id, defaults: props.defaultItem.id }))
  } else {
    form.post(route('admin.semesters.defaults.store', { semester: props.semester.id }))
  }
}
</script>

<template>
  <AuthenticatedLayout>
    <Head :title="`${isEdit ? 'Ubah' : 'Tambah'} Default Jadwal`" />

    <div class="mx-auto max-w-4xl space-y-6 py-6">
      <div class="rounded-xl bg-white p-6 shadow-sm">
        <h1 class="mb-4 text-2xl font-semibold text-gray-800">{{ isEdit ? 'Ubah' : 'Tambah' }} Default Jadwal</h1>
        <form @submit.prevent="submit" class="space-y-6">
          <div class="grid gap-6 md:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-700">Nama Mata Kuliah *</label>
              <input v-model="form.course_name" type="text" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
              <div v-if="form.errors.course_name" class="mt-1 text-sm text-red-600">{{ form.errors.course_name }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Kode Mata Kuliah *</label>
              <input v-model="form.course_code" type="text" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
              <div v-if="form.errors.course_code" class="mt-1 text-sm text-red-600">{{ form.errors.course_code }}</div>
            </div>
          </div>

          <div class="grid gap-6 md:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-700">Hari *</label>
              <select v-model="form.day_of_week" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                <option value="">-- Pilih Hari --</option>
                <option v-for="d in days" :key="d" :value="d">{{ d }}</option>
              </select>
              <div v-if="form.errors.day_of_week" class="mt-1 text-sm text-red-600">{{ form.errors.day_of_week }}</div>
            </div>
          </div>

          <div class="grid gap-6 md:grid-cols-3">
            <div class="md:col-span-3">
              <h3 class="text-lg font-semibold text-gray-800">Sesi Teori *</h3>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Jam Mulai</label>
              <input v-model="form.theory_start_time" type="time" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
              <div v-if="form.errors.theory_start_time" class="mt-1 text-sm text-red-600">{{ form.errors.theory_start_time }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Jam Selesai</label>
              <input v-model="form.theory_end_time" type="time" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
              <div v-if="form.errors.theory_end_time" class="mt-1 text-sm text-red-600">{{ form.errors.theory_end_time }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Ruang</label>
              <select v-model="form.theory_room_id" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                <option value="">-- Tanpa Ruang --</option>
                <option v-for="room in props.rooms" :key="room.id" :value="room.id">{{ room.name }}</option>
              </select>
              <div v-if="form.errors.theory_room_id" class="mt-1 text-sm text-red-600">{{ form.errors.theory_room_id }}</div>
            </div>
          </div>

          <div class="space-y-4">
            <div class="grid gap-6 md:grid-cols-3">
              <div class="md:col-span-3 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">Praktikum 1 (opsional)</h3>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Jam Mulai</label>
                <input v-model="form.practicum1_start_time" type="time" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
                <div v-if="form.errors.practicum1_start_time" class="mt-1 text-sm text-red-600">{{ form.errors.practicum1_start_time }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Jam Selesai</label>
                <input v-model="form.practicum1_end_time" type="time" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
                <div v-if="form.errors.practicum1_end_time" class="mt-1 text-sm text-red-600">{{ form.errors.practicum1_end_time }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Ruang</label>
                <select v-model="form.practicum1_room_id" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                  <option value="">-- Tanpa Ruang --</option>
                  <option v-for="room in props.rooms" :key="room.id" :value="room.id">{{ room.name }}</option>
                </select>
                <div v-if="form.errors.practicum1_room_id" class="mt-1 text-sm text-red-600">{{ form.errors.practicum1_room_id }}</div>
              </div>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
              <div class="md:col-span-3 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">Praktikum 2 (opsional)</h3>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Jam Mulai</label>
                <input v-model="form.practicum2_start_time" type="time" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
                <div v-if="form.errors.practicum2_start_time" class="mt-1 text-sm text-red-600">{{ form.errors.practicum2_start_time }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Jam Selesai</label>
                <input v-model="form.practicum2_end_time" type="time" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
                <div v-if="form.errors.practicum2_end_time" class="mt-1 text-sm text-red-600">{{ form.errors.practicum2_end_time }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Ruang</label>
                <select v-model="form.practicum2_room_id" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                  <option value="">-- Tanpa Ruang --</option>
                  <option v-for="room in props.rooms" :key="room.id" :value="room.id">{{ room.name }}</option>
                </select>
                <div v-if="form.errors.practicum2_room_id" class="mt-1 text-sm text-red-600">{{ form.errors.practicum2_room_id }}</div>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-3">
            <Link :href="route('admin.semesters.defaults.index', { semester: props.semester.id })" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Batal</Link>
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700" :disabled="form.processing">
              {{ isEdit ? 'Simpan Perubahan' : 'Simpan Jadwal' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
