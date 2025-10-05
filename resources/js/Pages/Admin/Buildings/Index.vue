<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

const props = defineProps({
  buildings: Array
})
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
        <Link
          :href="route('admin.buildings.create')"
          class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700"
        >
          ➕ Tambah Gedung
        </Link>
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
            <tr v-for="(building, index) in buildings" :key="building.id">
              <td class="px-4 py-2 text-sm text-gray-700">{{ index + 1 }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ building.name }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ building.campus?.name ?? '-' }}</td>
              <td class="px-4 py-2 text-sm text-gray-500">
                {{ new Date(building.created_at).toLocaleDateString('id-ID') }}
              </td>
              <td class="px-4 py-2 text-sm">
                <div class="flex gap-2">
                  <Link
                    :href="route('admin.buildings.edit', building.id)"
                    class="px-3 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500"
                  >
                    ✏️ Edit
                  </Link>
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
            <tr v-if="buildings.length === 0">
              <td colspan="5" class="px-4 py-4 text-center text-gray-500">
                Belum ada data gedung.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AuthenticatedLayout>
</template>