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

const hasActiveFilters = computed(() =>
  Boolean(activeFilter.search || activeFilter.status || activeFilter.start_date || activeFilter.end_date),
)

const activeFilterBadges = computed(() => {
  const badges = []

  if (activeFilter.search) badges.push(`Cari: ${activeFilter.search}`)
  if (activeFilter.status) badges.push(`Status: ${statusLabels[activeFilter.status] ?? activeFilter.status}`)
  if (activeFilter.start_date) badges.push(`Dari: ${activeFilter.start_date}`)
  if (activeFilter.end_date) badges.push(`Sampai: ${activeFilter.end_date}`)

  return badges
})

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

const summary = computed(() => {
  const list = bookingsList.value
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

const canGoToPreviousPage = computed(() => currentPage.value > 1 && bookingsList.value.length > 0)
const canGoToNextPage = computed(() => currentPage.value < pages.value.length && bookingsList.value.length > 0)

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
    <Head title="Persetujuan Peminjaman Ruangan" />

    <div class="space-y-6">
      <div class="space-y-1">
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">Persetujuan Peminjaman Ruangan</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Kelola permintaan peminjaman ruangan yang masuk.</p>
        <p class="text-xs text-slate-500 dark:text-slate-400">
          {{ hasActiveFilters ? `Menampilkan ${summary.total} hasil sesuai filter aktif.` : `Menampilkan seluruh ${summary.total} pengajuan yang tersedia.` }}
        </p>
      </div>

      <!-- Summary cards -->
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Total Data</p>
          <p class="mt-3 text-3xl font-semibold text-slate-900 dark:text-white">{{ summary.total }}</p>
          <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Jumlah pengajuan yang sedang tampil</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-white p-4 shadow-sm dark:border-amber-800 dark:bg-amber-900/30">
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-500 dark:text-amber-300">Menunggu</p>
          <p class="mt-3 text-3xl font-semibold text-amber-600 dark:text-amber-400">{{ summary.waiting }}</p>
          <p class="mt-2 text-xs text-amber-500 dark:text-amber-400">Perlu ditinjau lebih dulu</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-white p-4 shadow-sm dark:border-emerald-800 dark:bg-emerald-900/30">
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-500 dark:text-emerald-300">Disetujui</p>
          <p class="mt-3 text-3xl font-semibold text-emerald-600 dark:text-emerald-400">{{ summary.approved }}</p>
          <p class="mt-2 text-xs text-emerald-500 dark:text-emerald-400">Sudah mendapat persetujuan</p>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-white p-4 shadow-sm dark:border-rose-800 dark:bg-rose-900/30">
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-rose-500 dark:text-rose-300">Ditolak / Batal</p>
          <p class="mt-3 text-3xl font-semibold text-rose-600 dark:text-rose-400">{{ summary.rejected + summary.cancelled }}</p>
          <p class="mt-2 text-xs text-rose-500 dark:text-rose-400">Termasuk booking yang dibatalkan</p>
      </div>
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
      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div
          class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-700"
        >
          <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">Daftar Booking Masuk</div>
          <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
            <div class="flex flex-col gap-2 text-sm text-slate-600 dark:text-slate-300 sm:flex-row sm:items-center sm:justify-end">
              <label class="font-medium text-slate-700 dark:text-slate-200" for="admin-bookings-rows">Rows per page</label>
              <div class="relative">
                <select
                  id="admin-bookings-rows"
                  v-model.number="rowsPerPage"
                  class="w-full rounded border border-slate-300 bg-white px-3 py-1.5 pr-8 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white sm:w-20"
                >
                  <option v-for="option in perPageOptions" :key="option" :value="option">
                    {{ option }}
                  </option>
                </select>
              </div>
            </div>
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
              <td class="mobile-primary-cell mobile-span-2 px-5 py-4" data-title="Judul">
                <div class="mobile-primary-label">Judul</div>
                <div class="mobile-primary-title">{{ booking.title }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ booking.description || 'Tidak ada deskripsi.' }}</div>
              </td>
              <td class="px-5 py-4 text-sm" data-title="Pemohon">
                <div class="font-medium text-slate-800 dark:text-slate-200">{{ booking.user?.name ?? '-' }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ booking.user?.email ?? '-' }}</div>
              </td>
              <td class="px-5 py-4 mobile-compact-meta" data-title="Ruangan">
                <div class="font-medium text-slate-800 dark:text-slate-200">{{ booking.room?.name ?? '-' }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                  {{ booking.room?.building?.name ?? '-' }} - {{ booking.room?.building?.campus?.name ?? '-' }}
                </div>
              </td>
              <td class="mobile-span-2 px-5 py-4 text-sm" data-title="Jadwal">
                <div>Jenis: <span class="font-medium text-slate-800 dark:text-slate-200">{{ booking.schedule_mode_label }}</span></div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ booking.schedule_summary }}</div>
              </td>
              <td class="mobile-status-cell px-5 py-4 text-center" data-title="Status">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                  :class="badgeClasses[booking.normalizedStatus] ?? 'bg-slate-100 text-slate-600 border-slate-200'"
                >
                  {{ statusLabels[booking.normalizedStatus] ?? (booking.normalizedStatus || booking.status) }}
                </span>
              </td>
              <td class="mobile-action-cell px-5 py-4 text-right" data-title="Aksi">
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
          <div class="w-full sm:w-auto">
            <div class="mobile-pagination-compact sm:hidden">
              <button
                type="button"
                class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-600 transition hover:border-slate-400 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
                @click="changePage(currentPage - 1)"
                :disabled="!canGoToPreviousPage"
              >
                Sebelumnya
              </button>
              <button
                type="button"
                class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-600 transition hover:border-slate-400 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
                @click="changePage(currentPage + 1)"
                :disabled="!canGoToNextPage"
              >
                Berikutnya
              </button>
            </div>
            <div class="hidden items-center gap-2 sm:flex">
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
                :disabled="typeof page !== 'number'"
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
    </div>
  </AuthenticatedLayout>
</template>
