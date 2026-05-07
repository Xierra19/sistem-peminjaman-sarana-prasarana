<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { formatDateTimeToDDMMYY, formatToDDMMYY } from '@/Composables/useDateFormatter'
import { useTableSort } from '@/Composables/useTableSort'

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
  cancelled: 'Dibatalkan',
}

const statusBadgeClasses = {
  approved: 'bg-emerald-100 text-emerald-700 border border-emerald-200',
  waiting: 'bg-amber-100 text-amber-700 border border-amber-200',
  rejected: 'bg-rose-100 text-rose-700 border border-rose-200',
  cancelled: 'bg-slate-100 text-slate-700 border border-slate-200',
}

const formatDateTime = (value) => formatDateTimeToDDMMYY(value)

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

const sortOptionLabels = {
  'capacity-desc': 'Kapasitas terbesar',
  'capacity-asc': 'Kapasitas terkecil',
  availability: 'Ketersediaan',
  'name-asc': 'Nama ruangan (A-Z)',
}

const findCampusById = (id) => {
  if (!id) return null
  return campusOptions.value.find((campus) => String(campus.id) === String(id)) ?? null
}

const findBuildingById = (id) => {
  if (!id) return null
  for (const campus of campusOptions.value) {
    const building = (campus.buildings ?? []).find((item) => String(item.id) === String(id))
    if (building) {
      return building
    }
  }
  return null
}

const formatDateLabel = (value) => formatToDDMMYY(value)

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

const activeFilterChips = computed(() => {
  const filters = appliedFilters.value
  if (!filters) {
    return []
  }

  const chips = []

  if (filters.query) {
    chips.push({ label: 'Kata kunci', value: filters.query })
  }

  if (filters.minCapacity) {
    chips.push({ label: 'Kapasitas ≥', value: `${filters.minCapacity} orang` })
  }

  const campusName = findCampusById(filters.campusId)?.name
  if (campusName) {
    chips.push({ label: 'Kampus', value: campusName })
  }

  const buildingName = findBuildingById(filters.buildingId)?.name
  if (buildingName) {
    chips.push({ label: 'Gedung', value: buildingName })
  }

  if (filters.onlyAvailable) {
    chips.push({ label: 'Ketersediaan', value: 'Hanya ruangan tersedia' })
  }

  if (filters.date) {
    chips.push({ label: 'Tanggal', value: formatDateLabel(filters.date) })
  }

  if (filters.startTime || filters.endTime) {
    const start = filters.startTime || '--:--'
    const end = filters.endTime || '--:--'
    chips.push({ label: 'Jam', value: `${start} - ${end}` })
  }

  if (filters.sortOption && filters.sortOption !== 'capacity-desc') {
    chips.push({
      label: 'Urut',
      value: sortOptionLabels[filters.sortOption] ?? filters.sortOption,
    })
  }

  return chips
})

const recentBookingsList = computed(() =>
  (props.recentBookings ?? []).map((booking) => ({
    ...booking,
    normalizedStatus: normalizeStatus(booking.status),
  })),
)

const {
  sortedItems: sortedRecentBookings,
  toggleSort: toggleRecentBookingSort,
  sortDirection: recentBookingSortDirection,
  ariaSortValue: recentBookingAriaSortValue,
} = useTableSort(recentBookingsList, {
  accessors: {
    title: (booking) => booking.title ?? '',
    room: (booking) => booking.room?.name ?? '',
    start_time: (booking) => (booking.start_time ? new Date(booking.start_time) : null),
    status: (booking) => booking.normalizedStatus ?? '',
  },
})
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Dashboard" />

    <div class="py-8 sm:py-10">
      <div class="mx-auto space-y-8 px-4 sm:px-6 lg:max-w-7xl lg:px-8">
        <section class="card-surface bg-gradient-to-br from-blue-50 via-white to-blue-100 p-6 dark:from-slate-800 dark:via-slate-900 dark:to-slate-800">
          <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="space-y-3">
              <p class="text-sm font-semibold text-blue-700 dark:text-blue-300">Halo, {{ greetingName }}!</p>
              <h1 class="text-3xl font-semibold text-slate-900 dark:text-white">Temukan Ruangan untuk Kebutuhanmu</h1>
              <p class="text-sm text-slate-600 dark:text-slate-300">
                Cari ruangan yang sesuai dengan kapasitas, lokasi, dan jadwal kegiatanmu lalu ajukan booking secara cepat.
              </p>
              <div class="flex flex-wrap gap-2 text-xs text-slate-600 dark:text-slate-300">
                <span class="inline-flex items-center gap-1 rounded-full bg-white/90 px-3 py-1 font-semibold text-blue-700 shadow-sm dark:bg-slate-700 dark:text-blue-300">
                  🏢 {{ totalRooms }} Ruangan terdaftar
                </span>
                <span class="inline-flex items-center gap-1 rounded-full bg-white/90 px-3 py-1 font-semibold text-blue-700 shadow-sm dark:bg-slate-700 dark:text-blue-300">
                  🕑 {{ recentBookingsList.length }} Booking terbaru
                </span>
              </div>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row lg:flex-col lg:items-end">
              <Link
                :href="route('bookings.create')"
                class="inline-flex w-full items-center justify-center rounded-2xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-400/30 transition hover:bg-blue-700 sm:w-auto"
              >
                + Ajukan Booking
              </Link>
              <Link
                :href="route('bookings.index')"
                class="inline-flex w-full items-center justify-center rounded-2xl border border-blue-200 bg-white px-5 py-3 text-sm font-semibold text-blue-700 transition hover:border-blue-300 hover:bg-blue-50 sm:w-auto dark:border-slate-600 dark:bg-slate-700 dark:text-blue-300 dark:hover:border-slate-500 dark:hover:bg-slate-600"
              >
                Lihat Request Saya
              </Link>
            </div>
          </div>
        </section>

        <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
          <article class="rounded-2xl border border-blue-100 bg-blue-50 p-4 text-blue-900 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-100">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-500 dark:text-blue-300">Total Pengajuan</p>
            <p class="mt-2 text-3xl font-semibold">{{ bookingSummaryData.total }}</p>
          </article>
          <article class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-100">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-500 dark:text-emerald-300">Disetujui</p>
            <p class="mt-2 text-3xl font-semibold">{{ bookingSummaryData.approved }}</p>
          </article>
          <article class="rounded-2xl border border-amber-100 bg-amber-50 p-4 text-amber-800 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-100">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-500 dark:text-amber-300">Menunggu</p>
            <p class="mt-2 text-3xl font-semibold">{{ bookingSummaryData.waiting }}</p>
          </article>
          <article class="rounded-2xl border border-rose-100 bg-rose-50 p-4 text-rose-800 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-100">
            <p class="text-xs font-semibold uppercase tracking-wide text-rose-500 dark:text-rose-300">Ditolak</p>
            <p class="mt-2 text-3xl font-semibold">{{ bookingSummaryData.rejected }}</p>
          </article>
          <article class="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-slate-800 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-200">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Dibatalkan</p>
            <p class="mt-2 text-3xl font-semibold">{{ bookingSummaryData.cancelled }}</p>
          </article>
        </section>

        <section class="card-surface space-y-6 p-5 sm:p-6">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="section-heading">Filter Pencarian Ruangan</h2>
              <p class="section-subtitle">Isi detail kebutuhanmu lalu tekan Search untuk menampilkan hasil.</p>
            </div>
            <span class="text-xs uppercase tracking-[0.35em] text-blue-500 sm:text-right">
              {{ hasSearched ? 'Filter diperbarui' : 'Belum ada filter' }}
            </span>
          </div>

          <div
            v-if="activeFilterChips.length"
            class="flex flex-wrap items-center gap-2 rounded-2xl border border-blue-100 bg-blue-50/70 px-4 py-3 text-xs text-blue-800 dark:border-slate-700 dark:bg-slate-800/70 dark:text-blue-200"
          >
            <span class="text-[11px] font-semibold uppercase tracking-[0.45em] text-blue-500">Filter aktif</span>
              <span
                v-for="(chip, index) in activeFilterChips"
                :key="`${chip.label}-${index}`"
                class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 font-medium text-blue-700 shadow-sm dark:bg-slate-700 dark:text-blue-300"
              >
              {{ chip.label }}: <span class="font-semibold text-blue-900">{{ chip.value }}</span>
            </span>
          </div>

          <form class="space-y-6" @submit.prevent="handleSearch">
            <div class="grid gap-4 md:grid-cols-2">
              <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700" for="room-search">Kata Kunci</label>
                <input
                  id="room-search"
                  v-model="searchQuery"
                  type="text"
                  placeholder="Cari nama ruangan, fasilitas, kampus, atau gedung..."
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200 dark:placeholder:text-slate-400"
                />
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="room-capacity">Kapasitas Minimum</label>
                <input
                  id="room-capacity"
                  v-model="minCapacity"
                  type="number"
                  min="0"
                  step="1"
                  placeholder="Contoh: 30"
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200 dark:placeholder:text-slate-400"
                />
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="room-campus">Kampus</label>
                <select
                  id="room-campus"
                  v-model="campusFilter"
                  class="w-full appearance-none rounded-xl border border-slate-200 px-3 py-2 pr-9 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
                >
                  <option value="">Semua Kampus</option>
                  <option v-for="campus in campusOptions" :key="campus.id" :value="campus.id">
                    {{ campus.name }}
                  </option>
                </select>
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="room-building">Gedung</label>
                <select
                  id="room-building"
                  v-model="buildingFilter"
                  :disabled="!campusFilter"
                  class="w-full appearance-none rounded-xl border border-slate-200 px-3 py-2 pr-9 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200 dark:disabled:bg-slate-800"
                >
                  <option value="">
                    {{ campusFilter ? 'Semua Gedung' : 'Pilih kampus terlebih dahulu' }}
                  </option>
                  <option v-for="building in buildingOptions" :key="building.id" :value="building.id">
                    {{ building.name }}
                  </option>
                </select>
              </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="room-date">Tanggal Penggunaan</label>
                <input
                  id="room-date"
                  v-model="searchDate"
                  type="date"
                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
                />
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="room-start">Jam Mulai</label>
                <select
                  id="room-start"
                  v-model="searchStartTime"
                  :disabled="!searchDate"
                  class="w-full appearance-none rounded-xl border border-slate-200 px-3 py-2 pr-9 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200 dark:disabled:bg-slate-800"
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
                <label class="mb-1 block text-sm font-medium text-slate-700" for="room-end">Jam Selesai</label>
                <select
                  id="room-end"
                  v-model="searchEndTime"
                  :disabled="!searchDate || !searchStartTime"
                  class="w-full appearance-none rounded-xl border border-slate-200 px-3 py-2 pr-9 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200 dark:disabled:bg-slate-800"
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

            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
              <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-600 dark:border-slate-600 dark:text-slate-300">
                <input
                  type="checkbox"
                  v-model="onlyAvailable"
                  class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                />
                Tampilkan hanya ruangan yang tersedia
              </label>

              <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <span class="text-sm text-slate-500">Urutkan berdasarkan:</span>
                <div class="relative">
                  <select
                    v-model="sortOption"
                    class="w-full appearance-none rounded-xl border border-slate-200 px-3 py-2 pr-9 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 sm:w-56 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"
                  >
                    <option value="capacity-desc">Kapasitas terbesar</option>
                    <option value="capacity-asc">Kapasitas terkecil</option>
                    <option value="availability">Ketersediaan</option>
                    <option value="name-asc">Nama ruangan (A-Z)</option>
                  </select>
                  <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                      <path
                        fill-rule="evenodd"
                        d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08Z"
                        clip-rule="evenodd"
                      />
                    </svg>
                  </span>
                </div>
              </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
              <button
                type="button"
                class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                @click="resetFilters"
                :disabled="!canResetFilters"
              >
                Reset
              </button>
              <button
                type="submit"
                class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:bg-blue-700"
              >
                Search
              </button>
            </div>
          </form>
        </section>

        <section class="card-surface p-5 sm:p-6">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h2 class="section-heading">Daftar Ruangan</h2>
              <p class="section-subtitle">
                <template v-if="hasSearched && sortedRooms.length">
                  Menampilkan {{ resultsCount }} dari {{ totalRooms }} ruangan.
                </template>
                <template v-else>
                  Mulai dengan mengisi filter pencarian di atas.
                </template>
              </p>
            </div>
            <div v-if="hasSearched" class="flex items-center gap-2 text-sm text-slate-500">
              <span class="inline-flex items-center rounded-full border border-slate-200 px-3 py-1 font-semibold text-slate-700">
                {{ resultsCount }} hasil
              </span>
            </div>
          </div>

          <div
            v-if="!hasSearched"
            class="mt-6 rounded-2xl border border-dashed border-slate-200 bg-white p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800"
          >
            Gunakan filter di atas dan klik tombol Search untuk melihat daftar ruangan yang cocok.
          </div>

          <div v-else-if="sortedRooms.length" class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <article
              v-for="room in sortedRooms"
              :key="room.id"
              class="flex h-full flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-slate-700 dark:bg-slate-800"
            >
              <div class="space-y-4">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ room.name }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                      {{ room.building?.campus?.name ?? '-' }} - {{ room.building?.name ?? '-' }}
                    </p>
                  </div>
                  <span
                    class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                    :class="
                      room.is_available
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                        : 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-300'
                    "
                  >
                    {{ room.is_available ? 'Tersedia' : 'Tidak tersedia' }}
                  </span>
                </div>

                <dl class="grid gap-3 text-sm text-slate-600 dark:text-slate-300">
                  <div>
                    <dt class="font-medium text-slate-700 dark:text-slate-200">Kapasitas</dt>
                    <dd class="mt-1 text-base font-semibold text-slate-900 dark:text-white">
                      {{ room.capacity ?? '-' }} orang
                    </dd>
                  </div>
                  <div>
                    <dt class="font-medium text-slate-700 dark:text-slate-200">Fasilitas</dt>
                    <dd class="mt-2 flex flex-wrap gap-2">
                      <template v-if="extractFeatures(room.features).length">
                        <span
                          v-for="feature in extractFeatures(room.features)"
                          :key="feature"
                          class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-200"
                        >
                          {{ feature }}
                        </span>
                      </template>
                      <span v-else class="text-xs text-slate-400 dark:text-slate-500">Informasi fasilitas belum tersedia.</span>
                    </dd>
                  </div>
                </dl>
              </div>

              <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <Link
                  :href="route('bookings.create')"
                  class="inline-flex items-center justify-center rounded-xl border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:bg-blue-50 dark:border-slate-600 dark:text-blue-300 dark:hover:bg-slate-700"
                >
                  Ajukan Booking
                </Link>
                <span class="text-xs text-slate-400 dark:text-slate-500">Pilih ruangan ini pada formulir booking.</span>
              </div>
            </article>
          </div>

          <div
            v-else
            class="mt-6 rounded-2xl border border-dashed border-slate-200 bg-white p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800"
          >
            Tidak ada ruangan yang cocok dengan filter saat ini. Coba ubah kombinasi filter untuk melihat opsi lainnya.
          </div>
        </section>

        <section class="card-surface p-5 sm:p-6">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="section-heading">Pengajuan Terbaru</h2>
              <p class="section-subtitle">Pantau status beberapa pengajuan booking terakhir kamu.</p>
            </div>
            <Link
              :href="route('bookings.index')"
              class="inline-flex items-center rounded-xl border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
            >
              Lihat Semua
            </Link>
          </div>

          <div v-if="recentBookingsList.length" class="mt-4 overflow-x-auto">
            <table class="mobile-friendly-table min-w-full divide-y divide-slate-200 text-sm text-slate-700">
              <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                  <SortableTh
                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                    column="title"
                    label="Judul"
                    :direction="recentBookingSortDirection('title')"
                    :aria-sort="recentBookingAriaSortValue('title')"
                    @toggle="toggleRecentBookingSort"
                  />
                  <SortableTh
                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                    column="room"
                    label="Ruangan"
                    :direction="recentBookingSortDirection('room')"
                    :aria-sort="recentBookingAriaSortValue('room')"
                    @toggle="toggleRecentBookingSort"
                  />
                  <SortableTh
                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                    column="start_time"
                    label="Mulai"
                    :direction="recentBookingSortDirection('start_time')"
                    :aria-sort="recentBookingAriaSortValue('start_time')"
                    @toggle="toggleRecentBookingSort"
                  />
                  <SortableTh
                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                    column="status"
                    label="Status"
                    :direction="recentBookingSortDirection('status')"
                    :aria-sort="recentBookingAriaSortValue('status')"
                    @toggle="toggleRecentBookingSort"
                  />
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                <tr v-for="booking in sortedRecentBookings" :key="booking.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                  <td class="px-4 py-3" data-title="Judul">
                    <div class="font-semibold text-slate-900 dark:text-slate-200">{{ booking.title }}</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ booking.description || 'Tidak ada deskripsi.' }}</div>
                  </td>
                  <td class="px-4 py-3" data-title="Ruangan">
                    <div class="font-medium text-slate-900 dark:text-slate-200">{{ booking.room?.name ?? '-' }}</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                      {{ booking.room?.building?.name ?? '-' }} - {{ booking.room?.building?.campus?.name ?? '-' }}
                    </div>
                  </td>
                  <td class="px-4 py-3 text-slate-700 dark:text-slate-300" data-title="Mulai">{{ formatDateTime(booking.start_time) }}</td>
                  <td class="px-4 py-3" data-title="Status">
                    <span
                      class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold capitalize"
                      :class="statusBadgeClasses[booking.normalizedStatus] ?? 'bg-slate-100 text-slate-600 border-slate-200'"
                    >
                      {{ statusLabels[booking.normalizedStatus] ?? (booking.normalizedStatus || booking.status) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <p v-else class="mt-6 text-sm text-slate-500">
            Kamu belum memiliki pengajuan booking. Mulai dengan memilih ruangan yang sesuai di atas.
          </p>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
