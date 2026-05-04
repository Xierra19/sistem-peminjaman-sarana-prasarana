<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { Head, Link } from '@inertiajs/vue3'
import { computed, reactive, ref, onMounted, onBeforeUnmount } from 'vue'
import { formatDateTimeToDDMMYY } from '@/Composables/useDateFormatter'
import flatpickr from 'flatpickr'
import 'flatpickr/dist/flatpickr.css'

const props = defineProps({
  bookings: {
    type: Array,
    default: () => [],
  },
})

const statusLabels = {
  waiting: 'Menunggu Persetujuan',
  approved: 'Disetujui',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan',
}

const badgeClasses = {
  waiting: 'bg-amber-100 text-amber-700',
  approved: 'bg-emerald-100 text-emerald-700',
  rejected: 'bg-rose-100 text-rose-700',
  cancelled: 'bg-slate-100 text-slate-700',
}

const normalizeStatus = (status) => {
  if (!status) return ''
  return status === 'pending' || status === 'requested' ? 'waiting' : status
}

const formatDateTime = (value) => formatDateTimeToDDMMYY(value)

// ── Filter state ──────────────────────────────────────────────────────
const filterForm = reactive({
  search: '',
  status: '',
  start_date: '',
  end_date: '',
})

const activeFilter = reactive({
  search: '',
  status: '',
  start_date: '',
  end_date: '',
})

const datePickers = ref({})

const statusOptions = Object.keys(statusLabels)

const applyFilters = () => {
  Object.assign(activeFilter, filterForm)
}

const resetFilters = () => {
  Object.assign(filterForm, { search: '', status: '', start_date: '', end_date: '' })
  Object.assign(activeFilter, { search: '', status: '', start_date: '', end_date: '' })
}

// ── All bookings (normalized) ──────────────────────────────────────────────────
const allBookings = computed(() =>
  (props.bookings ?? []).map((booking) => ({
    ...booking,
    normalizedStatus: normalizeStatus(booking.status),
  })),
)

// ── Summary (always based on full list, not filtered) ─────────────────────────
const summary = computed(() => {
  const list = allBookings.value
  return {
    total: list.length,
    waiting: list.filter((b) => b.normalizedStatus === 'waiting').length,
    approved: list.filter((b) => b.normalizedStatus === 'approved').length,
    rejected: list.filter((b) => b.normalizedStatus === 'rejected').length,
    cancelled: list.filter((b) => b.normalizedStatus === 'cancelled').length,
  }
})

// ── Filtered list ─────────────────────────────────────────────────────
const bookingsList = computed(() => {
  let list = allBookings.value

  if (activeFilter.search) {
    const q = activeFilter.search.toLowerCase()
    list = list.filter(
      (b) =>
        b.title?.toLowerCase().includes(q) ||
        b.user?.name?.toLowerCase().includes(q) ||
        b.user?.email?.toLowerCase().includes(q) ||
        b.room?.name?.toLowerCase().includes(q),
    )
  }

  if (activeFilter.status) {
    list = list.filter((b) => b.normalizedStatus === activeFilter.status)
  }

  if (activeFilter.start_date) {
    const from = new Date(activeFilter.start_date)
    list = list.filter((b) => b.created_at && new Date(b.created_at) >= from)
  }

  if (activeFilter.end_date) {
    const to = new Date(activeFilter.end_date)
    to.setHours(23, 59, 59, 999)
    list = list.filter((b) => b.created_at && new Date(b.created_at) <= to)
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
    applicant: (booking) => booking.user?.name ?? '',
    room: (booking) => booking.room?.name ?? '',
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

// Inisialisasi Flatpickr
onMounted(() => {
  // Flatpickr untuk Tanggal Pengajuan (Dari)
  const startInput = document.getElementById('report-start-date')
  if (startInput) {
    datePickers.value.start = flatpickr(startInput, {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'd-m-y',
      onChange: (selectedDates, dateStr) => {
        filterForm.start_date = dateStr
      }
    })
  }

  // Flatpickr untuk Tanggal Pengajuan (Sampai)
  const endInput = document.getElementById('report-end-date')
  if (endInput) {
    datePickers.value.end = flatpickr(endInput, {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'd-m-y',
      onChange: (selectedDates, dateStr) => {
        filterForm.end_date = dateStr
      }
    })
  }
})

onBeforeUnmount(() => {
  Object.values(datePickers.value).forEach(picker => picker?.destroy())
})

const perPageOptions = [5, 10, 25, 50]
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Approval Booking Ruangan" />

    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">Approval Booking Ruangan</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Kelola permintaan booking ruangan yang masuk.</p>
      </div>

      <!-- Summary cards -->
      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Total Data</p>
          <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ summary.total }}</p>
          <p class="text-xs text-slate-500 dark:text-slate-400">Seluruh hasil sesuai filter aktif</p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm dark:border-amber-800 dark:bg-amber-900/30">
          <p class="text-xs font-medium uppercase tracking-wide text-amber-500 dark:text-amber-300">Menunggu</p>
          <p class="mt-2 text-2xl font-semibold text-amber-600 dark:text-amber-400">{{ summary.waiting }}</p>
          <p class="text-xs text-amber-500 dark:text-amber-400">Booking belum diputuskan</p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-white p-5 shadow-sm dark:border-emerald-800 dark:bg-emerald-900/30">
          <p class="text-xs font-medium uppercase tracking-wide text-emerald-500 dark:text-emerald-300">Disetujui</p>
          <p class="mt-2 text-2xl font-semibold text-emerald-600 dark:text-emerald-400">{{ summary.approved }}</p>
          <p class="text-xs text-emerald-500 dark:text-emerald-400">Booking aktif</p>
        </div>
        <div class="rounded-xl border border-rose-200 bg-white p-5 shadow-sm dark:border-rose-800 dark:bg-rose-900/30">
          <p class="text-xs font-medium uppercase tracking-wide text-rose-500 dark:text-rose-300">Ditolak / Batal</p>
          <p class="mt-2 text-2xl font-semibold text-rose-600 dark:text-rose-400">{{ summary.rejected + summary.cancelled }}</p>
          <p class="text-xs text-rose-500 dark:text-rose-400">Termasuk pembatalan admin</p>
        </div>
      </div>

      <!-- Filter panel -->
      <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
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
                {{ statusLabels[status] ?? status }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200" for="report-start-date">Tanggal pengajuan (dari)</label>
            <input
              id="report-start-date"
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
              v-model="filterForm.end_date"
              type="text"
              readonly
              placeholder="Pilih tanggal selesai"
              class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white cursor-pointer"
            />
          </div>
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:text-slate-800 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
            @click="resetFilters"
          >
            Reset
          </button>
          <button
            type="button"
            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
            @click="applyFilters"
          >
            Terapkan Filter
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div
          class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-700"
        >
          <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">Daftar Booking Masuk</div>
          <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
            <div class="flex items-center justify-end gap-3 text-sm text-slate-600 dark:text-slate-300">
              <label class="font-medium text-slate-700 dark:text-slate-200" for="admin-bookings-rows">Rows per page</label>
              <div class="relative">
                <select
                  id="admin-bookings-rows"
                  v-model.number="rowsPerPage"
                  class="w-20 rounded border border-slate-300 bg-white px-3 py-1.5 pr-8 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                >
                  <option v-for="option in perPageOptions" :key="option" :value="option">
                    {{ option }}
                  </option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
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
              <th class="px-5 py-3 text-slate-500 dark:text-slate-300"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-700 dark:text-slate-300">
            <tr v-for="booking in paginatedBookings" :key="booking.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
              <td class="px-5 py-4">
                <div class="font-medium text-slate-900 dark:text-white">{{ booking.title }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ booking.description || 'Tidak ada deskripsi.' }}</div>
              </td>
              <td class="px-5 py-4 text-sm">
                <div class="font-medium text-slate-800 dark:text-slate-200">{{ booking.user?.name ?? '-' }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ booking.user?.email ?? '-' }}</div>
              </td>
              <td class="px-5 py-4">
                <div class="font-medium text-slate-800 dark:text-slate-200">{{ booking.room?.name ?? '-' }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                  {{ booking.room?.building?.name ?? '-' }} - {{ booking.room?.building?.campus?.name ?? '-' }}
                </div>
              </td>
              <td class="px-5 py-4 text-sm">
                <div>Mulai: <span class="font-medium text-slate-800 dark:text-slate-200">{{ formatDateTime(booking.start_time) }}</span></div>
                <div>Selesai: <span class="font-medium text-slate-800 dark:text-slate-200">{{ formatDateTime(booking.end_time) }}</span></div>
              </td>
              <td class="px-5 py-4 text-center">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                  :class="badgeClasses[booking.normalizedStatus] ?? 'bg-slate-100 text-slate-600 border-slate-200'"
                >
                  {{ statusLabels[booking.normalizedStatus] ?? (booking.normalizedStatus || booking.status) }}
                </span>
              </td>
              <td class="px-5 py-4 text-right">
                <Link
                  :href="route('admin.bookings.show', booking.id)"
                  class="inline-flex items-center rounded-md border border-blue-200 px-3 py-1.5 text-xs font-medium text-blue-600 hover:bg-blue-50 dark:border-slate-600 dark:text-blue-300 dark:hover:border-slate-500 dark:hover:bg-slate-600"
                >
                  Lihat Detail
                </Link>
              </td>
            </tr>
            <tr v-if="!bookingsList.length">
              <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
                Belum ada data booking.
              </td>
            </tr>
          </tbody>
        </table>

        <div
          class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700"
        >
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} data</span>
            <span v-else>Menampilkan 0 data</span>
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-slate-400 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
              @click="changePage(1)"
              :disabled="currentPage === 1 || !bookingsList.length"
            >
              «
            </button>
            <button
              type="button"
              class="rounded border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-slate-400 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1 || !bookingsList.length"
            >
              ‹
            </button>
            <template v-if="bookingsList.length">
              <button
                v-for="page in pages"
                :key="`page-${page}`"
                type="button"
                class="rounded border px-3 py-1 text-sm transition"
                :class="
                  currentPage === page
                    ? 'border-blue-500 bg-blue-500 text-white'
                    : 'border-slate-300 text-slate-600 hover:border-slate-400 hover:text-slate-600 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200'"
                @click="changePage(page)"
              >
                {{ page }}
              </button>
            </template>
            <button
              type="button"
              class="rounded border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-slate-400 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
              @click="changePage(currentPage + 1)"
              :disabled="currentPage === pages.length || !bookingsList.length"
            >
              ›
            </button>
            <button
              type="button"
              class="rounded border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-slate-400 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
              @click="changePage(pages.length)"
              :disabled="currentPage === pages.length || !bookingsList.length"
            >
              »
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>