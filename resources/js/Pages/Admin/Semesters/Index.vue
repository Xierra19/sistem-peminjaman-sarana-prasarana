<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { useTableSort } from '@/Composables/useTableSort'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  semesters: { type: Array, default: () => [] },
})

const toggleActive = (semester) => {
  router.post(route('admin.semesters.toggle-active', { semester: semester.id }), {}, { preserveScroll: true })
}

const destroySemester = (semester) => {
  if (confirm('Hapus semester ini?')) {
    router.delete(route('admin.semesters.destroy', { semester: semester.id }), { preserveScroll: true })
  }
}

const semestersList = computed(() => props.semesters ?? [])

const {
  sortedItems: sortedSemesters,
  toggleSort: toggleSemesterSort,
  sortDirection: semesterSortDirection,
  ariaSortValue: semesterAriaSortValue,
} = useTableSort(semestersList, {
  accessors: {
    year: (semester) => semester.year ?? '',
    term: (semester) => semester.term ?? '',
    is_active: (semester) => (semester.is_active ? 1 : 0),
    start_date: (semester) => (semester.start_date ? new Date(semester.start_date) : null),
  },
})
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Daftar Semester" />

    <div class="mx-auto max-w-6xl space-y-6 py-6">
      <div class="rounded-xl bg-white p-6 shadow-sm">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="text-2xl font-semibold text-gray-800">Daftar Semester</h1>
            <p class="mt-1 text-sm text-gray-500">Kelola data semester akademik.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <Link
              :href="route('admin.semesters.create')"
              class="inline-flex items-center rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            >
              + Tambah Semester
            </Link>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <SortableTh
                  class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase"
                  column="year"
                  label="Tahun"
                  :direction="semesterSortDirection('year')"
                  :aria-sort="semesterAriaSortValue('year')"
                  @toggle="toggleSemesterSort"
                />
                <SortableTh
                  class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase"
                  column="term"
                  label="Semester"
                  :direction="semesterSortDirection('term')"
                  :aria-sort="semesterAriaSortValue('term')"
                  @toggle="toggleSemesterSort"
                />
                <SortableTh
                  class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase"
                  column="is_active"
                  label="Aktif"
                  :direction="semesterSortDirection('is_active')"
                  :aria-sort="semesterAriaSortValue('is_active')"
                  @toggle="toggleSemesterSort"
                />
                <SortableTh
                  class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase"
                  column="start_date"
                  label="Periode"
                  :direction="semesterSortDirection('start_date')"
                  :aria-sort="semesterAriaSortValue('start_date')"
                  @toggle="toggleSemesterSort"
                />
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="sem in sortedSemesters" :key="sem.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm text-gray-900">{{ sem.year }}</td>
                <td class="px-4 py-3 text-sm text-gray-900 capitalize">{{ sem.term }}</td>
                <td class="px-4 py-3 text-sm">
                  <span
                    class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold"
                    :class="sem.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                  >
                    <span class="h-2 w-2 rounded-full" :class="sem.is_active ? 'bg-green-500' : 'bg-gray-400'" />
                    {{ sem.is_active ? 'Aktif' : 'Nonaktif' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  <template v-if="sem.start_date && sem.end_date">
                    {{ new Date(sem.start_date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) }}
                    –
                    {{ new Date(sem.end_date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) }}
                  </template>
                  <span v-else class="text-gray-400">Belum diatur</span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  <div class="flex flex-wrap gap-2">
                    <Link
                      :href="route('admin.semesters.defaults.index', [sem.id])"
                      class="text-blue-600 hover:text-blue-800"
                    >
                      Defaults
                    </Link>
                    <Link :href="route('admin.semester.edit', { semester: sem.id })" class="text-amber-600 hover:text-amber-800">Edit</Link>
                    <button type="button" @click="toggleActive(sem)" class="text-indigo-600 hover:text-indigo-800">
                      {{ sem.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                    <button type="button" @click="destroySemester(sem)" class="text-red-600 hover:text-red-800">Hapus</button>
                  </div>
                </td>
              </tr>
              <tr v-if="!semestersList.length">
                <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">Belum ada data semester.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
  
</template>
