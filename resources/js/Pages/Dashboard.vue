<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const page = usePage()

const props = defineProps({
  rooms: {
    type: Array,
    default: () => [],
  },
  campuses: {
    type: Array,
    default: () => [],
  },
  recentBookings: {
    type: Array,
    default: () => [],
  },
  bookingSummary: {
    type: Object,
    default: () => ({
      total: 0,
      approved: 0,
      waiting: 0,
      rejected: 0,
      cancelled: 0,
    }),
  },
})

const normalizeStatus = (status) => {
  if (!status) return ''
  return status === 'pending' || status === 'requested' ? 'waiting' : status
}

const statusLabels = {
  approved: 'Disetujui',
  waiting: 'Menunggu Persetujuan',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan Admin',
}

const statusBadgeClasses = {
  approved: 'bg-emerald-100 text-emerald-700 border border-emerald-200',
  waiting: 'bg-amber-100 text-amber-700 border border-amber-200',
  rejected: 'bg-rose-100 text-rose-700 border border-rose-200',
  cancelled: 'bg-slate-100 text-slate-700 border border-slate-200',
}

const formatDateTime = (value) => {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) {
    return value
  }

  return parsed.toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const extractFeatures = (features) => {
  if (Array.isArray(features)) {
    return features.map((item) => String(item).trim()).filter(Boolean)
  }

  if (typeof features === 'string') {
    const trimmed = features.trim()
    if (!trimmed) {
      return []
    }

    try {
      const parsed = JSON.parse(trimmed)
      if (Array.isArray(parsed)) {
        return parsed.map((item) => String(item).trim()).filter(Boolean)
      }
    } catch {
      // abaikan jika string bukan JSON valid
    }

    return trimmed
      .split(',')
      .map((item) => item.trim())
      .filter(Boolean)
  }

  return []
}

const userName = computed(() => page.props?.auth?.user?.name ?? 'Pengguna')
const greetingName = computed(() => userName.value.split(' ')[0] || userName.value)

const bookingSummaryData = computed(() => ({
  total: props.bookingSummary?.total ?? 0,
  approved: props.bookingSummary?.approved ?? 0,
  waiting: props.bookingSummary?.waiting ?? 0,
  rejected: props.bookingSummary?.rejected ?? 0,
  cancelled: props.bookingSummary?.cancelled ?? 0,
}))

const rooms = computed(() => props.rooms ?? [])
const totalRooms = computed(() => rooms.value.length)

const searchQuery = ref('')
const minCapacity = ref('')
const campusFilter = ref('')
const buildingFilter = ref('')
const onlyAvailable = ref(false)
const sortOption = ref('capacity-desc')
const searchDate = ref('')
const searchStartTime = ref('')
const searchEndTime = ref('')
const appliedFilters = ref(null)

const campusOptions = computed(() => props.campuses ?? [])
const selectedCampus = computed(() =>
  campusOptions.value.find((campus) => String(campus.id) === String(campusFilter.value)),
)
const buildingOptions = computed(() => selectedCampus.value?.buildings ?? [])

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
const endTimeOptions = computed(() =>
  timeSlots.filter((slot) => !searchStartTime.value || slot > searchStartTime.value),
)

watch(campusFilter, () => {
  buildingFilter.value = ''
})

watch(searchStartTime, (value) => {
  if (searchEndTime.value && searchEndTime.value <= value) {
    searchEndTime.value = ''
  }
})

const hasActiveFilters = computed(
  () =>
    Boolean(
      searchQuery.value ||
        minCapacity.value ||
        campusFilter.value ||
        buildingFilter.value ||
        onlyAvailable.value ||
        sortOption.value !== 'capacity-desc' ||
        searchDate.value ||
        searchStartTime.value ||
        searchEndTime.value,
    ),
)

const canResetFilters = computed(() => hasActiveFilters.value || appliedFilters.value !== null)

const handleSearch = () => {
  appliedFilters.value = {
    query: searchQuery.value.trim(),
    minCapacity: minCapacity.value,
    campusId: campusFilter.value,
    buildingId: buildingFilter.value,
    onlyAvailable: onlyAvailable.value,
    sortOption: sortOption.value || 'capacity-desc',
    date: searchDate.value,
    startTime: searchStartTime.value,
    endTime: searchEndTime.value,
  }
}

const resetFilters = () => {
  searchQuery.value = ''
  minCapacity.value = ''
  campusFilter.value = ''
  buildingFilter.value = ''
  onlyAvailable.value = false
  sortOption.value = 'capacity-desc'
  searchDate.value = ''
  searchStartTime.value = ''
  searchEndTime.value = ''
  appliedFilters.value = null
}

const hasSearched = computed(() => appliedFilters.value !== null)

const filteredRooms = computed(() => {
  const filters = appliedFilters.value
  if (!filters) {
    return []
  }

  const query = filters.query.trim().toLowerCase()
  const campusId = filters.campusId ? String(filters.campusId) : ''
  const buildingId = filters.buildingId ? String(filters.buildingId) : ''

  let minCapValue = null
  if (filters.minCapacity !== '') {
    const parsed = Number(filters.minCapacity)
    if (!Number.isNaN(parsed)) {
      minCapValue = parsed
    }
  }

  const requireAvailableForSchedule = Boolean(filters.date || filters.startTime || filters.endTime)

  return rooms.value.filter((room) => {
    if (!room) return false
    if ((filters.onlyAvailable || requireAvailableForSchedule) && !room.is_available) {
      return false
    }

    const roomCampusId = String(room.building?.campus?.id ?? room.building?.campus_id ?? '')
    if (campusId && roomCampusId !== campusId) {
      return false
    }

    if (buildingId && String(room.building_id ?? '') !== buildingId) {
      return false
    }

    if (minCapValue !== null && (Number(room.capacity) || 0) < minCapValue) {
      return false
    }

    if (!query) {
      return true
    }

    const featuresText = extractFeatures(room.features).join(' ')
    const searchable = [
      room.name,
      room.building?.name,
      room.building?.campus?.name,
      featuresText,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()

    return searchable.includes(query)
  })
})

const sortedRooms = computed(() => {
  if (!appliedFilters.value) {
    return []
  }

  const list = [...filteredRooms.value]

  switch (appliedFilters.value.sortOption) {
    case 'capacity-asc':
      return list.sort(
        (a, b) => (Number(a.capacity) || 0) - (Number(b.capacity) || 0),
      )
    case 'name-asc':
      return list.sort((a, b) => (a.name || '').localeCompare(b.name || ''))
    case 'availability':
      return list.sort((a, b) => Number(b.is_available) - Number(a.is_available))
    case 'capacity-desc':
    default:
      return list.sort(
        (a, b) => (Number(b.capacity) || 0) - (Number(a.capacity) || 0),
      )
  }
})

const resultsCount = computed(() => sortedRooms.value.length)

const recentBookingsList = computed(() =>
  (props.recentBookings ?? []).map((booking) => ({
    ...booking,
    normalizedStatus: normalizeStatus(booking.status),
  })),
)
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Dashboard" />

    <div class="py-12">
      <div class="mx-auto space-y-8 px-4 sm:px-6 lg:max-w-7xl lg:px-8">
        <section class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
          <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
              <p class="text-sm font-medium text-blue-600">Halo, {{ greetingName }}!</p>
              <h1 class="mt-1 text-3xl font-semibold text-gray-900">Temukan Ruangan untuk Kebutuhanmu</h1>
              <p class="mt-2 text-sm text-gray-600">
                Cari ruangan yang sesuai dengan kebutuhan kapasitas, lokasi, serta jadwal penggunaan yang kamu inginkan.
              </p>
            </div>
            <Link
              :href="route('bookings.create')"
              class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
            >
              + Ajukan Booking
            </Link>
          </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
          <div class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-blue-800">
            <div class="text-sm font-medium">Total Pengajuan</div>
            <div class="mt-2 text-3xl font-semibold">{{ bookingSummaryData.total }}</div>
          </div>
          <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 text-emerald-700">
            <div class="text-sm font-medium">Disetujui</div>
            <div class="mt-2 text-3xl font-semibold">{{ bookingSummaryData.approved }}</div>
          </div>
          <div class="rounded-xl border border-amber-100 bg-amber-50 p-4 text-amber-700">
            <div class="text-sm font-medium">Menunggu</div>
            <div class="mt-2 text-3xl font-semibold">{{ bookingSummaryData.waiting }}</div>
          </div>
          <div class="rounded-xl border border-rose-100 bg-rose-50 p-4 text-rose-700">
            <div class="text-sm font-medium">Ditolak</div>
            <div class="mt-2 text-3xl font-semibold">{{ bookingSummaryData.rejected }}</div>
          </div>
          <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 text-slate-700">
            <div class="text-sm font-medium">Dibatalkan Admin</div>
            <div class="mt-2 text-3xl font-semibold">{{ bookingSummaryData.cancelled }}</div>
          </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="text-lg font-semibold text-gray-900">Filter Pencarian Ruangan</h2>
              <p class="text-sm text-gray-500">Isi detail kebutuhanmu, lalu klik Search untuk melihat hasilnya.</p>
            </div>
          </div>

          <form class="mt-6 space-y-6" @submit.prevent="handleSearch">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-gray-700" for="room-search">Kata Kunci</label>
                <input
                  id="room-search"
                  v-model="searchQuery"
                  type="text"
                  placeholder="Cari nama ruangan, fasilitas, kampus, atau gedung..."
                  class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="room-capacity">Kapasitas Minimum</label>
                <input
                  id="room-capacity"
                  v-model="minCapacity"
                  type="number"
                  min="0"
                  step="1"
                  placeholder="Contoh: 30"
                  class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="room-campus">Kampus</label>
                <select
                  id="room-campus"
                  v-model="campusFilter"
                  class="w-full appearance-none rounded-md border border-gray-300 px-3 py-2 pr-9 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                  <option value="">Semua Kampus</option>
                  <option v-for="campus in campusOptions" :key="campus.id" :value="campus.id">
                    {{ campus.name }}
                  </option>
                </select>
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="room-building">Gedung</label>
                <select
                  id="room-building"
                  v-model="buildingFilter"
                  :disabled="!campusFilter"
                  class="w-full appearance-none rounded-md border border-gray-300 px-3 py-2 pr-9 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100"
                >
                  <option value="">
                    {{ campusFilter ? 'Semua Gedung' : 'Pilih kampus terlebih dahulu' }}
                  </option>
                  <option v-for="building in buildingOptions" :key="building.id" :value="building.id">
                    {{ building.name }}
                  </option>
                </select>
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="room-date">Tanggal Penggunaan</label>
                <input
                  id="room-date"
                  v-model="searchDate"
                  type="date"
                  class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="room-start">Jam Mulai</label>
                <select
                  id="room-start"
                  v-model="searchStartTime"
                  :disabled="!searchDate"
                  class="w-full appearance-none rounded-md border border-gray-300 px-3 py-2 pr-9 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100"
                >
                  <option value="">
                    {{ searchDate ? 'Pilih jam mulai' : 'Pilih tanggal terlebih dahulu' }}
                  </option>
                  <option v-for="slot in timeSlots" :key="`start-${slot}`" :value="slot">
                    {{ slot }}
                  </option>
                </select>
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="room-end">Jam Selesai</label>
                <select
                  id="room-end"
                  v-model="searchEndTime"
                  :disabled="!searchDate || !searchStartTime"
                  class="w-full appearance-none rounded-md border border-gray-300 px-3 py-2 pr-9 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100"
                >
                  <option value="">
                    {{
                      searchStartTime
                        ? 'Pilih jam selesai'
                        : searchDate
                          ? 'Pilih jam mulai terlebih dahulu'
                          : 'Pilih tanggal terlebih dahulu'
                    }}
                  </option>
                  <option v-for="slot in endTimeOptions" :key="`end-${slot}`" :value="slot">
                    {{ slot }}
                  </option>
                </select>
              </div>
            </div>

            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                <input
                  type="checkbox"
                  v-model="onlyAvailable"
                  class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
                Tampilkan hanya ruangan yang tersedia
              </label>

              <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <span class="text-sm text-gray-500">Urutkan berdasarkan:</span>
                <select
                  v-model="sortOption"
                  class="w-full appearance-none rounded-md border border-gray-300 px-3 py-2 pr-9 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 sm:w-56"
                >
                  <option value="capacity-desc">Kapasitas terbesar</option>
                  <option value="capacity-asc">Kapasitas terkecil</option>
                  <option value="availability">Ketersediaan</option>
                  <option value="name-asc">Nama ruangan (A-Z)</option>
                </select>
              </div>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
              <button
                type="button"
                class="inline-flex items-center justify-center rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50"
                @click="resetFilters"
                :disabled="!canResetFilters"
              >
                Reset
              </button>
              <button
                type="submit"
                class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
              >
                Search
              </button>
            </div>
          </form>
        </section>

        <section>
          <div class="flex flex-col gap-2 sm:flex-row sm:items-baseline sm:justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Daftar Ruangan</h2>
            <p v-if="hasSearched && sortedRooms.length" class="text-sm text-gray-500">
              Menampilkan {{ resultsCount }} dari {{ totalRooms }} ruangan yang terdata.
            </p>
          </div>

          <div
            v-if="!hasSearched"
            class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500"
          >
            Gunakan filter di atas dan klik tombol Search untuk melihat daftar ruangan yang cocok.
          </div>

          <div v-else-if="sortedRooms.length" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
              v-for="room in sortedRooms"
              :key="room.id"
              class="flex h-full flex-col justify-between rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:shadow-md"
            >
              <div class="space-y-4">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <h3 class="text-lg font-semibold text-gray-900">{{ room.name }}</h3>
                    <p class="text-sm text-gray-500">
                      {{ room.building?.campus?.name ?? '-' }} - {{ room.building?.name ?? '-' }}
                    </p>
                  </div>
                  <span
                    class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                    :class="
                      room.is_available
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                        : 'border-rose-200 bg-rose-50 text-rose-700'
                    "
                  >
                    {{ room.is_available ? 'Tersedia' : 'Tidak tersedia' }}
                  </span>
                </div>

                <dl class="grid gap-3 text-sm text-gray-600">
                  <div>
                    <dt class="font-medium text-gray-700">Kapasitas</dt>
                    <dd class="mt-1 text-base font-semibold text-gray-900">
                      {{ room.capacity ?? '-' }} orang
                    </dd>
                  </div>
                  <div>
                    <dt class="font-medium text-gray-700">Fasilitas</dt>
                    <dd class="mt-2 flex flex-wrap gap-2">
                      <template v-if="extractFeatures(room.features).length">
                        <span
                          v-for="feature in extractFeatures(room.features)"
                          :key="feature"
                          class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600"
                        >
                          {{ feature }}
                        </span>
                      </template>
                      <span v-else class="text-xs text-gray-400">Informasi fasilitas belum tersedia.</span>
                    </dd>
                  </div>
                </dl>
              </div>

              <div class="mt-4 flex flex-wrap items-center gap-3">
                <Link
                  :href="route('bookings.create')"
                  class="inline-flex items-center rounded-md border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:bg-blue-50"
                >
                  Ajukan Booking
                </Link>
                <span class="text-xs text-gray-400">Pilih ruangan ini pada formulir booking.</span>
              </div>
            </article>
          </div>

          <div
            v-else
            class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500"
          >
            Tidak ada ruangan yang cocok dengan filter saat ini. Coba ubah kombinasi filter untuk melihat opsi lainnya.
          </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="text-lg font-semibold text-gray-900">Pengajuan Terbaru</h2>
              <p class="text-sm text-gray-500">Pantau status beberapa pengajuan booking terakhir kamu.</p>
            </div>
            <Link
              :href="route('bookings.index')"
              class="inline-flex items-center rounded-md border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-600 transition hover:bg-gray-100"
            >
              Lihat Semua
            </Link>
          </div>

          <div v-if="recentBookingsList.length" class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm text-gray-700">
              <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                  <th class="px-4 py-3 text-left">Judul</th>
                  <th class="px-4 py-3 text-left">Ruangan</th>
                  <th class="px-4 py-3 text-left">Mulai</th>
                  <th class="px-4 py-3 text-left">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="booking in recentBookingsList" :key="booking.id" class="hover:bg-gray-50">
                  <td class="px-4 py-3">
                    <div class="font-semibold text-gray-900">{{ booking.title }}</div>
                    <div class="text-xs text-gray-500">{{ booking.description || 'Tidak ada deskripsi.' }}</div>
                  </td>
                  <td class="px-4 py-3">
                    <div class="font-medium text-gray-900">{{ booking.room?.name ?? '-' }}</div>
                    <div class="text-xs text-gray-500">
                      {{ booking.room?.building?.name ?? '-' }} - {{ booking.room?.building?.campus?.name ?? '-' }}
                    </div>
                  </td>
                  <td class="px-4 py-3 text-gray-700">{{ formatDateTime(booking.start_time) }}</td>
                  <td class="px-4 py-3">
                    <span
                      class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold capitalize"
                      :class="statusBadgeClasses[booking.normalizedStatus] ?? 'bg-gray-100 text-gray-600 border border-gray-200'"
                    >
                      {{ statusLabels[booking.normalizedStatus] ?? (booking.normalizedStatus || booking.status) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <p v-else class="mt-6 text-sm text-gray-500">
            Kamu belum memiliki pengajuan booking. Mulai dengan memilih ruangan yang sesuai di atas.
          </p>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
