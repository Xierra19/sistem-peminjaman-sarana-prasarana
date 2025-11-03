// file: resources/js/Pages/Admin/Offerings/Show.vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'

const props = defineProps({
  offering: {
    type: Object,
    required: true,
  },
  meetings: {
    type: Array,
    default: () => [],
  },
  exams: {
    type: Object,
    required: true,
  },
  ranges: {
    type: Object,
    default: () => ({ uts: null, uas: null }),
  },
})

const createExamForm = (type) => {
  const data = props.exams?.[type.toLowerCase()] ?? {}

  return useForm({
    exam_type: type,
    exam_date: data.exam_date ?? '',
    room_code: data.room_code ?? '',
    start_time: data.start_time ?? '',
    end_time: data.end_time ?? '',
  })
}

const examForms = {
  UTS: createExamForm('UTS'),
  UAS: createExamForm('UAS'),
}

watch(
  () => props.exams,
  (value) => {
    const uts = value?.uts ?? {}
    const uas = value?.uas ?? {}

    Object.assign(examForms.UTS, {
      exam_date: uts.exam_date ?? '',
      room_code: uts.room_code ?? '',
      start_time: uts.start_time ?? '',
      end_time: uts.end_time ?? '',
    })

    Object.assign(examForms.UAS, {
      exam_date: uas.exam_date ?? '',
      room_code: uas.room_code ?? '',
      start_time: uas.start_time ?? '',
      end_time: uas.end_time ?? '',
    })
  }
)

const submitExam = (type) => {
  const form = examForms[type]
  form.put(route('admin.offerings.exam.update', props.offering.id), {
    preserveScroll: true,
  })
}

const weekBadge = (value) => {
  if (!value) {
    return { label: '-', class: 'bg-gray-100 text-gray-600' }
  }

  return {
    label: `Week ${value}`,
    class: value === 1 ? 'bg-indigo-100 text-indigo-700' : 'bg-purple-100 text-purple-700',
  }
}

const meetingCards = computed(() => props.meetings ?? [])

const examCards = computed(() => [
  {
    key: 'UTS',
    title: 'UTS',
    form: examForms.UTS,
    range: props.ranges?.uts ?? null,
    payload: props.exams?.uts ?? {},
  },
  {
    key: 'UAS',
    title: 'UAS',
    form: examForms.UAS,
    range: props.ranges?.uas ?? null,
    payload: props.exams?.uas ?? {},
  },
])
</script>

<template>
  <Head :title="`Offering ${props.offering.course_code}`" />

  <AuthenticatedLayout>
    <div class="space-y-6">
      <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-xl font-semibold text-gray-800">
          {{ props.offering.course_code }} - {{ props.offering.course_name }}
        </h1>
        <p v-if="props.offering.class_group" class="mt-1 text-sm text-gray-500">
          Class Group: {{ props.offering.class_group }}
        </p>
      </div>

      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Meetings</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div v-for="meeting in meetingCards" :key="meeting.meeting_no" class="rounded border border-gray-200 p-4">
            <p class="text-sm font-semibold text-gray-700">Pertemuan {{ meeting.meeting_no }}</p>
            <p class="mt-1 text-sm text-gray-900">
              {{ meeting.meeting_date ?? '-' }}
            </p>
            <p class="mt-1 text-xs text-gray-500">
              {{ meeting.room ? meeting.room.name : 'No room assigned' }}
            </p>
          </div>
        </div>
      </div>

      <div class="grid gap-6 md:grid-cols-2">
        <div
          v-for="exam in examCards"
          :key="exam.key"
          class="bg-white rounded-lg shadow p-6 space-y-4"
        >
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-semibold text-gray-800">Exam {{ exam.title }}</h3>
              <p v-if="exam.range" class="text-xs text-gray-500">Window: {{ exam.range }}</p>
            </div>
            <span
              class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold"
              :class="weekBadge(exam.payload?.week_seq).class"
            >
              {{ exam.payload?.exam_date ? weekBadge(exam.payload?.week_seq).label : '-' }}
            </span>
          </div>

          <form @submit.prevent="submitExam(exam.key)" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Date</label>
              <input
                v-model="exam.form.exam_date"
                type="date"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
              />
              <p v-if="exam.form.errors.exam_date" class="mt-1 text-sm text-red-600">{{ exam.form.errors.exam_date }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Room Code</label>
              <input
                v-model="exam.form.room_code"
                type="text"
                placeholder="e.g. LAB-101"
                class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
              />
              <p v-if="exam.form.errors.room_code" class="mt-1 text-sm text-red-600">{{ exam.form.errors.room_code }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label class="block text-sm font-medium text-gray-700">Start Time</label>
                <input
                  v-model="exam.form.start_time"
                  type="time"
                  class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                />
                <p v-if="exam.form.errors.start_time" class="mt-1 text-sm text-red-600">{{ exam.form.errors.start_time }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">End Time</label>
                <input
                  v-model="exam.form.end_time"
                  type="time"
                  class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                />
                <p v-if="exam.form.errors.end_time" class="mt-1 text-sm text-red-600">{{ exam.form.errors.end_time }}</p>
              </div>
            </div>

            <div class="flex justify-end">
              <button
                type="submit"
                class="inline-flex items-center rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                :disabled="exam.form.processing"
              >
                {{ exam.form.processing ? 'Saving...' : 'Save Exam' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
