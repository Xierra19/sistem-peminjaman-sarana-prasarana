<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
  getItemBorrowingActionLabel,
  getItemBorrowingStatusClasses,
  getItemBorrowingStatusLabel,
  normalizeItemBorrowingStatus,
} from '@/Composables/useItemBorrowingStatus'
import { formatToDDMMYY, formatDateTimeToDDMMYY } from '@/Composables/useDateFormatter'
import { groupItemBorrowingSchedules } from '@/Composables/useItemBorrowingSchedules'

const props = defineProps({
  itemBorrowing: {
    type: Object,
    required: true,
  },
  latestDecisionLog: {
    type: Object,
    default: null,
  },
})

const formatDate = (value) => formatToDDMMYY(value)

const formatDateTime = (value) => formatDateTimeToDDMMYY(value)

const normalizedStatus = computed(() => normalizeItemBorrowingStatus(props.itemBorrowing.effective_status ?? props.itemBorrowing.status))
const borrowingItems = computed(() => {
  if (props.itemBorrowing.items?.length) {
    return [...props.itemBorrowing.items].sort((left, right) =>
      String(left.borrow_date).localeCompare(String(right.borrow_date)),
    )
  }

  if (props.itemBorrowing.single_item && props.itemBorrowing.borrow_date && props.itemBorrowing.return_date) {
    return [{
      id: `legacy-${props.itemBorrowing.id}`,
      item: props.itemBorrowing.single_item,
      quantity: props.itemBorrowing.quantity,
      borrow_date: props.itemBorrowing.borrow_date,
      return_date: props.itemBorrowing.return_date,
    }]
  }

  return []
})
const scheduleGroups = computed(() => groupItemBorrowingSchedules(borrowingItems.value))
const decisionStatus = computed(() => props.latestDecisionLog?.action ?? '')
const hasDecision = computed(() =>
  ['approved', 'needs_revision', 'rejected', 'cancelled', 'returned'].includes(decisionStatus.value),
)
const earliestBorrowDate = computed(() => borrowingItems.value[0]?.borrow_date ?? null)
const latestReturnDate = computed(() =>
  borrowingItems.value
    .map((item) => item.return_date)
    .filter(Boolean)
    .sort()
    .at(-1) ?? null,
)

const decisionNote = computed(() => {
  const raw = props.latestDecisionLog?.description ?? ''
  if (!raw) return ''

  const parts = raw.split(' - ')
  if (parts.length <= 1) {
    return ''
  }

  return parts.slice(1).join(' - ').trim()
})

const cancelForm = useForm({})
const canCancel = computed(() => ['waiting', 'needs_revision'].includes(normalizedStatus.value))
const canRevise = computed(() => normalizedStatus.value === 'needs_revision')
const canResubmit = computed(() => normalizedStatus.value === 'rejected')

const cancelBorrowing = () => {
  if (!canCancel.value) {
    return
  }

  if (!window.confirm('Yakin ingin membatalkan permintaan peminjaman barang ini?')) {
    return
  }

  cancelForm.post(route('item-borrowings.cancel', props.itemBorrowing.id), {
    preserveScroll: true,
  })
}
</script>

<template>
  <AuthenticatedLayout>
    <Head :title="`Detail Peminjaman Barang: ${itemBorrowing.title}`" />

    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div class="text-sm text-gray-500 dark:text-slate-400">Detail Permintaan Peminjaman Barang</div>
          <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">{{ itemBorrowing.title }}</h1>
        </div>
        <div class="flex items-center gap-2">
          <span
            class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
                  :class="getItemBorrowingStatusClasses(normalizedStatus)"
          >
            {{ getItemBorrowingStatusLabel(normalizedStatus) || itemBorrowing.status }}
          </span>
          <button
            v-if="canCancel"
            type="button"
            class="inline-flex items-center rounded-md border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-rose-800 dark:text-rose-400 dark:hover:border-rose-700 dark:hover:bg-rose-900/30"
            :disabled="cancelForm.processing"
            @click="cancelBorrowing"
          >
            {{ cancelForm.processing ? 'Membatalkan...' : 'Batalkan Permintaan' }}
          </button>
          <Link
            v-if="canRevise"
            :href="route('item-borrowings.edit', itemBorrowing.id)"
            class="inline-flex items-center rounded-md bg-violet-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-violet-700"
          >
            Perbaiki Pengajuan
          </Link>
          <Link
            v-if="canResubmit"
            :href="route('item-borrowings.resubmit', itemBorrowing.id)"
            class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
          >
            Ajukan Ulang
          </Link>
          <Link
            :href="route('item-borrowings.index')"
            class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
          >
            Kembali
          </Link>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
          <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <header class="border-b border-gray-100 px-6 py-4 dark:border-slate-700">
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Informasi Pengajuan</h2>
              <p class="text-sm text-gray-500 dark:text-slate-400">Rincian barang dan periode yang kamu ajukan.</p>
            </header>
            <div class="grid gap-6 px-6 py-5 sm:grid-cols-2">
              <div class="space-y-3">
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Barang</div>
                  <ul class="space-y-2">
                    <li
                      v-for="schedule in scheduleGroups"
                      :key="schedule.key"
                      class="rounded-lg border border-gray-100 px-3 py-2 text-sm text-gray-700 dark:border-slate-700 dark:text-slate-300"
                    >
                      <div class="font-semibold text-gray-900 dark:text-white">{{ schedule.item?.name ?? '-' }}</div>
                      <div class="mt-1 text-xs text-gray-600 dark:text-slate-300">
                        Jumlah {{ schedule.quantity }} •
                        <template v-if="schedule.mode === 'dates'">
                          {{ schedule.dates.map(formatDate).join(', ') }} • {{ schedule.start_time }}–{{ schedule.end_time }} WIB
                        </template>
                        <template v-else>
                          {{ formatDateTime(schedule.borrow_date) }} s/d {{ formatDateTime(schedule.return_date) }}
                        </template>
                      </div>
                    </li>
                  </ul>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Deskripsi</div>
                  <p class="text-sm leading-relaxed text-gray-700 dark:text-slate-300">
                    {{ itemBorrowing.description || 'Tidak ada deskripsi tambahan.' }}
                  </p>
                </div>
              </div>
              <div class="space-y-3">
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Periode</div>
                  <p class="text-sm text-gray-700 dark:text-slate-300">
                    Pinjam: <span class="font-semibold text-gray-900 dark:text-white">{{ formatDateTime(earliestBorrowDate) }}</span>
                  </p>
                  <p class="text-sm text-gray-700 dark:text-slate-300">
                    Kembali: <span class="font-semibold text-gray-900 dark:text-white">{{ formatDateTime(latestReturnDate) }}</span>
                  </p>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Jenis Barang</div>
                  <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ new Set(borrowingItems.map((item) => item.item_id)).size }}</p>
                </div>
              </div>
            </div>
          </section>

          <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <header class="border-b border-gray-100 px-6 py-4 dark:border-slate-700">
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Lampiran</h2>
              <p class="text-sm text-gray-500 dark:text-slate-400">Dokumen pendukung yang diunggah pemohon.</p>
            </header>
            <div class="px-6 py-5 text-sm text-gray-600 dark:text-slate-300">
              <template v-if="itemBorrowing.attachment">
                <a
                  :href="route('item-borrowings.attachment', itemBorrowing.id)"
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

          <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <header class="border-b border-gray-100 px-6 py-4 dark:border-slate-700">
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Surat Admin Sarpras</h2>
              <p class="text-sm text-gray-500 dark:text-slate-400">Surat hasil persetujuan atau penolakan dari admin Sarpras.</p>
            </header>
            <div class="space-y-3 px-6 py-5 text-sm text-gray-600 dark:text-slate-300">
              <template v-if="itemBorrowing.signed_letter">
                <a
                  :href="route('item-borrowings.signed-letter', itemBorrowing.id)"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex items-center rounded-md border border-emerald-200 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:border-emerald-300 hover:text-emerald-800 dark:border-emerald-800 dark:text-emerald-300 dark:hover:border-emerald-700 dark:hover:text-emerald-200"
                >
                  Unduh Surat Ditandatangani
                </a>
                <p class="text-xs text-gray-400 dark:text-slate-500">
                  Diunggah {{ formatDateTime(itemBorrowing.signed_letter_uploaded_at) }}
                </p>
              </template>
              <p v-else class="text-gray-400 dark:text-slate-500">Belum ada surat yang diunggah admin.</p>
            </div>
          </section>
        </div>

        <aside class="space-y-6">
          <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <header class="border-b border-gray-100 px-6 py-4 dark:border-slate-700">
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Catatan Persetujuan</h2>
              <p class="text-sm text-gray-500 dark:text-slate-400">Catatan admin terkait status permintaanmu.</p>
            </header>
            <div class="space-y-3 px-6 py-5 text-sm text-gray-600 dark:text-slate-300">
              <template v-if="hasDecision">
                <span
                  class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
                  :class="getItemBorrowingStatusClasses(decisionStatus)"
                >
                  {{ getItemBorrowingStatusLabel(decisionStatus) }}
                </span>
                <p v-if="decisionNote" class="leading-relaxed text-gray-700 dark:text-slate-300">{{ decisionNote }}</p>
                <p v-else class="text-gray-400 dark:text-slate-500">Tidak ada catatan tambahan dari admin.</p>
                <p class="text-xs text-gray-400 dark:text-slate-500">
                  Diperbarui pada {{ formatDateTime(latestDecisionLog?.created_at) }}
                  <span v-if="latestDecisionLog?.user?.name"> oleh {{ latestDecisionLog.user.name }}</span>
                </p>
              </template>
              <template v-else>
                <p class="text-gray-500 dark:text-slate-400">
                  Permintaan masih menunggu keputusan admin. Catatan akan muncul di sini setelah diproses.
                </p>
              </template>
            </div>
          </section>

          <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <header class="border-b border-gray-100 px-6 py-4 dark:border-slate-700">
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Riwayat Aktivitas</h2>
              <p class="text-sm text-gray-500 dark:text-slate-400">Jejak proses permintaan peminjaman barang.</p>
            </header>
            <ul class="space-y-4 px-6 py-5">
              <li
                v-for="log in itemBorrowing.logs ?? []"
                :key="log.id"
                class="flex gap-3 rounded-lg border border-gray-100 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800"
              >
                <div
                  class="mt-1 h-2.5 w-2.5 flex-shrink-0 rounded-full"
                  :class="getItemBorrowingStatusClasses(log.action)"
                />
                <div class="min-w-0 flex-1 space-y-1 text-sm text-gray-600 dark:text-slate-300">
                  <div class="flex w-full items-start gap-3">
                    <span class="min-w-0 font-medium text-gray-800 dark:text-white">{{ log.user?.name ?? 'Sistem' }}</span>
                    <span class="ml-auto shrink-0 whitespace-nowrap text-right text-xs text-gray-400 dark:text-slate-500">{{ formatDateTime(log.created_at) }}</span>
                  </div>
                  <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-slate-500">
                    {{ getItemBorrowingActionLabel(log.action) || log.action }}
                  </p>
                  <p class="leading-snug text-gray-600 dark:text-slate-300">{{ log.description ?? '-' }}</p>
                </div>
              </li>
              <li
                v-if="!(itemBorrowing.logs && itemBorrowing.logs.length)"
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
