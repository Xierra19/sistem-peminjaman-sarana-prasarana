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
  requested: 'Diajukan',
}

const statusColors = {
  pending: 'bg-amber-100 text-amber-700 border-amber-200',
  approved: 'bg-emerald-100 text-emerald-700 border-emerald-200',
  rejected: 'bg-rose-100 text-rose-700 border-rose-200',
  requested: 'bg-sky-100 text-sky-700 border-sky-200',
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
    <Head title="Request Booking Ruangan" />

    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
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

      <div v-if="!bookings.length" class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center text-gray-500">
        Belum ada request booking. Yuk buat pengajuan pertama kamu!
      </div>

      <div v-else class="space-y-6">
        <div
          v-for="booking in bookings"
          :key="booking.id"
          class="rounded-xl border border-gray-200 bg-white shadow-sm"
        >
          <div class="border-b border-gray-100 px-6 py-4 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <div class="flex items-center gap-2">
                <h2 class="text-xl font-semibold text-gray-800">{{ booking.title }}</h2>
                <span
                  class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium"
                  :class="statusColors[booking.status] ?? 'bg-gray-100 text-gray-600 border-gray-200'"
                >
                  {{ statusLabels[booking.status] ?? booking.status }}
                </span>
              </div>
              <p class="mt-1 text-sm text-gray-500">{{ booking.description || 'Tidak ada deskripsi tambahan.' }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600">
              <div class="font-medium text-gray-700">Jadwal Penggunaan</div>
              <div class="mt-1">Mulai: <span class="font-semibold text-gray-800">{{ formatDateTime(booking.start_time) }}</span></div>
              <div>Selesai: <span class="font-semibold text-gray-800">{{ formatDateTime(booking.end_time) }}</span></div>
            </div>
          </div>

          <div class="grid gap-6 px-6 py-5 lg:grid-cols-3">
            <div class="space-y-3 lg:col-span-2">
              <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                Detail Ruangan
              </h3>
              <div class="rounded-lg border border-gray-100 bg-gray-50 p-4 text-sm text-gray-600">
                <p><span class="font-medium text-gray-700">Ruangan:</span> {{ booking.room?.name ?? '-' }}</p>
                <p>
                  <span class="font-medium text-gray-700">Gedung:</span>
                  {{ booking.room?.building?.name ?? '-' }}
                </p>
                <p>
                  <span class="font-medium text-gray-700">Kampus:</span>
                  {{ booking.room?.building?.campus?.name ?? '-' }}
                </p>
                <p>
                  <span class="font-medium text-gray-700">Ketersediaan:</span>
                  <span :class="booking.room?.is_available ? 'text-emerald-600' : 'text-rose-600'">
                    {{ booking.room?.is_available ? 'Tersedia' : 'Tidak tersedia' }}
                  </span>
                </p>
              </div>
            </div>

            <div>
              <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-3">
                Riwayat Persetujuan
              </h3>
              <ul class="space-y-3">
                <li
                  v-for="log in booking.logs ?? []"
                  :key="log.id"
                  class="flex gap-3 rounded-lg border border-gray-100 bg-white p-3 shadow-sm"
                >
                  <div class="mt-1 h-2.5 w-2.5 flex-shrink-0 rounded-full"
                    :class="statusColors[log.action] ?? 'bg-gray-300'"
                  />
                  <div class="text-sm text-gray-600">
                    <div class="flex items-center justify-between gap-3">
                      <span class="font-medium text-gray-800">{{ log.user?.name ?? 'Sistem' }}</span>
                      <span class="text-xs text-gray-400">{{ formatDateTime(log.created_at) }}</span>
                    </div>
                    <p class="text-xs uppercase tracking-wide text-gray-400">{{ statusLabels[log.action] ?? log.action }}</p>
                    <p class="mt-1 leading-snug text-gray-600">{{ log.description ?? '-' }}</p>
                  </div>
                </li>
                <li v-if="!(booking.logs && booking.logs.length)" class="rounded-lg border border-dashed border-gray-200 p-4 text-center text-xs text-gray-400">
                  Belum ada aktivitas persetujuan.
                </li>
              </ul>
            </div>
          </div>

          <div class="border-t border-gray-100 bg-gray-50 px-6 py-4">
            <template v-if="booking.status === 'approved'">
              <a
                :href="route('bookings.letter', booking.id)"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
              >
                📥 Download Surat Peminjaman Ruangan
              </a>
            </template>
            <p v-else class="text-sm text-gray-500">
              Surat peminjaman akan tersedia setelah permintaan disetujui oleh admin.
            </p>
          </div>          
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>