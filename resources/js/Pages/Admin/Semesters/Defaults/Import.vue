<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
  semester: { type: Object, required: true },
})

const form = useForm({
  csv_file: null,
})

const submit = () => {
  form.post(route('admin.semesters.defaults.import.preview', { semester: props.semester.id }), {
    forceFormData: true,
  })
}

const onFile = (e) => {
  const [file] = e.target.files || []
  form.csv_file = file ?? null
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Import Default Jadwal" />

    <div class="mx-auto max-w-3xl space-y-6 py-6">
      <div class="rounded-xl bg-white p-6 shadow-sm">
        <h1 class="mb-4 text-2xl font-semibold text-gray-800">Import CSV Default Jadwal</h1>
        <p class="mb-6 text-sm text-gray-600">
          Unggah file CSV dengan header:
          <code class="rounded bg-gray-100 px-2 py-1 text-xs">course_name,course_code,theory_time,practicum1_time,practicum2_time,day_of_week,theory_room_code,practicum1_room_code,practicum2_room_code</code>.
        </p>

        <form @submit.prevent="submit" class="space-y-6">
          <div>
            <label class="block text-sm font-medium text-gray-700">File CSV *</label>
            <input type="file" accept=".csv,text/csv" @change="onFile" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
            <div v-if="form.errors.csv_file" class="mt-1 text-sm text-red-600">{{ form.errors.csv_file }}</div>
          </div>

          <div class="flex justify-end gap-3">
            <Link :href="route('admin.semesters.defaults.index', { semester: props.semester.id })" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Batal</Link>
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700" :disabled="form.processing">Preview</button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
