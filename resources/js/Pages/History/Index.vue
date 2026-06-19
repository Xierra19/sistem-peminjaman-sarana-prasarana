<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import flatpickr from 'flatpickr'
import 'flatpickr/dist/flatpickr.css'

const props = defineProps({
  histories: {
    type: Array,
    default: () => [],
  },
  actionOptions: {
    type: Array,
    default: () => [],
  },
  roomOptions: {
    type: Array,
    default: () => [],
  },
})

const searchQuery = ref('')
const selectedAction = ref('')
const selectedRoom = ref('')
const startDate = ref('')
const endDate = ref('')
const datePickers = ref({})

const actionLabels = {
  requested: 'Diajukan',
  approved: 'Disetujui',
  needs_revision: 'Perlu Revisi',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan',
  expired: 'Kedaluwarsa',
}

const actionClasses = {
  requested: 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  approved: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
  needs_revision: 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
  rejected: 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
  cancelled: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
  expired: 'bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
}

const getSchedules = (history) => history.booking?.room_schedules ?? []

const getRoomSummary = (history) => {
  if (history.booking?.room_summary && history.booking.room_summary !== '-') {
    return history.booking.room_summary
  }

  const names = getSchedules(history)
    .map((schedule) => schedule.room?.name)
    .filter(Boolean)

  return [...new Set(names)].join(', ') || history.booking?.room?.name || '-'
}

const getLocationSummary = (history) => {
  const locations = getSchedules(history)
    .map((schedule) => {
      const building = schedule.room?.building?.name
      const campus = schedule.room?.building?.campus?.name

      return [building, campus].filter(Boolean).join(' · ')
    })
    .filter(Boolean)

  return [...new Set(locations)].join('; ') || '-'
}

const getHistoryRoomIds = (history) => {
  const ids = getSchedules(history)
    .map((schedule) => Number(schedule.room_id ?? schedule.room?.id))
    .filter(Boolean)

  if (history.booking?.room_id) {
    ids.push(Number(history.booking.room_id))
  }

  return [...new Set(ids)]
}

const getLocalDateKey = (value) => {
  if (!value) return ''

  const parts = new Intl.DateTimeFormat('en', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date(value))
  const values = Object.fromEntries(parts.map((part) => [part.type, part.value]))

  return `${values.year}-${values.month}-${values.day}`
}

const filteredHistories = computed(() => {
  const list = props.histories ?? []
  const query = searchQuery.value.trim().toLowerCase()

  return list.filter((history) => {
    const roomSearchText = [
      getRoomSummary(history),
      getLocationSummary(history),
    ].join(' ').toLowerCase()

    const matchesSearch =
      !query ||
      history.user?.name?.toLowerCase().includes(query) ||
      history.user?.email?.toLowerCase().includes(query) ||
      history.booking?.title?.toLowerCase().includes(query) ||
      String(history.booking?.id ?? '').includes(query) ||
      roomSearchText.includes(query) ||
      history.action?.toLowerCase().includes(query) ||
      (actionLabels[history.action] ?? '').toLowerCase().includes(query) ||
      history.description?.toLowerCase().includes(query)

    const matchesAction = !selectedAction.value || history.action === selectedAction.value
    const matchesRoom =
      !selectedRoom.value ||
      getHistoryRoomIds(history).includes(Number(selectedRoom.value))

    if (!matchesSearch || !matchesAction || !matchesRoom) {
      return false
    }

    const createdDate = getLocalDateKey(history.created_at)
    const startOk = !startDate.value || (createdDate && createdDate >= startDate.value)
    const endOk = !endDate.value || (createdDate && createdDate <= endDate.value)

    return startOk && endOk
  })
})

const {
  sortedItems: sortedHistories,
  toggleSort: toggleHistorySort,
  sortDirection: historySortDirection,
  ariaSortValue: historyAriaSortValue,
} = useTableSort(filteredHistories, {
  accessors: {
    id: (history) => history.id ?? 0,
    user: (history) => history.user?.name ?? '',
    room: (history) => getRoomSummary(history),
    action: (history) => history.action ?? '',
    created_at: (history) => (history.created_at ? new Date(history.created_at) : null),
    description: (history) => history.description ?? '',
  },
})

const {
  paginatedItems: paginatedHistories,
  rowsPerPage,
  currentPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(sortedHistories)

const canGoToPreviousPage = computed(() => currentPage.value > 1 && filteredHistories.value.length > 0)
const canGoToNextPage = computed(() => currentPage.value < pages.value.length && filteredHistories.value.length > 0)

const perPageOptions = [5, 10, 25, 50]

const formatDateTime = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleString('id-ID', {
    timeZone: 'Asia/Jakarta',
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const exportExcel = () => {
  const params = Object.fromEntries(
    Object.entries({
      search: searchQuery.value.trim(),
      action: selectedAction.value,
      room_id: selectedRoom.value,
      start_date: startDate.value,
      end_date: endDate.value,
    }).filter(([, value]) => value !== ''),
  )

  window.open(route('history.export.excel', params), '_blank')
}

const resetFilters = () => {
  searchQuery.value = ''
  selectedAction.value = ''
  selectedRoom.value = ''
  startDate.value = ''
  endDate.value = ''
  datePickers.value.start?.clear()
  datePickers.value.end?.clear()
}

const actionLabel = (action) => actionLabels[action] ?? action?.replaceAll('_', ' ') ?? '-'
const actionClass = (action) => actionClasses[action] ?? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'

// Inisialisasi Flatpickr
onMounted(() => {
  // Flatpickr untuk Tanggal Dari
  const startInput = document.getElementById('history-start')
  if (startInput) {
    datePickers.value.start = flatpickr(startInput, {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'd-m-y',
      onChange: (selectedDates, dateStr) => {
        startDate.value = dateStr
      }
    })
  }

  // Flatpickr untuk Tanggal Sampai
  const endInput = document.getElementById('history-end')
  if (endInput) {
    datePickers.value.end = flatpickr(endInput, {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'd-m-y',
      onChange: (selectedDates, dateStr) => {
        endDate.value = dateStr
      }
    })
  }
})

onBeforeUnmount(() => {
  Object.values(datePickers.value).forEach(picker => picker?.destroy())
})
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Riwayat Aktivitas" />

    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">Riwayat Aktivitas</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Pantau semua perubahan booking ruangan yang terekam di sistem.</p>
      </div>

      <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Total</p>
          <p class="mt-3 text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ filteredHistories.length }}</p>
          <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Aktivitas sesuai kata kunci dan tanggal.</p>
        </div>
        <div class="rounded-2xl border border-blue-200 bg-white p-4 shadow-sm dark:border-blue-900 dark:bg-slate-800">
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-600 dark:text-blue-300">Rentang Tanggal</p>
          <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-slate-100">
            {{ startDate || 'Awal' }} — {{ endDate || 'Sekarang' }}
          </p>
          <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Rentang tanggal aktivitas yang ditampilkan.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Per Halaman</p>
          <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ rowsPerPage }} baris</p>
          <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Atur kepadatan daftar aktivitas.</p>
        </div>
      </div>

      <div class="card-surface overflow-hidden dark:border-slate-700 dark:bg-slate-800">
        <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 dark:border-slate-700">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid flex-1 gap-3 md:grid-cols-2 xl:grid-cols-[minmax(16rem,1.4fr)_minmax(10rem,0.8fr)_minmax(12rem,1fr)]">
              <div class="relative w-full md:max-w-xs">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400 dark:text-slate-500">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M21 21l-4.35-4.35M9.5 17a7.5 7.5 0 1 1 7.5-7.5A7.5 7.5 0 0 1 9.5 17Z"
                    />
                  </svg>
                </span>
                <input
                  id="history-search"
                  v-model="searchQuery"
                  type="text"
                  placeholder="Cari pengguna, booking, ruangan, atau aksi…"
                  class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-700 placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                />
              </div>
              <select
                v-model="selectedAction"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
              >
                <option value="">Semua aksi</option>
                <option v-for="action in actionOptions" :key="action" :value="action">{{ actionLabel(action) }}</option>
              </select>
              <select
                v-model="selectedRoom"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
              >
                <option value="">Semua ruangan</option>
                <option v-for="room in roomOptions" :key="room.id" :value="room.id">
                  {{ room.name }}{{ room.location ? ` · ${room.location}` : '' }}
                </option>
              </select>
              <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600 dark:text-slate-300 md:col-span-2 xl:col-span-3">
                <div class="flex items-center gap-2">
                  <label class="font-medium text-slate-700 dark:text-slate-200" for="history-start">Dari</label>
                  <input
                    id="history-start"
                    v-model="startDate"
                    type="text"
                    readonly
                    placeholder="Pilih tanggal mulai"
                    class="cursor-pointer rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                  />
                </div>
                <div class="flex items-center gap-2">
                  <label class="font-medium text-slate-700 dark:text-slate-200" for="history-end">Sampai</label>
                  <input
                    id="history-end"
                    v-model="endDate"
                    type="text"
                    readonly
                    placeholder="Pilih tanggal selesai"
                    class="cursor-pointer rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                  />
                </div>
              </div>
            </div>
            <div class="flex flex-wrap gap-2 lg:justify-end">
              <button
                type="button"
                class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                @click="resetFilters"
              >
                Reset
              </button>
              <button
                type="button"
                class="inline-flex items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50"
                @click="exportExcel"
              >
                Export Excel
              </button>
            </div>
          </div>
          <div class="flex flex-col gap-2 text-sm text-slate-600 dark:text-slate-300 sm:flex-row sm:items-center sm:justify-end">
            <label class="font-medium text-slate-700 dark:text-slate-200" for="history-rows">Baris per halaman</label>
            <div class="relative">
              <select
                id="history-rows"
                v-model.number="rowsPerPage"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-1.5 pr-8 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white sm:w-24"
              >
                <option v-for="option in perPageOptions" :key="`rows-${option}`" :value="option">
                  {{ option }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="mobile-friendly-table min-w-full divide-y divide-slate-200 text-sm text-slate-700 dark:divide-slate-700 dark:text-slate-300">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-700 dark:text-slate-300">
              <tr>
                <SortableTh
                  class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"
                  column="id"
                  label="ID"
                  :direction="historySortDirection('id')"
                  :aria-sort="historyAriaSortValue('id')"
                  @toggle="toggleHistorySort"
                />
                <SortableTh
                  class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"
                  column="user"
                  label="Pengguna"
                  :direction="historySortDirection('user')"
                  :aria-sort="historyAriaSortValue('user')"
                  @toggle="toggleHistorySort"
                />
                <SortableTh
                  class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"
                  column="room"
                  label="Ruangan"
                  :direction="historySortDirection('room')"
                  :aria-sort="historyAriaSortValue('room')"
                  @toggle="toggleHistorySort"
                />
                <SortableTh
                  class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"
                  column="action"
                  label="Aksi"
                  :direction="historySortDirection('action')"
                  :aria-sort="historyAriaSortValue('action')"
                  @toggle="toggleHistorySort"
                />
                <SortableTh
                  class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"
                  column="created_at"
                  label="Waktu"
                  :direction="historySortDirection('created_at')"
                  :aria-sort="historyAriaSortValue('created_at')"
                  @toggle="toggleHistorySort"
                />
                <SortableTh
                  class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"
                  column="description"
                  label="Deskripsi"
                  :direction="historySortDirection('description')"
                  :aria-sort="historyAriaSortValue('description')"
                  @toggle="toggleHistorySort"
                />
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-700 dark:text-slate-300">
              <tr v-for="history in paginatedHistories" :key="history.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                <td class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400" data-title="ID">#{{ history.id }}</td>
                <td class="px-5 py-4" data-title="Pengguna">
                  <div class="font-medium text-slate-900 dark:text-white">{{ history.user?.name ?? '-' }}</div>
                  <div class="text-xs text-slate-500 dark:text-slate-400">{{ history.user?.email ?? '-' }}</div>
                </td>
                <td class="px-5 py-4" data-title="Ruangan">
                  <div class="font-medium text-slate-900 dark:text-white">{{ getRoomSummary(history) }}</div>
                  <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    {{ getLocationSummary(history) }}
                  </div>
                  <Link
                    v-if="history.booking?.id"
                    :href="route('admin.bookings.show', history.booking.id)"
                    class="mt-1 inline-flex text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                  >
                    #{{ history.booking.id }} · {{ history.booking.title || 'Detail booking' }}
                  </Link>
                  <div v-else class="mt-1 text-xs text-slate-400">
                    Booking tidak tersedia
                  </div>
                </td>
                <td class="px-5 py-4" data-title="Aksi">
                  <span
                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize"
                    :class="actionClass(history.action)"
                  >
                    {{ actionLabel(history.action) }}
                  </span>
                </td>
                <td class="px-5 py-4 text-sm text-slate-600 dark:text-slate-300" data-title="Waktu">
                  <div class="font-medium text-slate-800 dark:text-slate-200">{{ formatDateTime(history.created_at) }}</div>
                </td>
                <td class="px-5 py-4 text-sm text-slate-600 dark:text-slate-300" data-title="Deskripsi">
                  <span>{{ history.description ?? '-' }}</span>
                </td>
              </tr>
              <tr v-if="!paginatedHistories.length">
                <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-400 dark:text-slate-500" data-title="Info">
                  Belum ada riwayat yang sesuai dengan filter.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:text-slate-300"
        >
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} data</span>
            <span v-else>Menampilkan 0 data</span>
          </div>
          <div class="w-full sm:w-auto">
            <div class="mobile-pagination-compact sm:hidden">
              <button
                type="button"
                class="rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
                @click="changePage(currentPage - 1)"
                :disabled="!canGoToPreviousPage"
              >
                Sebelumnya
              </button>
              <button
                type="button"
                class="rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
                @click="changePage(currentPage + 1)"
                :disabled="!canGoToNextPage"
              >
                Berikutnya
              </button>
            </div>
            <div class="hidden items-center gap-2 sm:flex">
            <button
              type="button"
              class="rounded-xl border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
              @click="changePage(1)"
              :disabled="currentPage === 1 || !pageMeta.of"
            >
              «
            </button>
            <button
              type="button"
              class="rounded-xl border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1 || !pageMeta.of"
            >
              ‹
            </button>
            <template v-if="pageMeta.of">
              <button
                v-for="page in pages"
                :key="`history-page-${page}`"
                type="button"
                class="rounded-xl border px-3 py-1 text-sm transition"
                :class="
                  currentPage === page
                    ? 'border-blue-500 bg-blue-500 text-white'
                    : 'border-slate-300 text-slate-600 hover:border-blue-400 hover:text-blue-600 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200'"
                @click="changePage(page)"
              >
                {{ page }}
              </button>
            </template>
            <button
              type="button"
              class="rounded-xl border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
              @click="changePage(currentPage + 1)"
              :disabled="currentPage === pages.length || !pageMeta.of"
            >
              ›
            </button>
            <button
              type="button"
              class="rounded-xl border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
              @click="changePage(pages.length)"
              :disabled="currentPage === pages.length || !pageMeta.of"
            >
              »
            </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
