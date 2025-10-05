<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

const props = defineProps({
  campuses: Array
})
</script>

<template>
  <Head title="Master Campus" />

  <AuthenticatedLayout>
    <div class="bg-white p-6 rounded-lg shadow-md">
      <div class="flex justify-between items-center mb-4">
        <h1 class="text-xl font-semibold text-gray-800">🏫 Master Campus</h1>
        <Link
          :href="route('admin.campus.create')"
          class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700"
        >
          ➕ Tambah Campus
        </Link>
      </div>

      <div class="overflow-x-auto">
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
            <tr v-for="(campus, index) in campuses" :key="campus.id">
              <td class="px-4 py-2 text-sm text-gray-700">{{ index + 1 }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ campus.name }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ campus.address }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ campus.phone }}</td>
              <td class="px-4 py-2 text-sm">
                <Link
                  :href="route('admin.campus.edit', campus.id)"
                  class="px-3 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500 mr-2"
                >
                  ✏️ Edit
                </Link>
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
            <tr v-if="campuses.length === 0">
              <td colspan="5" class="px-4 py-4 text-center text-gray-500">
                Tidak ada data campus.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AuthenticatedLayout>
</template>