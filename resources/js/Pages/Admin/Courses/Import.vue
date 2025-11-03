// file: resources/js/Pages/Admin/Courses/Import.vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed, watchEffect } from 'vue'

const props = defineProps({
  semesters: {
    type: Array,
    default: () => [],
  },
})

const page = usePage()
const summary = computed(() => page.props.flash?.summary ?? null)

const form = useForm({
  semester_id: '',
  file: null,
})

watchEffect(() => {
  if (!form.semester_id && props.semesters.length) {
    form.semester_id = props.semesters[0].id
  }
})

const handleFileChange = (event) => {
  const [file] = event.target.files || []
  form.file = file || null
}

const submit = () => {
  form.post(route('admin.courses.import.store'), {
    forceFormData: true,
    preserveScroll: true,
  })
}
</script>

<template>
  <Head title="Import Course Offerings" />

  <AuthenticatedLayout>
    <div class="max-w-4xl mx-auto space-y-6">
      <div class="bg-white rounded-lg shadow p-6 space-y-4">
        <div>
          <h1 class="text-xl font-semibold text-gray-800">Import Course Offerings</h1>
          <p class="text-sm text-gray-500">
            Upload a CSV or Excel file containing pertemuan, praktikum, and exam schedules for a semester.
          </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Semester</label>
            <select
              v-model="form.semester_id"
              class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
            >
              <option value="" disabled>Select semester</option>
              <option v-for="semester in props.semesters" :key="semester.id" :value="semester.id">
                {{ semester.label }}
              </option>
            </select>
            <p v-if="form.errors.semester_id" class="mt-1 text-sm text-red-600">{{ form.errors.semester_id }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Import File</label>
            <input
              type="file"
              accept=".csv,.txt,.xls,.xlsx"
              class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
              @change="handleFileChange"
            />
            <p v-if="form.errors.file" class="mt-1 text-sm text-red-600">{{ form.errors.file }}</p>
          </div>

          <div class="flex justify-end">
            <button
              type="submit"
              class="inline-flex items-center rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
              :disabled="form.processing"
            >
              {{ form.processing ? 'Processing...' : 'Import' }}
            </button>
          </div>
        </form>
      </div>

      <div v-if="summary" class="bg-white rounded-lg shadow p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-800">Import Summary</h2>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div class="rounded border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Offerings Created</p>
            <p class="text-xl font-semibold text-gray-900">{{ summary.offerings_created }}</p>
          </div>
          <div class="rounded border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Offerings Updated</p>
            <p class="text-xl font-semibold text-gray-900">{{ summary.offerings_updated }}</p>
          </div>
          <div class="rounded border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Meetings Upserted</p>
            <p class="text-xl font-semibold text-gray-900">{{ summary.meetings_upserted }}</p>
          </div>
          <div class="rounded border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-600">Exams Upserted</p>
            <p class="text-xl font-semibold text-gray-900">{{ summary.exams_upserted }}</p>
          </div>
        </div>

        <div class="space-y-2">
          <div v-if="summary.unknown_room_codes?.length" class="rounded border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-700">
            <p class="font-medium">Unknown Room Codes</p>
            <ul class="list-disc pl-5">
              <li v-for="code in summary.unknown_room_codes" :key="code">{{ code }}</li>
            </ul>
          </div>

          <div v-if="summary.invalid_dates?.length" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            <p class="font-medium">Invalid Dates</p>
            <ul class="list-disc pl-5">
              <li v-for="item in summary.invalid_dates" :key="item">{{ item }}</li>
            </ul>
          </div>
        </div>

        <div v-if="summary.rows?.length" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead>
              <tr class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                <th class="px-3 py-2">Row</th>
                <th class="px-3 py-2">Course Code</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Errors</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="detail in summary.rows" :key="`${detail.row}-${detail.course_code}`">
                <td class="px-3 py-2">{{ detail.row }}</td>
                <td class="px-3 py-2">{{ detail.course_code }}</td>
                <td class="px-3 py-2 capitalize">
                  <span
                    class="inline-flex rounded px-2 py-0.5 text-xs font-semibold"
                    :class="{
                      'bg-green-100 text-green-700': detail.status === 'created',
                      'bg-blue-100 text-blue-700': detail.status === 'updated',
                      'bg-gray-100 text-gray-600': detail.status === 'skipped',
                    }"
                  >
                    {{ detail.status }}
                  </span>
                </td>
                <td class="px-3 py-2">
                  <template v-if="detail.errors?.length">
                    <ul class="list-disc pl-5 text-xs text-red-600 space-y-1">
                      <li v-for="error in detail.errors" :key="error">{{ error }}</li>
                    </ul>
                  </template>
                  <span v-else class="text-gray-400">-</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
