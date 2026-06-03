<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import flatpickr from 'flatpickr'
import 'flatpickr/dist/flatpickr.css'

// Definisikan props untuk menerima data dari controller
const props = defineProps({
  campuses: {
    type: Array,
    default: () => [],
  },
})

// Definisikan form
const form = useForm({
  campus_id: '',
  building_id: '',
  room_id: '',
  schedule_mode: 'continuous',
  title: '',
  description: '',
  start_date: '',
  end_date: '',
  start_time: '',
  end_time: '',
  attachment: null,
})

const bookedIntervals = ref([])
const dailyBookedIntervals = ref([])
const availabilityDate = ref('')
const availabilityMessage = ref('')
const isAvailabilityLoading = ref(false)
const datePickers = ref({}) // Menyimpan instance flatpickr

const formatDateForInput = (date) => {
  if (!date) return ''
  const d = new Date(date)
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const minBookingDate = computed(() => {
  const date = new Date()
  date.setHours(0, 0, 0, 0)
  date.setDate(date.getDate() + 3)
  return formatDateForInput(date)
})

const minEndDate = computed(() => form.start_date || minBookingDate.value)

const generateTimeSlots = (startHour = 7, endHour = 21, stepMinutes = 30) => {
  const slots = []
  for (let hour = startHour; hour <= endHour; hour++) {
    for (let minute = 0; minute < 60; minute += stepMinutes) {
      if (hour === endHour && minute > 0) {
        break
      }
      const h = String(hour).padStart(2, '0')
      const m = String(minute).padStart(2, '0')
      slots.push(`${h}:${m}`)
    }
  }
  return slots
}

const timeSlots = generateTimeSlots()
const indonesianDateFormatter = new Intl.DateTimeFormat('id-ID', {
  weekday: 'long',
  day: '2-digit',
  month: 'long',
  year: 'numeric',
})

const findById = (items = [], id) => items.find((item) => String(item.id) === String(id))

const filteredBuildings = computed(() => {
  const campus = findById(props.campuses, form.campus_id)
  return campus?.buildings ?? []
})

const filteredRooms = computed(() => {
  const building = findById(filteredBuildings.value, form.building_id)
  return building?.rooms ?? []
})

const currentCampus = computed(() => findById(props.campuses, form.campus_id))
const selectedBuilding = computed(() => findById(filteredBuildings.value, form.building_id))
const selectedRoom = computed(() => findById(filteredRooms.value, form.room_id))
const hasBookingDateRange = computed(() => Boolean(form.start_date && form.end_date))
const canSelectAvailabilityDate = computed(() => Boolean(form.room_id && hasBookingDateRange.value))
const isSameHoursDailyMode = computed(() => form.schedule_mode === 'same_hours_daily')
const scheduleModeOptions = [
  {
    value: 'continuous',
    label: 'Kontinu antar hari',
    description: 'Cocok untuk penggunaan yang berjalan terus melewati malam hingga hari berikutnya.',
  },
  {
    value: 'same_hours_daily',
    label: 'Jam sama pada rentang tanggal',
    description: 'Cocok untuk kegiatan berulang dengan jam yang sama pada seluruh tanggal dalam rentang booking.',
  },
]
const scheduleDateLabels = computed(() =>
  isSameHoursDailyMode.value
    ? {
        start: 'Tanggal Mulai Rentang',
        end: 'Tanggal Selesai Rentang',
        startHelp: 'Tanggal pertama jadwal berulang akan dipakai.',
        endHelp: 'Tanggal terakhir yang akan memakai jam yang sama setiap hari.',
        timeStart: 'Jam Mulai Setiap Hari',
        timeEnd: 'Jam Selesai Setiap Hari',
      }
    : {
        start: 'Tanggal Mulai',
        end: 'Tanggal Selesai',
        startHelp: `Tanggal minimal peminjaman: ${minBookingDate.value} (H+3)`,
        endHelp: 'Samakan dengan tanggal mulai bila booking hanya satu hari.',
        timeStart: 'Waktu Mulai',
        timeEnd: 'Waktu Selesai',
      },
)
const selectedAvailabilityDateLabel = computed(() =>
  availabilityDate.value ? formatDateLabel(availabilityDate.value) : '',
)
const bookingDateSummary = computed(() => {
  if (!form.start_date) return 'Pilih tanggal peminjaman'
  if (!form.end_date || form.end_date === form.start_date) {
    return formatDateLabel(form.start_date)
  }

  return `${formatDateLabel(form.start_date)} - ${formatDateLabel(form.end_date)}`
})
const bookingTimeSummary = computed(() => {
  if (!form.start_time || !form.end_time) return 'Pilih jam mulai dan selesai'
  return `${form.start_time} - ${form.end_time}`
})
const availabilityRangeSummary = computed(() =>
  dailyBookedIntervals.value.map((entry) => ({
    ...entry,
    label: formatDateLabel(entry.date),
  })),
)

const groupedIntervalsByDate = computed(() => {
  const map = new Map()

  for (const entry of dailyBookedIntervals.value) {
    map.set(entry.date, entry.bookings ?? [])
  }

  return map
})

const startDateIntervals = computed(() => groupedIntervalsByDate.value.get(form.start_date) ?? [])
const endDateIntervals = computed(() => groupedIntervalsByDate.value.get(form.end_date) ?? [])
const allDailyIntervals = computed(() =>
  dailyBookedIntervals.value.flatMap((entry) => entry.bookings ?? []),
)

const startSelectionIntervals = computed(() =>
  isSameHoursDailyMode.value ? allDailyIntervals.value : startDateIntervals.value,
)

const endSelectionIntervals = computed(() =>
  isSameHoursDailyMode.value ? allDailyIntervals.value : endDateIntervals.value,
)

const isTimeBlocked = (time) =>
  startSelectionIntervals.value.some((interval) => time >= interval.start && time < interval.end)

const isEndTimeBlocked = (time) =>
  endSelectionIntervals.value.some((interval) => time > interval.start && time < interval.end)

const availableStartOptions = computed(() =>
  timeSlots.filter((slot) => !isTimeBlocked(slot)),
)

const availableEndOptions = computed(() =>
  timeSlots.filter((slot) => slot > form.start_time && !isEndTimeBlocked(slot)),
)

const startOptionLabel = (slot) =>
  isTimeBlocked(slot) ? `${slot} — tidak tersedia` : slot

const endOptionLabel = (slot) => {
  if (!form.start_time) {
    return slot
  }

  if (slot <= form.start_time) {
    return `${slot} — sebelum jam mulai`
  }

  return isEndTimeBlocked(slot) ? `${slot} — tidak tersedia` : slot
}

const isStartOptionDisabled = (slot) => isTimeBlocked(slot)

const isEndOptionDisabled = (slot) => {
  if (!form.start_time) {
    return true
  }

  if (slot <= form.start_time) {
    return true
  }

  return isEndTimeBlocked(slot)
}

watch(availableStartOptions, (options) => {
  if (form.start_time && !options.includes(form.start_time)) {
    form.start_time = ''
  }
})

watch(availableEndOptions, (options) => {
  if (form.end_time && !options.includes(form.end_time)) {
    form.end_time = ''
  }
})

watch(
  () => form.start_time,
  () => {
    if (form.end_time && form.end_time <= form.start_time) {
      form.end_time = ''
    }
  },
)

watch(
  () => form.campus_id,
  () => {
    form.building_id = ''
    form.room_id = ''
    form.start_time = ''
    form.end_time = ''
    resetAvailability()
  },
)

watch(
  () => form.building_id,
  () => {
    form.room_id = ''
    form.start_time = ''
    form.end_time = ''
    resetAvailability()
  },
)

watch(
  () => form.schedule_mode,
  () => {
    form.start_time = ''
    form.end_time = ''

    if (form.room_id && availabilityDate.value) {
      loadAvailability()
    }
  },
)

watch(
  () => form.start_date,
  (value) => {
    if (!value) {
      form.end_date = ''
      availabilityDate.value = ''
      datePickers.value.avail?.clear()
      form.start_time = ''
      form.end_time = ''
      resetAvailability()
      return
    }

    if (value < minBookingDate.value) {
      form.start_date = ''
      return
    }

    if (!form.end_date || form.end_date < value) {
      form.end_date = value
    }

    if (!availabilityDate.value || availabilityDate.value < value) {
      availabilityDate.value = value
      datePickers.value.avail?.setDate(value, true)
    }

    datePickers.value.avail?.set('minDate', value)

    form.start_time = ''
    form.end_time = ''
  },
)

watch(
  () => form.end_date,
  (value) => {
    if (!value) {
      if (form.start_date) {
        form.end_date = form.start_date
      }
      return
    }

    if (form.start_date && value < form.start_date) {
      form.end_date = form.start_date
      return
    }

    if (availabilityDate.value && availabilityDate.value > value) {
      availabilityDate.value = value
      datePickers.value.avail?.setDate(value, true)
    }

    datePickers.value.avail?.set('maxDate', value || null)

    if (form.room_id && availabilityDate.value) {
      loadAvailability()
    }
  },
)

watch(
  () => form.start_date,
  () => {
    if (form.room_id && availabilityDate.value) {
      loadAvailability()
    }
  },
)

watch(
  () => form.room_id,
  () => {
    form.start_time = ''
    form.end_time = ''
    if (form.room_id && availabilityDate.value) {
      loadAvailability()
    } else {
      resetAvailability()
    }
  },
)

watch(
  canSelectAvailabilityDate,
  async () => {
    await nextTick()
    syncAvailabilityPickerState()
  },
  { immediate: true },
)

watch(
  availabilityDate,
  (value) => {
    if (!value) {
      resetAvailability()
      return
    }

    const minDate = form.start_date || minBookingDate.value

    if (value < minDate) {
      if (availabilityDate.value !== minDate) {
        availabilityDate.value = minDate
        datePickers.value.avail?.setDate(minDate, true)
      }
      return
    }

    if (form.end_date && value > form.end_date) {
      availabilityDate.value = form.end_date
      datePickers.value.avail?.setDate(form.end_date, true)
      return
    }

    if (form.room_id) {
      loadAvailability()
    }
  },
)

function resetAvailability() {
  bookedIntervals.value = []
  dailyBookedIntervals.value = []
  availabilityMessage.value = ''
  isAvailabilityLoading.value = false
}

const syncAvailabilityPickerState = () => {
  const availabilityPicker = datePickers.value.avail

  if (!availabilityPicker) {
    return
  }

  const isDisabled = !canSelectAvailabilityDate.value

  availabilityPicker.input.disabled = isDisabled

  if (availabilityPicker.altInput) {
    availabilityPicker.altInput.disabled = isDisabled
  }
}

const formatDateLabel = (date) => {
  if (!date) return ''

  const parsedDate = new Date(`${date}T00:00:00`)
  return Number.isNaN(parsedDate.getTime()) ? date : indonesianDateFormatter.format(parsedDate)
}

const loadAvailability = async () => {
  const room = selectedRoom.value
  const dateToCheck = availabilityDate.value
  const startDate = form.start_date
  const endDate = form.end_date
  const scheduleMode = form.schedule_mode

  if (!room || !dateToCheck) {
    resetAvailability()
    return
  }

  if (!room.is_available) {
    bookedIntervals.value = []
    dailyBookedIntervals.value = []
    availabilityMessage.value = 'Ruangan ini sedang tidak tersedia untuk dipilih.'
    isAvailabilityLoading.value = false
    return
  }

  isAvailabilityLoading.value = true
  availabilityMessage.value = ''
  bookedIntervals.value = []
  dailyBookedIntervals.value = []

  try {
    const response = await fetch(route('rooms.availability', {
      room: room.id,
      date: dateToCheck,
      start_date: startDate,
      end_date: endDate,
      schedule_mode: scheduleMode,
    }), {
      headers: {
        Accept: 'application/json',
      },
    })

    if (!response.ok) {
      throw new Error('Gagal memuat ketersediaan')
    }

    const data = await response.json()

    if (data.available === false) {
      availabilityMessage.value = data.message || 'Ruangan tidak tersedia.'
      bookedIntervals.value = []
      dailyBookedIntervals.value = []
    } else {
      bookedIntervals.value = Array.isArray(data.bookings) ? data.bookings : []
      dailyBookedIntervals.value = Array.isArray(data.daily_bookings) ? data.daily_bookings : []
      availabilityMessage.value =
        dailyBookedIntervals.value.length === 0
          ? 'Semua slot waktu tersedia pada rentang tanggal ini.'
          : ''
    }
  } catch (error) {
    availabilityMessage.value =
      'Tidak dapat memuat ketersediaan ruangan. Silakan coba lagi.'
  } finally {
    isAvailabilityLoading.value = false
  }
}

const handleFileChange = (event) => {
  const [file] = event.target.files || []
  form.attachment = file ?? null
}

const formatInterval = (interval) => `${interval.start} - ${interval.end}`

const submit = () => {
  form
    .transform((data) => {
      return {
        room_id: data.room_id,
        schedule_mode: data.schedule_mode,
        title: data.title,
        description: data.description,
        start_date: data.start_date,
        end_date: data.end_date || data.start_date,
        attachment: data.attachment,
        start_time: data.start_time,
        end_time: data.end_time,
      }
    })
    .post(route('bookings.store'), {
      onFinish: () => {
        form.transform((data) => data)
      },
    })
}

// Inisialisasi Flatpickr
onMounted(() => {
  // Flatpickr untuk Tanggal Mulai
  const startInput = document.getElementById('start_date_input')
  if (startInput) {
    datePickers.value.start = flatpickr(startInput, {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'd-m-y',
      minDate: minBookingDate.value,
      onChange: (selectedDates, dateStr) => {
        form.start_date = dateStr
        if (datePickers.value.end) {
          datePickers.value.end.set('minDate', dateStr || minBookingDate.value)
        }
      }
    })
  }

  // Flatpickr untuk Tanggal Selesai
  const endInput = document.getElementById('end_date_input')
  if (endInput) {
    datePickers.value.end = flatpickr(endInput, {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'd-m-y',
      minDate: minEndDate.value,
      onChange: (selectedDates, dateStr) => {
        form.end_date = dateStr
      }
    })
  }

  // Flatpickr untuk Tanggal Cek Ketersediaan
  const availInput = document.getElementById('availability_date_input')
  if (availInput) {
    datePickers.value.avail = flatpickr(availInput, {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'd-m-y',
      minDate: form.start_date || minBookingDate.value,
      maxDate: form.end_date || undefined,
      onChange: (selectedDates, dateStr) => {
        availabilityDate.value = dateStr
      }
    })

    syncAvailabilityPickerState()
  }
})

onBeforeUnmount(() => {
  Object.values(datePickers.value).forEach(picker => picker?.destroy())
})
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Permintaan Peminjaman Ruangan" />

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-0">
        <div class="card-surface space-y-6 p-5 sm:p-6 dark:bg-slate-800 dark:border-slate-700">
            <div class="border-b border-slate-200 pb-5 dark:border-slate-700">
                <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">Permintaan Peminjaman Ruangan</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Pilih kampus, gedung, dan ruangan yang tersedia lalu tentukan jadwal penggunaan.
                </p>
            </div>

        <div class="hidden gap-3 rounded-2xl bg-slate-50 p-4 text-xs text-slate-600 sm:grid sm:grid-cols-3 dark:bg-slate-700/50 dark:text-slate-300">
          <div class="flex items-start gap-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">1</span>
            <div>
              <p class="font-semibold text-slate-800 dark:text-slate-200">Pilih Lokasi</p>
              <p>Pilih kampus, gedung, dan ruangan yang tersedia.</p>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">2</span>
            <div>
              <p class="font-semibold text-slate-800 dark:text-slate-200">Atur Jadwal</p>
              <p>Tentukan tanggal, jam mulai, dan selesai.</p>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">3</span>
            <div>
              <p class="font-semibold text-slate-800 dark:text-slate-200">Lengkapi Detail</p>
              <p>Isi deskripsi kegiatan dan lampiran jika diperlukan.</p>
            </div>
          </div>
        </div>

        <form @submit.prevent="submit" class="space-y-8">
          <section class="space-y-5">
            <div class="flex items-center gap-3">
              <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white shadow-sm">1</span>
              <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Lokasi Peminjaman</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Tentukan kampus, gedung, dan ruangan yang akan dipakai.</p>
              </div>
            </div>

          <div class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Kampus</label>
              <select
                v-model="form.campus_id"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
              >
                <option value="" disabled class="dark:bg-slate-700">Pilih kampus</option>
                <option
                  v-for="campus in props.campuses"
                  :key="campus.id"
                  :value="campus.id"
                  class="dark:bg-slate-700"
                >
                  {{ campus.name }}
                </option>
              </select>
              <div v-if="form.errors.campus_id" class="text-sm text-red-500">{{ form.errors.campus_id }}</div>
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Gedung</label>
              <select
                v-model="form.building_id"
                :disabled="!filteredBuildings.length"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-600 dark:bg-slate-700 dark:text-white disabled:dark:bg-slate-600"
              >
                <option value="" disabled class="dark:bg-slate-700">
                  {{ filteredBuildings.length ? 'Pilih gedung' : 'Pilih kampus terlebih dahulu' }}
                </option>
                <option
                  v-for="building in filteredBuildings"
                  :key="building.id"
                  :value="building.id"
                  class="dark:bg-slate-700"
                >
                  {{ building.name }}
                </option>
              </select>
              <div v-if="form.errors.building_id" class="text-sm text-red-500">{{ form.errors.building_id }}</div>
            </div>

            <div class="space-y-2 md:col-span-2">
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Ruangan</label>
              <select
                v-model="form.room_id"
                :disabled="!filteredRooms.length"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-600 dark:bg-slate-700 dark:text-white disabled:dark:bg-slate-600"
              >
                <option value="" disabled class="dark:bg-slate-700">
                  {{ filteredRooms.length ? 'Pilih ruangan' : 'Pilih gedung untuk melihat ruangan' }}
                </option>
                <option
                  v-for="room in filteredRooms"
                  :key="room.id"
                  :value="room.id"
                  :disabled="!room.is_available"
                  class="dark:bg-slate-700"
                >
                  {{ room.name }} — Kapasitas {{ room.capacity }}
                  {{ room.is_available ? '' : '(Tidak tersedia)' }}
                </option>
              </select>
              <div v-if="form.errors.room_id" class="text-sm text-red-500">{{ form.errors.room_id }}</div>
              <p v-if="!filteredRooms.length && form.building_id" class="text-xs text-slate-400 dark:text-slate-400">
                Tidak ada ruangan yang terdaftar pada gedung ini.
              </p>
            </div>
          </div>
          </section>

          <div
            v-if="selectedRoom"
            class="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-700/50 dark:text-slate-300"
          >
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
              <div>
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Detail Ruangan</p>
                <p class="mt-1">Kampus: <span class="font-medium text-slate-800 dark:text-slate-200">{{ currentCampus?.name ?? '-' }}</span></p>
                <p>Gedung: <span class="font-medium text-slate-800 dark:text-slate-200">{{ selectedBuilding?.name ?? '-' }}</span></p>
                <p>Ruangan: <span class="font-medium text-slate-800 dark:text-slate-200">{{ selectedRoom.name }}</span></p>
                <p>Kapasitas: <span class="font-medium text-slate-800 dark:text-slate-200">{{ selectedRoom.capacity }} orang</span></p>
              </div>
              <div>
                <span
                  class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium"
                  :class="selectedRoom.is_available
                    ? 'border-emerald-200 bg-emerald-100 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                    : 'border-rose-200 bg-rose-100 text-rose-700 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-300'"
                >
                  {{ selectedRoom.is_available ? 'Tersedia' : 'Tidak tersedia' }}
                </span>
              </div>
            </div>
          </div>

          <section class="space-y-5">
            <div class="flex items-center gap-3">
              <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white shadow-sm">2</span>
              <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Jadwal Penggunaan</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pilih tipe jadwal, tanggal, dan jam yang sesuai.</p>
              </div>
            </div>

          <div class="space-y-3 rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-700/50">
            <div>
              <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Jenis Jadwal</p>
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Pilih apakah peminjaman berlangsung terus antar hari atau memakai jam yang sama setiap hari dalam rentang tanggal.
              </p>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
              <label
                v-for="option in scheduleModeOptions"
                :key="option.value"
                class="flex cursor-pointer gap-3 rounded-xl border p-4 transition"
                :class="form.schedule_mode === option.value
                  ? 'border-blue-500 bg-blue-50/80 dark:border-blue-500 dark:bg-blue-900/20'
                  : 'border-slate-200 bg-white dark:border-slate-600 dark:bg-slate-800'"
              >
                <input
                  v-model="form.schedule_mode"
                  type="radio"
                  name="schedule_mode"
                  :value="option.value"
                  class="mt-1 h-4 w-4 border-slate-300 text-blue-600 focus:ring-blue-500"
                />
                <span class="space-y-1">
                  <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">{{ option.label }}</span>
                  <span class="block text-xs text-slate-500 dark:text-slate-400">{{ option.description }}</span>
                </span>
              </label>
            </div>
            <p v-if="form.schedule_mode === 'same_hours_daily'" class="text-xs text-blue-600 dark:text-blue-300">
              Sistem akan memeriksa bentrok untuk jam yang sama pada seluruh tanggal di rentang yang dipilih.
            </p>
            <p v-if="form.errors.schedule_mode" class="text-sm text-red-500">{{ form.errors.schedule_mode }}</p>
          </div>

          <div class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">{{ scheduleDateLabels.start }}</label>
              <input
                id="start_date_input"
                type="text"
                readonly
                placeholder="Pilih tanggal mulai"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 cursor-pointer dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400"
              />
              <p class="text-xs text-slate-500 dark:text-slate-400">{{ scheduleDateLabels.startHelp }}</p>
              <p v-if="form.errors.start_date" class="text-sm text-red-500">{{ form.errors.start_date }}</p>
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">{{ scheduleDateLabels.end }}</label>
              <input
                id="end_date_input"
                type="text"
                readonly
                placeholder="Pilih tanggal selesai"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 cursor-pointer dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400"
              />
              <p class="text-xs text-slate-500 dark:text-slate-400">{{ scheduleDateLabels.endHelp }}</p>
              <p v-if="form.errors.end_date" class="text-sm text-red-500">{{ form.errors.end_date }}</p>
            </div>
          </div>

            <div class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">{{ scheduleDateLabels.timeStart }}</label>
              <select
                v-model="form.start_time"
                :disabled="!selectedRoom || !selectedRoom.is_available || !form.start_date || isAvailabilityLoading"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-600 dark:bg-slate-700 dark:text-white disabled:dark:bg-slate-600"
              >
                <option value="" disabled class="dark:bg-slate-700">
                  {{
                    selectedRoom
                      ? selectedRoom.is_available
                        ? 'Pilih jam mulai'
                        : 'Ruangan tidak tersedia'
                      : 'Pilih ruangan terlebih dahulu'
                  }}
                </option>
                <option
                  v-for="slot in timeSlots"
                  :key="slot"
                  :value="slot"
                  :disabled="isStartOptionDisabled(slot)"
                  class="dark:bg-slate-700"
                >
                  {{ startOptionLabel(slot) }}
                </option>
              </select>
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">{{ scheduleDateLabels.timeEnd }}</label>
              <select
                v-model="form.end_time"
                :disabled="!selectedRoom || !selectedRoom.is_available || !form.start_time || isAvailabilityLoading"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-600 dark:bg-slate-700 dark:text-white disabled:dark:bg-slate-600"
              >
                <option value="" disabled class="dark:bg-slate-700">
                  {{
                    form.start_time
                      ? selectedRoom?.is_available
                        ? 'Pilih jam selesai'
                        : 'Ruangan tidak tersedia'
                      : 'Pilih jam mulai terlebih dahulu'
                  }}
                </option>
                <option
                  v-for="slot in timeSlots"
                  :key="slot"
                  :value="slot"
                  :disabled="isEndOptionDisabled(slot)"
                  class="dark:bg-slate-700"
                >
                  {{ endOptionLabel(slot) }}
                </option>
              </select>
            </div>
            <div class="md:col-span-2 text-sm text-red-500">
              <p v-if="form.errors.start_time">{{ form.errors.start_time }}</p>
              <p v-if="form.errors.end_time">{{ form.errors.end_time }}</p>
            </div>
          </div>
          </section>

          <section class="space-y-5">
            <div class="flex items-center gap-3">
              <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white shadow-sm">3</span>
              <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Cek Ketersediaan</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Lihat bentrok per hari sebelum pengajuan dikirim.</p>
              </div>
            </div>

          <div class="space-y-4 rounded-2xl border border-slate-100 bg-slate-50 p-4 text-sm dark:border-slate-700 dark:bg-slate-700/50 dark:text-slate-300">
            <div class="flex items-center justify-between gap-4">
              <div>
                <p class="font-semibold text-slate-700 dark:text-slate-200">Waktu yang Tidak Tersedia untuk Ruangan Ini</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  Lihat ringkasan bentrok untuk seluruh rentang tanggal booking, lalu cek detail per hari bila diperlukan.
                </p>
              </div>
              <span v-if="isAvailabilityLoading" class="text-xs text-blue-500">Memuat jadwal...</span>
            </div>

            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
              <div class="space-y-3">
                <div class="space-y-2">
                  <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Tanggal Cek Ketersediaan</label>
                  <input
                    id="availability_date_input"
                    type="text"
                    readonly
                    placeholder="Pilih tanggal dalam rentang booking"
                    :disabled="!canSelectAvailabilityDate"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm cursor-pointer focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400 disabled:dark:bg-slate-600"
                  />
                  <p class="text-xs text-slate-500 dark:text-slate-400">
                    Pilih satu tanggal untuk melihat detail jam bentrok pada hari tersebut.
                  </p>
                </div>

                <div class="space-y-2">
                  <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Ringkasan Bentrok per Hari</p>
                  <p v-if="availabilityMessage" class="text-sm text-slate-600 dark:text-slate-300">{{ availabilityMessage }}</p>
                  <div v-else-if="availabilityRangeSummary.length" class="space-y-3">
                    <div
                      v-for="entry in availabilityRangeSummary"
                      :key="entry.date"
                      class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-600 dark:bg-slate-600"
                    >
                      <div class="flex items-center justify-between gap-3">
                        <p class="font-medium text-slate-800 dark:text-slate-100">{{ entry.label }}</p>
                        <span class="text-xs text-slate-500 dark:text-slate-300">{{ entry.bookings.length }} bentrok</span>
                      </div>
                      <ul class="mt-2 space-y-2">
                        <li
                          v-for="interval in entry.bookings"
                          :key="`${entry.date}-${interval.id}-${interval.start}-${interval.end}`"
                          class="flex items-center justify-between gap-3 rounded-md bg-slate-50 px-3 py-2 dark:bg-slate-700"
                        >
                          <span>{{ formatInterval(interval) }}</span>
                          <span class="text-xs font-semibold uppercase text-slate-400 dark:text-slate-300">{{ interval.status }}</span>
                        </li>
                      </ul>
                    </div>
                  </div>
                  <p v-else-if="form.room_id && hasBookingDateRange" class="text-sm text-slate-500 dark:text-slate-400">
                    Belum ada jadwal lain pada rentang tanggal yang dipilih.
                  </p>
                  <p v-else class="text-sm text-slate-500 dark:text-slate-400">
                    Pilih ruangan, tanggal mulai, dan tanggal selesai untuk melihat ringkasan bentrok per hari.
                  </p>
                </div>
              </div>

              <div class="space-y-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-600 dark:bg-slate-600">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                  Detail pada {{ selectedAvailabilityDateLabel || 'Tanggal yang Dipilih' }}
                </p>
                <ul v-if="bookedIntervals.length" class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                  <li
                    v-for="interval in bookedIntervals"
                    :key="`detail-${interval.id}-${interval.start}-${interval.end}`"
                    class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2 dark:bg-slate-700 dark:text-slate-200"
                  >
                    <span>{{ formatInterval(interval) }}</span>
                    <span class="text-xs font-semibold uppercase text-slate-400 dark:text-slate-300">{{ interval.status }}</span>
                  </li>
                </ul>
                <p v-else-if="form.room_id && availabilityDate" class="text-sm text-slate-500 dark:text-slate-400">
                  Belum ada jadwal lain pada tanggal ini.
                </p>
                <p v-else class="text-sm text-slate-500 dark:text-slate-400">
                  Pilih tanggal cek ketersediaan untuk melihat detail bentrok per hari.
                </p>
              </div>
            </div>
          </div>
          </section>

          <section class="space-y-5">
            <div class="flex items-center gap-3">
              <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white shadow-sm">4</span>
              <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Detail Pengajuan</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Lengkapi informasi kegiatan dan periksa ringkasannya.</p>
              </div>
            </div>

            <div class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 dark:border-slate-700 dark:bg-slate-800">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Lokasi</p>
                <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">
                  {{ selectedRoom?.name ?? 'Belum memilih ruangan' }}
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  {{ selectedBuilding?.name ?? '-' }} - {{ currentCampus?.name ?? '-' }}
                </p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Tanggal</p>
                <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">{{ bookingDateSummary }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Waktu</p>
                <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">{{ bookingTimeSummary }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Mode Jadwal</p>
                <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">
                  {{ scheduleModeOptions.find((option) => option.value === form.schedule_mode)?.label ?? '-' }}
                </p>
              </div>
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Judul Kegiatan *</label>
              <input
                v-model="form.title"
                type="text"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400"
                placeholder="Contoh: rapat koordinasi proyek"
              />
              <div v-if="form.errors.title" class="text-sm text-red-500">{{ form.errors.title }}</div>
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Deskripsi</label>
              <textarea
                v-model="form.description"
                rows="4"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400"
                placeholder="Tuliskan detail kegiatan, kebutuhan fasilitas, atau catatan lainnya"
              />
              <div v-if="form.errors.description" class="text-sm text-red-500">{{ form.errors.description }}</div>
            </div>

            <div class="space-y-3">
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Lampiran Pendukung</label>
              <p class="text-xs text-slate-500 mb-2 dark:text-slate-400">PDF, JPG, atau PNG maks. 2MB (Opsional)</p>
              <label class="flex cursor-pointer items-center justify-between rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-6 transition hover:border-blue-400 hover:bg-blue-50/20 dark:border-slate-600 dark:bg-slate-700/50 dark:hover:border-slate-500 dark:hover:bg-slate-600/50">
                <div class="text-left">
                  <p class="font-medium text-slate-700 dark:text-slate-200">
                    {{ form.attachment ? form.attachment.name : 'Upload lampiran (opsional)' }}
                  </p>
                  <p class="text-xs text-slate-500 mt-1 dark:text-slate-400">Lampiran pendukung kegiatan (surat, dokumen, dll)</p>
                </div>
                <span class="rounded-xl bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm dark:bg-slate-600 dark:text-slate-200">Pilih File</span>
                <input class="hidden" type="file" accept=".pdf,.jpg,.jpeg,.png" @change="handleFileChange" />
              </label>
              <div v-if="form.errors.attachment" class="text-sm text-red-500">{{ form.errors.attachment }}</div>
            </div>
          </section>

          <div class="sticky bottom-0 z-10 -mx-5 border-t border-slate-200 bg-white/95 px-5 py-4 backdrop-blur sm:-mx-6 sm:px-6 dark:border-slate-700 dark:bg-slate-800/95">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="text-sm text-slate-500 dark:text-slate-400">
                <p class="font-medium text-slate-700 dark:text-slate-200">{{ selectedRoom?.name ?? 'Ruangan belum dipilih' }}</p>
                <p>{{ bookingDateSummary }} - {{ bookingTimeSummary }}</p>
              </div>
              <button
                type="submit"
                class="inline-flex w-full items-center justify-center rounded-2xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-md transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto"
                :disabled="form.processing"
              >
                {{ form.processing ? 'Mengajukan...' : 'Ajukan Peminjaman' }}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
