// file: resources/js/Pages/Admin/Offerings/Index.vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

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

const badgeClass = (value) => {
  if (!value) return 'bg-gray-100 text-gray-600'
  return value === 1 ? 'bg-indigo-100 text-indigo-700' : 'bg-purple-100 text-purple-700'
}
</script>

<template>
  <Head title="Course Offerings" />

  <AuthenticatedLayout>
    <div class="bg-white rounded-lg shadow">
      <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
        <div>
          <h1 class="text-lg font-semibold text-gray-800">Course Offerings</h1>
          <p class="text-sm text-gray-500">Semester ID: {{ props.semesterId }}</p>
        </div>
        <Link
          :href="route('admin.courses.import.create')"
          class="inline-flex items-center rounded bg-blue-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-blue-700"
        >
          Import Courses
        </Link>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr class="text-left font-semibold uppercase tracking-wider text-gray-500">
              <th class="px-4 py-3">Code</th>
              <th class="px-4 py-3">Course</th>
              <th class="px-4 py-3">Meetings</th>
              <th class="px-4 py-3">UTS</th>
              <th class="px-4 py-3">UAS</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-gray-700">
            <tr v-if="!props.offerings.length">
              <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">No offerings found.</td>
            </tr>
            <tr v-for="offering in props.offerings" :key="offering.id">
              <td class="px-4 py-3 font-semibold text-gray-800">{{ offering.course_code }}</td>
              <td class="px-4 py-3">
                <div class="text-sm font-medium text-gray-800">
                  {{ offering.course_name }}
                </div>
                <div v-if="offering.class_group" class="text-xs text-gray-500">
                  Group {{ offering.class_group }}
                </div>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ offering.meetings_count }}</td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold"
                  :class="badgeClass(offering.uts_week_seq)"
                >
                  {{ offering.has_uts ? `Week ${offering.uts_week_seq ?? '-'}` : '-' }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold"
                  :class="badgeClass(offering.uas_week_seq)"
                >
                  {{ offering.has_uas ? `Week ${offering.uas_week_seq ?? '-'}` : '-' }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <Link
                  :href="route('admin.offerings.show', offering.id)"
                  class="text-sm font-semibold text-blue-600 hover:text-blue-700"
                >
                  View
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
