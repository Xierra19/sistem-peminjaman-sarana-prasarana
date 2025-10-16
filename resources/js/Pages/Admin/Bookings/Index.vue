<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

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
}

const badgeClasses = {
  pending: 'bg-amber-100 text-amber-700',
  approved: 'bg-emerald-100 text-emerald-700',
  rejected: 'bg-rose-100 text-rose-700',
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
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
            <tr>
              <th class="px-5 py-3 text-left">Judul</th>
              <th class="px-5 py-3 text-left">Pemohon</th>
              <th class="px-5 py-3 text-left">Ruangan</th>
              <th class="px-5 py-3 text-left">Jadwal</th>
              <th class="px-5 py-3 text-center">Status</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-gray-700">
            <tr v-for="booking in bookings" :key="booking.id" class="hover:bg-gray-50">
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
                  {{ booking.room?.building?.name ?? '-' }} · {{ booking.room?.building?.campus?.name ?? '-' }}
                </div>
              </td>
              <td class="px-5 py-4 text-sm">
                <div>Mulai: <span class="font-medium text-gray-800">{{ formatDateTime(booking.start_time) }}</span></div>
                <div>Selesai: <span class="font-medium text-gray-800">{{ formatDateTime(booking.end_time) }}</span></div>
              </td>
              <td class="px-5 py-4 text-center">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                  :class="badgeClasses[booking.status] ?? 'bg-gray-100 text-gray-600'"
                >
                  {{ statusLabels[booking.status] ?? booking.status }}
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
            <tr v-if="!bookings.length">
              <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-400">
                Belum ada data booking.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AuthenticatedLayout>
</template>