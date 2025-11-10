<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  booking: {
    type: Object,
    required: true,
  },
})

const approvalForm = useForm({
  status: '',
  notes: '',
})

const statusLabels = {
  waiting: 'Menunggu Persetujuan',
  approved: 'Disetujui',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan Admin',
}

const statusColors = {
  waiting: 'bg-amber-100 text-amber-700 border-amber-200',
  approved: 'bg-emerald-100 text-emerald-700 border-emerald-200',
  rejected: 'bg-rose-100 text-rose-700 border-rose-200',
  cancelled: 'bg-slate-100 text-slate-700 border-slate-200',
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

const normalizedStatus = computed(() => normalizeStatus(props.booking.status))
const isWaiting = computed(() => normalizedStatus.value === 'waiting')
const canCancel = computed(() => normalizedStatus.value === 'approved')
const actionsLocked = computed(() => !isWaiting.value && !canCancel.value)

const submitApproval = (status) => {
  approvalForm.status = status
  approvalForm.post(route('admin.bookings.update-status', props.booking.id), {
    preserveScroll: true,
    onSuccess: () => {
      approvalForm.reset('notes')
    },
  })
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Detail Approval Booking" />

    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div class="text-sm text-gray-500">Approval Booking Ruangan</div>
          <h1 class="text-2xl font-semibold text-gray-800">{{ booking.title }}</h1>
        </div>
        <Link
          :href="route('admin.bookings.index')"
          class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
        >
          Kembali ke Daftar
        </Link>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
          <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <header class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
              <div class="space-y-1">
                <h2 class="text-lg font-semibold text-gray-800">Detail Pengajuan</h2>
                <p class="text-sm text-gray-500">Informasi lengkap mengenai permintaan yang diajukan pengguna.</p>
              </div>
              <span
                class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
                :class="statusColors[normalizedStatus] ?? 'bg-gray-100 text-gray-600 border-gray-200'"
              >
                {{ statusLabels[normalizedStatus] ?? (normalizedStatus || booking.status) }}
              </span>
            </header>
            <div class="grid gap-6 px-6 py-5 sm:grid-cols-2">
              <div class="space-y-3">
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500">Pemohon</div>
                  <div class="text-sm font-medium text-gray-800">{{ booking.user?.name ?? '-' }}</div>
                  <div class="text-xs text-gray-500">{{ booking.user?.email ?? '-' }}</div>
                  <div class="text-xs text-gray-500">Telp: {{ booking.user?.phone ?? '-' }}</div>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500">Deskripsi</div>
                  <p class="text-sm leading-relaxed text-gray-700">{{ booking.description || 'Tidak ada deskripsi.' }}</p>
                </div>
              </div>
              <div class="space-y-3">
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500">Jadwal</div>
                  <p class="text-sm text-gray-700">
                    Mulai: <span class="font-semibold text-gray-900">{{ formatDateTime(booking.start_time) }}</span>
                  </p>
                  <p class="text-sm text-gray-700">
                    Selesai: <span class="font-semibold text-gray-900">{{ formatDateTime(booking.end_time) }}</span>
                  </p>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500">Lokasi</div>
                  <p class="text-sm text-gray-700">
                    Ruangan:
                    <span class="font-semibold text-gray-900">{{ booking.room?.name ?? '-' }}</span>
                  </p>
                  <p class="text-sm text-gray-700">Gedung: {{ booking.room?.building?.name ?? '-' }}</p>
                  <p class="text-sm text-gray-700">Kampus: {{ booking.room?.building?.campus?.name ?? '-' }}</p>
                </div>
              </div>
            </div>
          </section>

          <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <header class="border-b border-gray-100 px-6 py-4">
              <h2 class="text-lg font-semibold text-gray-800">Lampiran</h2>
              <p class="text-sm text-gray-500">Dokumen pendukung dari pemohon.</p>
            </header>
            <div class="px-6 py-5 text-sm text-gray-600">
              <template v-if="booking.attachment">
                <a
                  :href="route('bookings.attachment', booking.id)"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex items-center rounded-md border border-blue-200 px-4 py-2 text-sm font-semibold text-blue-700 transition hover:border-blue-300 hover:text-blue-800"
                >
                  Lihat Lampiran
                </a>
              </template>
              <p v-else class="text-gray-400">Tidak ada lampiran yang diunggah.</p>
            </div>
          </section>

          <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <header class="border-b border-gray-100 px-6 py-4">
              <h2 class="text-lg font-semibold text-gray-800">Surat Peminjaman</h2>
              <p class="text-sm text-gray-500">Unduh surat persetujuan peminjaman ruangan.</p>
            </header>
            <div class="px-6 py-5 text-sm text-gray-600">
              <template v-if="normalizedStatus === 'approved'">
                <a
                  :href="route('bookings.letter', booking.id)"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                >
                  Download Surat Peminjaman Ruangan
                </a>
              </template>
              <p v-else-if="normalizedStatus === 'cancelled'" class="text-gray-500">
                Booking dibatalkan oleh admin. Surat peminjaman tidak tersedia.
              </p>
              <p v-else class="text-gray-400">Surat peminjaman tersedia setelah booking disetujui.</p>
            </div>
          </section>
        </div>

        <aside class="space-y-6">
          <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <header class="border-b border-gray-100 px-6 py-4">
              <h2 class="text-lg font-semibold text-gray-800">Tindakan Persetujuan</h2>
              <p class="text-sm text-gray-500">Setujui atau tolak permintaan booking ruangan ini.</p>
            </header>
            <div class="space-y-4 px-6 py-5">
              <textarea
                v-model="approvalForm.notes"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
                rows="3"
                :placeholder="canCancel ? 'Catatan (wajib) ketika membatalkan booking' : 'Catatan (opsional) untuk pemohon'"
              />
              <p v-if="canCancel" class="text-xs text-slate-500">
                Cantumkan alasan pembatalan agar pengguna memahami perubahan mendadak.
              </p>
              <p v-if="approvalForm.errors.notes" class="text-xs text-rose-500">
                {{ approvalForm.errors.notes }}
              </p>

              <div class="flex flex-col gap-2">
                <button
                  type="button"
                  class="inline-flex items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="approvalForm.processing || !isWaiting"
                  @click="submitApproval('approved')"
                >
                  Setujui Booking
                </button>
                <button
                  type="button"
                  class="inline-flex items-center justify-center rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="approvalForm.processing || !isWaiting"
                  @click="submitApproval('rejected')"
                >
                  Tolak Booking
                </button>
                <button
                  v-if="canCancel"
                  type="button"
                  class="inline-flex items-center justify-center rounded-md bg-slate-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="approvalForm.processing"
                  @click="submitApproval('cancelled')"
                >
                  Batalkan Booking (Darurat)
                </button>
              </div>
              <p v-if="actionsLocked" class="text-xs text-gray-400">
                Status booking sudah final. Tidak dapat melakukan tindakan lanjutan.
              </p>
            </div>
          </section>

          <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <header class="border-b border-gray-100 px-6 py-4">
              <h2 class="text-lg font-semibold text-gray-800">Riwayat Aktivitas</h2>
              <p class="text-sm text-gray-500">Jejak persetujuan untuk booking ini.</p>
            </header>
            <ul class="space-y-4 px-6 py-5">
              <li
                v-for="log in booking.logs ?? []"
                :key="log.id"
                class="flex gap-3 rounded-lg border border-gray-100 bg-white p-3 shadow-sm"
              >
                <div
                  class="mt-1 h-2.5 w-2.5 flex-shrink-0 rounded-full"
                  :class="statusColors[normalizeStatus(log.action)] ?? 'bg-gray-300'"
                />
                <div class="space-y-1 text-sm text-gray-600">
                  <div class="flex items-center justify-between gap-3">
                    <span class="font-medium text-gray-800">{{ log.user?.name ?? 'Sistem' }}</span>
                    <span class="text-xs text-gray-400">{{ formatDateTime(log.created_at) }}</span>
                  </div>
                  <p class="text-xs uppercase tracking-wide text-gray-400">
                    {{ statusLabels[normalizeStatus(log.action)] ?? (normalizeStatus(log.action) || log.action) }}
                  </p>
                  <p class="leading-snug text-gray-600">{{ log.description ?? '-' }}</p>
                </div>
              </li>
              <li
                v-if="!(booking.logs && booking.logs.length)"
                class="rounded-lg border border-dashed border-gray-200 p-4 text-center text-xs text-gray-400"
              >
                Belum ada aktivitas tercatat.
              </li>
            </ul>
          </section>
        </aside>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
