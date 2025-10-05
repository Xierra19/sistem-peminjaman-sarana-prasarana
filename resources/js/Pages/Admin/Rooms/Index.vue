<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

const props = defineProps({
  rooms: Array
})
</script>

<template>
  <Head title="Master Room" />

  <AuthenticatedLayout>
    <div class="bg-white p-6 rounded-lg shadow-md">
      <div class="flex justify-between items-center mb-4">
        <div>
          <h1 class="text-xl font-semibold text-gray-800">🚪 Master Room</h1>
          <p class="text-sm text-gray-500">Kelola ruangan berdasarkan gedung dan campus.</p>
        </div>
        <Link
          :href="route('admin.rooms.create')"
          class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700"
        >
          ➕ Tambah Ruangan
        </Link>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200 divide-y divide-gray-200">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">#</th>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Nama Ruangan</th>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Gedung</th>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Campus</th>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Kapasitas</th>
              <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(room, index) in rooms" :key="room.id">
              <td class="px-4 py-2 text-sm text-gray-700">{{ index + 1 }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ room.name }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ room.building?.name ?? '-' }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ room.building?.campus?.name ?? '-' }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ room.capacity }}</td>
              <td class="px-4 py-2 text-sm">
                <div class="flex gap-2">
                  <Link
                    :href="route('admin.rooms.edit', room.id)"
                    class="px-3 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500"
                  >
                    ✏️ Edit
                  </Link>
                  <Link
                    :href="route('admin.rooms.destroy', room.id)"
                    method="delete"
                    as="button"
                    class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700"
                  >
                    🗑 Hapus
                  </Link>
                </div>
              </td>
            </tr>
            <tr v-if="rooms.length === 0">
              <td colspan="6" class="px-4 py-4 text-center text-gray-500">
                Belum ada data ruangan.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AuthenticatedLayout>
</template>