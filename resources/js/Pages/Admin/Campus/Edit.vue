<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'

const props = defineProps({
  campus: Object
})

const form = useForm({
  name: props.campus.name,
  address: props.campus.address
})

const submit = () => {
  form.put(route('admin.campus.update', props.campus.id))
}
</script>

<template>
  <Head title="Edit Campus" />

  <AuthenticatedLayout>
    <div class="bg-white p-6 rounded-lg shadow-md max-w-lg">
      <h1 class="text-xl font-semibold text-gray-800 mb-4">✏️ Edit Campus</h1>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Nama Campus</label>
          <input
            v-model="form.name"
            type="text"
            class="w-full border rounded px-3 py-2 mt-1"
          />
          <div v-if="form.errors.name" class="text-red-500 text-sm">{{ form.errors.name }}</div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Alamat</label>
          <textarea
            v-model="form.address"
            class="w-full border rounded px-3 py-2 mt-1"
          ></textarea>
          <div v-if="form.errors.address" class="text-red-500 text-sm">{{ form.errors.address }}</div>
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
            :href="route('admin.campus.index')"
            class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600"
          >
            Batal
          </Link>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>
