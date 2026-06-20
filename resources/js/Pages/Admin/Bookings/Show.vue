<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
  getBookingActionLabel,
  getBookingStatusClasses,
  getBookingStatusLabel,
  normalizeBookingStatus,
} from '@/Composables/useBookingStatus'

const props = defineProps({
  booking: {
    type: Object,
    required: true,
  },
  approvedConflicts: {
    type: Array,
    default: () => [],
  },
  queuedConflicts: {
    type: Array,
    default: () => [],
  },
})

const approvalForm = useForm({
  status: '',
  notes: '',
})

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

const fullDateFormatter = new Intl.DateTimeFormat('id-ID', {
  timeZone: 'UTC',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
})

const monthYearFormatter = new Intl.DateTimeFormat('id-ID', {
  timeZone: 'UTC',
  month: 'long',
  year: 'numeric',
})

const monthFormatter = new Intl.DateTimeFormat('id-ID', {
  timeZone: 'UTC',
  month: 'long',
})

const scheduleDateTimeParts = (value) => {
  if (!value) return null

  const match = String(value).match(
    /^(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})(?::\d{2})?/,
  )

  if (!match) return null

  return {
    dateKey: `${match[1]}-${match[2]}-${match[3]}`,
    clock: `${match[4]}.${match[5]}`,
  }
}

const toDateKey = (value) => {
  return scheduleDateTimeParts(value)?.dateKey ?? ''
}

const dateFromKey = (dateKey) => new Date(`${dateKey}T00:00:00Z`)

const addDaysToKey = (dateKey, days) => {
  const date = dateFromKey(dateKey)
  date.setUTCDate(date.getUTCDate() + days)
  return date.toISOString().slice(0, 10)
}

const formatDateRange = (startKey, endKey) => {
  const start = dateFromKey(startKey)
  const end = dateFromKey(endKey)

  if (startKey === endKey) return fullDateFormatter.format(start)

  const sameYear = start.getUTCFullYear() === end.getUTCFullYear()
  const sameMonth = sameYear && start.getUTCMonth() === end.getUTCMonth()

  if (sameMonth) {
    return `${start.getUTCDate()}-${end.getUTCDate()} ${monthYearFormatter.format(end)}`
  }

  if (sameYear) {
    return `${start.getUTCDate()} ${monthFormatter.format(start)}-${fullDateFormatter.format(end)}`
  }

  return `${fullDateFormatter.format(start)}-${fullDateFormatter.format(end)}`
}

const summarizeDateKeys = (dateKeys) => {
  const dates = [...new Set(dateKeys)].sort()
  const ranges = []

  for (const dateKey of dates) {
    const latestRange = ranges[ranges.length - 1]

    if (latestRange && addDaysToKey(latestRange.end, 1) === dateKey) {
      latestRange.end = dateKey
    } else {
      ranges.push({ start: dateKey, end: dateKey })
    }
  }

  return ranges.map((range) => formatDateRange(range.start, range.end)).join(', ')
}

const groupedRoomSchedules = computed(() => {
  const rooms = new Map()

  for (const schedule of props.booking.room_schedules ?? []) {
    const roomKey = String(schedule.room_id ?? schedule.room?.id ?? `schedule-${schedule.id}`)
    const startParts = scheduleDateTimeParts(schedule.start_time)
    const endParts = scheduleDateTimeParts(schedule.end_time)
    const startDate = schedule.display_start_date ?? startParts?.dateKey
    const endDate = schedule.display_end_date ?? endParts?.dateKey
    const startClock = (schedule.display_start_time ?? startParts?.clock)?.replace(':', '.')
    const endClock = (schedule.display_end_time ?? endParts?.clock)?.replace(':', '.')

    if (!startDate || !endDate || !startClock || !endClock) continue
    const dayOffset = Math.round(
      (dateFromKey(endDate).getTime() - dateFromKey(startDate).getTime()) / 86400000,
    )
    const timeKey = `${startClock}|${endClock}|${dayOffset}`

    if (!rooms.has(roomKey)) {
      rooms.set(roomKey, {
        id: roomKey,
        name: schedule.room?.name ?? '-',
        location: [
          schedule.room?.building?.name,
          schedule.room?.building?.campus?.name,
        ].filter(Boolean).join(' · ') || '-',
        scheduleCount: 0,
        timeGroups: new Map(),
      })
    }

    const room = rooms.get(roomKey)
    room.scheduleCount += 1

    if (!room.timeGroups.has(timeKey)) {
      room.timeGroups.set(timeKey, {
        key: timeKey,
        startClock,
        endClock,
        dayOffset,
        dates: [],
      })
    }

    room.timeGroups.get(timeKey).dates.push(startDate)
  }

  return [...rooms.values()].map((room) => ({
    ...room,
    timeGroups: [...room.timeGroups.values()]
      .map((timeGroup) => {
        const dates = [...new Set(timeGroup.dates)].sort()

        return {
          ...timeGroup,
          dates,
          dateSummary: summarizeDateKeys(dates),
        }
      })
      .sort((first, second) => {
        const dateComparison = first.dates[0].localeCompare(second.dates[0])
        return dateComparison || first.startClock.localeCompare(second.startClock)
      }),
  }))
})

const scheduleOverview = computed(() => {
  const schedules = props.booking.room_schedules ?? []

  return {
    roomCount: groupedRoomSchedules.value.length,
    scheduleCount: schedules.length,
    dateSummary: summarizeDateKeys(schedules.map((schedule) => toDateKey(schedule.start_time))),
  }
})

const normalizedStatus = computed(() => normalizeBookingStatus(props.booking.status))
const isWaiting = computed(() => normalizedStatus.value === 'waiting')
const canCancel = computed(() => normalizedStatus.value === 'approved')
const actionsLocked = computed(() => !isWaiting.value && !canCancel.value)
const hasApprovedConflict = computed(() => props.approvedConflicts.length > 0)

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
    <Head title="Detail Persetujuan Peminjaman" />

    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div class="text-sm text-gray-500 dark:text-slate-400">Persetujuan Peminjaman Ruangan</div>
          <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">{{ booking.title }}</h1>
        </div>
        <Link
          :href="route('admin.bookings.index')"
          class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700 sm:w-auto"
        >
          Kembali ke Daftar
        </Link>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
          <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <header class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 dark:border-slate-700 sm:flex-row sm:items-center sm:justify-between sm:px-6">
              <div class="space-y-1">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Detail Pengajuan</h2>
                <p class="text-sm text-gray-500 dark:text-slate-400">Informasi lengkap mengenai permintaan yang diajukan pengguna.</p>
              </div>
              <span
                class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
                :class="getBookingStatusClasses(normalizedStatus)"
              >
                {{ getBookingStatusLabel(normalizedStatus) || booking.status }}
              </span>
            </header>
            <div class="grid gap-6 px-5 py-5 sm:grid-cols-2 sm:px-6">
              <div class="space-y-3">
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Pemohon</div>
                  <div class="text-sm font-medium text-gray-800 dark:text-white">{{ booking.user?.name ?? '-' }}</div>
                  <div class="text-xs text-gray-500 dark:text-slate-400">{{ booking.user?.email ?? '-' }}</div>
                  <div class="text-xs text-gray-500 dark:text-slate-400">Telp: {{ booking.user?.phone ?? '-' }}</div>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Deskripsi</div>
                  <p class="text-sm leading-relaxed text-gray-700 dark:text-slate-300">{{ booking.description || 'Tidak ada deskripsi.' }}</p>
                </div>
              </div>
              <div class="space-y-3 sm:col-span-2">
                <div class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Ruangan dan Jadwal</div>
                <div
                  v-if="scheduleOverview.scheduleCount"
                  class="flex flex-wrap gap-x-5 gap-y-2 rounded-xl border border-blue-100 bg-blue-50/70 px-4 py-3 text-sm dark:border-blue-900/60 dark:bg-blue-950/30"
                >
                  <span class="font-semibold text-blue-900 dark:text-blue-200">
                    {{ scheduleOverview.roomCount }} ruangan
                  </span>
                  <span class="text-blue-700 dark:text-blue-300">
                    {{ scheduleOverview.scheduleCount }} jadwal
                  </span>
                  <span class="text-blue-700 dark:text-blue-300">
                    {{ scheduleOverview.dateSummary }}
                  </span>
                </div>

                <div v-if="groupedRoomSchedules.length" class="grid gap-4">
                  <article
                    v-for="room in groupedRoomSchedules"
                    :key="room.id"
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800"
                  >
                    <header class="flex flex-col gap-1 border-b border-slate-100 bg-slate-50/80 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/30 sm:flex-row sm:items-center sm:justify-between">
                      <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white">{{ room.name }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ room.location }}</p>
                      </div>
                      <span class="text-xs font-medium text-slate-500 dark:text-slate-400">
                        {{ room.scheduleCount }} jadwal
                      </span>
                    </header>

                    <div class="divide-y divide-slate-100 dark:divide-slate-700">
                      <div
                        v-for="timeGroup in room.timeGroups"
                        :key="timeGroup.key"
                        class="grid gap-1 px-4 py-3 sm:grid-cols-[10rem_1fr] sm:gap-4"
                      >
                        <div class="font-semibold text-slate-800 dark:text-slate-200">
                          {{ timeGroup.startClock }}-{{ timeGroup.endClock }} WIB
                          <span v-if="timeGroup.dayOffset > 0" class="block text-xs font-normal text-slate-500 dark:text-slate-400">
                            Selesai {{ timeGroup.dayOffset === 1 ? 'hari berikutnya' : `${timeGroup.dayOffset} hari kemudian` }}
                          </span>
                        </div>
                        <div>
                          <p class="text-sm text-slate-700 dark:text-slate-300">{{ timeGroup.dateSummary }}</p>
                          <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
                            {{ timeGroup.dates.length }} tanggal penggunaan
                          </p>
                        </div>
                      </div>
                    </div>
                  </article>
                </div>

                <div
                  v-else
                  class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
                >
                  Belum ada jadwal ruangan.
                </div>
              </div>
            </div>
          </section>

          <section
            v-if="approvedConflicts.length || queuedConflicts.length"
            class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800"
          >
            <header class="border-b border-gray-100 px-5 py-4 dark:border-slate-700 sm:px-6">
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Pengajuan Bentrok</h2>
              <p class="text-sm text-gray-500 dark:text-slate-400">
                Bandingkan pengajuan lain yang memakai ruangan pada waktu yang sama.
              </p>
            </header>
            <div class="space-y-4 px-5 py-5 sm:px-6">
              <div
                v-if="approvedConflicts.length"
                class="rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-900 dark:bg-rose-950/30"
              >
                <p class="font-semibold text-rose-800 dark:text-rose-300">
                  Jadwal telah dikunci oleh {{ approvedConflicts.length }} booking yang disetujui.
                </p>
                <p class="mt-1 text-xs text-rose-700 dark:text-rose-400">
                  Booking ini tidak dapat disetujui sebelum jadwalnya direvisi.
                </p>
              </div>

              <div
                v-if="queuedConflicts.length"
                class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/30"
              >
                <p class="font-semibold text-amber-800 dark:text-amber-300">
                  {{ queuedConflicts.length }} pengajuan lain masih menunggu keputusan.
                </p>
                <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">
                  Menyetujui booking ini akan mengunci jadwal bagi pengajuan lainnya.
                </p>
              </div>

              <div class="divide-y divide-slate-100 rounded-xl border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                <div
                  v-for="conflict in [...approvedConflicts, ...queuedConflicts]"
                  :key="conflict.id"
                  class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                      <p class="font-semibold text-slate-900 dark:text-white">{{ conflict.title }}</p>
                      <span
                        class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                        :class="getBookingStatusClasses(normalizeBookingStatus(conflict.status))"
                      >
                        {{ getBookingStatusLabel(normalizeBookingStatus(conflict.status)) }}
                      </span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                      {{ conflict.user?.name ?? '-' }} · {{ conflict.schedule_short_summary }}
                    </p>
                    <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
                      Diajukan {{ formatDateTime(conflict.created_at) }}
                    </p>
                  </div>
                  <Link
                    :href="route('admin.bookings.show', conflict.id)"
                    class="inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-lg border border-blue-300 px-3 py-2 text-xs font-semibold text-blue-700 transition hover:bg-blue-50 dark:border-blue-700 dark:text-blue-300 dark:hover:bg-blue-950/40"
                  >
                    Lihat Detail
                  </Link>
                </div>
              </div>
            </div>
          </section>

          <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <header class="border-b border-gray-100 px-5 py-4 dark:border-slate-700 sm:px-6">
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Lampiran</h2>
              <p class="text-sm text-gray-500 dark:text-slate-400">Dokumen pendukung dari pemohon.</p>
            </header>
            <div class="px-5 py-5 text-sm text-gray-600 dark:text-slate-300 sm:px-6">
              <template v-if="booking.attachment">
                <a
                  :href="route('bookings.attachment', booking.id)"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex items-center rounded-md border border-blue-200 px-4 py-2 text-sm font-semibold text-blue-700 transition hover:border-blue-300 hover:text-blue-800 dark:border-blue-800 dark:text-blue-300 dark:hover:border-blue-700 dark:hover:text-blue-200"
                >
                  Lihat Lampiran
                </a>
              </template>
              <p v-else class="text-gray-400 dark:text-slate-500">Tidak ada lampiran yang diunggah.</p>
            </div>
          </section>

          <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <header class="border-b border-gray-100 px-5 py-4 dark:border-slate-700 sm:px-6">
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Surat Peminjaman</h2>
              <p class="text-sm text-gray-500 dark:text-slate-400">Unduh surat persetujuan peminjaman ruangan.</p>
            </header>
            <div class="px-5 py-5 text-sm text-gray-600 dark:text-slate-300 sm:px-6">
              <template v-if="normalizedStatus === 'approved'">
                <a
                  :href="route('bookings.letter', booking.id)"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 dark:bg-emerald-700 dark:hover:bg-emerald-800"
                >
                  Download Surat Peminjaman Ruangan
                </a>
              </template>
              <p v-else-if="normalizedStatus === 'cancelled'" class="text-gray-500 dark:text-slate-400">
                Booking telah dibatalkan. Surat peminjaman tidak tersedia.
              </p>
              <p v-else-if="normalizedStatus === 'expired'" class="text-orange-600 dark:text-orange-400">
                Permintaan kedaluwarsa karena hari peminjaman terakhir telah berakhir.
              </p>
              <p v-else class="text-gray-400 dark:text-slate-500">Surat peminjaman tersedia setelah booking disetujui.</p>
            </div>
          </section>
        </div>

        <aside class="space-y-6">
          <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <header class="border-b border-gray-100 px-5 py-4 dark:border-slate-700 sm:px-6">
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Tindakan Persetujuan</h2>
              <p class="text-sm text-gray-500 dark:text-slate-400">Setujui atau tolak permintaan booking ruangan ini.</p>
            </header>
            <div class="space-y-4 px-5 py-5 sm:px-6">
              <textarea
                v-model="approvalForm.notes"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:focus:border-blue-500 dark:focus:ring-blue-900/30"
                rows="3"
                :placeholder="canCancel ? 'Catatan (wajib) ketika membatalkan booking' : 'Catatan untuk pemohon (wajib saat revisi atau penolakan)'"
              />
              <p v-if="canCancel" class="text-xs text-slate-500 dark:text-slate-400">
                Cantumkan alasan pembatalan agar pengguna memahami perubahan mendadak.
              </p>
              <p v-if="approvalForm.errors.notes" class="text-xs text-rose-500 dark:text-rose-400">
                {{ approvalForm.errors.notes }}
              </p>

              <div class="flex flex-col gap-2">
                <button
                  type="button"
                  class="inline-flex items-center justify-center rounded-md bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-violet-700 dark:hover:bg-violet-800"
                  :disabled="approvalForm.processing || !isWaiting"
                  @click="submitApproval('needs_revision')"
                >
                  Minta Revisi
                </button>
                <button
                  type="button"
                  class="inline-flex items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-emerald-700 dark:hover:bg-emerald-800"
                  :disabled="approvalForm.processing || !isWaiting || hasApprovedConflict"
                  @click="submitApproval('approved')"
                >
                  Setujui Booking
                </button>
                <button
                  type="button"
                  class="inline-flex items-center justify-center rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-rose-700 dark:hover:bg-rose-800"
                  :disabled="approvalForm.processing || !isWaiting"
                  @click="submitApproval('rejected')"
                >
                  Tolak Booking
                </button>
                <button
                  v-if="canCancel"
                  type="button"
                  class="inline-flex items-center justify-center rounded-md bg-slate-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-600 dark:hover:bg-slate-700"
                  :disabled="approvalForm.processing"
                  @click="submitApproval('cancelled')"
                >
                  Batalkan Booking (Darurat)
                </button>
              </div>
              <p v-if="isWaiting && hasApprovedConflict" class="text-xs font-medium text-rose-600 dark:text-rose-400">
                Tombol persetujuan dinonaktifkan karena jadwal sudah dikunci booking lain.
              </p>
              <p v-if="actionsLocked" class="text-xs text-gray-400 dark:text-slate-500">
                {{ normalizedStatus === 'expired'
                  ? 'Permintaan sudah kedaluwarsa dan tidak dapat diproses.'
                  : normalizedStatus === 'needs_revision'
                    ? 'Menunggu pemohon mengirim revisi.'
                  : 'Status booking sudah final. Tidak dapat melakukan tindakan lanjutan.' }}
              </p>
            </div>
          </section>

          <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <header class="border-b border-gray-100 px-5 py-4 dark:border-slate-700 sm:px-6">
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Riwayat Aktivitas</h2>
              <p class="text-sm text-gray-500 dark:text-slate-400">Jejak persetujuan untuk booking ini.</p>
            </header>
            <ul class="space-y-4 px-5 py-5 sm:px-6">
              <li
                v-for="log in booking.logs ?? []"
                :key="log.id"
                class="flex gap-3 rounded-lg border border-gray-100 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800"
              >
                <div
                  class="mt-1 h-2.5 w-2.5 flex-shrink-0 rounded-full"
                  :class="getBookingStatusClasses(log.action)"
                />
                <div class="min-w-0 flex-1 space-y-1 text-sm text-gray-600 dark:text-slate-300">
                  <div class="flex w-full min-w-0 flex-col gap-1 sm:flex-row sm:items-start sm:gap-3">
                    <span class="min-w-0 break-words font-medium text-gray-800 dark:text-white">{{ log.user?.name ?? 'Sistem' }}</span>
                    <span class="shrink-0 text-xs text-gray-400 sm:ml-auto sm:whitespace-nowrap sm:text-right dark:text-slate-500">{{ formatDateTime(log.created_at) }}</span>
                  </div>
                  <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-slate-500">
                    {{ getBookingActionLabel(log.action) || log.action }}
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
