<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'

const props = defineProps({
  semester: { type: Object, required: true },
  defaults: { type: Array, default: () => [] },
})

const time = (value) => (value ? new Date(`1970-01-01T${value}`).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '')
const roomName = (row, key) => {
  const rel = row[key]
  return rel && rel.name ? rel.name : 'Tanpa ruang'
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

    <div class="mx-auto max-w-6xl space-y-6 py-6">
      <div class="rounded-xl bg-white p-6 shadow-sm">
        <div class="mb-6 flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-semibold text-gray-800">Default Jadwal</h1>
            <p class="text-sm text-gray-600">Semester {{ props.semester.term }} {{ props.semester.year }}</p>
          </div>
          <div class="flex flex-wrap gap-3">
            <Link
              :href="route('admin.semesters.defaults.import.form', [props.semester.id])"
              class="inline-flex items-center rounded border border-indigo-500 px-4 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50"
            >
              Import CSV
            </Link>
            <Link
              :href="route('admin.semesters.defaults.create', [props.semester.id])"
              class="inline-flex items-center rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            >
              + Tambah Jadwal
            </Link>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Nama Matkul</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Kode</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Hari</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Teori</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Praktikum 1</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Praktikum 2</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="row in props.defaults" :key="row.id" class="hover:bg-gray-50">
                <td class="px-3 py-3 text-sm text-gray-900">{{ row.course_name }}</td>
                <td class="px-3 py-3 text-sm text-gray-700">{{ row.course_code }}</td>
                <td class="px-3 py-3 text-sm text-gray-700">{{ row.day_of_week }}</td>
                <td class="px-3 py-3 text-sm text-gray-700">
                  <div>{{ time(row.theory_start_time) }} - {{ time(row.theory_end_time) }}</div>
                  <div class="text-xs text-gray-500">{{ roomName(row, 'theory_room') }}</div>
                </td>
                <td class="px-3 py-3 text-sm text-gray-700">
                  <template v-if="row.practicum1_start_time && row.practicum1_end_time">
                    <div>{{ time(row.practicum1_start_time) }} - {{ time(row.practicum1_end_time) }}</div>
                    <div class="text-xs text-gray-500">{{ roomName(row, 'practicum1_room') }}</div>
                  </template>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="px-3 py-3 text-sm text-gray-700">
                  <template v-if="row.practicum2_start_time && row.practicum2_end_time">
                    <div>{{ time(row.practicum2_start_time) }} - {{ time(row.practicum2_end_time) }}</div>
                    <div class="text-xs text-gray-500">{{ roomName(row, 'practicum2_room') }}</div>
                  </template>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="px-3 py-3 text-sm text-gray-700">
                  <div class="flex flex-wrap gap-2">
                    <Link
                      :href="route('admin.semesters.defaults.edit', [props.semester.id, row.id])"
                      class="text-amber-600 hover:text-amber-800"
                    >
                      Edit
                    </Link>
                    <button type="button" @click="destroyDefault(row)" class="text-red-600 hover:text-red-800">Hapus</button>
                  </div>
                </td>
              </tr>
              <tr v-if="!props.defaults.length">
                <td colspan="7" class="px-3 py-6 text-center text-sm text-gray-500">Belum ada default jadwal untuk semester ini.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>