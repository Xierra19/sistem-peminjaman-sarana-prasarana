// file: resources/js/Pages/Admin/Offerings/Index.vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
  semesterId: {
    type: Number,
    required: true,
  },
  offerings: {
    type: Array,
    default: () => [],
  },
})

const form = useForm({
  semester_id: props.semesterId,
  course_code: '',
  course_name: '',
})

const submit = () => {
  form.post(route('admin.offerings.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('course_code', 'course_name')
    },
  })
}
</script>

<template>
  <Head title="Course Offerings" />

  <AuthenticatedLayout>
    <div class="space-y-6">
      <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">Course Offerings</h1>
          <p class="text-sm text-gray-600">Semester ID: {{ semesterId }}</p>
        </div>
        <Link
          :href="route('admin.courses.import.create')"
          class="inline-flex items-center rounded bg-blue-600 px-3 py-1.5 text-sm font-semibold text-white shadow hover:bg-blue-500"
        >
          Import CSV
        </Link>
      </header>

      <section class="bg-white shadow rounded-lg p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-900">Add Offering</h2>
        <form class="grid gap-4 sm:grid-cols-[200px,1fr,auto]" @submit.prevent="submit">
          <div>
            <label for="course_code" class="block text-sm font-medium text-gray-700">Course Code</label>
            <input
              id="course_code"
              v-model="form.course_code"
              type="text"
              class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
              placeholder="e.g. INF101"
            />
            <p v-if="form.errors.course_code" class="mt-1 text-sm text-red-600">{{ form.errors.course_code }}</p>
          </div>
          <div>
            <label for="course_name" class="block text-sm font-medium text-gray-700">Course Name</label>
            <input
              id="course_name"
              v-model="form.course_name"
              type="text"
              class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
              placeholder="Course title"
            />
            <p v-if="form.errors.course_name" class="mt-1 text-sm text-red-600">{{ form.errors.course_name }}</p>
          </div>
          <div class="flex items-end">
            <button
              type="submit"
              :disabled="form.processing"
              class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
            >
              {{ form.processing ? 'Saving...' : 'Save' }}
            </button>
          </div>
        </form>
      </section>

      <section class="bg-white shadow rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50 text-left font-semibold uppercase tracking-wide text-gray-600">
            <tr>
              <th class="px-4 py-3">Code</th>
              <th class="px-4 py-3">Name</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!offerings.length">
              <td colspan="2" class="px-4 py-6 text-center text-sm text-gray-500">No offerings yet.</td>
            </tr>
            <tr v-for="offering in offerings" :key="offering.id" class="text-gray-800">
              <td class="px-4 py-3 font-medium">{{ offering.course_code }}</td>
              <td class="px-4 py-3">{{ offering.course_name }}</td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>
  </AuthenticatedLayout>
</template>
