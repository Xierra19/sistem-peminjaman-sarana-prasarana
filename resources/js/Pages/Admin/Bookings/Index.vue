<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { Head, Link } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'

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

const formatDateTime = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// ── Filter state ──────────────────────────────────────────────────────────────
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

// ── Filtered list ─────────────────────────────────────────────────────────────
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

// ── Sort & pagination ─────────────────────────────────────────────────────────
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

const perPageOptions = [5, 10, 25, 50]
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Approval Booking Ruangan" />

    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-800">Approval Booking Ruangan</h1>
        <p class="text-sm text-gray-500">Kelola permintaan booking ruangan yang masuk.</p>
      </div>

      <!-- Summary cards -->
      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total Data</p>
          <p class="mt-2 text-2xl font-semibold text-slate-900">{{ summary.total }}</p>
          <p class="text-xs text-slate-500">Seluruh hasil sesuai filter aktif</p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-amber-500">Menunggu</p>
          <p class="mt-2 text-2xl font-semibold text-amber-600">{{ summary.waiting }}</p>
          <p class="text-xs text-amber-500">Booking belum diputuskan</p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-emerald-500">Disetujui</p>
          <p class="mt-2 text-2xl font-semibold text-emerald-600">{{ summary.approved }}</p>
          <p class="text-xs text-emerald-500">Booking aktif</p>
        </div>
        <div class="rounded-xl border border-rose-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-rose-500">Ditolak / Batal</p>
          <p class="mt-2 text-2xl font-semibold text-rose-600">{{ summary.rejected + summary.cancelled }}</p>
          <p class="text-xs text-rose-500">Termasuk pembatalan admin</p>
        </div>
      </div>

      <!-- Filter panel -->
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <div class="flex flex-col gap-2">
            <label for="report-search" class="text-sm font-medium text-gray-700">Pencarian bebas</label>
            <input
              id="report-search"
              v-model="filterForm.search"
              type="text"
              placeholder="Nama pemohon, email, ruangan…"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
          </div>
          <div class="flex flex-col gap-2">
            <label for="report-status" class="text-sm font-medium text-gray-700">Status peminjaman</label>
            <select
              id="report-status"
              v-model="filterForm.status"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
              <option value="">Semua status</option>
              <option v-for="status in statusOptions" :key="`status-${status}`" :value="status">
                {{ statusLabels[status] ?? status }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-2">
            <label for="report-start-date" class="text-sm font-medium text-gray-700">Tanggal pengajuan (dari)</label>
            <input
              id="report-start-date"
              v-model="filterForm.start_date"
              type="date"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
          </div>
          <div class="flex flex-col gap-2">
            <label for="report-end-date" class="text-sm font-medium text-gray-700">Tanggal pengajuan (sampai)</label>
            <input
              id="report-end-date"
              v-model="filterForm.end_date"
              type="date"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
          </div>
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:border-gray-300 hover:text-gray-800"
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
      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div
          class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 md:flex-row md:items-center md:justify-between"
        >
          <div class="text-sm font-semibold text-gray-700">Daftar Booking Masuk</div>
          <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
            <div class="flex items-center justify-end gap-3 text-sm text-gray-600">
              <label class="font-medium text-gray-700" for="admin-bookings-rows">Rows per page</label>
              <div class="relative">
                <select
                  id="admin-bookings-rows"
                  v-model.number="rowsPerPage"
                  class="w-20 rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                  <option v-for="option in perPageOptions" :key="option" :value="option">
                    {{ option }}
                  </option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
            <tr>
              <SortableTh
                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                column="title"
                label="Judul"
                :direction="adminBookingSortDirection('title')"
                :aria-sort="adminBookingAriaSortValue('title')"
                @toggle="toggleAdminBookingSort"
              />
              <SortableTh
                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                column="applicant"
                label="Pemohon"
                :direction="adminBookingSortDirection('applicant')"
                :aria-sort="adminBookingAriaSortValue('applicant')"
                @toggle="toggleAdminBookingSort"
              />
              <SortableTh
                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                column="room"
                label="Ruangan"
                :direction="adminBookingSortDirection('room')"
                :aria-sort="adminBookingAriaSortValue('room')"
                @toggle="toggleAdminBookingSort"
              />
              <SortableTh
                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                column="schedule"
                label="Jadwal"
                :direction="adminBookingSortDirection('schedule')"
                :aria-sort="adminBookingAriaSortValue('schedule')"
                @toggle="toggleAdminBookingSort"
              />
              <SortableTh
                class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500"
                column="status"
                label="Status"
                :direction="adminBookingSortDirection('status')"
                :aria-sort="adminBookingAriaSortValue('status')"
                align="center"
                @toggle="toggleAdminBookingSort"
              />
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-gray-700">
            <tr v-for="booking in paginatedBookings" :key="booking.id" class="hover:bg-gray-50">
              <td class="px-5 py-4">
                <div class="font-medium text-gray-900">{{ booking.title }}</div>
                <div class="text-xs text-gray-500">{{ booking.description || 'Tidak ada deskripsi.' }}</div>
              </td>
              <td class="px-5 py-4 text-sm">
                <div class="font-medium text-gray-800">{{ booking.user?.name ?? '-' }}</div>
                <div class="text-xs text-gray-500">{{ booking.user?.email ?? '-' }}</div>
              </td>
              <td class="px-5 py-4">
                <div class="font-medium text-gray-800">{{ booking.room?.name ?? '-' }}</div>
                <div class="text-xs text-gray-500">
                  {{ booking.room?.building?.name ?? '-' }} - {{ booking.room?.building?.campus?.name ?? '-' }}
                </div>
              </td>
              <td class="px-5 py-4 text-sm">
                <div>Mulai: <span class="font-medium text-gray-800">{{ formatDateTime(booking.start_time) }}</span></div>
                <div>Selesai: <span class="font-medium text-gray-800">{{ formatDateTime(booking.end_time) }}</span></div>
              </td>
              <td class="px-5 py-4 text-center">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                  :class="badgeClasses[booking.normalizedStatus] ?? 'bg-gray-100 text-gray-600'"
                >
                  {{ statusLabels[booking.normalizedStatus] ?? (booking.normalizedStatus || booking.status) }}
                </span>
              </td>
              <td class="px-5 py-4 text-right">
                <Link
                  :href="route('admin.bookings.show', booking.id)"
                  class="inline-flex items-center rounded-md border border-blue-200 px-3 py-1.5 text-xs font-medium text-blue-600 hover:bg-blue-50"
                >
                  Lihat Detail
                </Link>
              </td>
            </tr>
            <tr v-if="!bookingsList.length">
              <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-400">
                Belum ada data booking.
              </td>
            </tr>
          </tbody>
        </table>

        <div
          class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between"
        >
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} data</span>
            <span v-else>Menampilkan 0 data</span>
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(1)"
              :disabled="currentPage === 1 || !bookingsList.length"
            >
              «
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
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
                    : 'border-gray-300 text-gray-600 hover:border-blue-400 hover:text-blue-600'
                "
                @click="changePage(page)"
              >
                {{ page }}
              </button>
            </template>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage + 1)"
              :disabled="currentPage === pages.length || !bookingsList.length"
            >
              ›
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
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