<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed, nextTick, ref, watch } from 'vue'
import {
  Boxes,
  Building2,
  CalendarClock,
  CheckCircle2,
  ClipboardList,
  PackageCheck,
  Search,
} from 'lucide-vue-next'
import {
  getBookingStatusClasses,
  getBookingStatusLabel,
  normalizeBookingStatus,
} from '@/Composables/useBookingStatus'
import {
  getItemBorrowingStatusClasses,
  getItemBorrowingStatusLabel,
  normalizeItemBorrowingStatus,
} from '@/Composables/useItemBorrowingStatus'
import { usePagination } from '@/Composables/usePagination'

const props = defineProps({
  rooms: { type: Array, default: () => [] },
  campuses: { type: Array, default: () => [] },
  items: { type: Array, default: () => [] },
  roomSummary: { type: Object, default: () => ({}) },
  itemSummary: { type: Object, default: () => ({}) },
  combinedSummary: { type: Object, default: () => ({}) },
  requestHistory: { type: Array, default: () => [] },
  minimumBookingDate: { type: String, required: true },
  minimumBorrowDate: { type: String, required: true },
})

const page = usePage()
const greetingName = computed(() => {
  const name = page.props?.auth?.user?.name ?? 'Pengguna'
  return name.split(' ')[0] || name
})

const activeStatsTab = ref('all')
const activeSearchTab = ref('room')
const availabilitySection = ref(null)
const requestHistorySection = ref(null)
const historyType = ref('all')
const historyStatus = ref('')

const emptyRoomFilters = () => ({
  query: '',
  minCapacity: '',
  campusId: '',
  buildingId: '',
  date: '',
  startTime: '',
  endTime: '',
  onlyAvailable: true,
  sort: 'capacity-desc',
})

const emptyItemFilters = () => ({
  itemId: '',
  date: '',
  startTime: '',
  endTime: '',
  quantity: 1,
  onlyAvailable: true,
  sort: 'name-asc',
})

const roomFilters = ref(emptyRoomFilters())
const itemFilters = ref(emptyItemFilters())
const appliedRoomFilters = ref(null)
const appliedItemFilters = ref(null)
const roomAvailability = ref({})
const itemAvailability = ref({})
const isSearching = ref(false)
const searchError = ref('')

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

const roomEndTimes = computed(() =>
  timeSlots.filter((slot) => !roomFilters.value.startTime || slot > roomFilters.value.startTime),
)
const itemEndTimes = computed(() =>
  timeSlots.filter((slot) => !itemFilters.value.startTime || slot > itemFilters.value.startTime),
)

watch(() => roomFilters.value.campusId, () => {
  roomFilters.value.buildingId = ''
})
watch(() => roomFilters.value.startTime, (value) => {
  if (roomFilters.value.endTime && roomFilters.value.endTime <= value) {
    roomFilters.value.endTime = ''
  }
})
watch(() => itemFilters.value.startTime, (value) => {
  if (itemFilters.value.endTime && itemFilters.value.endTime <= value) {
    itemFilters.value.endTime = ''
  }
})

const selectedCampus = computed(() =>
  props.campuses.find((campus) => String(campus.id) === String(roomFilters.value.campusId)),
)
const buildingOptions = computed(() => selectedCampus.value?.buildings ?? [])

const statsTabs = [
  { key: 'all', label: 'Semua' },
  { key: 'room', label: 'Ruangan' },
  { key: 'item', label: 'Barang' },
]

const summaryForActiveTab = computed(() => {
  if (activeStatsTab.value === 'room') return props.roomSummary
  if (activeStatsTab.value === 'item') return props.itemSummary
  return props.combinedSummary
})

const statisticCards = computed(() => {
  const summary = summaryForActiveTab.value ?? {}
  const cards = [
    { key: 'total', label: 'Total Pengajuan', tone: 'blue' },
    { key: 'approved', label: 'Disetujui', tone: 'emerald' },
    { key: 'waiting', label: 'Menunggu', tone: 'amber' },
    { key: 'needs_revision', label: 'Perlu Direvisi', tone: 'violet' },
    { key: 'rejected', label: 'Ditolak', tone: 'rose' },
    { key: 'cancelled', label: 'Dibatalkan', tone: 'slate' },
  ]

  if (activeStatsTab.value === 'room') {
    cards.push({ key: 'expired', label: 'Kedaluwarsa', tone: 'orange' })
  }
  if (activeStatsTab.value === 'item') {
    cards.push({ key: 'completed', label: 'Selesai', tone: 'sky' })
  }

  return cards.map((card) => ({ ...card, value: summary[card.key] ?? 0 }))
})

const statisticTone = {
  blue: 'border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-700 dark:text-blue-200',
  emerald: 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-700 dark:text-emerald-300',
  amber: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-700 dark:text-amber-300',
  violet: 'border-violet-200 bg-violet-50 text-violet-900 dark:border-violet-700 dark:text-violet-300',
  rose: 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-700 dark:text-rose-300',
  slate: 'border-slate-200 bg-slate-50 text-slate-900 dark:border-slate-600 dark:text-slate-200',
  orange: 'border-orange-200 bg-orange-50 text-orange-900 dark:border-orange-700 dark:text-orange-300',
  sky: 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-700 dark:text-sky-300',
}

const extractFeatures = (features) => {
  if (Array.isArray(features)) return features.filter(Boolean)
  if (!features || typeof features !== 'string') return []
  try {
    const parsed = JSON.parse(features)
    if (Array.isArray(parsed)) return parsed.filter(Boolean)
  } catch {
    return features.split(',').map((feature) => feature.trim()).filter(Boolean)
  }
  return []
}

const localRoomCandidates = computed(() => {
  const filters = appliedRoomFilters.value
  if (!filters) return []
  const query = filters.query.toLowerCase()

  const filtered = props.rooms.filter((room) => {
    const campusId = String(room.building?.campus?.id ?? '')
    const searchable = [
      room.name,
      room.building?.name,
      room.building?.campus?.name,
      ...extractFeatures(room.features),
    ].filter(Boolean).join(' ').toLowerCase()

    return (!query || searchable.includes(query))
      && (!filters.campusId || campusId === String(filters.campusId))
      && (!filters.buildingId || String(room.building_id) === String(filters.buildingId))
      && (!filters.minCapacity || Number(room.capacity) >= Number(filters.minCapacity))
  })

  return filtered.sort((left, right) => {
    if (filters.sort === 'capacity-asc') return Number(left.capacity) - Number(right.capacity)
    if (filters.sort === 'name-asc') return left.name.localeCompare(right.name)
    if (filters.sort === 'availability') return Number(right.is_available) - Number(left.is_available)
    return Number(right.capacity) - Number(left.capacity)
  })
})

const roomResults = computed(() => localRoomCandidates.value.filter((room) => {
  if (!appliedRoomFilters.value?.onlyAvailable) return true
  if (!room.is_available) return false
  const result = roomAvailability.value[room.id]
  return !result || result.available
}))

const localItemCandidates = computed(() => {
  const filters = appliedItemFilters.value
  if (!filters) return []

  const filtered = props.items.filter((item) =>
    !filters.itemId || String(item.id) === String(filters.itemId),
  )

  return filtered.sort((left, right) => {
    if (filters.sort === 'quantity-desc') return Number(right.quantity) - Number(left.quantity)
    if (filters.sort === 'quantity-asc') return Number(left.quantity) - Number(right.quantity)
    return left.name.localeCompare(right.name)
  })
})

const itemResults = computed(() => localItemCandidates.value.filter((item) => {
  if (!appliedItemFilters.value?.onlyAvailable) return true
  if (!item.is_available || Number(item.quantity) < Number(appliedItemFilters.value.quantity)) return false
  const result = itemAvailability.value[item.id]
  return !result || result.remaining >= Number(appliedItemFilters.value.quantity)
}))

const hasCompleteSchedule = (filters) =>
  Boolean(filters.date && filters.startTime && filters.endTime)

const validateSchedule = (filters, minimumDate) => {
  const hasAnySchedule = filters.date || filters.startTime || filters.endTime
  if (hasAnySchedule && !hasCompleteSchedule(filters)) {
    return 'Tanggal, jam mulai, dan jam selesai harus diisi bersama.'
  }
  if (filters.date && filters.date < minimumDate) {
    return `Tanggal paling awal yang dapat dipilih adalah ${minimumDate}.`
  }
  return ''
}

const overlapsSelectedTime = (booking, startTime, endTime) =>
  booking.start < endTime && booking.end > startTime

const searchRooms = async () => {
  searchError.value = validateSchedule(roomFilters.value, props.minimumBookingDate)
  if (searchError.value) return

  appliedRoomFilters.value = { ...roomFilters.value, query: roomFilters.value.query.trim() }
  roomAvailability.value = {}
  if (!hasCompleteSchedule(appliedRoomFilters.value)) return

  isSearching.value = true
  try {
    const results = await Promise.all(localRoomCandidates.value.map(async (room) => {
      const response = await fetch(route('rooms.availability', {
        room: room.id,
        dates: [appliedRoomFilters.value.date],
      }), { headers: { Accept: 'application/json' } })
      if (!response.ok) throw new Error('Gagal memeriksa ketersediaan ruangan.')
      const data = await response.json()
      const bookings = (data.daily_bookings ?? []).flatMap((entry) => entry.bookings ?? [])
      const hasConflict = bookings.some((booking) =>
        overlapsSelectedTime(
          booking,
          appliedRoomFilters.value.startTime,
          appliedRoomFilters.value.endTime,
        ),
      )
      return [room.id, {
        available: data.available !== false && !hasConflict,
        conflictCount: bookings.filter((booking) =>
          overlapsSelectedTime(
            booking,
            appliedRoomFilters.value.startTime,
            appliedRoomFilters.value.endTime,
          ),
        ).length,
      }]
    }))
    roomAvailability.value = Object.fromEntries(results)
  } catch (error) {
    searchError.value = error.message || 'Ketersediaan ruangan tidak dapat diperiksa.'
  } finally {
    isSearching.value = false
  }
}

const searchItems = async () => {
  searchError.value = validateSchedule(itemFilters.value, props.minimumBorrowDate)
  if (searchError.value) return

  appliedItemFilters.value = {
    ...itemFilters.value,
    quantity: Math.max(1, Number(itemFilters.value.quantity) || 1),
  }
  itemAvailability.value = {}
  if (!hasCompleteSchedule(appliedItemFilters.value)) return

  isSearching.value = true
  try {
    const results = await Promise.all(localItemCandidates.value.map(async (item) => {
      const response = await fetch(route('items.availability', {
        item: item.id,
        dates: [appliedItemFilters.value.date],
        start_time: appliedItemFilters.value.startTime,
        end_time: appliedItemFilters.value.endTime,
      }), { headers: { Accept: 'application/json' } })
      if (!response.ok) throw new Error('Gagal memeriksa ketersediaan barang.')
      const data = await response.json()
      const daily = data.daily_availability?.[0]
      return [item.id, {
        available: data.available !== false,
        remaining: Number(daily?.remaining_quantity ?? item.quantity),
      }]
    }))
    itemAvailability.value = Object.fromEntries(results)
  } catch (error) {
    searchError.value = error.message || 'Ketersediaan barang tidak dapat diperiksa.'
  } finally {
    isSearching.value = false
  }
}

const handleSearch = () => {
  if (activeSearchTab.value === 'room') return searchRooms()
  return searchItems()
}

const resetSearch = () => {
  searchError.value = ''
  if (activeSearchTab.value === 'room') {
    roomFilters.value = emptyRoomFilters()
    appliedRoomFilters.value = null
    roomAvailability.value = {}
    return
  }
  itemFilters.value = emptyItemFilters()
  appliedItemFilters.value = null
  itemAvailability.value = {}
}

const showSearch = async (type) => {
  activeSearchTab.value = type
  searchError.value = ''
  await nextTick()
  availabilitySection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const roomCreateUrl = (room) => route('bookings.create', {
  room_id: room.id,
  date: appliedRoomFilters.value?.date || undefined,
  start_time: appliedRoomFilters.value?.startTime || undefined,
  end_time: appliedRoomFilters.value?.endTime || undefined,
})

const itemCreateUrl = (item) => route('item-borrowings.create', {
  item_id: item.id,
  quantity: appliedItemFilters.value?.quantity || 1,
  date: appliedItemFilters.value?.date || undefined,
  start_time: appliedItemFilters.value?.startTime || undefined,
  end_time: appliedItemFilters.value?.endTime || undefined,
})

const statusLabel = (request) => request.type === 'room'
  ? getBookingStatusLabel(normalizeBookingStatus(request.status))
  : getItemBorrowingStatusLabel(normalizeItemBorrowingStatus(request.status))

const statusClasses = (request) => request.type === 'room'
  ? getBookingStatusClasses(normalizeBookingStatus(request.status))
  : getItemBorrowingStatusClasses(normalizeItemBorrowingStatus(request.status))

const requestDetailUrl = (request) => request.type === 'room'
  ? route('bookings.show', request.id)
  : route('item-borrowings.show', request.id)

const normalizedRequestStatus = (request) => request.type === 'room'
  ? normalizeBookingStatus(request.status)
  : normalizeItemBorrowingStatus(request.status)

const filteredRequestHistory = computed(() =>
  props.requestHistory.filter((request) =>
    (historyType.value === 'all' || request.type === historyType.value)
    && (!historyStatus.value || normalizedRequestStatus(request) === historyStatus.value),
  ),
)

const {
  paginatedItems: paginatedRequestHistory,
  currentPage: historyPage,
  totalPages: historyTotalPages,
  pageMeta: historyPageMeta,
  changePage: changeHistoryPage,
} = usePagination(filteredRequestHistory, { perPage: 5 })

watch([historyType, historyStatus], () => {
  historyPage.value = 1
})

watch(historyType, (type) => {
  if (type === 'room' && historyStatus.value === 'completed') {
    historyStatus.value = ''
  }
  if (type === 'item' && historyStatus.value === 'expired') {
    historyStatus.value = ''
  }
})

const historyStatusOptions = computed(() => {
  const options = [
    { value: 'waiting', label: 'Menunggu Persetujuan' },
    { value: 'needs_revision', label: 'Perlu Direvisi' },
    { value: 'approved', label: 'Disetujui' },
    { value: 'rejected', label: 'Ditolak' },
    { value: 'cancelled', label: 'Dibatalkan' },
  ]

  if (historyType.value !== 'item') {
    options.push({ value: 'expired', label: 'Kedaluwarsa' })
  }
  if (historyType.value !== 'room') {
    options.push({ value: 'completed', label: 'Selesai' })
  }

  return options
})

const filterHistoryFromStatistic = async (stat) => {
  historyType.value = activeStatsTab.value
  historyStatus.value = stat.key === 'total' ? '' : stat.key
  historyPage.value = 1
  await nextTick()
  requestHistorySection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Dashboard" />

    <div class="py-8 sm:py-10">
      <div class="mx-auto space-y-8 px-4 sm:px-6 lg:max-w-7xl lg:px-8">
        <section class="dashboard-hero card-surface overflow-hidden p-6 sm:p-8">
          <div class="flex flex-col gap-7 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-3xl space-y-3">
              <p class="text-sm font-semibold text-blue-700 dark:text-blue-300">Halo, {{ greetingName }}!</p>
              <h1 class="text-3xl font-semibold leading-tight text-slate-900 dark:text-white sm:text-4xl">
                Ajukan Peminjaman Ruangan dan Barang dengan Mudah
              </h1>
              <p class="max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300 sm:text-base">
                Cari ruangan atau barang yang tersedia sesuai kebutuhan kegiatanmu, lalu ajukan peminjaman secara cepat.
              </p>
              <div class="flex flex-wrap gap-2 pt-1 text-xs font-semibold">
                <span class="rounded-full bg-white/90 px-3 py-1.5 text-blue-700 shadow-sm dark:bg-slate-700 dark:text-blue-300">
                  {{ rooms.length }} ruangan
                </span>
                <span class="rounded-full bg-white/90 px-3 py-1.5 text-blue-700 shadow-sm dark:bg-slate-700 dark:text-blue-300">
                  {{ items.length }} barang
                </span>
                <span class="rounded-full bg-white/90 px-3 py-1.5 text-blue-700 shadow-sm dark:bg-slate-700 dark:text-blue-300">
                  {{ combinedSummary.total ?? 0 }} request kamu
                </span>
              </div>
            </div>

            <div class="grid w-full gap-3 sm:grid-cols-3 lg:w-auto lg:grid-cols-1">
              <Link :href="route('bookings.create')" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/20 transition hover:bg-blue-700">
                <Building2 class="h-4 w-4" /> + Pinjam Ruangan
              </Link>
              <Link :href="route('item-borrowings.create')" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/20 transition hover:bg-blue-700">
                <Boxes class="h-4 w-4" /> + Pinjam Barang
              </Link>
              <a href="#riwayat-request" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600">
                <ClipboardList class="h-4 w-4" /> Lihat Request Saya
              </a>
            </div>
          </div>
        </section>

        <section>
          <div class="mb-4">
            <h2 class="section-heading">Pilih Jenis Peminjaman</h2>
            <p class="section-subtitle">Mulai dari kebutuhan ruangan atau perlengkapan kegiatanmu.</p>
          </div>
          <div class="grid gap-5 lg:grid-cols-2">
            <article class="card-surface flex flex-col justify-between gap-6 p-6">
              <div class="flex items-start gap-4">
                <div class="rounded-2xl bg-blue-100 p-3 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                  <Building2 class="h-7 w-7" />
                </div>
                <div>
                  <h3 class="text-xl font-semibold text-slate-900 dark:text-white">Peminjaman Ruangan</h3>
                  <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    Cari ruangan berdasarkan kampus, gedung, kapasitas, tanggal, dan jam penggunaan.
                  </p>
                </div>
              </div>
              <div class="flex flex-col gap-2 sm:flex-row">
                <Link :href="route('bookings.create')" class="inline-flex justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                  Ajukan Ruangan
                </Link>
                <button type="button" class="inline-flex justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700" @click="showSearch('room')">
                  Lihat Ruangan
                </button>
              </div>
            </article>

            <article class="card-surface flex flex-col justify-between gap-6 p-6">
              <div class="flex items-start gap-4">
                <div class="rounded-2xl bg-blue-100 p-3 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                  <Boxes class="h-7 w-7" />
                </div>
                <div>
                  <h3 class="text-xl font-semibold text-slate-900 dark:text-white">Peminjaman Barang</h3>
                  <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    Ajukan proyektor, sound system, kabel, atau perlengkapan lain sesuai stok dan jadwal.
                  </p>
                </div>
              </div>
              <div class="flex flex-col gap-2 sm:flex-row">
                <Link :href="route('item-borrowings.create')" class="inline-flex justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                  Ajukan Barang
                </Link>
                <button type="button" class="inline-flex justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700" @click="showSearch('item')">
                  Lihat Barang
                </button>
              </div>
            </article>
          </div>
        </section>

        <section class="card-surface p-5 sm:p-6">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="section-heading">Ringkasan Request</h2>
              <p class="section-subtitle">Pantau status pengajuan ruangan dan barang milikmu.</p>
            </div>
            <div class="inline-flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800" role="tablist" aria-label="Jenis statistik">
              <button
                v-for="tab in statsTabs"
                :key="tab.key"
                type="button"
                class="rounded-lg px-3 py-2 text-sm font-semibold transition"
                :class="activeStatsTab === tab.key ? 'bg-white text-blue-700 shadow-sm dark:bg-slate-700 dark:text-blue-300' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'"
                @click="activeStatsTab = tab.key"
              >
                {{ tab.label }}
              </button>
            </div>
          </div>

          <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-7">
            <button
              v-for="stat in statisticCards"
              :key="`${activeStatsTab}-${stat.key}`"
              type="button"
              class="dashboard-stat rounded-2xl border p-4 text-left transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-800"
              :class="statisticTone[stat.tone]"
              :aria-label="`Tampilkan riwayat ${stat.label.toLowerCase()} untuk ${statsTabs.find((tab) => tab.key === activeStatsTab)?.label ?? 'Semua'}`"
              @click="filterHistoryFromStatistic(stat)"
            >
              <p class="text-[11px] font-semibold uppercase tracking-wide opacity-70">{{ stat.label }}</p>
              <p class="mt-2 text-3xl font-semibold">{{ stat.value }}</p>
            </button>
          </div>
        </section>

        <section ref="availabilitySection" class="card-surface scroll-mt-6 p-5 sm:p-6">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="section-heading">Cari Ketersediaan</h2>
              <p class="section-subtitle">Filter ruangan dan barang dipisahkan agar pencarian tetap ringkas.</p>
            </div>
            <div class="inline-flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800" role="tablist" aria-label="Jenis pencarian">
              <button type="button" class="rounded-lg px-4 py-2 text-sm font-semibold transition" :class="activeSearchTab === 'room' ? 'bg-white text-blue-700 shadow-sm dark:bg-slate-700 dark:text-blue-300' : 'text-slate-500 dark:text-slate-400'" @click="activeSearchTab = 'room'; searchError = ''">
                Ruangan
              </button>
              <button type="button" class="rounded-lg px-4 py-2 text-sm font-semibold transition" :class="activeSearchTab === 'item' ? 'bg-white text-blue-700 shadow-sm dark:bg-slate-700 dark:text-blue-300' : 'text-slate-500 dark:text-slate-400'" @click="activeSearchTab = 'item'; searchError = ''">
                Barang
              </button>
            </div>
          </div>

          <p v-if="searchError" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">
            {{ searchError }}
          </p>

          <form class="mt-6 space-y-5" @submit.prevent="handleSearch">
            <template v-if="activeSearchTab === 'room'">
              <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="xl:col-span-2">
                  <span class="form-label">Kata Kunci</span>
                  <input v-model="roomFilters.query" type="text" placeholder="Nama ruangan, gedung..." class="dashboard-input" />
                </label>
                <label>
                  <span class="form-label">Kapasitas Minimum</span>
                  <input v-model="roomFilters.minCapacity" type="number" min="1" class="dashboard-input" placeholder="Contoh: 30" />
                </label>
                <label>
                  <span class="form-label">Urutkan</span>
                  <select v-model="roomFilters.sort" class="dashboard-input">
                    <option value="capacity-desc">Kapasitas terbesar</option>
                    <option value="capacity-asc">Kapasitas terkecil</option>
                    <option value="availability">Ketersediaan</option>
                    <option value="name-asc">Nama A-Z</option>
                  </select>
                </label>
                <label>
                  <span class="form-label">Kampus</span>
                  <select v-model="roomFilters.campusId" class="dashboard-input">
                    <option value="">Semua Kampus</option>
                    <option v-for="campus in campuses" :key="campus.id" :value="campus.id">{{ campus.name }}</option>
                  </select>
                </label>
                <label>
                  <span class="form-label">Gedung</span>
                  <select v-model="roomFilters.buildingId" :disabled="!roomFilters.campusId" class="dashboard-input disabled:cursor-not-allowed disabled:opacity-60">
                    <option value="">{{ roomFilters.campusId ? 'Semua Gedung' : 'Pilih kampus dahulu' }}</option>
                    <option v-for="building in buildingOptions" :key="building.id" :value="building.id">{{ building.name }}</option>
                  </select>
                </label>
                <label>
                  <span class="form-label">Tanggal Penggunaan</span>
                  <input v-model="roomFilters.date" type="date" :min="minimumBookingDate" class="dashboard-input" />
                </label>
                <label>
                  <span class="form-label">Jam Mulai</span>
                  <select v-model="roomFilters.startTime" :disabled="!roomFilters.date" class="dashboard-input disabled:cursor-not-allowed disabled:opacity-60">
                    <option value="">Pilih jam</option>
                    <option v-for="slot in timeSlots.slice(0, -1)" :key="`room-start-${slot}`" :value="slot">{{ slot }}</option>
                  </select>
                </label>
                <label>
                  <span class="form-label">Jam Selesai</span>
                  <select v-model="roomFilters.endTime" :disabled="!roomFilters.startTime" class="dashboard-input disabled:cursor-not-allowed disabled:opacity-60">
                    <option value="">Pilih jam</option>
                    <option v-for="slot in roomEndTimes" :key="`room-end-${slot}`" :value="slot">{{ slot }}</option>
                  </select>
                </label>
              </div>
              <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input v-model="roomFilters.onlyAvailable" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                Tampilkan hanya ruangan yang tersedia
              </label>
            </template>

            <template v-else>
              <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="xl:col-span-2">
                  <span class="form-label">Barang</span>
                  <select v-model="itemFilters.itemId" class="dashboard-input">
                    <option value="">Semua Barang</option>
                    <option v-for="item in items" :key="item.id" :value="item.id">
                      {{ item.name }}
                    </option>
                  </select>
                </label>
                <label>
                  <span class="form-label">Jumlah Dibutuhkan</span>
                  <input v-model.number="itemFilters.quantity" type="number" min="1" class="dashboard-input" />
                </label>
                <label>
                  <span class="form-label">Tanggal Penggunaan</span>
                  <input v-model="itemFilters.date" type="date" :min="minimumBorrowDate" class="dashboard-input" />
                </label>
                <label>
                  <span class="form-label">Jam Mulai</span>
                  <select v-model="itemFilters.startTime" :disabled="!itemFilters.date" class="dashboard-input disabled:cursor-not-allowed disabled:opacity-60">
                    <option value="">Pilih jam</option>
                    <option v-for="slot in timeSlots.slice(0, -1)" :key="`item-start-${slot}`" :value="slot">{{ slot }}</option>
                  </select>
                </label>
                <label>
                  <span class="form-label">Jam Selesai</span>
                  <select v-model="itemFilters.endTime" :disabled="!itemFilters.startTime" class="dashboard-input disabled:cursor-not-allowed disabled:opacity-60">
                    <option value="">Pilih jam</option>
                    <option v-for="slot in itemEndTimes" :key="`item-end-${slot}`" :value="slot">{{ slot }}</option>
                  </select>
                </label>
                <label>
                  <span class="form-label">Urutkan</span>
                  <select v-model="itemFilters.sort" class="dashboard-input">
                    <option value="name-asc">Nama A-Z</option>
                    <option value="quantity-desc">Stok terbesar</option>
                    <option value="quantity-asc">Stok terkecil</option>
                  </select>
                </label>
              </div>
              <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input v-model="itemFilters.onlyAvailable" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                Tampilkan hanya barang yang tersedia sesuai jumlah
              </label>
            </template>

            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
              <button type="button" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700" @click="resetSearch">
                Reset
              </button>
              <button type="submit" :disabled="isSearching" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-wait disabled:opacity-70">
                <Search class="h-4 w-4" /> {{ isSearching ? 'Memeriksa...' : 'Cari Ketersediaan' }}
              </button>
            </div>
          </form>
        </section>

        <section class="card-surface p-5 sm:p-6">
          <div class="flex items-end justify-between gap-4">
            <div>
              <h2 class="section-heading">Hasil {{ activeSearchTab === 'room' ? 'Ruangan' : 'Barang' }}</h2>
              <p class="section-subtitle">
                <template v-if="activeSearchTab === 'room' && appliedRoomFilters">{{ roomResults.length }} hasil sesuai filter.</template>
                <template v-else-if="activeSearchTab === 'item' && appliedItemFilters">{{ itemResults.length }} hasil sesuai filter.</template>
                <template v-else>Isi filter dan tekan Cari Ketersediaan untuk melihat hasil.</template>
              </p>
            </div>
          </div>

          <div v-if="activeSearchTab === 'room' && appliedRoomFilters && roomResults.length" class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <article v-for="room in roomResults" :key="room.id" class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
              <div>
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white">{{ room.name }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ room.building?.campus?.name }} · {{ room.building?.name }}</p>
                  </div>
                  <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="roomAvailability[room.id]?.available === false || !room.is_available ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'">
                    {{ roomAvailability[room.id]?.available === false || !room.is_available ? 'Tidak tersedia' : 'Tersedia' }}
                  </span>
                </div>
                <p class="mt-4 text-sm font-medium text-slate-700 dark:text-slate-200">Kapasitas {{ room.capacity }} orang</p>
                <div v-if="extractFeatures(room.features).length" class="mt-3 flex flex-wrap gap-2">
                  <span v-for="feature in extractFeatures(room.features).slice(0, 4)" :key="feature" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ feature }}</span>
                </div>
              </div>
              <Link :href="roomCreateUrl(room)" class="mt-5 inline-flex justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                Ajukan Ruangan Ini
              </Link>
            </article>
          </div>

          <div v-else-if="activeSearchTab === 'item' && appliedItemFilters && itemResults.length" class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <article v-for="item in itemResults" :key="item.id" class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
              <div>
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-300">{{ item.code || 'Tanpa kode' }}</p>
                    <h3 class="mt-1 font-semibold text-slate-900 dark:text-white">{{ item.name }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ item.category || 'Tanpa kategori' }}</p>
                  </div>
                  <PackageCheck class="h-6 w-6 text-emerald-500" />
                </div>
                <div class="mt-4 rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-slate-700/60">
                  <span class="text-slate-500 dark:text-slate-400">Tersedia:</span>
                  <span class="ml-1 font-semibold text-slate-900 dark:text-white">{{ itemAvailability[item.id]?.remaining ?? item.quantity }} dari {{ item.quantity }} unit</span>
                </div>
              </div>
              <Link :href="itemCreateUrl(item)" class="mt-5 inline-flex justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                Ajukan Barang Ini
              </Link>
            </article>
          </div>

          <div v-else class="mt-6 rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
            <Search class="mx-auto mb-3 h-8 w-8 opacity-50" />
            {{ (activeSearchTab === 'room' ? appliedRoomFilters : appliedItemFilters) ? 'Tidak ada hasil yang cocok dengan filter saat ini.' : 'Belum ada pencarian.' }}
          </div>
        </section>

        <section id="riwayat-request" ref="requestHistorySection" class="card-surface scroll-mt-6 p-5 sm:p-6">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <h2 class="section-heading">Riwayat Request Saya</h2>
              <p class="section-subtitle">Seluruh pengajuan ruangan dan barang milikmu, diurutkan dari yang terbaru.</p>
            </div>
            <div class="grid gap-2 sm:grid-cols-2">
              <label>
                <span class="sr-only">Jenis peminjaman</span>
                <select v-model="historyType" class="dashboard-input min-w-44">
                  <option value="all">Semua Jenis</option>
                  <option value="room">Ruangan</option>
                  <option value="item">Barang</option>
                </select>
              </label>
              <label>
                <span class="sr-only">Status request</span>
                <select v-model="historyStatus" class="dashboard-input min-w-48">
                  <option value="">Semua Status</option>
                  <option v-for="option in historyStatusOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </label>
            </div>
          </div>

          <div v-if="paginatedRequestHistory.length" class="mt-5 grid gap-3">
            <Link v-for="request in paginatedRequestHistory" :key="request.key" :href="requestDetailUrl(request)" class="group flex flex-col gap-3 rounded-2xl border border-slate-200 p-4 transition hover:border-blue-300 hover:bg-blue-50/40 dark:border-slate-700 dark:hover:border-blue-700 dark:hover:bg-blue-950/20 sm:flex-row sm:items-center sm:justify-between">
              <div class="flex min-w-0 items-start gap-3">
                <div class="rounded-xl bg-blue-100 p-2.5 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                  <Building2 v-if="request.type === 'room'" class="h-5 w-5" />
                  <Boxes v-else class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ request.type === 'room' ? 'Ruangan' : 'Barang' }}</span>
                    <span class="font-semibold text-slate-900 dark:text-white">{{ request.title }}</span>
                  </div>
                  <p class="mt-1 truncate text-sm text-slate-600 dark:text-slate-300">{{ request.resource_name }}</p>
                  <p class="mt-1 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                    <CalendarClock class="h-3.5 w-3.5" /> {{ request.schedule || 'Jadwal belum tersedia' }}
                    <template v-if="request.quantity"> · {{ request.quantity }} unit</template>
                  </p>
                </div>
              </div>
              <span class="inline-flex shrink-0 self-start rounded-full border px-3 py-1 text-xs font-semibold sm:self-center" :class="statusClasses(request)">
                {{ statusLabel(request) }}
              </span>
            </Link>
          </div>
          <div v-else class="mt-5 rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
            <CheckCircle2 class="mx-auto mb-3 h-8 w-8 opacity-50" />
            Tidak ada request yang cocok dengan filter saat ini.
          </div>

          <div v-if="historyPageMeta.of" class="mt-5 flex flex-col gap-3 border-t border-slate-200 pt-4 dark:border-slate-700 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-500 dark:text-slate-400">
              Menampilkan {{ historyPageMeta.from }}–{{ historyPageMeta.to }} dari {{ historyPageMeta.of }} request
            </p>
            <div class="flex items-center gap-2">
              <button type="button" :disabled="historyPage <= 1" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700" @click="changeHistoryPage(historyPage - 1)">
                Sebelumnya
              </button>
              <span class="px-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                {{ historyPage }} / {{ historyTotalPages }}
              </span>
              <button type="button" :disabled="historyPage >= historyTotalPages" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700" @click="changeHistoryPage(historyPage + 1)">
                Berikutnya
              </button>
            </div>
          </div>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.dashboard-hero {
  background-color: #ffffff;
  background-image: linear-gradient(to bottom right, #eff6ff, #ffffff, #e0f2fe);
}

.form-label {
  @apply mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300;
}

.dashboard-input {
  @apply w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200;
}
</style>
