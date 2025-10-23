<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
  semester: { type: Object, required: true },
  preview: { type: Object, required: true },
  payload: { type: String, required: true },
})

const rows = props.preview?.rows ?? []
const hasErrors = rows.some((r) => Array.isArray(r.errors) && r.errors.length > 0)

const form = useForm({
  payload: props.payload,
  has_errors: hasErrors ? '1' : '0',
})

const commit = () => {
  form.post(route('admin.semesters.defaults.import.commit', { semester: props.semester.id }))
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Preview Import Default Jadwal" />

    <div class="mx-auto max-w-6xl space-y-6 py-6">
      <div class="rounded-xl bg-white p-6 shadow-sm">
        <div class="mb-6 flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-semibold text-gray-800">Preview Data Import</h1>
            <p class="text-sm text-gray-600">Periksa kembali hasil parsing CSV sebelum disimpan.</p>
          </div>
          <Link :href="route('admin.semesters.defaults.import.form', { semester: props.semester.id })" class="text-sm text-blue-600 hover:text-blue-800">&larr; Ganti File</Link>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Baris</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Mata Kuliah</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Kode</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Hari</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Teori</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Praktikum 1</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Praktikum 2</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="row in rows" :key="row.raw?.lineNumber" class="align-top">
                <td class="px-3 py-3 text-sm text-gray-700">#{{ row.raw?.lineNumber }}</td>
                <td class="px-3 py-3 text-sm text-gray-900">
                  <div>{{ row.raw?.courseName }}</div>
                </td>
                <td class="px-3 py-3 text-sm text-gray-700">{{ row.raw?.courseCode }}</td>
                <td class="px-3 py-3 text-sm text-gray-700">{{ row.normalizedData?.day_of_week ?? row.raw?.dayOfWeek }}</td>
                <td class="px-3 py-3 text-sm text-gray-700">
                  <template v-if="row.normalizedData?.theory_start_time && row.normalizedData?.theory_end_time">
                    {{ row.normalizedData?.theory_start_time }} - {{ row.normalizedData?.theory_end_time }}<br />
                    <span class="text-xs text-gray-500">{{ row.raw?.theoryRoomCode || 'Tanpa ruang' }}</span>
                  </template>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="px-3 py-3 text-sm text-gray-700">
                  <template v-if="row.normalizedData?.practicum1_start_time && row.normalizedData?.practicum1_end_time">
                    {{ row.normalizedData?.practicum1_start_time }} - {{ row.normalizedData?.practicum1_end_time }}<br />
                    <span class="text-xs text-gray-500">{{ row.raw?.practicum1RoomCode || 'Tanpa ruang' }}</span>
                  </template>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="px-3 py-3 text-sm text-gray-700">
                  <template v-if="row.normalizedData?.practicum2_start_time && row.normalizedData?.practicum2_end_time">
                    {{ row.normalizedData?.practicum2_start_time }} - {{ row.normalizedData?.practicum2_end_time }}<br />
                    <span class="text-xs text-gray-500">{{ row.raw?.practicum2RoomCode || 'Tanpa ruang' }}</span>
                  </template>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="px-3 py-3 text-sm">
                  <template v-if="row.errors?.length">
                    <span class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">ERROR</span>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-xs text-red-600">
                      <li v-for="(err, i) in row.errors" :key="i">{{ err }}</li>
                    </ul>
                  </template>
                  <span v-else class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">OK</span>
                </td>
              </tr>
              <tr v-if="!rows.length">
                <td colspan="8" class="px-3 py-6 text-center text-sm text-gray-500">Tidak ada data yang dapat dipreview.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-6 flex items-center justify-between">
          <Link :href="route('admin.semesters.defaults.index', { semester: props.semester.id })" class="text-sm text-gray-600 hover:text-gray-800">&larr; Kembali ke daftar</Link>
          <button
            v-if="!hasErrors && rows.length"
            type="button"
            class="rounded bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700"
            @click="commit"
          >
            Commit Import
          </button>
          <button
            v-else
            type="button"
            disabled
            class="cursor-not-allowed rounded bg-gray-400 px-4 py-2 text-sm font-semibold text-white"
          >
            Perbaiki Data Terlebih Dahulu
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
