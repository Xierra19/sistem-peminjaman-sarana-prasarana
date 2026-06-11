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

const props = defineProps({
  itemBorrowing: {
    type: Object,
    required: true,
  },
})

const approvalForm = useForm({
  status: '',
  notes: '',
  signed_letter: null,
})

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
const isWaiting = computed(() => normalizedStatus.value === 'waiting')
const canCancel = computed(() => normalizedStatus.value === 'approved')
const actionsLocked = computed(() => !isWaiting.value && !canCancel.value)
const approvalFileLabel = computed(() => approvalForm.signed_letter?.name ?? 'Upload surat bertandatangan')
const earliestBorrowDate = computed(() => borrowingItems.value[0]?.borrow_date ?? null)
const latestReturnDate = computed(() => borrowingItems.value[borrowingItems.value.length - 1]?.return_date ?? null)
const signedLetterHelpText = computed(() => {
  if (normalizedStatus.value === 'approved') {
    return 'Surat yang tersimpan untuk persetujuan ini.'
  }

  if (normalizedStatus.value === 'rejected') {
    return 'Surat penolakan yang tersimpan untuk permintaan ini.'
  }

  return 'Belum ada surat yang diunggah admin.'
})

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
    forceFormData: true,
    onSuccess: () => {
      approvalForm.reset('notes', 'signed_letter')
    },
  })
}

const onSignedLetterChange = (event) => {
  const [file] = event.target.files || []
  approvalForm.signed_letter = file ?? null
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Detail Persetujuan Peminjaman Barang" />

    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div class="text-sm text-gray-500 dark:text-slate-400">Approval Peminjaman Barang</div>
          <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">{{ itemBorrowing.title }}</h1>
        </div>
        <Link
          :href="route('admin.item-borrowings.index')"
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
                :class="getItemBorrowingStatusClasses(normalizedStatus)"
              >
                {{ getItemBorrowingStatusLabel(normalizedStatus) }}
              </span>
            </header>
            <div class="grid gap-6 px-5 py-5 sm:grid-cols-2 sm:px-6">
              <div class="space-y-3">
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Pemohon</div>
                  <div class="text-sm font-medium text-gray-800 dark:text-white">{{ itemBorrowing.user?.name ?? '-' }}</div>
                  <div class="text-xs text-gray-500 dark:text-slate-400">{{ itemBorrowing.user?.email ?? '-' }}</div>
                  <div class="text-xs text-gray-500 dark:text-slate-400">Telp: {{ itemBorrowing.user?.phone ?? '-' }}</div>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Deskripsi</div>
                  <p class="text-sm leading-relaxed text-gray-700 dark:text-slate-300">{{ itemBorrowing.description || 'Tidak ada deskripsi.' }}</p>
                </div>
              </div>
              <div class="space-y-3">
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Barang</div>
                  <ul class="space-y-2">
                    <li
                      v-for="borrowingItem in borrowingItems"
                      :key="borrowingItem.id"
                      class="rounded-lg border border-gray-100 px-3 py-2 text-sm text-gray-700 dark:border-slate-700 dark:text-slate-300"
                    >
                      <div class="font-semibold text-gray-900 dark:text-white">{{ borrowingItem.item?.name ?? '-' }}</div>
                      <div class="mt-1 text-xs text-gray-600 dark:text-slate-300">
                        Jumlah {{ borrowingItem.quantity }} •
                        {{ formatDateTime(borrowingItem.borrow_date) }} s/d {{ formatDateTime(borrowingItem.return_date) }}
                      </div>
                    </li>
                  </ul>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Periode</div>
                  <p class="text-sm text-gray-700 dark:text-slate-300">Pinjam: <span class="font-semibold text-gray-900 dark:text-white">{{ formatDateTime(earliestBorrowDate) }}</span></p>
                  <p class="text-sm text-gray-700 dark:text-slate-300">Kembali: <span class="font-semibold text-gray-900 dark:text-white">{{ formatDateTime(latestReturnDate) }}</span></p>
                  <p class="text-sm text-gray-700 dark:text-slate-300">Jenis barang: <span class="font-semibold text-gray-900 dark:text-white">{{ borrowingItems.length }}</span></p>
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

          <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <header class="border-b border-gray-100 px-5 py-4 dark:border-slate-700 sm:px-6">
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Surat Admin Sarpras</h2>
              <p class="text-sm text-gray-500 dark:text-slate-400">Surat yang sudah ditandatangani admin untuk persetujuan atau penolakan.</p>
            </header>
            <div class="space-y-3 px-5 py-5 text-sm text-gray-600 dark:text-slate-300 sm:px-6">
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
          <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <header class="border-b border-gray-100 px-5 py-4 dark:border-slate-700 sm:px-6">
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Tindakan Persetujuan</h2>
              <p class="text-sm text-gray-500 dark:text-slate-400">Setujui, tolak, atau batalkan peminjaman.</p>
            </header>
            <div class="space-y-4 px-5 py-5 sm:px-6">
              <textarea
                v-model="approvalForm.notes"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:focus:border-blue-500 dark:focus:ring-blue-900/30"
                rows="3"
                :placeholder="canCancel ? 'Catatan (wajib) untuk pembatalan' : 'Catatan (opsional) untuk pemohon'"
              />
              <p v-if="approvalForm.errors.notes" class="text-xs text-rose-500">{{ approvalForm.errors.notes }}</p>

              <div v-if="itemBorrowing.signed_letter" class="space-y-2 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 dark:border-emerald-800 dark:bg-emerald-900/30">
                <div class="text-xs font-semibold uppercase text-emerald-700 dark:text-emerald-300">Surat tersimpan</div>
                <a
                  :href="route('item-borrowings.signed-letter', itemBorrowing.id)"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex items-center rounded-md border border-emerald-200 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:border-emerald-300 hover:text-emerald-800 dark:border-emerald-800 dark:text-emerald-300 dark:hover:border-emerald-700 dark:hover:text-emerald-200"
                >
                  Lihat Surat Ditandatangani
                </a>
                <p class="text-xs text-emerald-700/80 dark:text-emerald-300/80">
                  {{ signedLetterHelpText }}
                  <span v-if="itemBorrowing.signed_letter_uploaded_at">
                    Diunggah {{ formatDateTime(itemBorrowing.signed_letter_uploaded_at) }}.
                  </span>
                </p>
              </div>

              <div v-if="isWaiting" class="space-y-2">
                <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Surat bertandatangan admin</label>
                <label
                  class="flex cursor-pointer items-center justify-between rounded-lg border border-dashed border-gray-300 px-3 py-3 text-sm text-gray-600 transition hover:border-blue-300 hover:bg-blue-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-blue-700 dark:hover:bg-blue-900/30"
                >
                  <span class="truncate pr-3">{{ approvalFileLabel }}</span>
                  <span class="rounded-md bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-slate-600 dark:text-slate-200">Pilih File</span>
                  <input
                    type="file"
                    class="hidden"
                    accept=".pdf,.jpg,.jpeg,.png"
                    @change="onSignedLetterChange"
                  >
                </label>
                <p class="text-xs text-gray-400">Wajib saat setujui, opsional saat tolak. Format PDF/JPG/JPEG/PNG, maksimal 2MB.</p>
                <p v-if="approvalForm.errors.signed_letter" class="text-xs text-rose-500">{{ approvalForm.errors.signed_letter }}</p>
              </div>

              <div class="flex flex-col gap-2">
                <button
                  v-if="isWaiting"
                  type="button"
                  class="inline-flex items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="approvalForm.processing"
                  @click="submitApproval('approved')"
                >
                  Setujui Peminjaman
                </button>
                <button
                  v-if="isWaiting"
                  type="button"
                  class="inline-flex items-center justify-center rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="approvalForm.processing"
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
              </div>
              <p v-if="actionsLocked" class="text-xs text-gray-400">
                Status peminjaman sudah final. Tidak ada tindakan lanjutan yang tersedia.
              </p>
            </div>
          </section>

          <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <header class="border-b border-gray-100 px-5 py-4 dark:border-slate-700 sm:px-6">
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Riwayat Aktivitas</h2>
              <p class="text-sm text-gray-500 dark:text-slate-400">Jejak persetujuan untuk peminjaman ini.</p>
            </header>
            <ul class="space-y-4 px-5 py-5 sm:px-6">
              <li
                v-for="log in itemBorrowing.logs ?? []"
                :key="log.id"
                class="flex gap-3 rounded-lg border border-gray-100 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800"
              >
                <div
                  class="mt-1 h-2.5 w-2.5 flex-shrink-0 rounded-full"
                  :class="getItemBorrowingStatusClasses(log.action)"
                />
                <div class="space-y-1 text-sm text-gray-600 dark:text-slate-300">
                  <div class="flex items-center justify-between gap-3">
                    <span class="font-medium text-gray-800 dark:text-white">{{ log.user?.name ?? 'Sistem' }}</span>
                    <span class="text-xs text-gray-400 dark:text-slate-500">{{ formatDateTime(log.created_at) }}</span>
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
