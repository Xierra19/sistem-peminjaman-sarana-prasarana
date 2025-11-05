<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

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
  title: '',
  description: '',
  date: '',
  start_time: '',
  end_time: '',
  attachment: null,
})

const bookedIntervals = ref([])
const availabilityMessage = ref('')
const isAvailabilityLoading = ref(false)

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

const isTimeBlocked = (time) =>
  bookedIntervals.value.some((interval) => time >= interval.start && time < interval.end)

const isEndTimeBlocked = (time) =>
  bookedIntervals.value.some((interval) => time > interval.start && time < interval.end)

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
    resetAvailability()
  },
)

watch(
  () => form.building_id,
  () => {
    form.room_id = ''
    resetAvailability()
  },
)

watch(
  () => [form.room_id, form.date],
  ([roomId, date]) => {
    form.start_time = ''
    form.end_time = ''
    if (roomId && date) {
      loadAvailability()
    } else {
      resetAvailability()
    }
  },
)

function resetAvailability() {
  bookedIntervals.value = []
  availabilityMessage.value = ''
  isAvailabilityLoading.value = false
}

const loadAvailability = async () => {
  const room = selectedRoom.value
  if (!room || !form.date) {
    resetAvailability()
    return
  }

  if (!room.is_available) {
    bookedIntervals.value = []
    availabilityMessage.value = 'Ruangan ini sedang tidak tersedia untuk dipilih.'
    isAvailabilityLoading.value = false
    return
  }

  isAvailabilityLoading.value = true
  availabilityMessage.value = ''
  bookedIntervals.value = []

  try {
    const response = await fetch(
      route('rooms.availability', { room: room.id, date: form.date }),
      {
        headers: {
          Accept: 'application/json',
        },
      },
    )

    if (!response.ok) {
      throw new Error('Gagal memuat ketersediaan')
    }

    const data = await response.json()

    if (data.available === false) {
      availabilityMessage.value = data.message || 'Ruangan tidak tersedia.'
      bookedIntervals.value = []
    } else {
      bookedIntervals.value = Array.isArray(data.bookings) ? data.bookings : []
      availabilityMessage.value =
        bookedIntervals.value.length === 0
          ? 'Semua slot waktu tersedia pada tanggal ini.'
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
    .transform((data) => ({
      room_id: data.room_id,
      title: data.title,
      description: data.description,
      attachment: data.attachment,
      start_time:
        data.date && data.start_time ? `${data.date}T${data.start_time}` : '',
      end_time: data.date && data.end_time ? `${data.date}T${data.end_time}` : '',
    }))
    .post(route('bookings.store'), {
      onFinish: () => {
        form.transform((data) => data)
      },
    })
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Request Booking" />

    <div class="mx-auto max-w-5xl space-y-6 py-6">
      <div class="rounded-xl bg-white p-6 shadow-sm">
        <div class="mb-6 border-b border-gray-200 pb-4">
          <h1 class="text-2xl font-semibold text-gray-800">Request Booking Ruangan</h1>
          <p class="mt-1 text-sm text-gray-500">
            Pilih kampus, gedung, dan ruangan yang tersedia lalu tentukan jadwal penggunaan.
          </p>
        </div>

        <form @submit.prevent="submit" class="space-y-8">
          <div class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <label class="block text-sm font-medium text-gray-700">Kampus</label>
              <select
                v-model="form.campus_id"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              >
                <option value="" disabled>Pilih kampus</option>
                <option
                  v-for="campus in props.campuses"
                  :key="campus.id"
                  :value="campus.id"
                >
                  {{ campus.name }}
                </option>
              </select>
              <div v-if="form.errors.campus_id" class="text-sm text-red-500">{{ form.errors.campus_id }}</div>
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-gray-700">Gedung</label>
              <select
                v-model="form.building_id"
                :disabled="!filteredBuildings.length"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100"
              >
                <option value="" disabled>
                  {{ filteredBuildings.length ? 'Pilih gedung' : 'Pilih kampus terlebih dahulu' }}
                </option>
                <option
                  v-for="building in filteredBuildings"
                  :key="building.id"
                  :value="building.id"
                >
                  {{ building.name }}
                </option>
              </select>
              <div v-if="form.errors.building_id" class="text-sm text-red-500">{{ form.errors.building_id }}</div>
            </div>

            <div class="space-y-2 md:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Ruangan</label>
              <select
                v-model="form.room_id"
                :disabled="!filteredRooms.length"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100"
              >
                <option value="" disabled>
                  {{ filteredRooms.length ? 'Pilih ruangan' : 'Pilih gedung untuk melihat ruangan' }}
                </option>
                <option
                  v-for="room in filteredRooms"
                  :key="room.id"
                  :value="room.id"
                  :disabled="!room.is_available"
                >
                  {{ room.name }} — Kapasitas {{ room.capacity }}
                  {{ room.is_available ? '' : '(Tidak tersedia)' }}
                </option>
              </select>
              <div v-if="form.errors.room_id" class="text-sm text-red-500">{{ form.errors.room_id }}</div>
              <p v-if="!filteredRooms.length && form.building_id" class="text-xs text-gray-400">
                Tidak ada ruangan yang terdaftar pada gedung ini.
              </p>
            </div>
          </div>

          <div
            v-if="selectedRoom"
            class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600"
          >
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
              <div>
                <p class="text-sm font-semibold text-gray-700">Detail Ruangan</p>
                <p class="mt-1">Kampus: <span class="font-medium text-gray-800">{{ currentCampus?.name ?? '-' }}</span></p>
                <p>Gedung: <span class="font-medium text-gray-800">{{ selectedBuilding?.name ?? '-' }}</span></p>
                <p>Ruangan: <span class="font-medium text-gray-800">{{ selectedRoom.name }}</span></p>
                <p>Kapasitas: <span class="font-medium text-gray-800">{{ selectedRoom.capacity }} orang</span></p>
              </div>
              <div>
                <span
                  class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium"
                  :class="selectedRoom.is_available
                    ? 'border-emerald-200 bg-emerald-100 text-emerald-700'
                    : 'border-rose-200 bg-rose-100 text-rose-700'"
                >
                  {{ selectedRoom.is_available ? 'Tersedia' : 'Tidak tersedia' }}
                </span>
              </div>
            </div>
          </div>

          <div class="grid gap-6 md:grid-cols-3">
            <div class="space-y-2 md:col-span-1">
              <label class="block text-sm font-medium text-gray-700">Tanggal Penggunaan</label>
              <input
                v-model="form.date"
                type="date"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-medium text-gray-700">Waktu Mulai</label>
              <select
                v-model="form.start_time"
                :disabled="!selectedRoom || !selectedRoom.is_available || !form.date || isAvailabilityLoading"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100"
              >
                <option value="" disabled>
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
                >
                  {{ startOptionLabel(slot) }}
                </option>
              </select>
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-medium text-gray-700">Waktu Selesai</label>
              <select
                v-model="form.end_time"
                :disabled="!selectedRoom || !selectedRoom.is_available || !form.start_time || isAvailabilityLoading"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100"
              >
                <option value="" disabled>
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
                >
                  {{ endOptionLabel(slot) }}
                </option>
              </select>
            </div>
            <div class="md:col-span-3 text-sm text-red-500">
              <p v-if="form.errors.start_time">{{ form.errors.start_time }}</p>
              <p v-if="form.errors.end_time">{{ form.errors.end_time }}</p>
            </div>
          </div>

          <div class="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm">
            <div class="flex items-center justify-between">
              <p class="font-semibold text-gray-700">Waktu yang Tidak Tersedia untuk Ruangan Ini</p>
              <span v-if="isAvailabilityLoading" class="text-xs text-blue-500">Memuat jadwal...</span>
            </div>
            <p v-if="availabilityMessage" class="text-sm text-gray-600">{{ availabilityMessage }}</p>
            <ul v-else-if="bookedIntervals.length" class="space-y-1 text-sm text-gray-600">
              <li
                v-for="interval in bookedIntervals"
                :key="interval.id"
                class="flex items-center justify-between rounded-md bg-white px-3 py-2 shadow-sm"
              >
                <span>{{ formatInterval(interval) }}</span>
                <span class="text-xs font-semibold uppercase text-gray-400">{{ interval.status }}</span>
              </li>
            </ul>
            <p v-else-if="form.room_id && form.date" class="text-sm text-gray-500">
              Belum ada jadwal lain di tanggal ini.
            </p>
            <p v-else class="text-sm text-gray-500">
              Pilih ruangan dan tanggal untuk melihat ketersediaan waktunya.
            </p>
          </div>

          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Judul Kegiatan</label>
            <input
              v-model="form.title"
              type="text"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              placeholder="Contoh: Rapat Koordinasi Proyek"
            />
            <div v-if="form.errors.title" class="text-sm text-red-500">{{ form.errors.title }}</div>
          </div>

          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Deskripsi</label>
            <textarea
              v-model="form.description"
              rows="4"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              placeholder="Tuliskan detail kegiatan, kebutuhan fasilitas, atau catatan lainnya."
            />
            <div v-if="form.errors.description" class="text-sm text-red-500">{{ form.errors.description }}</div>
          </div>

          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <label class="block text-sm font-medium text-gray-700">Lampiran Pendukung</label>
              <span class="text-xs text-gray-400">PDF, JPG, atau PNG maks. 2MB</span>
            </div>
            <input
              type="file"
              @change="handleFileChange"
              class="w-full rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm file:mr-4 file:rounded-md file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-blue-600 hover:file:bg-blue-100"
            />
            <div v-if="form.errors.attachment" class="text-sm text-red-500">{{ form.errors.attachment }}</div>
          </div>

          <div class="flex items-center justify-end gap-3">
            <button
              type="submit"
              class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
              :disabled="form.processing"
            >
              Ajukan Booking
            </button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
