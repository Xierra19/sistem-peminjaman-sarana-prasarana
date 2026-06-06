<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { ref, watch, computed, onBeforeUnmount } from 'vue'
import {
  getBookingStatusClasses,
  getBookingStatusLabel,
  normalizeBookingStatus,
} from '@/Composables/useBookingStatus'
import { formatDateTimeToDDMMYY } from '@/Composables/useDateFormatter'

const props = defineProps({
  bookings: {
    type: Object,
    default: () => ({}),
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
})

// Debounce helper (no external library)
let timer = null
const debounce = (fn, delay) => {
  return (...args) => {
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => fn(...args), delay)
  }
}

// Cleanup on unmount to prevent memory leaks
onBeforeUnmount(() => {
  if (timer) clearTimeout(timer)
})

const perPageOptions = [5, 10, 25, 50]
const searchQuery = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || '')
const sortColumn = ref(props.filters.sort || 'created_at')
const sortDirection = ref(props.filters.direction || 'desc')
const rowsPerPage = ref(props.filters.per_page || 10)
const isLoading = ref(false)

// Prevent race conditions with plain variable (not ref)
let currentRequestId = 0

// Data from server
const paginatedBookings = computed(() => props.bookings.data || [])

const formatDateTime = (value) => formatDateTimeToDDMMYY(value)

const cancelForm = useForm({})
const cancellingId = ref(null)

const canCancelBooking = (booking) => normalizeBookingStatus(booking.status) === 'waiting'

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

// Centralized request logic (reusable function to avoid duplication)
const sendRequest = (params) => {
  isLoading.value = true
  const thisRequestId = ++currentRequestId
  
  router.get(
    route('bookings.index'),
    params,
    {
      preserveState: true,
      replace: true,
      onFinish: () => {
        if (thisRequestId === currentRequestId) {
          isLoading.value = false
        }
      },
    }
  )
}

// Function to send request with new filter/sort
const applyFilters = () => {
  sendRequest({
    search: searchQuery.value || undefined,
    status: statusFilter.value || undefined,
    sort: sortColumn.value,
    direction: sortDirection.value,
    per_page: rowsPerPage.value,
  })
}

// Debounced search (300ms) - only for search input
const debouncedSearch = debounce(applyFilters, 300)

// Watchers
watch(searchQuery, () => { debouncedSearch() })
watch([statusFilter, rowsPerPage], () => { applyFilters() })

// Toggle sort (server-side only)
const toggleBookingSort = (column) => {
  if (sortColumn.value === column) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortColumn.value = column
    sortDirection.value = 'asc'
  }
  applyFilters()
}

const bookingSortDirection = (column) => {
  if (sortColumn.value !== column) return 'none'
  return sortDirection.value
}

const bookingAriaSortValue = (column) => {
  if (sortColumn.value !== column) return 'none'
  return sortDirection.value === 'asc' ? 'ascending' : 'descending'
}

// Pagination
const pageMeta = computed(() => ({
  from: props.bookings.from || 0,
  to: props.bookings.to || 0,
  of: props.bookings.total || 0,
}))

const currentPage = computed(() => props.bookings.current_page || 1)
const totalPages = computed(() => props.bookings.last_page || 1)

// Skeleton rows (max 5 even if rowsPerPage is larger)
const skeletonRows = computed(() => Math.min(rowsPerPage.value, 5))
const canGoToPreviousPage = computed(() => currentPage.value > 1 && pageMeta.value.of > 0)
const canGoToNextPage = computed(() => currentPage.value < totalPages.value && pageMeta.value.of > 0)

// Limit pagination to max 5 visible pages with ellipsis
const pages = computed(() => {
  const total = totalPages.value
  const current = currentPage.value
  
  if (total <= 5) {
    return Array.from({ length: total }, (_, i) => i + 1)
  }
  
  const pages = []
  
  if (current <= 3) {
    // Show first 4 pages + ellipsis + last page
    for (let i = 1; i <= Math.min(4, total); i++) {
      pages.push(i)
    }
    if (total > 4) {
      pages.push('...')
      pages.push(total)
    }
  } else if (current >= total - 2) {
    // Show first page + ellipsis + last 4 pages
    pages.push(1)
    pages.push('...')
    for (let i = Math.max(total - 3, 2); i <= total; i++) {
      pages.push(i)
    }
  } else {
    // Show first page + ellipsis + current-1, current, current+1 + ellipsis + last page
    pages.push(1)
    pages.push('...')
    pages.push(current - 1)
    pages.push(current)
    pages.push(current + 1)
    pages.push('...')
    pages.push(total)
  }
  
  return pages
})

const changePage = (page) => {
  // Defensive coding: guard against invalid page values
  if (typeof page !== 'number' || page < 1 || page > totalPages.value) return
  
  sendRequest({
    page,
    search: searchQuery.value || undefined,
    status: statusFilter.value || undefined,
    sort: sortColumn.value,
    direction: sortDirection.value,
    per_page: rowsPerPage.value,
  })
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Permintaan Peminjaman Ruangan" />

    <div class="space-y-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Permintaan Peminjaman Ruangan</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400">
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

      <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Total</p>
          <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ isLoading ? '...' : pageMeta.of }}</p>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Permintaan sesuai filter aktif.</p>
        </div>
        <div class="rounded-2xl border border-blue-200 bg-white p-4 shadow-sm dark:border-blue-900 dark:bg-slate-800">
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-600 dark:text-blue-300">Status Filter</p>
          <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ statusFilter || 'Semua status' }}</p>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Gunakan filter untuk fokus ke status tertentu.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Per Halaman</p>
          <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ rowsPerPage }} baris</p>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Atur kepadatan daftar sesuai kebutuhan.</p>
        </div>
      </div>

      <div class="card-surface overflow-hidden">
        <div class="space-y-4 border-b border-slate-100 px-5 py-4 dark:border-slate-700">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex flex-1 flex-col gap-3 md:flex-row md:items-center">
              <div class="w-full md:max-w-sm">
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300" for="user-bookings-search">Pencarian</label>
                <div class="relative">
                  <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10 18a8 8 0 1 0-8-8 8 8 0 0 0 8 8z" />
                    </svg>
                  </span>
                  <input
                    id="user-bookings-search"
                    v-model="searchQuery"
                    type="text"
                    placeholder="Cari judul, ruangan, atau kampus..."
                    class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-700 placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
                  />
                </div>
              </div>
              <div class="w-full md:w-60">
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300" for="user-bookings-status">Status</label>
                <div class="relative">
                  <select
                    id="user-bookings-status"
                    v-model="statusFilter"
                    class="w-full appearance-none rounded-xl border border-slate-200 bg-white px-3 py-2 pr-9 text-sm leading-5 text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
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
                        d="M5.23 7.21a.75.75 0 0 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06 0l4.24-4.24a.75.75 0 0 1 1.06 1.06l-5.3 5.3a.75.75 0 0 1-1.06 0l-5.3-5.3z"
                        clip-rule="evenodd"
                      />
                    </svg>
                  </span>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-3 rounded-2xl bg-slate-50 px-3 py-2 text-sm text-slate-600 dark:bg-slate-700 dark:text-slate-300">
              <span class="font-medium">Total data</span>
              <span class="inline-flex h-8 w-12 items-center justify-center rounded-xl bg-white text-sm font-semibold text-slate-900 shadow-sm dark:bg-slate-600 dark:text-slate-100">
                {{ isLoading ? '...' : pageMeta.of }}
              </span>
            </div>
          </div>
          <div class="flex flex-col gap-2 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-end dark:text-slate-400">
            <label class="font-medium text-slate-700 dark:text-slate-300" for="user-bookings-rows">Rows per page</label>
            <div class="relative">
              <select
                id="user-bookings-rows"
                v-model.number="rowsPerPage"
                class="w-24 rounded-xl border border-slate-200 bg-white px-3 py-1.5 pr-9 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
              >
                <option v-for="option in perPageOptions" :key="`bookings-rows-${option}`" :value="option">
                  {{ option }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="request-mobile-table mobile-friendly-table min-w-full divide-y divide-slate-200 text-sm text-slate-700 dark:divide-slate-700 dark:text-slate-300">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-700 dark:text-slate-400">
              <tr>
                <SortableTh
                  class="px-4 py-3 text-left"
                  column="number"
                  label="#"
                  :direction="bookingSortDirection('number')"
                  :aria-sort="bookingAriaSortValue('number')"
                  @toggle="toggleBookingSort"
                />
                <SortableTh
                  class="px-4 py-3 text-left"
                  column="title"
                  label="Judul"
                  :direction="bookingSortDirection('title')"
                  :aria-sort="bookingAriaSortValue('title')"
                  @toggle="toggleBookingSort"
                />
                <SortableTh
                  class="px-4 py-3 text-left"
                  column="room"
                  label="Ruangan"
                  :direction="bookingSortDirection('room')"
                  :aria-sort="bookingAriaSortValue('room')"
                  @toggle="toggleBookingSort"
                />
                <SortableTh
                  class="px-4 py-3 text-left"
                  column="start_time"
                  label="Mulai"
                  :direction="bookingSortDirection('start_time')"
                  :aria-sort="bookingAriaSortValue('start_time')"
                  @toggle="toggleBookingSort"
                />
                <SortableTh
                  class="px-4 py-3 text-left"
                  column="end_time"
                  label="Selesai"
                  :direction="bookingSortDirection('end_time')"
                  :aria-sort="bookingAriaSortValue('end_time')"
                  @toggle="toggleBookingSort"
                />
                <SortableTh
                  class="px-4 py-3 text-left"
                  column="status"
                  label="Status"
                  :direction="bookingSortDirection('status')"
                  :aria-sort="bookingAriaSortValue('status')"
                  @toggle="toggleBookingSort"
                />
                <th class="px-4 py-3 text-left">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
              <!-- Loading skeleton rows (max 5) -->
              <template v-if="isLoading">
                <tr v-for="n in skeletonRows" :key="`skeleton-${n}`">
                  <td colspan="7" class="px-4 py-3">
                    <div class="h-6 animate-pulse rounded bg-slate-200 dark:bg-slate-700"></div>
                  </td>
                </tr>
              </template>
              
              <template v-else>
                <tr v-for="(booking, index) in paginatedBookings" :key="booking.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                  <td class="mobile-id-cell px-4 py-3 text-slate-500 dark:text-slate-400" data-title="#">
                    {{ (pageMeta.from || 1) + index }}
                  </td>
                  <td class="mobile-primary-cell mobile-span-2 px-4 py-3" data-title="Judul">
                    <div class="mobile-primary-label">Judul</div>
                    <div class="mobile-primary-title">{{ booking.title }}</div>
                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ booking.description || 'Tidak ada deskripsi tambahan.' }}</div>
                  </td>
                <td class="mobile-compact-meta px-4 py-3" data-title="Ruangan">
                  <div class="font-medium text-slate-900 dark:text-slate-100">{{ booking.room_name ?? '-' }}</div>
                  <div class="text-xs text-slate-500 dark:text-slate-400">
                    {{ booking.building_name ?? '-' }} - {{ booking.campus_name ?? '-' }}
                  </div>
                </td>
                  <td class="mobile-span-2 px-4 py-3 text-slate-700 dark:text-slate-300" data-title="Mulai">
                    <div class="font-medium">{{ booking.schedule_mode_label ?? 'Jadwal' }}</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ booking.schedule_short_summary ?? formatDateTime(booking.start_time) }}</div>
                  </td>
                  <td class="px-4 py-3 text-slate-700 dark:text-slate-300 mobile-compact-meta" data-title="Selesai">
                    {{ booking.schedule_summary ?? formatDateTime(booking.end_time) }}
                  </td>
                  <td class="mobile-status-cell px-4 py-3" data-title="Status">
                    <span
                      class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
                      :class="getBookingStatusClasses(booking.status)"
                    >
                      {{ getBookingStatusLabel(booking.status) }}
                    </span>
                  </td>
                  <td class="mobile-action-cell px-4 py-3" data-title="Aksi">
                    <div class="flex flex-col gap-2">
                      <Link
                        :href="route('bookings.show', booking.id)"
                        class="inline-flex items-center justify-center rounded-xl border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-700 transition hover:border-blue-300 hover:text-blue-800 dark:border-blue-800 dark:text-blue-300 dark:hover:border-blue-700 dark:hover:text-blue-200"
                      >
                        Lihat Detail
                      </Link>
                      <template v-if="normalizeBookingStatus(booking.status) === 'approved'">
                        <a
                          :href="route('bookings.letter', booking.id)"
                          target="_blank"
                          rel="noopener"
                          class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                        >
                          Download Surat
                        </a>
                      </template>
                      <template v-else-if="canCancelBooking(booking)">
                        <button
                          type="button"
                          class="inline-flex items-center justify-center rounded-xl border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-rose-800 dark:text-rose-300 dark:hover:border-rose-700 dark:hover:bg-rose-900/30"
                          :disabled="cancelForm.processing && cancellingId === booking.id"
                          @click="cancelBooking(booking)"
                        >
                          {{ cancellingId === booking.id ? 'Membatalkan...' : 'Batalkan Permintaan' }}
                        </button>
                      </template>
                      <template v-else-if="normalizeBookingStatus(booking.status) === 'rejected'">
                        <span class="text-xs font-semibold text-rose-500 dark:text-rose-400">Permintaan ditolak</span>
                      </template>
                      <template v-else-if="normalizeBookingStatus(booking.status) === 'cancelled'">
                        <span class="text-xs text-slate-400 dark:text-slate-500">Booking telah dibatalkan</span>
                      </template>
                      <template v-else>
                        <span class="text-xs text-slate-400 dark:text-slate-500">Menunggu persetujuan admin</span>
                      </template>
                    </div>
                  </td>
                </tr>
                <tr v-if="!paginatedBookings.length">
                  <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
                    Tidak ada data booking yang cocok dengan filter saat ini.
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:text-slate-400">
          <div>
            <template v-if="isLoading">
              Menampilkan ... data
            </template>
            <template v-else-if="pageMeta.of">
              Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} data
            </template>
            <template v-else>
              Menampilkan 0 data
            </template>
          </div>
          <div class="w-full sm:w-auto">
            <div class="mobile-pagination-compact sm:hidden">
              <button
                type="button"
                class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400"
                @click="changePage(currentPage - 1)"
                :disabled="isLoading || !canGoToPreviousPage"
              >
                Sebelumnya
              </button>
              <button
                type="button"
                class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400"
                @click="changePage(currentPage + 1)"
                :disabled="isLoading || !canGoToNextPage"
              >
                Berikutnya
              </button>
            </div>
            <div class="hidden items-center gap-2 sm:flex">
              <button
                type="button"
                class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400"
                @click="changePage(1)"
                :disabled="isLoading || currentPage === 1 || !pageMeta.of"
              >
                «
              </button>
              <button
                type="button"
                class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400"
                @click="changePage(currentPage - 1)"
                :disabled="isLoading || currentPage === 1 || !pageMeta.of"
              >
                ‹
              </button>
              <template v-if="pageMeta.of">
                <button
                  v-for="(page, index) in pages"
                  :key="`bookings-page-${page}-${index}`"
                  type="button"
                  class="rounded border px-3 py-1 text-sm transition"
                  :class="
                    page === '...'
                      ? 'cursor-default border-transparent text-slate-400'
                      : currentPage === page
                      ? 'border-blue-500 bg-blue-500 text-white'
                      : 'border-gray-300 text-gray-600 hover:border-blue-400 hover:text-blue-600 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400'
                  "
                  @click="changePage(page)"
                  :disabled="isLoading || page === '...'"
                >
                  {{ page }}
                </button>
              </template>
              <button
                type="button"
                class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400"
                @click="changePage(currentPage + 1)"
                :disabled="isLoading || currentPage === totalPages.value || !pageMeta.of"
              >
                ›
              </button>
              <button
                type="button"
                class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-400 dark:hover:border-blue-500 dark:hover:text-blue-400"
                @click="changePage(totalPages.value)"
                :disabled="isLoading || currentPage === totalPages.value || !pageMeta.of"
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
