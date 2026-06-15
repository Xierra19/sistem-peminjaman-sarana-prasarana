<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import flatpickr from 'flatpickr'
import 'flatpickr/dist/flatpickr.css'
import { Indonesian } from 'flatpickr/dist/l10n/id'

const props = defineProps({
  campuses: {
    type: Array,
    default: () => [],
  },
  minimumBookingDate: {
    type: String,
    required: true,
  },
  formMode: {
    type: String,
    default: 'create',
  },
  sourceBookingId: {
    type: Number,
    default: null,
  },
  initialData: {
    type: Object,
    default: null,
  },
  revisionNote: {
    type: String,
    default: null,
  },
  existingAttachment: {
    type: String,
    default: null,
  },
})

let nextScheduleKey = 1

const createSchedule = (data = {}) => ({
  _key: nextScheduleKey++,
  campus_id: data.campus_id ?? '',
  building_id: data.building_id ?? '',
  room_id: data.room_id ?? '',
  dates: [...(data.dates ?? [])],
  start_time: data.start_time ?? '',
  end_time: data.end_time ?? '',
})

const form = useForm({
  title: props.initialData?.title ?? '',
  description: props.initialData?.description ?? '',
  schedules: props.initialData?.schedules?.length
    ? props.initialData.schedules.map((schedule) => createSchedule(schedule))
    : [createSchedule()],
  attachment: null,
  resubmitted_from_id: props.formMode === 'resubmission' ? props.sourceBookingId : null,
})

const availabilityByKey = ref({})
const datePickers = new Map()
const availabilityRequestIds = new Map()

const formatDateForInput = (date) => {
  const value = new Date(date)
  const year = value.getFullYear()
  const month = String(value.getMonth() + 1).padStart(2, '0')
  const day = String(value.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

const minimumBookingDate = computed(() => props.minimumBookingDate)
const isRevision = computed(() => props.formMode === 'revision')
const isResubmission = computed(() => props.formMode === 'resubmission')
const pageTitle = computed(() => {
  if (isRevision.value) return 'Perbaiki Booking Ruangan'
  if (isResubmission.value) return 'Ajukan Ulang Booking Ruangan'
  return 'Ajukan Booking Ruangan'
})
const hasInvalidPrefilledDates = computed(() =>
  form.schedules.some((schedule) =>
    schedule.dates.some((date) => date < minimumBookingDate.value),
  ),
)

const timeSlots = (() => {
  const slots = []

  for (let hour = 7; hour <= 21; hour++) {
    for (let minute = 0; minute < 60; minute += 30) {
      if (hour === 21 && minute > 0) break

      slots.push(`${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`)
    }
  }

  return slots
})()

const findById = (items, id) => items.find((item) => String(item.id) === String(id))

const buildingsFor = (schedule) =>
  findById(props.campuses, schedule.campus_id)?.buildings ?? []

const roomsFor = (schedule) =>
  findById(buildingsFor(schedule), schedule.building_id)?.rooms ?? []

const selectedRoom = (schedule) => findById(roomsFor(schedule), schedule.room_id)

const availabilityFor = (schedule) =>
  availabilityByKey.value[schedule._key] ?? {
    loading: false,
    loaded: false,
    available: true,
    bookings: [],
    message: '',
  }

const isBlockingBooking = (booking) => booking.status === 'approved'
const blockingBookings = (schedule) =>
  availabilityFor(schedule).bookings.filter(isBlockingBooking)
const queuedBookings = (schedule) =>
  availabilityFor(schedule).bookings.filter((booking) =>
    ['waiting', 'pending', 'requested'].includes(booking.status),
  )

const overlapsSelectedTime = (schedule, booking) =>
  Boolean(
    schedule.start_time
      && schedule.end_time
      && schedule.start_time < booking.end
      && schedule.end_time > booking.start,
  )

const selectedBlockingBookings = (schedule) =>
  blockingBookings(schedule).filter((booking) => overlapsSelectedTime(schedule, booking))

const selectedQueuedBookings = (schedule) =>
  queuedBookings(schedule).filter((booking) => overlapsSelectedTime(schedule, booking))

const hasApprovedScheduleConflict = computed(() =>
  form.schedules.some((schedule) => selectedBlockingBookings(schedule).length > 0),
)

const hasUncheckedCompleteSchedule = computed(() =>
  form.schedules.some((schedule) =>
    Boolean(
      schedule.room_id
        && schedule.dates.length
        && schedule.start_time
        && schedule.end_time,
    )
      && !availabilityFor(schedule).loaded
      && !availabilityFor(schedule).message,
  ),
)

const resetAvailability = (schedule) => {
  availabilityRequestIds.set(schedule._key, (availabilityRequestIds.get(schedule._key) ?? 0) + 1)
  availabilityByKey.value[schedule._key] = {
    loading: false,
    loaded: false,
    available: true,
    bookings: [],
    message: '',
  }
}

const scheduleDates = (schedule) => [...schedule.dates].sort()

const datePickerRef = (element, schedule) => {
  const existing = datePickers.get(schedule._key)

  if (!element) {
    existing?.destroy()
    datePickers.delete(schedule._key)
    return
  }

  if (existing) return

  const instance = flatpickr(element, {
    mode: 'multiple',
    dateFormat: 'Y-m-d',
    altInput: true,
    altFormat: 'j F Y',
    conjunction: ', ',
    locale: Indonesian,
    minDate: minimumBookingDate.value,
    defaultDate: schedule.dates,
    disableMobile: true,
    onChange: (_selectedDates, dateString) => {
      schedule.dates = dateString ? dateString.split(', ').sort() : []
      onScheduleTargetChange(schedule)
    },
  })

  instance.altInput?.classList.add(
    'min-h-12',
    'w-full',
    'rounded-xl',
    'border',
    'border-slate-200',
    'bg-slate-50',
    'px-3',
    'text-sm',
    'text-slate-900',
    'transition',
    'focus:border-blue-500',
    'focus:bg-white',
    'focus:ring-4',
    'focus:ring-blue-500/10',
    'dark:border-slate-600',
    'dark:bg-slate-900/40',
    'dark:text-white',
    'dark:focus:bg-slate-900',
  )

  datePickers.set(schedule._key, instance)
}

const onCampusChange = (schedule) => {
  schedule.building_id = ''
  schedule.room_id = ''
  schedule.start_time = ''
  schedule.end_time = ''
  resetAvailability(schedule)
}

const onBuildingChange = (schedule) => {
  schedule.room_id = ''
  schedule.start_time = ''
  schedule.end_time = ''
  resetAvailability(schedule)
}

const onScheduleTargetChange = (schedule) => {
  schedule.start_time = ''
  schedule.end_time = ''
  loadAvailability(schedule)
}

const loadAvailability = async (schedule) => {
  const dates = scheduleDates(schedule)
  const requestId = (availabilityRequestIds.get(schedule._key) ?? 0) + 1
  availabilityRequestIds.set(schedule._key, requestId)

  if (!schedule.room_id || !dates.length) {
    availabilityByKey.value[schedule._key] = {
      loading: false,
      loaded: false,
      available: true,
      bookings: [],
      message: '',
    }
    return
  }

  availabilityByKey.value[schedule._key] = {
    loading: true,
    loaded: false,
    available: true,
    bookings: [],
    message: '',
  }

  try {
    const response = await fetch(
      route('rooms.availability', {
        room: schedule.room_id,
        dates,
      }),
      {
        headers: {
          Accept: 'application/json',
        },
      },
    )

    if (!response.ok) throw new Error('Availability request failed')

    const data = await response.json()

    if (availabilityRequestIds.get(schedule._key) !== requestId) return

    availabilityByKey.value[schedule._key] = {
      loading: false,
      loaded: true,
      available: data.available !== false,
      bookings: (data.daily_bookings ?? []).flatMap((entry) =>
        (entry.bookings ?? []).map((booking) => ({ ...booking, date: entry.date })),
      ),
      message: data.message ?? '',
    }
  } catch {
    if (availabilityRequestIds.get(schedule._key) !== requestId) return

    availabilityByKey.value[schedule._key] = {
      loading: false,
      loaded: true,
      available: true,
      bookings: [],
      message: 'Ketersediaan belum dapat dimuat. Validasi tetap dilakukan saat pengajuan dikirim.',
    }
  }
}

const isStartBlocked = (schedule, time) =>
  blockingBookings(schedule).some(
    (booking) => time >= booking.start && time < booking.end,
  )

const wouldOverlapExisting = (schedule, time) =>
  blockingBookings(schedule).some(
    (booking) => schedule.start_time < booking.end && time > booking.start,
  )

const endTimeDisabled = (schedule, time) =>
  !schedule.start_time ||
  time <= schedule.start_time ||
  wouldOverlapExisting(schedule, time)

const onStartTimeChange = (schedule) => {
  if (
    schedule.end_time &&
    (schedule.end_time <= schedule.start_time || wouldOverlapExisting(schedule, schedule.end_time))
  ) {
    schedule.end_time = ''
  }
}

const addSchedule = () => {
  form.schedules.push(createSchedule())
}

const removeSchedule = (index) => {
  if (form.schedules.length === 1) return

  const [removed] = form.schedules.splice(index, 1)
  datePickers.get(removed._key)?.destroy()
  datePickers.delete(removed._key)
  availabilityRequestIds.delete(removed._key)
  delete availabilityByKey.value[removed._key]
}

const scheduleError = (index, field) => form.errors[`schedules.${index}.${field}`]
const scheduleDatesError = (index) =>
  scheduleError(index, 'dates') ?? scheduleError(index, 'dates.0') ?? scheduleError(index, 'date')

const fileName = computed(() => form.attachment?.name ?? '')
const completedScheduleCount = computed(() =>
  form.schedules.filter(
    (schedule) =>
      schedule.room_id &&
      schedule.dates.length &&
      schedule.start_time &&
      schedule.end_time,
  ).length,
)

const isScheduleComplete = (schedule) =>
  Boolean(schedule.room_id && schedule.dates.length && schedule.start_time && schedule.end_time)

const selectedCampus = (schedule) => findById(props.campuses, schedule.campus_id)
const selectedBuilding = (schedule) => findById(buildingsFor(schedule), schedule.building_id)

const formatScheduleDate = (value) => {
  if (!value) return 'Tanggal belum dipilih'

  return new Intl.DateTimeFormat('id-ID', {
    weekday: 'short',
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(`${value}T00:00:00`))
}

const formatScheduleDates = (schedule) => {
  const dates = scheduleDates(schedule)

  if (!dates.length) return 'Tanggal belum dipilih'
  if (dates.length === 1) return formatScheduleDate(dates[0])

  return `${dates.length} tanggal: ${dates.map((date) => formatScheduleDate(date)).join(', ')}`
}

const groupedAvailability = (bookings) => {
  const grouped = new Map()

  for (const booking of bookings) {
    if (!grouped.has(booking.date)) grouped.set(booking.date, [])
    grouped.get(booking.date).push(`${booking.start}-${booking.end}`)
  }

  return [...grouped.entries()].map(([date, intervals]) => ({
    date,
    label: formatScheduleDate(date),
    intervals: intervals.join(', '),
  }))
}

const scheduleLocationSummary = (schedule) =>
  [selectedRoom(schedule)?.name, selectedBuilding(schedule)?.name, selectedCampus(schedule)?.name]
    .filter(Boolean)
    .join(' · ') || 'Lokasi belum lengkap'

const scheduleTimeSummary = (schedule) => {
  if (!schedule.start_time || !schedule.end_time) return 'Jam belum lengkap'
  return `${schedule.start_time} - ${schedule.end_time} WIB`
}

const onAttachmentChange = (event) => {
  form.attachment = event.target.files?.[0] ?? null
}

const submit = () => {
  if (hasApprovedScheduleConflict.value || hasUncheckedCompleteSchedule.value) {
    return
  }

  if (isRevision.value) {
    form
      .transform((data) => ({ ...data, _method: 'put' }))
      .post(route('bookings.update', props.sourceBookingId), {
        forceFormData: true,
        preserveScroll: true,
      })
    return
  }

  form.post(route('bookings.store'), {
    forceFormData: true,
    preserveScroll: true,
  })
}

onMounted(() => {
  for (const schedule of form.schedules) {
    if (schedule.room_id && schedule.dates.length) {
      loadAvailability(schedule)
    }
  }
})

onBeforeUnmount(() => {
  datePickers.forEach((picker) => picker.destroy())
  datePickers.clear()
})
</script>

<template>
  <AuthenticatedLayout>
    <Head :title="pageTitle" />

    <div class="mx-auto max-w-6xl space-y-6 pb-24">
      <section class="space-y-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:p-6">
        <div class="border-b border-slate-200 pb-5 dark:border-slate-700">
          <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">{{ pageTitle }}</h1>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            {{ isRevision
              ? 'Perbaiki data berdasarkan catatan admin. Tenggat H+3 tetap mengacu pada pengajuan awal.'
              : isResubmission
                ? 'Data lama sudah disalin. Pengajuan baru tetap mengikuti tenggat H+3 dari hari ini.'
                : 'Tambahkan satu atau beberapa ruangan dengan jadwal penggunaan masing-masing.' }}
          </p>
        </div>

        <div
          v-if="revisionNote"
          class="rounded-2xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-800 dark:border-violet-900 dark:bg-violet-950/40 dark:text-violet-200"
        >
          <p class="font-semibold">Catatan admin</p>
          <p class="mt-1">{{ revisionNote }}</p>
        </div>

        <div
          v-if="hasInvalidPrefilledDates"
          class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300"
        >
          Sebagian jadwal lama tidak lagi memenuhi batas minimum {{ minimumBookingDate }}. Ganti tanggal tersebut sebelum mengirim.
        </div>

        <div class="grid gap-4 rounded-2xl bg-slate-50 p-4 text-xs text-slate-600 dark:bg-slate-700/50 dark:text-slate-300 sm:grid-cols-3">
          <div class="flex items-start gap-3">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">1</span>
            <div>
              <p class="font-semibold text-slate-800 dark:text-slate-200">Informasi Kegiatan</p>
              <p class="mt-0.5">Isi judul dan deskripsi kegiatan.</p>
            </div>
          </div>

          <div class="flex items-start gap-3">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">2</span>
            <div>
              <p class="font-semibold text-slate-800 dark:text-slate-200">Tambah Ruangan</p>
              <p class="mt-0.5">Pilih ruangan, tanggal, dan jam.</p>
            </div>
          </div>

          <div class="flex items-start gap-3">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">3</span>
            <div>
              <p class="font-semibold text-slate-800 dark:text-slate-200">Lampiran</p>
              <p class="mt-0.5">Tambahkan dokumen pendukung bila ada.</p>
            </div>
          </div>
        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
          <header class="flex items-center gap-4 border-b border-slate-100 px-5 py-5 dark:border-slate-700 sm:px-7">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-600 text-base font-bold text-white shadow-lg shadow-blue-600/20">1</span>
            <div>
              <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Informasi Kegiatan</h2>
              <p class="text-sm text-slate-500 dark:text-slate-400">Berlaku untuk seluruh ruangan di dalam pengajuan ini.</p>
            </div>
          </header>

          <div class="grid gap-6 p-5 sm:p-7">
            <label class="space-y-2">
              <span class="flex items-center gap-1 text-sm font-semibold text-slate-700 dark:text-slate-200">
                Judul Kegiatan <span class="text-rose-500">*</span>
              </span>
              <input
                v-model="form.title"
                type="text"
                maxlength="255"
                class="min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-900 shadow-inner shadow-slate-100/60 transition placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white dark:shadow-none dark:placeholder:text-slate-500 dark:focus:bg-slate-900"
                placeholder="Contoh: Seminar Program Studi"
              />
              <span v-if="form.errors.title" class="text-sm font-medium text-rose-600 dark:text-rose-400">{{ form.errors.title }}</span>
            </label>

            <label class="space-y-2">
              <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Deskripsi Kegiatan</span>
              <textarea
                v-model="form.description"
                rows="4"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-900 shadow-inner shadow-slate-100/60 transition placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white dark:shadow-none dark:placeholder:text-slate-500 dark:focus:bg-slate-900"
                placeholder="Jelaskan keperluan, peserta, dan rangkaian kegiatan."
              />
              <span v-if="form.errors.description" class="text-sm font-medium text-rose-600 dark:text-rose-400">{{ form.errors.description }}</span>
            </label>
          </div>
        </section>

        <section class="space-y-4">
          <div class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div class="flex items-center gap-4">
              <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-600 text-base font-bold text-white shadow-lg shadow-blue-600/20">2</span>
              <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Ruangan dan Jadwal</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Satu kartu dapat memakai ruangan dan jam yang sama pada beberapa tanggal.</p>
              </div>
            </div>
            <button
              type="button"
              class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 text-sm font-semibold text-white shadow-lg shadow-blue-600/20 transition hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-blue-600/30 focus:outline-none focus:ring-4 focus:ring-blue-500/20"
              @click="addSchedule"
            >
              <span class="text-xl leading-none">+</span>
              Tambah Jadwal
            </button>
          </div>

          <p v-if="form.errors.schedules" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">
            {{ form.errors.schedules }}
          </p>

          <article
            v-for="(schedule, index) in form.schedules"
            :key="schedule._key"
            class="group overflow-hidden rounded-3xl border bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:bg-slate-800"
            :class="selectedBlockingBookings(schedule).length
              ? 'border-rose-300 dark:border-rose-900/70'
              : isScheduleComplete(schedule)
                ? 'border-emerald-200 dark:border-emerald-900/70'
                : 'border-slate-200 hover:border-blue-300 dark:border-slate-700 dark:hover:border-blue-700'"
          >
            <header class="flex flex-col gap-4 border-b border-slate-100 bg-slate-50/70 px-5 py-5 dark:border-slate-700 dark:bg-slate-900/30 sm:flex-row sm:items-center sm:justify-between sm:px-7">
              <div class="flex min-w-0 items-center gap-4">
                <span
                  class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold"
                  :class="selectedBlockingBookings(schedule).length
                    ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'
                    : isScheduleComplete(schedule)
                      ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                      : 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300'"
                >
                  {{ index + 1 }}
                </span>
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-semibold text-slate-900 dark:text-white">Jadwal {{ index + 1 }}</h3>
                    <span
                      class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                      :class="selectedBlockingBookings(schedule).length
                        ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'
                        : isScheduleComplete(schedule)
                          ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                          : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'"
                    >
                      {{ selectedBlockingBookings(schedule).length
                        ? 'Jadwal bentrok'
                        : isScheduleComplete(schedule)
                          ? 'Lengkap'
                          : 'Belum lengkap' }}
                    </span>
                  </div>
                  <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ scheduleLocationSummary(schedule) }}</p>
                </div>
              </div>
              <button
                v-if="form.schedules.length > 1"
                type="button"
                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-3 text-sm font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-50 dark:border-rose-900 dark:bg-slate-800 dark:text-rose-400 dark:hover:bg-rose-950/40"
                @click="removeSchedule(index)"
              >
                <span aria-hidden="true">×</span>
                Hapus
              </button>
            </header>

            <div class="grid gap-6 p-5 sm:grid-cols-2 sm:p-7 lg:grid-cols-3">
              <label class="space-y-2">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Kampus</span>
                <select
                  v-model="schedule.campus_id"
                  class="min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-900 transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white dark:focus:bg-slate-900"
                  @change="onCampusChange(schedule)"
                >
                  <option value="">Pilih kampus</option>
                  <option v-for="campus in campuses" :key="campus.id" :value="campus.id">{{ campus.name }}</option>
                </select>
              </label>

              <label class="space-y-2">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Gedung</span>
                <select
                  v-model="schedule.building_id"
                  :disabled="!schedule.campus_id"
                  class="min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-900 transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 disabled:cursor-not-allowed disabled:opacity-45 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white dark:focus:bg-slate-900"
                  @change="onBuildingChange(schedule)"
                >
                  <option value="">Pilih gedung</option>
                  <option v-for="building in buildingsFor(schedule)" :key="building.id" :value="building.id">{{ building.name }}</option>
                </select>
              </label>

              <label class="space-y-2">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Ruangan</span>
                <select
                  v-model="schedule.room_id"
                  :disabled="!schedule.building_id"
                  class="min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-900 transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 disabled:cursor-not-allowed disabled:opacity-45 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white dark:focus:bg-slate-900"
                  @change="onScheduleTargetChange(schedule)"
                >
                  <option value="">Pilih ruangan</option>
                  <option v-for="room in roomsFor(schedule)" :key="room.id" :value="room.id" :disabled="!room.is_available">
                    {{ room.name }}{{ room.is_available ? '' : ' (tidak tersedia)' }}
                  </option>
                </select>
                <span v-if="selectedBlockingBookings(schedule).length" class="text-sm font-medium text-rose-600 dark:text-rose-400">
                  Jadwal yang dipilih sudah disetujui untuk booking lain.
                </span>
                <span v-else-if="scheduleError(index, 'room_id')" class="text-sm font-medium text-rose-600 dark:text-rose-400">{{ scheduleError(index, 'room_id') }}</span>
              </label>

              <label class="space-y-2">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Tanggal Penggunaan</span>
                <input
                  :ref="(element) => datePickerRef(element, schedule)"
                  type="text"
                  class="w-full"
                  placeholder="Pilih satu atau beberapa tanggal"
                />
                <span v-if="scheduleDatesError(index)" class="text-sm font-medium text-rose-600 dark:text-rose-400">{{ scheduleDatesError(index) }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">Pilih beberapa tanggal bila ruangan dan jamnya sama. Minimal H+3: {{ minimumBookingDate }}</span>
              </label>

              <label class="space-y-2">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Jam Mulai</span>
                <select
                  v-model="schedule.start_time"
                  :disabled="!schedule.room_id || !schedule.dates.length || availabilityFor(schedule).loading"
                  class="min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-900 transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 disabled:cursor-not-allowed disabled:opacity-45 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white dark:focus:bg-slate-900"
                  @change="onStartTimeChange(schedule)"
                >
                  <option value="">{{ availabilityFor(schedule).loading ? 'Memeriksa jadwal...' : 'Pilih jam mulai' }}</option>
                  <option v-for="time in timeSlots" :key="time" :value="time" :disabled="isStartBlocked(schedule, time)">
                    {{ time }}{{ isStartBlocked(schedule, time) ? ' - terpakai' : '' }}
                  </option>
                </select>
                <span v-if="scheduleError(index, 'start_time')" class="text-sm font-medium text-rose-600 dark:text-rose-400">{{ scheduleError(index, 'start_time') }}</span>
              </label>

              <label class="space-y-2">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Jam Selesai</span>
                <select
                  v-model="schedule.end_time"
                  :disabled="!schedule.start_time"
                  class="min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-900 transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 disabled:cursor-not-allowed disabled:opacity-45 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white dark:focus:bg-slate-900"
                >
                  <option value="">Pilih jam selesai</option>
                  <option v-for="time in timeSlots" :key="time" :value="time" :disabled="endTimeDisabled(schedule, time)">
                    {{ time }}{{ wouldOverlapExisting(schedule, time) ? ' - bentrok' : '' }}
                  </option>
                </select>
                <span v-if="scheduleError(index, 'end_time')" class="text-sm font-medium text-rose-600 dark:text-rose-400">{{ scheduleError(index, 'end_time') }}</span>
              </label>
            </div>

            <div class="border-t border-slate-100 bg-slate-50/60 px-5 py-4 dark:border-slate-700 dark:bg-slate-900/20 sm:px-7">
              <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="grid min-w-0 gap-1 text-sm">
                  <p class="font-semibold text-slate-800 dark:text-slate-200">{{ formatScheduleDates(schedule) }}</p>
                  <p class="text-slate-500 dark:text-slate-400">{{ scheduleTimeSummary(schedule) }}</p>
                </div>
                <div v-if="availabilityFor(schedule).loading" class="inline-flex items-center gap-2 text-sm font-medium text-blue-600 dark:text-blue-300">
                  <span class="h-2 w-2 animate-pulse rounded-full bg-blue-500"></span>
                  Memeriksa ketersediaan
                </div>
                <div v-else-if="availabilityFor(schedule).message" class="rounded-xl bg-amber-100 px-3 py-2 text-sm font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                  {{ availabilityFor(schedule).message }}
                </div>
                <div v-else-if="selectedBlockingBookings(schedule).length" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-300">
                  <p class="font-semibold">Jadwal yang dipilih bertabrakan dengan booking yang sudah disetujui:</p>
                  <p v-for="entry in groupedAvailability(selectedBlockingBookings(schedule))" :key="entry.date">
                    {{ entry.label }}: {{ entry.intervals }}
                  </p>
                  <p class="mt-1 font-medium">Ganti ruangan, tanggal, atau jam sebelum mengirim pengajuan.</p>
                </div>
                <div v-else-if="selectedQueuedBookings(schedule).length" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-300">
                  <p class="font-semibold">
                    Ada {{ new Set(selectedQueuedBookings(schedule).map((booking) => booking.id)).size }} pengajuan lain pada jadwal yang dipilih.
                  </p>
                  <p v-for="entry in groupedAvailability(selectedQueuedBookings(schedule))" :key="entry.date">
                    {{ entry.label }}: {{ entry.intervals }}
                  </p>
                  <p class="mt-1">Pengajuan tetap dapat dikirim. Admin akan menentukan pengajuan yang disetujui.</p>
                </div>
                <div v-else-if="availabilityFor(schedule).loaded && selectedRoom(schedule)" class="inline-flex items-center gap-2 rounded-xl bg-emerald-100 px-3 py-2 text-sm font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                  <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-600 text-xs text-white">✓</span>
                  Ruangan tersedia
                </div>
                <div v-else class="text-xs text-slate-400 dark:text-slate-500">Pilih ruangan dan tanggal untuk melihat ketersediaan.</div>
              </div>
            </div>
          </article>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
          <header class="flex items-center gap-4 border-b border-slate-100 px-5 py-5 dark:border-slate-700 sm:px-7">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-600 text-base font-bold text-white shadow-lg shadow-blue-600/20">3</span>
            <div>
              <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Lampiran Pendukung</h2>
              <p class="text-sm text-slate-500 dark:text-slate-400">Opsional, PDF/JPG/PNG maksimal 2 MB.</p>
              <p v-if="existingAttachment" class="text-xs text-emerald-600 dark:text-emerald-400">
                Lampiran lama tetap digunakan bila tidak memilih file baru.
              </p>
            </div>
          </header>
          <div class="p-5 sm:p-7">
            <label class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-8 text-center transition hover:border-blue-400 hover:bg-blue-50/60 dark:border-slate-600 dark:bg-slate-900/30 dark:hover:border-blue-700 dark:hover:bg-blue-950/20">
              <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-100 text-2xl text-blue-700 dark:bg-blue-950 dark:text-blue-300">↑</span>
              <span class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ fileName || 'Pilih lampiran pendukung' }}</span>
              <span class="mt-1 text-xs text-slate-500 dark:text-slate-400">Klik area ini untuk memilih file dari perangkat</span>
              <input type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" @change="onAttachmentChange" />
            </label>
            <p v-if="form.errors.attachment" class="mt-3 text-sm font-medium text-rose-600 dark:text-rose-400">{{ form.errors.attachment }}</p>
          </div>
        </section>

        <div class="sticky bottom-4 z-20 rounded-2xl border border-slate-200 bg-white/90 p-3 shadow-2xl shadow-slate-900/10 backdrop-blur-xl dark:border-slate-700 dark:bg-slate-800/90 sm:p-4">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm">
              <p class="font-semibold text-slate-900 dark:text-white">{{ completedScheduleCount }} dari {{ form.schedules.length }} jadwal sudah lengkap</p>
              <p v-if="hasApprovedScheduleConflict" class="text-xs font-medium text-rose-600 dark:text-rose-400">
                Ganti jadwal yang bentrok dengan booking yang sudah disetujui.
              </p>
              <p v-else class="text-xs text-slate-500 dark:text-slate-400">Pastikan informasi kegiatan dan seluruh jadwal sudah benar.</p>
            </div>
            <div class="flex flex-col-reverse gap-2 sm:flex-row">
              <Link
                :href="route('bookings.index')"
                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
              >
                Batal
              </Link>
              <button
                type="submit"
                :disabled="
                  form.processing
                    || hasInvalidPrefilledDates
                    || hasApprovedScheduleConflict
                    || hasUncheckedCompleteSchedule
                "
                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-6 text-sm font-semibold text-white shadow-lg shadow-blue-600/20 transition hover:-translate-y-0.5 hover:bg-blue-700 disabled:cursor-not-allowed disabled:translate-y-0 disabled:opacity-60"
              >
                {{ form.processing
                  ? 'Mengirim Pengajuan...'
                  : hasUncheckedCompleteSchedule
                    ? 'Memeriksa Jadwal...'
                  : isRevision
                    ? 'Kirim Revisi'
                    : isResubmission
                      ? 'Ajukan Ulang'
                      : 'Ajukan Booking Ruangan' }}
              </button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>
