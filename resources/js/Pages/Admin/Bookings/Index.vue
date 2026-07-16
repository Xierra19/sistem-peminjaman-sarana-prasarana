<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import RowsPerPageSelect from '@/Components/RowsPerPageSelect.vue'
import SortableTh from '@/Components/SortableTh.vue'
import TablePagination from '@/Components/TablePagination.vue'
import { isDateWithinRange, useAppliedFilters } from '@/Composables/useAppliedFilters'
import {
  bookingStatusLabels,
  getBookingStatusClasses,
  getBookingStatusLabel,
  normalizeBookingStatus,
} from '@/Composables/useBookingStatus'
import { useDateRangePickers } from '@/Composables/useDateRangePickers'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'
import { formatDateTimeToDDMMYY } from '@/Composables/useDateFormatter'

const props = defineProps({
  bookings: {
    type: Array,
    default: () => [],
  },
})

const formatDateTime = (value) => formatDateTimeToDDMMYY(value)

const statusOptions = Object.keys(bookingStatusLabels)

const {
  filterForm,
  appliedFilters,
  hasActiveFilters,
  activeFilterBadges,
  applyFilters: applyBaseFilters,
  resetFilters: resetBaseFilters,
} = useAppliedFilters(bookingStatusLabels)

const { startInput, endInput } = useDateRangePickers(filterForm)
const summaryCardFilter = ref('')

// ── All bookings (normalized) ──────────────────────────────────────────────────
const allBookings = computed(() =>
  (props.bookings ?? []).map((booking) => ({
    ...booking,
    normalizedStatus: normalizeBookingStatus(booking.status),
  })),
)

const summary = computed(() => {
  const list = allBookings.value
  return {
    total: list.length,
    waiting: list.filter((b) => ['waiting', 'needs_revision'].includes(b.normalizedStatus)).length,
    approved: list.filter((b) => b.normalizedStatus === 'approved').length,
    rejected: list.filter((b) => b.normalizedStatus === 'rejected').length,
    cancelled: list.filter((b) => b.normalizedStatus === 'cancelled').length,
    expired: list.filter((b) => b.normalizedStatus === 'expired').length,
  }
})

// ── Filtered list ─────────────────────────────────────────────────────
const bookingsList = computed(() => {
  let list = allBookings.value

  if (appliedFilters.search) {
    const q = appliedFilters.search.toLowerCase()
    list = list.filter(
      (b) =>
        b.title?.toLowerCase().includes(q) ||
        b.user?.name?.toLowerCase().includes(q) ||
        b.user?.email?.toLowerCase().includes(q) ||
        b.room_summary?.toLowerCase().includes(q),
    )
  }

  if (appliedFilters.status) {
    list = list.filter((b) => b.normalizedStatus === appliedFilters.status)
  }

  if (summaryCardFilter.value === 'waiting') {
    list = list.filter((b) => ['waiting', 'needs_revision'].includes(b.normalizedStatus))
  }

  if (summaryCardFilter.value === 'approved') {
    list = list.filter((b) => b.normalizedStatus === 'approved')
  }

  if (summaryCardFilter.value === 'final_other') {
    list = list.filter((b) => ['rejected', 'cancelled', 'expired'].includes(b.normalizedStatus))
  }

  if (appliedFilters.start_date || appliedFilters.end_date) {
    list = list.filter((b) =>
      isDateWithinRange(b.created_at, appliedFilters.start_date, appliedFilters.end_date),
    )
  }

  return list
})

// ── Sort & pagination ─────────────────────────────────────────────────
const {
  sortedItems: sortedBookings,
  toggleSort: toggleAdminBookingSort,
  sortDirection: adminBookingSortDirection,
  ariaSortValue: adminBookingAriaSortValue,
} = useTableSort(bookingsList, {
  accessors: {
    title: (booking) => booking.title ?? '',
    created_at: (booking) => (booking.created_at ? new Date(booking.created_at) : null),
    applicant: (booking) => booking.user?.name ?? '',
    room: (booking) => booking.room_summary ?? '',
    schedule: (booking) => (booking.start_time ? new Date(booking.start_time) : null),
    status: (booking) => booking.normalizedStatus ?? '',
  },
})

const {
  paginatedItems: paginatedBookings,
  currentPage,
  rowsPerPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(sortedBookings)

const perPageOptions = [5, 10, 25, 50]

const isSummaryCardActive = (filter) => {
  if (filter === '') {
    return summaryCardFilter.value === '' && !appliedFilters.status
  }

  return summaryCardFilter.value === filter
}

const applySummaryCardFilter = (filter) => {
  summaryCardFilter.value = filter
  filterForm.status = ''
  appliedFilters.status = ''
  currentPage.value = 1
}

const applyFilters = () => {
  summaryCardFilter.value = ''
  applyBaseFilters()
  currentPage.value = 1
}

const resetFilters = () => {
  summaryCardFilter.value = ''
  resetBaseFilters()
  currentPage.value = 1
}

const jakartaDateKey = () => {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date())
  const values = Object.fromEntries(parts.map((part) => [part.type, part.value]))

  return `${values.year}-${values.month}-${values.day}`
}

onMounted(() => {
  const today = jakartaDateKey()
  const hasStaleWaitingBooking = (props.bookings ?? []).some((booking) => {
    if (normalizeBookingStatus(booking.status) !== 'waiting') {
      return false
    }

    const finalDate = String(booking.schedule_end_date || booking.end_time || '').slice(0, 10)

    return finalDate !== '' && finalDate < today
  })

  if (hasStaleWaitingBooking) {
    router.reload({
      only: ['bookings'],
      preserveScroll: true,
    })
  }
})
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Persetujuan Peminjaman Ruangan" />

    <div class="space-y-6">
      <div class="space-y-1">
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">Persetujuan Peminjaman Ruangan</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Kelola permintaan peminjaman ruangan yang masuk.</p>
        <p class="text-xs text-slate-500 dark:text-slate-400">
          {{ hasActiveFilters || summaryCardFilter ? `Menampilkan ${bookingsList.length} hasil sesuai filter aktif.` : `Menampilkan seluruh ${summary.total} pengajuan yang tersedia.` }}
        </p>
      </div>

      <!-- Summary cards -->
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <button
          type="button"
          class="rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-slate-200 dark:bg-slate-800 dark:focus:ring-slate-700"
          :class="isSummaryCardActive('') ? 'border-slate-500 ring-2 ring-slate-300 dark:border-slate-400 dark:ring-slate-600' : 'border-slate-200 dark:border-slate-700'"
          :aria-pressed="isSummaryCardActive('')"
          @click="applySummaryCardFilter('')"
        >
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Total Data</p>
          <p class="mt-3 text-3xl font-semibold text-slate-900 dark:text-white">{{ summary.total }}</p>
          <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Klik untuk menampilkan semua status</p>
        </button>
        <button
          type="button"
          class="rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-amber-100 dark:bg-amber-900/30 dark:focus:ring-amber-950"
          :class="isSummaryCardActive('waiting') ? 'border-amber-500 ring-2 ring-amber-200 dark:border-amber-400 dark:ring-amber-900' : 'border-amber-200 dark:border-amber-800'"
          :aria-pressed="isSummaryCardActive('waiting')"
          @click="applySummaryCardFilter('waiting')"
        >
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-500 dark:text-amber-300">Menunggu</p>
          <p class="mt-3 text-3xl font-semibold text-amber-600 dark:text-amber-400">{{ summary.waiting }}</p>
          <p class="mt-2 text-xs text-amber-500 dark:text-amber-400">Klik untuk melihat antrean tinjauan</p>
        </button>
        <button
          type="button"
          class="rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-emerald-100 dark:bg-emerald-900/30 dark:focus:ring-emerald-950"
          :class="isSummaryCardActive('approved') ? 'border-emerald-500 ring-2 ring-emerald-200 dark:border-emerald-400 dark:ring-emerald-900' : 'border-emerald-200 dark:border-emerald-800'"
          :aria-pressed="isSummaryCardActive('approved')"
          @click="applySummaryCardFilter('approved')"
        >
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-500 dark:text-emerald-300">Disetujui</p>
          <p class="mt-3 text-3xl font-semibold text-emerald-600 dark:text-emerald-400">{{ summary.approved }}</p>
          <p class="mt-2 text-xs text-emerald-500 dark:text-emerald-400">Klik untuk melihat data disetujui</p>
        </button>
        <button
          type="button"
          class="rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-rose-100 dark:bg-rose-900/30 dark:focus:ring-rose-950"
          :class="isSummaryCardActive('final_other') ? 'border-rose-500 ring-2 ring-rose-200 dark:border-rose-400 dark:ring-rose-900' : 'border-rose-200 dark:border-rose-800'"
          :aria-pressed="isSummaryCardActive('final_other')"
          @click="applySummaryCardFilter('final_other')"
        >
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-rose-500 dark:text-rose-300">Status Final Lain</p>
          <p class="mt-3 text-3xl font-semibold text-rose-600 dark:text-rose-400">{{ summary.rejected + summary.cancelled + summary.expired }}</p>
          <p class="mt-2 text-xs text-rose-500 dark:text-rose-400">Klik untuk melihat status final lain</p>
        </button>
      </div>

      <!-- Filter panel -->
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div v-if="hasActiveFilters" class="mb-4 flex flex-wrap gap-2">
          <span
            v-for="badge in activeFilterBadges"
            :key="badge"
            class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-300"
          >
            {{ badge }}
          </span>
        </div>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200" for="report-search">Pencarian bebas</label>
            <input
              id="report-search"
              v-model="filterForm.search"
              type="text"
              placeholder="Nama pemohon, email, ruangan…"
              class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
            />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200" for="report-status">Status peminjaman</label>
            <select
              id="report-status"
              v-model="filterForm.status"
              class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
            >
              <option value="">Semua status</option>
              <option v-for="status in statusOptions" :key="`status-${status}`" :value="status">
                {{ getBookingStatusLabel(status) }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200" for="report-start-date">Tanggal pengajuan (dari)</label>
            <input
              id="report-start-date"
              ref="startInput"
              v-model="filterForm.start_date"
              type="text"
              readonly
              placeholder="Pilih tanggal mulai"
              class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white cursor-pointer"
            />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200" for="report-end-date">Tanggal pengajuan (sampai)</label>
            <input
              id="report-end-date"
              ref="endInput"
              v-model="filterForm.end_date"
              type="text"
              readonly
              placeholder="Pilih tanggal selesai"
              class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white cursor-pointer"
            />
          </div>
        </div>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
          <button
            type="button"
            class="w-full rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:text-slate-800 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200 sm:w-auto"
            @click="resetFilters"
          >
            Reset
          </button>
          <button
            type="button"
            class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 sm:w-auto"
            @click="applyFilters"
          >
            Terapkan Filter
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div
          class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-700"
        >
          <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">Daftar Booking Masuk</div>
          <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
            <RowsPerPageSelect
              v-model="rowsPerPage"
              input-id="admin-bookings-rows"
              :options="perPageOptions"
            />
          </div>
        </div>

        <table class="approval-mobile-table mobile-friendly-table min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
          <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-700 dark:text-slate-300">
            <tr>
              <SortableTh
                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"
                column="title"
                label="Judul"
                :direction="adminBookingSortDirection('title')"
                :aria-sort="adminBookingAriaSortValue('title')"
                @toggle="toggleAdminBookingSort"
              />
              <SortableTh
                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"
                column="created_at"
                label="Tanggal Pengajuan"
                :direction="adminBookingSortDirection('created_at')"
                :aria-sort="adminBookingAriaSortValue('created_at')"
                @toggle="toggleAdminBookingSort"
              />
              <SortableTh
                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"
                column="applicant"
                label="Pemohon"
                :direction="adminBookingSortDirection('applicant')"
                :aria-sort="adminBookingAriaSortValue('applicant')"
                @toggle="toggleAdminBookingSort"
              />
              <SortableTh
                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"
                column="room"
                label="Ruangan"
                :direction="adminBookingSortDirection('room')"
                :aria-sort="adminBookingAriaSortValue('room')"
                @toggle="toggleAdminBookingSort"
              />
              <SortableTh
                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"
                column="schedule"
                label="Jadwal"
                :direction="adminBookingSortDirection('schedule')"
                :aria-sort="adminBookingAriaSortValue('schedule')"
                @toggle="toggleAdminBookingSort"
              />
              <SortableTh
                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300"
                column="status"
                label="Status"
                :direction="adminBookingSortDirection('status')"
                :aria-sort="adminBookingAriaSortValue('status')"
                align="center"
                @toggle="toggleAdminBookingSort"
              />
              <th class="min-w-36 px-5 py-3 text-center text-slate-500 dark:text-slate-300">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-700 dark:text-slate-300">
            <tr v-for="booking in paginatedBookings" :key="booking.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
              <td class="mobile-primary-cell mobile-span-2 px-5 py-4" data-title="Judul">
                <div class="mobile-primary-label">Judul</div>
                <div class="mobile-primary-title">{{ booking.title }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ booking.description || 'Tidak ada deskripsi.' }}</div>
              </td>
              <td class="px-5 py-4 text-sm mobile-compact-meta" data-title="Tanggal Pengajuan">
                <div class="font-medium text-slate-800 dark:text-slate-200">{{ formatDateTime(booking.created_at) }}</div>
              </td>
              <td class="px-5 py-4 text-sm" data-title="Pemohon">
                <div class="font-medium text-slate-800 dark:text-slate-200">{{ booking.user?.name ?? '-' }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ booking.user?.email ?? '-' }}</div>
              </td>
              <td class="px-5 py-4 mobile-compact-meta" data-title="Ruangan">
                <div class="font-medium text-slate-800 dark:text-slate-200">{{ booking.room_summary ?? '-' }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                  {{ booking.room_schedules?.length ?? 0 }} jadwal ruangan
                </div>
              </td>
              <td class="mobile-span-2 px-5 py-4 text-sm" data-title="Jadwal">
                <div class="font-medium text-slate-800 dark:text-slate-200">Jadwal penggunaan</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ booking.schedule_summary }}</div>
              </td>
              <td class="mobile-status-cell px-5 py-4 text-center" data-title="Status">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                  :class="getBookingStatusClasses(booking.normalizedStatus)"
                >
                  {{ getBookingStatusLabel(booking.normalizedStatus) || booking.status }}
                </span>
              </td>
              <td class="mobile-action-cell min-w-36 px-5 py-4 text-center" data-title="Aksi">
                <Link
                  :href="route('admin.bookings.show', booking.id)"
                  class="inline-flex min-h-10 min-w-28 items-center justify-center whitespace-nowrap rounded-lg border border-blue-200 px-4 py-2 text-xs font-semibold text-blue-600 transition hover:bg-blue-50 dark:border-slate-600 dark:text-blue-300 dark:hover:border-slate-500 dark:hover:bg-slate-600"
                >
                  Lihat Detail
                </Link>
              </td>
            </tr>
            <tr v-if="!bookingsList.length">
              <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
                Belum ada data booking.
              </td>
            </tr>
          </tbody>
        </table>

        <TablePagination
          :page-meta="pageMeta"
          :pages="pages"
          :current-page="currentPage"
          @change="changePage"
        />
      </div>
    </div>
  </AuthenticatedLayout>
</template>
