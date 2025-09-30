<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'

const form = useForm({
  name: '',
  address: ''
})

const submit = () => {
  form.post(route('admin.campus.store'))
}
</script>

<template>
  <Head title="Tambah Campus" />

  <AuthenticatedLayout>
    <div class="bg-white p-6 rounded-lg shadow-md max-w-lg">
      <h1 class="text-xl font-semibold text-gray-800 mb-4">➕ Tambah Campus</h1>

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
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
            :disabled="form.processing"
          >
            Simpan
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
