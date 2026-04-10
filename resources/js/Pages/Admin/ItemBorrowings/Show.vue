<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  itemBorrowing: {
    type: Object,
    required: true,
  },
})

const approvalForm = useForm({
  status: '',
  notes: '',
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
}

const normalizedStatus = computed(() =>
  props.itemBorrowing.status === 'requested' ? 'waiting' : props.itemBorrowing.status,
)
const isWaiting = computed(() => normalizedStatus.value === 'waiting')
const canCancel = computed(() => normalizedStatus.value === 'approved')
const canMarkReturned = computed(() => normalizedStatus.value === 'approved')
const actionsLocked = computed(() => !isWaiting.value && !canCancel.value && !canMarkReturned.value)

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

const submitApproval = (status) => {
  approvalForm.status = status
  approvalForm.post(route('admin.item-borrowings.update-status', props.itemBorrowing.id), {
    preserveScroll: true,
    onSuccess: () => {
      approvalForm.reset('notes')
    },
  })
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Detail Approval Peminjaman Barang" />

    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div class="text-sm text-gray-500">Approval Peminjaman Barang</div>
          <h1 class="text-2xl font-semibold text-gray-800">{{ itemBorrowing.title }}</h1>
        </div>
        <Link
          :href="route('admin.item-borrowings.index')"
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
                {{ statusLabels[normalizedStatus] ?? normalizedStatus }}
              </span>
            </header>
            <div class="grid gap-6 px-6 py-5 sm:grid-cols-2">
              <div class="space-y-3">
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500">Pemohon</div>
                  <div class="text-sm font-medium text-gray-800">{{ itemBorrowing.user?.name ?? '-' }}</div>
                  <div class="text-xs text-gray-500">{{ itemBorrowing.user?.email ?? '-' }}</div>
                  <div class="text-xs text-gray-500">Telp: {{ itemBorrowing.user?.phone ?? '-' }}</div>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500">Deskripsi</div>
                  <p class="text-sm leading-relaxed text-gray-700">{{ itemBorrowing.description || 'Tidak ada deskripsi.' }}</p>
                </div>
              </div>
              <div class="space-y-3">
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500">Barang</div>
                  <p class="text-sm text-gray-700">
                    Nama:
                    <span class="font-semibold text-gray-900">{{ itemBorrowing.item?.name ?? '-' }}</span>
                  </p>
                  <p class="text-sm text-gray-700">Kode: {{ itemBorrowing.item?.code ?? '-' }}</p>
                  <p class="text-sm text-gray-700">Kategori: {{ itemBorrowing.item?.category ?? '-' }}</p>
                  <p class="text-sm text-gray-700">Stok total: {{ itemBorrowing.item?.quantity ?? '-' }}</p>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500">Periode</div>
                  <p class="text-sm text-gray-700">Pinjam: <span class="font-semibold text-gray-900">{{ formatDate(itemBorrowing.borrow_date) }}</span></p>
                  <p class="text-sm text-gray-700">Kembali: <span class="font-semibold text-gray-900">{{ formatDate(itemBorrowing.return_date) }}</span></p>
                  <p class="text-sm text-gray-700">Jumlah: <span class="font-semibold text-gray-900">{{ itemBorrowing.quantity }}</span></p>
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
              <h2 class="text-lg font-semibold text-gray-800">Tindakan Persetujuan</h2>
              <p class="text-sm text-gray-500">Setujui, tolak, batalkan, atau tandai telah kembali.</p>
            </header>
            <div class="space-y-4 px-6 py-5">
              <textarea
                v-model="approvalForm.notes"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
                rows="3"
                :placeholder="canCancel ? 'Catatan (wajib) untuk pembatalan' : 'Catatan (opsional) untuk pemohon'"
              />
              <p v-if="approvalForm.errors.notes" class="text-xs text-rose-500">{{ approvalForm.errors.notes }}</p>

              <div class="flex flex-col gap-2">
                <button
                  type="button"
                  class="inline-flex items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="approvalForm.processing || !isWaiting"
                  @click="submitApproval('approved')"
                >
                  Setujui Peminjaman
                </button>
                <button
                  type="button"
                  class="inline-flex items-center justify-center rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="approvalForm.processing || !isWaiting"
                  @click="submitApproval('rejected')"
                >
                  Tolak Peminjaman
                </button>
                <button
                  v-if="canCancel"
                  type="button"
                  class="inline-flex items-center justify-center rounded-md bg-slate-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="approvalForm.processing"
                  @click="submitApproval('cancelled')"
                >
                  Batalkan Peminjaman
                </button>
                <button
                  v-if="canMarkReturned"
                  type="button"
                  class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="approvalForm.processing"
                  @click="submitApproval('returned')"
                >
                  Tandai Sudah Kembali
                </button>
              </div>
              <p v-if="actionsLocked" class="text-xs text-gray-400">
                Status peminjaman sudah final. Tidak ada tindakan lanjutan yang tersedia.
              </p>
            </div>
          </section>

          <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <header class="border-b border-gray-100 px-6 py-4">
              <h2 class="text-lg font-semibold text-gray-800">Riwayat Aktivitas</h2>
              <p class="text-sm text-gray-500">Jejak persetujuan untuk peminjaman ini.</p>
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
                    {{ statusLabels[log.action] ?? log.action }}
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
