<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { formatDateTimeToDDMMYY } from '@/Composables/useDateFormatter'

const props = defineProps({
  booking: {
    type: Object,
    required: true,
  },
  latestDecisionLog: {
    type: Object,
    default: null,
  },
})

const statusLabels = {
  waiting: 'Menunggu Persetujuan',
  approved: 'Disetujui',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan',
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

const formatDateTime = (value) => formatDateTimeToDDMMYY(value)

const normalizedStatus = computed(() => normalizeStatus(props.booking?.status))

const decisionStatus = computed(() => normalizeStatus(props.latestDecisionLog?.action))
const hasDecision = computed(() => ['approved', 'rejected', 'cancelled'].includes(decisionStatus.value))

const decisionNote = computed(() => {
  const raw = props.latestDecisionLog?.description ?? ''
  if (!raw) return ''

  const parts = raw.split(' - ')
  if (parts.length <= 1) {
    return ''
  }

  return parts.slice(1).join(' - ').trim()
})

const decisionTimestamp = computed(() => formatDateTime(props.latestDecisionLog?.created_at))

const cancelForm = useForm({})
const canCancelBooking = computed(() => normalizedStatus.value === 'waiting')

const cancelBooking = () => {
  if (!canCancelBooking.value) {
    return
  }

  if (!window.confirm('Yakin ingin membatalkan permintaan booking ini?')) {
    return
  }

  cancelForm.post(route('bookings.cancel', props.booking.id), {
    preserveScroll: true,
  })
}
</script>

<template>
  <AuthenticatedLayout>
    <Head :title="`Detail Booking: ${booking.title}`" />

    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div class="text-sm text-gray-500">Detail Permintaan Booking</div>
          <h1 class="text-2xl font-semibold text-gray-800">{{ booking.title }}</h1>
        </div>
        <div class="flex items-center gap-2">
          <span
            class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
            :class="statusColors[normalizedStatus] ?? 'bg-gray-100 text-gray-600 border-gray-200'"
          >
            {{ statusLabels[normalizedStatus] ?? booking.status }}
          </span>
          <button
            v-if="canCancelBooking"
            type="button"
            class="inline-flex items-center rounded-md border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="cancelForm.processing"
            @click="cancelBooking"
          >
            {{ cancelForm.processing ? 'Membatalkan...' : 'Batalkan Permintaan' }}
          </button>
          <Link
            :href="route('bookings.index')"
            class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
          >
            Kembali
          </Link>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
          <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <header class="flex flex-col gap-2 border-b border-gray-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 class="text-lg font-semibold text-gray-800">Informasi Pengajuan</h2>
                <p class="text-sm text-gray-500">Rincian ruangan dan jadwal yang kamu ajukan.</p>
              </div>
            </header>
            <div class="grid gap-6 px-6 py-5 sm:grid-cols-2">
              <div class="space-y-3">
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500">Deskripsi</div>
                  <p class="text-sm leading-relaxed text-gray-700">
                    {{ booking.description || 'Tidak ada deskripsi tambahan.' }}
                  </p>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500">Lampiran</div>
                  <template v-if="booking.attachment">
                    <a
                      :href="route('bookings.attachment', booking.id)"
                      target="_blank"
                      rel="noopener"
                      class="inline-flex items-center rounded-md border border-blue-200 px-3 py-2 text-xs font-semibold text-blue-700 transition hover:border-blue-300 hover:text-blue-800"
                    >
                      Lihat Lampiran
                    </a>
                  </template>
                  <p v-else class="text-sm text-gray-400">Tidak ada lampiran yang diunggah.</p>
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
              <h2 class="text-lg font-semibold text-gray-800">Surat Peminjaman</h2>
              <p class="text-sm text-gray-500">Unduh surat persetujuan setelah booking disetujui.</p>
            </header>
            <div class="px-6 py-5 text-sm text-gray-600">
              <template v-if="normalizedStatus === 'approved'">
                <a
                  :href="route('bookings.letter', booking.id)"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                >
                  Download Surat Peminjaman
                </a>
              </template>
              <p v-else-if="normalizedStatus === 'cancelled'" class="text-gray-500">
                Booking ini telah dibatalkan sehingga surat peminjaman tidak tersedia.
              </p>
              <p v-else class="text-gray-400">
                Surat peminjaman tersedia setelah permintaan disetujui oleh admin.
              </p>
            </div>
          </section>
        </div>

        <aside class="space-y-6">
          <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <header class="border-b border-gray-100 px-6 py-4">
              <h2 class="text-lg font-semibold text-gray-800">Catatan Persetujuan</h2>
              <p class="text-sm text-gray-500">Catatan dari admin terkait status permintaanmu.</p>
            </header>
            <div class="space-y-3 px-6 py-5 text-sm text-gray-600">
              <template v-if="hasDecision">
                <span
                  class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
                  :class="statusColors[decisionStatus] ?? 'bg-gray-100 text-gray-600 border-gray-200'"
                >
                  {{ statusLabels[decisionStatus] ?? decisionStatus }}
                </span>
                <p v-if="decisionNote" class="leading-relaxed text-gray-700">
                  {{ decisionNote }}
                </p>
                <p v-else class="text-gray-400">Tidak ada catatan tambahan dari admin.</p>
                <p class="text-xs text-gray-400">
                  Diperbarui pada {{ decisionTimestamp }}
                  <span v-if="latestDecisionLog?.user?.name"> oleh {{ latestDecisionLog.user.name }}</span>
                </p>
              </template>
              <template v-else>
                <p class="text-gray-500">
                  Permintaan masih menunggu keputusan admin. Kamu akan melihat catatan di sini setelah disetujui atau ditolak.
                </p>
              </template>
            </div>
          </section>

          <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <header class="border-b border-gray-100 px-6 py-4">
              <h2 class="text-lg font-semibold text-gray-800">Riwayat Aktivitas</h2>
              <p class="text-sm text-gray-500">Jejak proses permintaan booking ini.</p>
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
