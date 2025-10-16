<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'

const props = defineProps({
  room: Object,
  buildings: Array
})

const form = useForm({
  name: props.room.name,
  building_id: props.room.building_id,
  capacity: props.room.capacity,
  is_available: Boolean(props.room.is_available),
})

const submit = () => {
  form.put(route('admin.rooms.update', props.room.id))
}
</script>

<template>
  <Head title="Edit Ruangan" />

  <AuthenticatedLayout>
    <div class="bg-white p-6 rounded-lg shadow-md max-w-lg">
      <h1 class="text-xl font-semibold text-gray-800 mb-4">✏️ Edit Ruangan</h1>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Nama Ruangan</label>
          <input
            v-model="form.name"
            type="text"
            class="w-full border rounded px-3 py-2 mt-1"
          />
          <div v-if="form.errors.name" class="text-red-500 text-sm">{{ form.errors.name }}</div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Gedung</label>
          <select
            v-model="form.building_id"
            class="w-full border rounded px-3 py-2 mt-1"
          >
            <option v-for="building in buildings" :key="building.id" :value="building.id">
              {{ building.name }} - {{ building.campus?.name ?? '-' }}
            </option>
          </select>
          <div v-if="form.errors.building_id" class="text-red-500 text-sm">{{ form.errors.building_id }}</div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Kapasitas</label>
          <input
            v-model.number="form.capacity"
            type="number"
            min="1"
            class="w-full border rounded px-3 py-2 mt-1"
          />
          <div v-if="form.errors.capacity" class="text-red-500 text-sm">{{ form.errors.capacity }}</div>
        </div>

        <div>
          <span class="block text-sm font-medium text-gray-700">Ketersediaan</span>
          <label class="mt-1 inline-flex items-center gap-2 text-sm text-gray-600">
            <input
              type="checkbox"
              v-model="form.is_available"
              class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            Ruangan dapat dipilih oleh pengguna
          </label>
          <p class="mt-1 text-xs text-gray-400">Nonaktifkan jika ruangan sedang dalam perawatan atau tidak aktif.</p>
          <div v-if="form.errors.is_available" class="text-red-500 text-sm">{{ form.errors.is_available }}</div>
        </div>

        <div class="flex space-x-2">
          <button
            type="submit"
            class="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600"
            :disabled="form.processing"
          >
            Update
          </button>
          <Link
            :href="route('admin.rooms.index')"
            class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600"
          >
            Batal
          </Link>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>