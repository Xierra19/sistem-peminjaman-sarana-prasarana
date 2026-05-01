<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { formatDateTimeToDDMMYY } from '@/Composables/useDateFormatter'

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

const statusClasses = {
  waiting: 'bg-amber-100 text-amber-700 border-amber-200',
  approved: 'bg-emerald-100 text-emerald-700 border-emerald-200',
  rejected: 'bg-rose-100 text-rose-700 border-rose-200',
  cancelled: 'bg-slate-100 text-slate-700 border-slate-200',
}

const normalizeStatus = (status) => {
  if (!status) return ''
  return status === 'pending' || status === 'requested' ? 'waiting' : status
}

const perPageOptions = [5, 10, 25, 50]
const searchQuery = ref('')
const statusFilter = ref('')

const filteredBookings = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  const status = statusFilter.value

  return (props.bookings ?? []).filter((booking) => {
    const searchable = [
      booking.title,
      booking.description,
      booking.room?.name,
      booking.room?.building?.name,
      booking.room?.building?.campus?.name,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()

    const matchesSearch = !q || searchable.includes(q)
    const bookingStatus = normalizeStatus(booking.status)
    const matchesStatus = !status || bookingStatus === status

    return matchesSearch && matchesStatus
  })
})

const {
  sortedItems: sortedBookings,
  toggleSort: toggleBookingSort,
  sortDirection: bookingSortDirection,
  ariaSortValue: bookingAriaSortValue,
} = useTableSort(filteredBookings, {
  accessors: {
    number: (booking) => booking.id ?? 0,
    title: (booking) => booking.title ?? '',
    room: (booking) => booking.room?.name ?? '',
    start_time: (booking) => (booking.start_time ? new Date(booking.start_time) : null),
    end_time: (booking) => (booking.end_time ? new Date(booking.end_time) : null),
    status: (booking) => normalizeStatus(booking.status) ?? '',
  },
})

const {
  paginatedItems: paginatedBookings,
  rowsPerPage,
  currentPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(sortedBookings)

const formatDateTime = (value) => formatDateTimeToDDMMYY(value)

const cancelForm = useForm({})
const cancellingId = ref(null)

const canCancelBooking = (booking) => normalizeStatus(booking.status) === 'waiting'

const cancelBooking = (booking) => {
  if (!canCancelBooking(booking)) {
    return
  }

  if (!window.confirm('Yakin ingin membatalkan permintaan booking ini?')) {
    return
  }

  cancellingId.value = booking.id

  cancelForm.post(route('bookings.cancel', booking.id), {
    preserveScroll: true,
    onFinish: () => {
      cancellingId.value = null
    },
  })
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Request Booking Ruangan" />

    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-slate-900">Request Booking Ruangan</h1>
          <p class="text-sm text-slate-500">
            Pantau status persetujuan booking ruangan yang telah kamu ajukan.
          </p>
        </div>
        <Link
          :href="route('bookings.create')"
          class="inline-flex w-full items-center justify-center rounded-2xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 sm:w-auto"
        >
          + Buat Request Baru
        </Link>
      </div>

      <div class="card-surface overflow-hidden">
        <div class="space-y-4 border-b border-slate-100 px-5 py-4">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex flex-1 flex-col gap-3 md:flex-row md:items-center">
              <div class="w-full md:max-w-sm">
                <label class="mb-1 block text-sm font-medium text-slate-700" for="user-bookings-search">Pencarian</label>
                <div class="relative">
                  <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z" />
                    </svg>
                  </span>
                  <input
                    id="user-bookings-search"
                    v-model="searchQuery"
                    type="text"
                    placeholder="Cari judul, ruangan, atau kampus..."
                    class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-700 placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  />
                </div>
              </div>
              <div class="w-full md:w-60">
                <label class="mb-1 block text-sm font-medium text-slate-700" for="user-bookings-status">Status</label>
                <div class="relative">
                  <select
                    id="user-bookings-status"
                    v-model="statusFilter"
                    class="w-full appearance-none rounded-xl border border-slate-200 bg-white px-3 py-2 pr-9 text-sm leading-5 text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  >
                    <option value="">Semua Status</option>
                    <option value="waiting">Menunggu Persetujuan</option>
                    <option value="approved">Disetujui</option>
                    <option value="rejected">Ditolak</option>
                    <option value="cancelled">Dibatalkan</option>
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
            <div class="flex items-center gap-3 rounded-2xl bg-slate-50 px-3 py-2 text-sm text-slate-600">
              <span class="font-medium">Total data</span>
              <span class="inline-flex h-8 w-12 items-center justify-center rounded-xl bg-white text-sm font-semibold text-slate-900 shadow-sm">
                {{ paginatedBookings.length }}
              </span>
            </div>
          </div>
          <div class="flex flex-col gap-2 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-end">
            <label class="font-medium text-slate-700" for="user-bookings-rows">Rows per page</label>
            <div class="relative">
              <select
                id="user-bookings-rows"
                v-model.number="rowsPerPage"
                class="w-24 rounded-xl border border-slate-200 bg-white px-3 py-1.5 pr-9 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              >
                <option v-for="option in perPageOptions" :key="`bookings-rows-${option}`" :value="option">
                  {{ option }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="mobile-friendly-table min-w-full divide-y divide-slate-200 text-sm text-slate-700">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <SortableTh
                  class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                  column="number"
                  label="#"
                  :direction="bookingSortDirection('number')"
                  :aria-sort="bookingAriaSortValue('number')"
                  @toggle="toggleBookingSort"
                />
                <SortableTh
                  class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                  column="title"
                  label="Judul"
                  :direction="bookingSortDirection('title')"
                  :aria-sort="bookingAriaSortValue('title')"
                  @toggle="toggleBookingSort"
                />
                <SortableTh
                  class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                  column="room"
                  label="Ruangan"
                  :direction="bookingSortDirection('room')"
                  :aria-sort="bookingAriaSortValue('room')"
                  @toggle="toggleBookingSort"
                />
                <SortableTh
                  class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                  column="start_time"
                  label="Mulai"
                  :direction="bookingSortDirection('start_time')"
                  :aria-sort="bookingAriaSortValue('start_time')"
                  @toggle="toggleBookingSort"
                />
                <SortableTh
                  class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                  column="end_time"
                  label="Selesai"
                  :direction="bookingSortDirection('end_time')"
                  :aria-sort="bookingAriaSortValue('end_time')"
                  @toggle="toggleBookingSort"
                />
                <SortableTh
                  class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                  column="status"
                  label="Status"
                  :direction="bookingSortDirection('status')"
                  :aria-sort="bookingAriaSortValue('status')"
                  @toggle="toggleBookingSort"
                />
                <th class="px-4 py-3 text-left">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="(booking, index) in paginatedBookings" :key="booking.id" class="hover:bg-slate-50">
                <td class="px-4 py-3 text-slate-500" data-title="#">
                  {{ pageMeta.from + index }}
                </td>
                <td class="px-4 py-3" data-title="Judul">
                  <div class="font-semibold text-slate-900">{{ booking.title }}</div>
                  <div class="mt-1 text-xs text-slate-500">
                    {{ booking.description || 'Tidak ada deskripsi tambahan.' }}
                  </div>
                </td>
                <td class="px-4 py-3" data-title="Ruangan">
                  <div class="font-medium text-slate-900">{{ booking.room?.name ?? '-' }}</div>
                  <div class="text-xs text-slate-500">
                    {{ booking.room?.building?.name ?? '-' }} - {{ booking.room?.building?.campus?.name ?? '-' }}
                  </div>
                </td>
                <td class="px-4 py-3 text-slate-700" data-title="Mulai">{{ formatDateTime(booking.start_time) }}</td>
                <td class="px-4 py-3 text-slate-700" data-title="Selesai">{{ formatDateTime(booking.end_time) }}</td>
                <td class="px-4 py-3" data-title="Status">
                  <span
                    class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
                    :class="statusClasses[normalizeStatus(booking.status)] ?? 'bg-slate-100 text-slate-600 border-slate-200'"
                  >
                    {{ statusLabels[normalizeStatus(booking.status)] ?? booking.status }}
                  </span>
                </td>
                <td class="px-4 py-3" data-title="Aksi">
                  <div class="flex flex-col gap-2">
                    <Link
                      :href="route('bookings.show', booking.id)"
                      class="inline-flex items-center justify-center rounded-xl border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-700 transition hover:border-blue-300 hover:text-blue-800"
                    >
                      Lihat Detail
                    </Link>
                    <template v-if="normalizeStatus(booking.status) === 'approved'">
                      <a
                        :href="route('bookings.letter', booking.id)"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                      >
                        Download Surat
                      </a>
                    </template>
                    <template v-else-if="normalizeStatus(booking.status) === 'waiting'">
                      <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="cancelForm.processing && cancellingId === booking.id"
                        @click="cancelBooking(booking)"
                      >
                        {{
                          cancelForm.processing && cancellingId === booking.id ? 'Membatalkan...' : 'Batalkan Permintaan'
                        }}
                      </button>
                      <span class="text-xs text-slate-400">Menunggu persetujuan admin</span>
                    </template>
                    <template v-else-if="normalizeStatus(booking.status) === 'rejected'">
                      <span class="text-xs font-semibold text-rose-500">Permintaan ditolak</span>
                    </template>
                    <template v-else-if="normalizeStatus(booking.status) === 'cancelled'">
                      <span class="text-xs text-slate-400">Booking telah dibatalkan</span>
                    </template>
                    <template v-else>
                      <span class="text-xs text-slate-400">
                        {{ statusLabels[normalizeStatus(booking.status)] ?? booking.status }}
                      </span>
                    </template>
                  </div>
                </td>
              </tr>
              <tr v-if="!paginatedBookings.length">
                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500" data-title="Info">
                  Tidak ada data booking yang cocok dengan filter saat ini.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between"
        >
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} data</span>
            <span v-else>Menampilkan 0 data</span>
          </div>
          <div class="flex items-center gap-1">
            <button
              class="inline-flex items-center rounded-xl border border-slate-300 px-2 py-1 text-slate-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(1)"
              :disabled="currentPage === 1 || !pageMeta.of"
            >
              «
            </button>
            <button
              class="inline-flex items-center rounded-xl border border-slate-300 px-2 py-1 text-slate-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1 || !pageMeta.of"
            >
              ‹
            </button>
            <template v-if="pageMeta.of">
              <button
                v-for="page in pages"
                :key="`bookings-page-${page}`"
                class="inline-flex items-center rounded-xl border px-3 py-1 text-sm transition"
                :class="currentPage === page ? 'border-blue-500 bg-blue-500 text-white' : 'border-slate-300 text-slate-600 hover:border-blue-400 hover:text-blue-600'"
                @click="changePage(page)"
              >
                {{ page }}
              </button>
            </template>
            <button
              class="inline-flex items-center rounded-xl border border-slate-300 px-2 py-1 text-slate-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage + 1)"
              :disabled="currentPage === pages.length || !pageMeta.of"
            >
              ›
            </button>
            <button
              class="inline-flex items-center rounded-xl border border-slate-300 px-2 py-1 text-slate-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(pages.length)"
              :disabled="currentPage === pages.length || !pageMeta.of"
            >
              »
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
