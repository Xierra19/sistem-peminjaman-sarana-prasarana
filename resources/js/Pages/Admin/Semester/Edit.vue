<script setup>
import { computed, ref } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'

const props = defineProps({
  semester: {
    type: Object,
    required: true,
  },
  dateRangeStrings: {
    type: Object,
    required: true,
  },
  semesters: {
    type: Array,
    default: () => [],
  },
})

const form = useForm({
  id: props.semester.id ?? null,
  start_date: props.semester.start_date ?? '',
  end_date: props.semester.end_date ?? '',
  teaching_1_7_start_date: props.semester.teaching_1_7_start_date ?? '',
  teaching_1_7_end_date: props.semester.teaching_1_7_end_date ?? '',
  teaching_8_14_start_date: props.semester.teaching_8_14_start_date ?? '',
  teaching_8_14_end_date: props.semester.teaching_8_14_end_date ?? '',
  uts_start_date: props.semester.uts_start_date ?? '',
  uts_end_date: props.semester.uts_end_date ?? '',
  uas_start_date: props.semester.uas_start_date ?? '',
  uas_end_date: props.semester.uas_end_date ?? '',
  is_active: Boolean(props.semester.is_active ?? false),
})

const showIsActive = Object.prototype.hasOwnProperty.call(props.semester, 'is_active')

const summaryRanges = computed(() => ({
  semester: props.dateRangeStrings.semester ?? null,
  uts: props.dateRangeStrings.uts ?? null,
  uas: props.dateRangeStrings.uas ?? null,
}))

const selectedSemesterId = ref(props.semester.id ?? null)

const changeSemester = (id) => {
  if (!id || id === props.semester.id) {
    return
  }

  router.get(route('admin.semester.edit', { semester: id }), {}, {
    preserveState: false,
    preserveScroll: true,
  })
}

const submit = () => {
  form.put(route('admin.semester.update'), {
    preserveScroll: true,
  })
}

</script>

<template>
  <AuthenticatedLayout>
    <Head title="Atur Kalender Semester" />

    <div class="mx-auto max-w-6xl space-y-8 py-8">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Atur Kalender Semester</h1>
          <p class="mt-1 text-sm text-gray-500">Tetapkan rentang perkuliahan, UTS, dan UAS untuk semester aktif.</p>
        </div>

        <div v-if="props.semesters.length" class="w-full max-w-xs">
          <label for="semester_selector" class="block text-sm font-medium text-gray-700">Pilih Semester</label>
          <div class="relative mt-1">
            <select
              id="semester_selector"
              v-model="selectedSemesterId"
              class="w-full appearance-none rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              @change="changeSemester(selectedSemesterId)"
            >
              <option
                v-for="item in props.semesters"
                :key="item.id"
                :value="item.id"
              >
                {{ item.label }}
              </option>
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
              <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path
                  fill-rule="evenodd"
                  d="M5.23 7.21a.75.75 0 011.06.02L10 10.939l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"
                  clip-rule="evenodd"
                />
              </svg>
            </span>
          </div>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
          <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
            <h2 class="text-base font-semibold text-gray-800">Detail Periode</h2>
            <p class="mt-1 text-sm text-gray-500">Isi rentang tanggal semester, blok pertemuan, dan jendela ujian pada formulir di bawah.</p>
          </div>

          <form @submit.prevent="submit" class="space-y-8 px-6 py-6">
            <input type="hidden" v-model="form.id" />

            <div class="grid gap-6 md:grid-cols-2">
              <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700">Tanggal Mulai</label>
                <input
                  id="start_date"
                  v-model="form.start_date"
                  type="date"
                  class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                />
                <p v-if="form.errors.start_date" class="mt-1 text-sm text-red-600">{{ form.errors.start_date }}</p>
              </div>
              <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700">Tanggal Selesai</label>
                <input
                  id="end_date"
                  v-model="form.end_date"
                  type="date"
                  class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                />
                <p v-if="form.errors.end_date" class="mt-1 text-sm text-red-600">{{ form.errors.end_date }}</p>
              </div>
            </div>

            <div class="space-y-5 rounded-lg border border-gray-200 bg-gray-50 px-5 py-5">
              <div>
                <h3 class="text-sm font-semibold text-gray-800">Blok Pertemuan</h3>
                <p class="mt-1 text-sm text-gray-500">Pisahkan jadwal pertemuan awal dan lanjutan agar lebih mudah dipantau.</p>
              </div>
              <div class="grid gap-6 md:grid-cols-2">
                <div>
                  <label for="teaching_1_7_start_date" class="block text-sm font-medium text-gray-700">Pertemuan 1-7 Dimulai</label>
                  <input
                    id="teaching_1_7_start_date"
                    v-model="form.teaching_1_7_start_date"
                    type="date"
                    class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                  />
                  <p v-if="form.errors.teaching_1_7_start_date" class="mt-1 text-sm text-red-600">{{ form.errors.teaching_1_7_start_date }}</p>
                </div>
                <div>
                  <label for="teaching_1_7_end_date" class="block text-sm font-medium text-gray-700">Pertemuan 1-7 Selesai</label>
                  <input
                    id="teaching_1_7_end_date"
                    v-model="form.teaching_1_7_end_date"
                    type="date"
                    class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                  />
                  <p v-if="form.errors.teaching_1_7_end_date" class="mt-1 text-sm text-red-600">{{ form.errors.teaching_1_7_end_date }}</p>
                </div>
                <div>
                  <label for="teaching_8_14_start_date" class="block text-sm font-medium text-gray-700">Pertemuan 8-14 Dimulai</label>
                  <input
                    id="teaching_8_14_start_date"
                    v-model="form.teaching_8_14_start_date"
                    type="date"
                    class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                  />
                  <p v-if="form.errors.teaching_8_14_start_date" class="mt-1 text-sm text-red-600">{{ form.errors.teaching_8_14_start_date }}</p>
                </div>
                <div>
                  <label for="teaching_8_14_end_date" class="block text-sm font-medium text-gray-700">Pertemuan 8-14 Selesai</label>
                  <input
                    id="teaching_8_14_end_date"
                    v-model="form.teaching_8_14_end_date"
                    type="date"
                    class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                  />
                  <p v-if="form.errors.teaching_8_14_end_date" class="mt-1 text-sm text-red-600">{{ form.errors.teaching_8_14_end_date }}</p>
                </div>
              </div>
            </div>

            <div class="space-y-5 rounded-lg border border-gray-200 bg-gray-50 px-5 py-5">
              <div>
                <h3 class="text-sm font-semibold text-gray-800">Jendela Ujian</h3>
                <p class="mt-1 text-sm text-gray-500">Tetapkan tanggal ujian tengah semester dan ujian akhir.</p>
              </div>
              <div class="grid gap-6 md:grid-cols-2">
                <div>
                  <label for="uts_start_date" class="block text-sm font-medium text-gray-700">Tanggal Mulai UTS</label>
                  <input
                    id="uts_start_date"
                    v-model="form.uts_start_date"
                    type="date"
                    class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                  />
                  <p v-if="form.errors.uts_start_date" class="mt-1 text-sm text-red-600">{{ form.errors.uts_start_date }}</p>
                </div>
                <div>
                  <label for="uts_end_date" class="block text-sm font-medium text-gray-700">Tanggal Selesai UTS</label>
                  <input
                    id="uts_end_date"
                    v-model="form.uts_end_date"
                    type="date"
                    class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                  />
                  <p v-if="form.errors.uts_end_date" class="mt-1 text-sm text-red-600">{{ form.errors.uts_end_date }}</p>
                </div>
                <div>
                  <label for="uas_start_date" class="block text-sm font-medium text-gray-700">Tanggal Mulai UAS</label>
                  <input
                    id="uas_start_date"
                    v-model="form.uas_start_date"
                    type="date"
                    class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                  />
                  <p v-if="form.errors.uas_start_date" class="mt-1 text-sm text-red-600">{{ form.errors.uas_start_date }}</p>
                </div>
                <div>
                  <label for="uas_end_date" class="block text-sm font-medium text-gray-700">Tanggal Selesai UAS</label>
                  <input
                    id="uas_end_date"
                    v-model="form.uas_end_date"
                    type="date"
                    class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                  />
                  <p v-if="form.errors.uas_end_date" class="mt-1 text-sm text-red-600">{{ form.errors.uas_end_date }}</p>
                </div>
              </div>
            </div>

            <div class="space-y-6 border-t border-gray-100 pt-6">
              <label
                v-if="showIsActive"
                class="flex cursor-pointer items-start gap-3 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm"
              >
                <input
                  id="is_active"
                  v-model="form.is_active"
                  type="checkbox"
                  class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
                <span>
                  <span class="block font-semibold text-blue-700">Tandai sebagai semester aktif</span>
                  <span class="mt-0.5 block text-xs text-blue-600">Semester aktif akan digunakan sebagai acuan default di seluruh sistem.</span>
                </span>
              </label>

              <div class="flex flex-col gap-3 text-sm text-gray-600 lg:flex-row lg:items-center lg:justify-between">
                <p class="text-gray-500">Pastikan semua tanggal sudah tepat sebelum menyimpan perubahan.</p>
                <button
                  type="submit"
                  :disabled="form.processing"
                  class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  <span v-if="form.processing">Menyimpan...</span>
                  <span v-else>Simpan Perubahan</span>
                </button>
              </div>
            </div>
          </form>
        </div>

        <aside class="space-y-5 rounded-xl border border-gray-200 bg-white px-6 py-6 shadow-sm">
          <div>
            <h2 class="text-base font-semibold text-gray-800">Ringkasan Jadwal</h2>
            <p class="mt-1 text-sm text-gray-500">Pratinjau singkat tanggal yang sudah diisi pada formulir.</p>
          </div>
          <div class="space-y-4 text-sm text-gray-600">
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
              <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Periode Semester</div>
              <div class="mt-1 text-sm font-medium text-gray-800">{{ summaryRanges.semester ?? 'Belum diatur' }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
              <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Window UTS</div>
              <div class="mt-1 text-sm font-medium text-gray-800">{{ summaryRanges.uts ?? 'Belum diatur' }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
              <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Window UAS</div>
              <div class="mt-1 text-sm font-medium text-gray-800">{{ summaryRanges.uas ?? 'Belum diatur' }}</div>
            </div>
          </div>
          <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-700">
            <span class="font-semibold">Tips:</span> Pastikan rentang UTS dan UAS berada di dalam periode semester utama.
          </div>
        </aside>
      </div>
    </div>

  </AuthenticatedLayout>
</template>
