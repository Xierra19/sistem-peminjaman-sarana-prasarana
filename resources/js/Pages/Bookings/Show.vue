<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
  getBookingStatusClasses,
  getBookingStatusLabel,
  normalizeBookingStatus,
} from '@/Composables/useBookingStatus'
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

const formatDateTime = (value) => formatDateTimeToDDMMYY(value)

const normalizedStatus = computed(() => normalizeBookingStatus(props.booking?.status))

const decisionStatus = computed(() => normalizeBookingStatus(props.latestDecisionLog?.action))
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
    <Head :title="`Detail Peminjaman: ${booking.title}`" />

    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div class="text-sm text-gray-500 dark:text-slate-400">Detail Permintaan Peminjaman</div>
          <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">{{ booking.title }}</h1>
        </div>
        <div class="flex items-center gap-2">
          <span
            class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
            :class="getBookingStatusClasses(normalizedStatus)"
          >
            {{ getBookingStatusLabel(normalizedStatus) || booking.status }}
          </span>
          <button
            v-if="canCancelBooking"
            type="button"
            class="inline-flex items-center rounded-md border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-rose-800 dark:text-rose-400 dark:hover:border-rose-700 dark:hover:bg-rose-900/30"
            :disabled="cancelForm.processing"
            @click="cancelBooking"
          >
            {{ cancelForm.processing ? 'Membatalkan...' : 'Batalkan Permintaan' }}
          </button>
          <Link
            :href="route('bookings.index')"
            class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
          >
            Kembali
          </Link>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
          <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <header class="flex flex-col gap-2 border-b border-gray-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700">
              <div>
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Informasi Pengajuan</h2>
                <p class="text-sm text-gray-500 dark:text-slate-400">Rincian ruangan dan jadwal yang kamu ajukan.</p>
              </div>
            </header>
            <div class="grid gap-6 px-6 py-5 sm:grid-cols-2">
              <div class="space-y-3">
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Deskripsi</div>
                  <p class="text-sm leading-relaxed text-gray-700 dark:text-slate-300">
                    {{ booking.description || 'Tidak ada deskripsi tambahan.' }}
                  </p>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Lampiran</div>
                  <template v-if="booking.attachment">
                    <a
                      :href="route('bookings.attachment', booking.id)"
                      target="_blank"
                      rel="noopener"
                      class="inline-flex items-center rounded-md border border-blue-200 px-3 py-2 text-xs font-semibold text-blue-700 transition hover:border-blue-300 hover:text-blue-800 dark:border-blue-800 dark:text-blue-300 dark:hover:border-blue-700 dark:hover:text-blue-200"
                    >
                      Lihat Lampiran
                    </a>
                  </template>
                  <p v-else class="text-sm text-gray-400 dark:text-slate-500">Tidak ada lampiran yang diunggah.</p>
                </div>
              </div>
              <div class="space-y-3">
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Jadwal</div>
                  <p class="text-sm text-gray-700 dark:text-slate-300">
                    Jenis: <span class="font-semibold text-gray-900 dark:text-white">{{ booking.schedule_mode_label }}</span>
                  </p>
                  <p class="text-sm text-gray-700 dark:text-slate-300">
                    Detail: <span class="font-semibold text-gray-900 dark:text-white">{{ booking.schedule_summary }}</span>
                  </p>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Lokasi</div>
                  <p class="text-sm text-gray-700 dark:text-slate-300">
                    Ruangan:
                    <span class="font-semibold text-gray-900 dark:text-white">{{ booking.room?.name ?? '-' }}</span>
                  </p>
                  <p class="text-sm text-gray-700 dark:text-slate-300">Gedung: {{ booking.room?.building?.name ?? '-' }}</p>
                  <p class="text-sm text-gray-700 dark:text-slate-300">Kampus: {{ booking.room?.building?.campus?.name ?? '-' }}</p>
                </div>
              </div>
            </div>
          </section>

          <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <header class="border-b border-gray-100 px-6 py-4 dark:border-slate-700">
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Surat Peminjaman</h2>
              <p class="text-sm text-gray-500 dark:text-slate-400">Unduh surat persetujuan setelah peminjaman disetujui.</p>
            </header>
            <div class="px-6 py-5 text-sm text-gray-600 dark:text-slate-300">
              <template v-if="normalizedStatus === 'approved'">
                <a
                  :href="route('bookings.letter', booking.id)"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 dark:bg-emerald-700 dark:hover:bg-emerald-800"
                >
                  Download Surat Peminjaman
                </a>
              </template>
              <p v-else-if="normalizedStatus === 'cancelled'" class="text-gray-500 dark:text-slate-400">
                Peminjaman ini telah dibatalkan sehingga surat peminjaman tidak tersedia.
              </p>
              <p v-else class="text-gray-400 dark:text-slate-500">
                Surat peminjaman tersedia setelah permintaan disetujui oleh admin.
              </p>
            </div>
          </section>
        </div>

        <aside class="space-y-6">
          <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <header class="border-b border-gray-100 px-6 py-4 dark:border-slate-700">
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Catatan Persetujuan</h2>
              <p class="text-sm text-gray-500 dark:text-slate-400">Catatan dari admin terkait status permintaanmu.</p>
            </header>
            <div class="space-y-3 px-6 py-5 text-sm text-gray-600 dark:text-slate-300">
              <template v-if="hasDecision">
                <span
                  class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
                  :class="getBookingStatusClasses(decisionStatus)"
                >
                  {{ getBookingStatusLabel(decisionStatus) }}
                </span>
                <p v-if="decisionNote" class="leading-relaxed text-gray-700 dark:text-slate-300">
                  {{ decisionNote }}
                </p>
                <p v-else class="text-gray-400 dark:text-slate-500">Tidak ada catatan tambahan dari admin.</p>
                <p class="text-xs text-gray-400 dark:text-slate-500">
                  Diperbarui pada {{ decisionTimestamp }}
                  <span v-if="latestDecisionLog?.user?.name"> oleh {{ latestDecisionLog.user.name }}</span>
                </p>
              </template>
              <template v-else>
                <p class="text-gray-500 dark:text-slate-400">
                  Permintaan masih menunggu keputusan admin. Kamu akan melihat catatan di sini setelah disetujui atau ditolak.
                </p>
              </template>
            </div>
          </section>

          <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <header class="border-b border-gray-100 px-6 py-4 dark:border-slate-700">
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Riwayat Aktivitas</h2>
              <p class="text-sm text-gray-500 dark:text-slate-400">Jejak proses permintaan peminjaman ini.</p>
            </header>
            <ul class="space-y-4 px-6 py-5">
              <li
                v-for="log in booking.logs ?? []"
                :key="log.id"
                class="flex gap-3 rounded-lg border border-gray-100 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800"
              >
                <div
                  class="mt-1 h-2.5 w-2.5 flex-shrink-0 rounded-full"
                  :class="getBookingStatusClasses(log.action)"
                />
                <div class="space-y-1 text-sm text-gray-600 dark:text-slate-300">
                  <div class="flex items-center justify-between gap-3">
                    <span class="font-medium text-gray-800 dark:text-white">{{ log.user?.name ?? 'Sistem' }}</span>
                    <span class="text-xs text-gray-400 dark:text-slate-500">{{ formatDateTime(log.created_at) }}</span>
                  </div>
                  <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-slate-500">
                    {{ getBookingStatusLabel(log.action) || log.action }}
                  </p>
                  <p class="leading-snug text-gray-600 dark:text-slate-300">{{ log.description ?? '-' }}</p>
                </div>
              </li>
              <li
                v-if="!(booking.logs && booking.logs.length)"
                class="rounded-lg border border-dashed border-gray-200 p-4 text-center text-xs text-gray-400 dark:border-slate-700 dark:text-slate-500"
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
