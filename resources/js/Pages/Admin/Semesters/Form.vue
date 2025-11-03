<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
  semester: { type: Object, default: () => ({}) },
  mode: { type: String, default: 'create' },
})

const isEdit = props.mode === 'edit' && !!props.semester?.id

const form = useForm({
  year: props.semester?.year ?? '',
  term: props.semester?.term ?? '',
  anchor_date: props.semester?.anchor_date ?? '',
  is_active: !!props.semester?.is_active,
  start_date: props.semester?.start_date ?? '',
  end_date: props.semester?.end_date ?? '',
  uts_start_date: props.semester?.uts_start_date ?? '',
  uts_end_date: props.semester?.uts_end_date ?? '',
  uas_start_date: props.semester?.uas_start_date ?? '',
  uas_end_date: props.semester?.uas_end_date ?? '',
  uts_week: props.semester?.uts_week ?? '',
  uas_week: props.semester?.uas_week ?? '',
})

const submit = () => {
  if (isEdit) {
    form.put(route('admin.semesters.update', props.semester.id))
  } else {
    form.post(route('admin.semesters.store'))
  }
}
</script>

<template>
  <AuthenticatedLayout>
    <Head :title="isEdit ? 'Ubah Semester' : 'Tambah Semester'" />

    <div class="mx-auto max-w-3xl space-y-6 py-6">
      <div class="rounded-xl bg-white p-6 shadow-sm">
        <h1 class="mb-4 text-2xl font-semibold text-gray-800">{{ isEdit ? 'Ubah Semester' : 'Tambah Semester' }}</h1>
        <form @submit.prevent="submit" class="space-y-6">
          <div class="grid gap-6 md:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-700">Tahun *</label>
              <input v-model="form.year" type="number" min="2000" max="2100" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
              <div v-if="form.errors.year" class="mt-1 text-sm text-red-600">{{ form.errors.year }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Semester *</label>
              <select v-model="form.term" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                <option value="">-- Pilih --</option>
                <option value="ganjil">Ganjil</option>
                <option value="genap">Genap</option>
              </select>
              <div v-if="form.errors.term" class="mt-1 text-sm text-red-600">{{ form.errors.term }}</div>
            </div>
          </div>

          <div class="grid gap-6 md:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-700">Tanggal Anchor</label>
              <input v-model="form.anchor_date" type="date" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
              <div v-if="form.errors.anchor_date" class="mt-1 text-sm text-red-600">{{ form.errors.anchor_date }}</div>
            </div>
            <div class="flex items-center gap-2">
              <input id="is_active" v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
              <label for="is_active" class="text-sm font-medium text-gray-700">Jadikan semester aktif</label>
            </div>
          </div>

          <div class="grid gap-6 md:grid-cols-3">
            <div>
              <label class="block text-sm font-medium text-gray-700">Tanggal Mulai</label>
              <input v-model="form.start_date" type="date" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
              <div v-if="form.errors.start_date" class="mt-1 text-sm text-red-600">{{ form.errors.start_date }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Tanggal Selesai</label>
              <input v-model="form.end_date" type="date" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
              <div v-if="form.errors.end_date" class="mt-1 text-sm text-red-600">{{ form.errors.end_date }}</div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Minggu UTS</label>
                <input v-model="form.uts_week" type="number" min="1" max="30" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
                <div v-if="form.errors.uts_week" class="mt-1 text-sm text-red-600">{{ form.errors.uts_week }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Minggu UAS</label>
                <input v-model="form.uas_week" type="number" min="1" max="30" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
                <div v-if="form.errors.uas_week" class="mt-1 text-sm text-red-600">{{ form.errors.uas_week }}</div>
              </div>
            </div>
          </div>

          <div class="grid gap-6 md:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-700">Tanggal Mulai UTS</label>
              <input v-model="form.uts_start_date" type="date" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
              <div v-if="form.errors.uts_start_date" class="mt-1 text-sm text-red-600">{{ form.errors.uts_start_date }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Tanggal Selesai UTS</label>
              <input v-model="form.uts_end_date" type="date" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
              <div v-if="form.errors.uts_end_date" class="mt-1 text-sm text-red-600">{{ form.errors.uts_end_date }}</div>
            </div>
          </div>

          <div class="grid gap-6 md:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-700">Tanggal Mulai UAS</label>
              <input v-model="form.uas_start_date" type="date" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
              <div v-if="form.errors.uas_start_date" class="mt-1 text-sm text-red-600">{{ form.errors.uas_start_date }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Tanggal Selesai UAS</label>
              <input v-model="form.uas_end_date" type="date" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
              <div v-if="form.errors.uas_end_date" class="mt-1 text-sm text-red-600">{{ form.errors.uas_end_date }}</div>
            </div>
          </div>

          <div class="flex justify-end gap-3">
            <Link :href="route('admin.semesters.index')" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Batal</Link>
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700" :disabled="form.processing">
              {{ isEdit ? 'Simpan Perubahan' : 'Simpan Semester' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
