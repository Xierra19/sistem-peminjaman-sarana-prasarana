<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Dropdown from '@/Components/Dropdown.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'

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

const bookingsList = computed(() =>
  (props.bookings ?? []).map((booking) => ({
    ...booking,
    normalizedStatus: normalizeStatus(booking.status),
  })),
)

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

const exportExcel = () => {
  window.open(route('admin.bookings.export.excel'), '_blank')
}

const exportPdf = () => {
  window.open(route('admin.bookings.export.pdf'), '_blank')
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Approval Booking Ruangan" />

    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-800">Approval Booking Ruangan</h1>
        <p class="text-sm text-gray-500">Kelola permintaan booking ruangan yang masuk.</p>
      </div>

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
                  class="w-20 appearance-none rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                  <option v-for="option in perPageOptions" :key="option" :value="option">
                    {{ option }}
                  </option>
                </select>
                <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-gray-400">
                  <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path
                      fill-rule="evenodd"
                      d="M5.23 7.21a.75.75 0 011.06.02L10 10.939l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"
                      clip-rule="evenodd"
                    />
                  </svg>
                </span>
              </div>
            </div>
            <Dropdown align="right" width="48">
              <template #trigger>
                <button
                  type="button"
                  class="inline-flex items-center justify-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-100"
                >
                  <span>Export</span>
                  <svg class="h-4 w-4" fill="none" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path
                      d="M5.25 7.5 10 12.5l4.75-5"
                      stroke="currentColor"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="1.5"
                    />
                  </svg>
                </button>
              </template>
              <template #content>
                <div class="flex flex-col gap-1 p-2 text-sm text-gray-700">
                  <button
                    type="button"
                    class="flex items-center gap-2 rounded-md px-3 py-2 hover:bg-blue-50"
                    @click="exportExcel"
                  >
                    <span>Export Excel</span>
                  </button>
                  <button
                    type="button"
                    class="flex items-center gap-2 rounded-md px-3 py-2 hover:bg-blue-50"
                    @click="exportPdf"
                  >
                    <span>Export PDF</span>
                  </button>
                </div>
              </template>
            </Dropdown>
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
              First
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1 || !bookingsList.length"
            >
              Prev
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
              Next
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(pages.length)"
              :disabled="currentPage === pages.length || !bookingsList.length"
            >
              Last
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
