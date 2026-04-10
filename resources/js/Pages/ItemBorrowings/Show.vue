<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

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

const statusLabels = {
  requested: 'Menunggu Persetujuan',
  waiting: 'Menunggu Persetujuan',
  approved: 'Disetujui',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan',
  returned: 'Dikembalikan',
}

const statusColors = {
  requested: 'bg-amber-100 text-amber-700 border-amber-200',
  waiting: 'bg-amber-100 text-amber-700 border-amber-200',
  approved: 'bg-emerald-100 text-emerald-700 border-emerald-200',
  rejected: 'bg-rose-100 text-rose-700 border-rose-200',
  cancelled: 'bg-slate-100 text-slate-700 border-slate-200',
  returned: 'bg-blue-100 text-blue-700 border-blue-200',
  requested: 'bg-blue-100 text-blue-700 border-blue-200',
}

const logLabels = {
  requested: 'Diajukan',
  approved: 'Disetujui',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan',
  returned: 'Dikembalikan',
}

const formatDate = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
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

const normalizedStatus = computed(() =>
  props.itemBorrowing.status === 'requested' ? 'waiting' : props.itemBorrowing.status,
)
const decisionStatus = computed(() => props.latestDecisionLog?.action ?? '')
const hasDecision = computed(() =>
  ['approved', 'rejected', 'cancelled', 'returned'].includes(decisionStatus.value),
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
const canCancel = computed(() => normalizedStatus.value === 'waiting')

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
          <div class="text-sm text-gray-500">Detail Permintaan Peminjaman Barang</div>
          <h1 class="text-2xl font-semibold text-gray-800">{{ itemBorrowing.title }}</h1>
        </div>
        <div class="flex items-center gap-2">
          <span
            class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
            :class="statusColors[normalizedStatus] ?? 'bg-gray-100 text-gray-600 border-gray-200'"
          >
            {{ statusLabels[normalizedStatus] ?? itemBorrowing.status }}
          </span>
          <button
            v-if="canCancel"
            type="button"
            class="inline-flex items-center rounded-md border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="cancelForm.processing"
            @click="cancelBorrowing"
          >
            {{ cancelForm.processing ? 'Membatalkan...' : 'Batalkan Permintaan' }}
          </button>
          <Link
            :href="route('item-borrowings.index')"
            class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
          >
            Kembali
          </Link>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
          <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <header class="border-b border-gray-100 px-6 py-4">
              <h2 class="text-lg font-semibold text-gray-800">Informasi Pengajuan</h2>
              <p class="text-sm text-gray-500">Rincian barang dan periode yang kamu ajukan.</p>
            </header>
            <div class="grid gap-6 px-6 py-5 sm:grid-cols-2">
              <div class="space-y-3">
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500">Barang</div>
                  <div class="text-sm font-medium text-gray-800">{{ itemBorrowing.item?.name ?? '-' }}</div>
                  <div class="text-xs text-gray-500">
                    {{ itemBorrowing.item?.code ?? '-' }} • {{ itemBorrowing.item?.category ?? '-' }}
                  </div>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500">Deskripsi</div>
                  <p class="text-sm leading-relaxed text-gray-700">
                    {{ itemBorrowing.description || 'Tidak ada deskripsi tambahan.' }}
                  </p>
                </div>
              </div>
              <div class="space-y-3">
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500">Periode</div>
                  <p class="text-sm text-gray-700">
                    Pinjam: <span class="font-semibold text-gray-900">{{ formatDate(itemBorrowing.borrow_date) }}</span>
                  </p>
                  <p class="text-sm text-gray-700">
                    Kembali: <span class="font-semibold text-gray-900">{{ formatDate(itemBorrowing.return_date) }}</span>
                  </p>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500">Jumlah</div>
                  <p class="text-sm font-semibold text-gray-900">{{ itemBorrowing.quantity }}</p>
                </div>
              </div>
            </div>
          </section>

          <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <header class="border-b border-gray-100 px-6 py-4">
              <h2 class="text-lg font-semibold text-gray-800">Lampiran</h2>
              <p class="text-sm text-gray-500">Dokumen pendukung yang diunggah pemohon.</p>
            </header>
            <div class="px-6 py-5 text-sm text-gray-600">
              <template v-if="itemBorrowing.attachment">
                <a
                  :href="route('item-borrowings.attachment', itemBorrowing.id)"
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
        </div>

        <aside class="space-y-6">
          <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <header class="border-b border-gray-100 px-6 py-4">
              <h2 class="text-lg font-semibold text-gray-800">Catatan Persetujuan</h2>
              <p class="text-sm text-gray-500">Catatan admin terkait status permintaanmu.</p>
            </header>
            <div class="space-y-3 px-6 py-5 text-sm text-gray-600">
              <template v-if="hasDecision">
                <span
                  class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
                  :class="statusColors[decisionStatus] ?? 'bg-gray-100 text-gray-600 border-gray-200'"
                >
                  {{ statusLabels[decisionStatus] ?? decisionStatus }}
                </span>
                <p v-if="decisionNote" class="leading-relaxed text-gray-700">{{ decisionNote }}</p>
                <p v-else class="text-gray-400">Tidak ada catatan tambahan dari admin.</p>
                <p class="text-xs text-gray-400">
                  Diperbarui pada {{ formatDateTime(latestDecisionLog?.created_at) }}
                  <span v-if="latestDecisionLog?.user?.name"> oleh {{ latestDecisionLog.user.name }}</span>
                </p>
              </template>
              <template v-else>
                <p class="text-gray-500">
                  Permintaan masih menunggu keputusan admin. Catatan akan muncul di sini setelah diproses.
                </p>
              </template>
            </div>
          </section>

          <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <header class="border-b border-gray-100 px-6 py-4">
              <h2 class="text-lg font-semibold text-gray-800">Riwayat Aktivitas</h2>
              <p class="text-sm text-gray-500">Jejak proses permintaan peminjaman barang.</p>
            </header>
            <ul class="space-y-4 px-6 py-5">
              <li
                v-for="log in itemBorrowing.logs ?? []"
                :key="log.id"
                class="flex gap-3 rounded-lg border border-gray-100 bg-white p-3 shadow-sm"
              >
                <div
                  class="mt-1 h-2.5 w-2.5 flex-shrink-0 rounded-full"
                  :class="statusColors[log.action] ?? 'bg-gray-300'"
                />
                <div class="space-y-1 text-sm text-gray-600">
                  <div class="flex items-center justify-between gap-3">
                    <span class="font-medium text-gray-800">{{ log.user?.name ?? 'Sistem' }}</span>
                    <span class="text-xs text-gray-400">{{ formatDateTime(log.created_at) }}</span>
                  </div>
                  <p class="text-xs uppercase tracking-wide text-gray-400">
                    {{ logLabels[log.action] ?? log.action }}
                  </p>
                  <p class="leading-snug text-gray-600">{{ log.description ?? '-' }}</p>
                </div>
              </li>
              <li
                v-if="!(itemBorrowing.logs && itemBorrowing.logs.length)"
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
