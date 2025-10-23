<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { usePagination } from '@/Composables/usePagination'

const props = defineProps({
  bookings: {
    type: Array,
    default: () => [],
  },
})

const statusLabels = {
  pending: 'Menunggu Persetujuan',
  approved: 'Disetujui',
  rejected: 'Ditolak',
  requested: 'Diajukan',
}

const statusClasses = {
  pending: 'bg-amber-100 text-amber-700 border-amber-200',
  approved: 'bg-emerald-100 text-emerald-700 border-emerald-200',
  rejected: 'bg-rose-100 text-rose-700 border-rose-200',
  requested: 'bg-sky-100 text-sky-700 border-sky-200',
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
    const matchesStatus = !status || booking.status === status

    return matchesSearch && matchesStatus
  })
})

const {
  paginatedItems: paginatedBookings,
  rowsPerPage,
  currentPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(filteredBookings)

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
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Request Booking Ruangan" />

    <div class="space-y-6">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Request Booking Ruangan</h1>
          <p class="text-sm text-gray-500">
            Pantau status persetujuan booking ruangan yang telah kamu ajukan.
          </p>
        </div>
        <Link
          :href="route('bookings.create')"
          class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
        >
          + Buat Request Baru
        </Link>
      </div>

      <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="grid gap-4 border-b border-gray-100 p-4 md:grid-cols-3">
          <div class="md:col-span-2 flex flex-wrap gap-4">
            <div class="min-w-[200px] flex-1">
              <label class="mb-1 block text-sm font-medium text-gray-700">Pencarian</label>
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Cari judul, ruangan, atau kampus..."
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
            </div>
            <div class="w-full min-w-[180px] md:w-auto">
              <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
              <div class="relative">
                <select
                  v-model="statusFilter"
                  class="w-full appearance-none rounded-md border border-gray-300 px-3 py-2 pr-9 text-sm leading-5 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                  <option value="">Semua Status</option>
                  <option value="pending">Menunggu Persetujuan</option>
                  <option value="approved">Disetujui</option>
                  <option value="rejected">Ditolak</option>
                  <option value="requested">Diajukan</option>
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
          </div>
          <div class="flex items-end justify-end">
            <div class="flex items-center gap-3">
              <label class="text-sm font-medium text-gray-700" for="user-bookings-rows">Rows per page</label>
              <div class="relative">
                <select
                  id="user-bookings-rows"
                  v-model.number="rowsPerPage"
                  class="appearance-none w-20 rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                  <option v-for="option in perPageOptions" :key="`bookings-rows-${option}`" :value="option">
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
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">#</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Judul</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Ruangan</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Mulai</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Selesai</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="(booking, index) in paginatedBookings" :key="booking.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-gray-600">
                  {{ pageMeta.from + index }}
                </td>
                <td class="px-4 py-3">
                  <div class="font-semibold text-gray-800">{{ booking.title }}</div>
                  <div class="mt-1 text-xs text-gray-500">
                    {{ booking.description || 'Tidak ada deskripsi tambahan.' }}
                  </div>
                </td>
                <td class="px-4 py-3 text-gray-700">
                  <div class="font-medium">{{ booking.room?.name ?? '-' }}</div>
                  <div class="text-xs text-gray-500">
                    {{ booking.room?.building?.name ?? '-' }} • {{ booking.room?.building?.campus?.name ?? '-' }}
                  </div>
                </td>
                <td class="px-4 py-3 text-gray-700">{{ formatDateTime(booking.start_time) }}</td>
                <td class="px-4 py-3 text-gray-700">{{ formatDateTime(booking.end_time) }}</td>
                <td class="px-4 py-3">
                  <span
                    class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
                    :class="statusClasses[booking.status] ?? 'bg-gray-100 text-gray-600 border-gray-200'"
                  >
                    {{ statusLabels[booking.status] ?? booking.status }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <template v-if="booking.status === 'approved'">
                    <a
                      :href="route('bookings.letter', booking.id)"
                      target="_blank"
                      rel="noopener"
                      class="inline-flex items-center rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                    >
                      Download Surat
                    </a>
                  </template>
                  <span v-else class="text-xs text-gray-400">Menunggu Persetujuan</span>
                </td>
              </tr>
              <tr v-if="!paginatedBookings.length">
                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">
                  Tidak ada data booking yang cocok dengan filter saat ini.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          class="flex flex-col gap-3 border-t border-gray-100 px-4 py-4 text-sm text-gray-600 md:flex-row md:items-center md:justify-between"
        >
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} data</span>
            <span v-else>Menampilkan 0 data</span>
          </div>
          <div class="flex items-center gap-1">
            <button
              class="inline-flex items-center rounded border border-gray-300 px-2 py-1 text-gray-600 hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(1)"
              :disabled="currentPage === 1 || !pageMeta.of"
            >
              «
            </button>
            <button
              class="inline-flex items-center rounded border border-gray-300 px-2 py-1 text-gray-600 hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1 || !pageMeta.of"
            >
              ‹
            </button>
            <template v-if="pageMeta.of">
              <button
                v-for="page in pages"
                :key="`bookings-page-${page}`"
                class="inline-flex items-center rounded border px-3 py-1 text-sm"
                :class="currentPage === page ? 'border-blue-500 bg-blue-500 text-white' : 'border-gray-300 text-gray-600 hover:border-blue-400 hover:text-blue-600'"
                @click="changePage(page)"
              >
                {{ page }}
              </button>
            </template>
            <button
              class="inline-flex items-center rounded border border-gray-300 px-2 py-1 text-gray-600 hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage + 1)"
              :disabled="currentPage === pages.length || !pageMeta.of"
            >
              ›
            </button>
            <button
              class="inline-flex items-center rounded border border-gray-300 px-2 py-1 text-gray-600 hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
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
