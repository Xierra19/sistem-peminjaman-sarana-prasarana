// file: resources/js/Pages/Admin/Courses/Import.vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  semesters: {
    type: Array,
    default: () => [],
  },
})

const page = usePage()
const summary = computed(() => page.props.flash?.summary ?? null)

const form = useForm({
  semester_id: props.semesters[0]?.id ?? '',
  file: null,
})

const handleFileChange = (event) => {
  const [file] = event.target.files || []
  form.file = file ?? null
}

const submit = () => {
  form.post(route('admin.courses.import.store'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      form.reset('file')
      const input = document.getElementById('import_file')
      if (input) {
        input.value = ''
      }
    },
  })
}
</script>

<template>
  <Head title="Import Courses" />

  <AuthenticatedLayout>
    <div class="max-w-3xl mx-auto space-y-6">
      <section class="bg-white shadow rounded-lg p-6 space-y-4">
        <header>
          <h1 class="text-2xl font-semibold text-gray-900">Import Course Offerings</h1>
          <p class="mt-2 text-sm text-gray-600">
            Upload a CSV with the columns: Kode Mata Kuliah, Nama Mata Kuliah, Tanggal UTS, Jam UTS, Tanggal UAS, Jam UAS.
          </p>
        </header>

        <form class="space-y-4" @submit.prevent="submit">
          <div>
            <label for="semester_id" class="block text-sm font-medium text-gray-700">Semester</label>
            <select
              id="semester_id"
              v-model="form.semester_id"
              class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
              <option value="" disabled>Select semester</option>
              <option v-for="semester in semesters" :key="semester.id" :value="semester.id">
                {{ semester.label }}
              </option>
            </select>
            <p v-if="form.errors.semester_id" class="mt-1 text-sm text-red-600">{{ form.errors.semester_id }}</p>
          </div>

          <div>
            <label for="import_file" class="block text-sm font-medium text-gray-700">CSV File</label>
            <input
              id="import_file"
              type="file"
              accept=".csv,.txt"
              class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
              @change="handleFileChange"
            />
            <p v-if="form.errors.file" class="mt-1 text-sm text-red-600">{{ form.errors.file }}</p>
          </div>

          <div class="flex justify-end">
            <button
              type="submit"
              :disabled="form.processing"
              class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
            >
              {{ form.processing ? 'Uploading...' : 'Start Import' }}
            </button>
          </div>
        </form>
      </section>

      <section v-if="summary" class="bg-white shadow rounded-lg p-6 space-y-4">
        <header>
          <h2 class="text-lg font-semibold text-gray-900">Import Summary</h2>
          <p class="text-xs text-gray-500">Numbers below include rows with partial data.</p>
        </header>

        <dl class="grid gap-4 sm:grid-cols-2">
          <div class="rounded border border-gray-200 p-4">
            <dt class="text-sm text-gray-600">Rows Processed</dt>
            <dd class="mt-1 text-xl font-semibold text-gray-900">{{ summary.rows_processed }}</dd>
          </div>
          <div class="rounded border border-gray-200 p-4">
            <dt class="text-sm text-gray-600">Offerings Created</dt>
            <dd class="mt-1 text-xl font-semibold text-gray-900">{{ summary.offerings_created }}</dd>
          </div>
          <div class="rounded border border-gray-200 p-4">
            <dt class="text-sm text-gray-600">Offerings Updated</dt>
            <dd class="mt-1 text-xl font-semibold text-gray-900">{{ summary.offerings_updated }}</dd>
          </div>
          <div class="rounded border border-gray-200 p-4">
            <dt class="text-sm text-gray-600">Exams Upserted</dt>
            <dd class="mt-1 text-xl font-semibold text-gray-900">{{ summary.exams_upserted }}</dd>
          </div>
        </dl>

        <div v-if="summary.invalid_rows?.length" class="space-y-2">
          <h3 class="text-sm font-medium text-red-600">Rows with issues</h3>
          <ul class="space-y-2 text-sm text-red-600">
            <li v-for="row in summary.invalid_rows" :key="`${row.row}-${row.course_code ?? 'unknown'}`">
              <span class="font-semibold">Row {{ row.row }} ({{ row.course_code ?? 'no code' }}):</span>
              <ul class="list-disc pl-5">
                <li v-for="message in row.errors" :key="message">{{ message }}</li>
              </ul>
            </li>
          </ul>
        </div>
      </section>
    </div>
  </AuthenticatedLayout>
</template>
