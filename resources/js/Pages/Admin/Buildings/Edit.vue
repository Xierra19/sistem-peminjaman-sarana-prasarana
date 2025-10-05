<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'

const props = defineProps({
  building: Object,
  campuses: Array
})

const form = useForm({
  name: props.building.name,
  campus_id: props.building.campus_id
})

const submit = () => {
  form.put(route('admin.buildings.update', props.building.id))
}
</script>

<template>
  <Head title="Edit Gedung" />

  <AuthenticatedLayout>
    <div class="bg-white p-6 rounded-lg shadow-md max-w-lg">
      <h1 class="text-xl font-semibold text-gray-800 mb-4">✏️ Edit Gedung</h1>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Nama Gedung</label>
          <input
            v-model="form.name"
            type="text"
            class="w-full border rounded px-3 py-2 mt-1"
          />
          <div v-if="form.errors.name" class="text-red-500 text-sm">{{ form.errors.name }}</div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Campus</label>
          <select
            v-model="form.campus_id"
            class="w-full border rounded px-3 py-2 mt-1"
          >
            <option v-for="campus in campuses" :key="campus.id" :value="campus.id">
              {{ campus.name }}
            </option>
          </select>
          <div v-if="form.errors.campus_id" class="text-red-500 text-sm">{{ form.errors.campus_id }}</div>
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
            :href="route('admin.buildings.index')"
            class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600"
          >
            Batal
          </Link>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>