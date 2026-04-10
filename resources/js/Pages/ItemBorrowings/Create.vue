<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
})

const form = useForm({
  item_id: '',
  title: '',
  description: '',
  borrow_date: '',
  return_date: '',
  quantity: 1,
  attachment: null,
})

const availability = ref({
  total_quantity: 0,
  reserved_quantity: 0,
  remaining_quantity: 0,
  borrowings: [],
})
const availabilityMessage = ref('')
const isAvailabilityLoading = ref(false)

const formatDateForInput = (date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const minBorrowDate = computed(() => {
  const date = new Date()
  date.setHours(0, 0, 0, 0)
  date.setDate(date.getDate() + 3)
  return formatDateForInput(date)
})

const minReturnDate = computed(() => form.borrow_date || minBorrowDate.value)
const selectedItem = computed(() =>
  (props.items ?? []).find((item) => String(item.id) === String(form.item_id)),
)

const requestedQuantityExceeded = computed(() => {
  const requested = Number(form.quantity || 0)
  const remaining = Number(availability.value.remaining_quantity || 0)
  return requested > 0 && form.item_id && form.borrow_date && form.return_date && requested > remaining
})

watch(
  () => form.borrow_date,
  (value) => {
    if (!value) {
      form.return_date = ''
      resetAvailability()
      return
    }

    if (value < minBorrowDate.value) {
      form.borrow_date = ''
      return
    }

    if (!form.return_date || form.return_date < value) {
      form.return_date = value
    }
  },
)

watch(
  () => form.return_date,
  (value) => {
    if (!value) {
      if (form.borrow_date) {
        form.return_date = form.borrow_date
      }
      return
    }

    if (form.borrow_date && value < form.borrow_date) {
      form.return_date = form.borrow_date
    }
  },
)

watch(
  () => [form.item_id, form.borrow_date, form.return_date],
  () => {
    if (form.item_id && form.borrow_date && form.return_date) {
      loadAvailability()
      return
    }

    resetAvailability()
  },
)

function resetAvailability() {
  availability.value = {
    total_quantity: selectedItem.value?.quantity ?? 0,
    reserved_quantity: 0,
    remaining_quantity: selectedItem.value?.quantity ?? 0,
    borrowings: [],
  }
  availabilityMessage.value = ''
  isAvailabilityLoading.value = false
}

const loadAvailability = async () => {
  const item = selectedItem.value

  if (!item || !form.borrow_date || !form.return_date) {
    resetAvailability()
    return
  }

  if (!item.is_available) {
    availability.value = {
      total_quantity: item.quantity ?? 0,
      reserved_quantity: item.quantity ?? 0,
      remaining_quantity: 0,
      borrowings: [],
    }
    availabilityMessage.value = 'Barang ini sedang tidak tersedia untuk dipilih.'
    isAvailabilityLoading.value = false
    return
  }

  isAvailabilityLoading.value = true
  availabilityMessage.value = ''

  try {
    const response = await fetch(
      route('items.availability', {
        item: item.id,
        borrow_date: form.borrow_date,
        return_date: form.return_date,
      }),
      {
        headers: {
          Accept: 'application/json',
        },
      },
    )

    if (!response.ok) {
      throw new Error('Gagal memuat ketersediaan')
    }

    const data = await response.json()
    availability.value = {
      total_quantity: Number(data.total_quantity ?? item.quantity ?? 0),
      reserved_quantity: Number(data.reserved_quantity ?? 0),
      remaining_quantity: Number(data.remaining_quantity ?? item.quantity ?? 0),
      borrowings: Array.isArray(data.borrowings) ? data.borrowings : [],
    }
    availabilityMessage.value =
      data.available === false && data.message
        ? data.message
        : availability.value.borrowings.length === 0
          ? 'Belum ada pengajuan lain pada rentang tanggal ini.'
          : ''
  } catch (error) {
    availabilityMessage.value = 'Tidak dapat memuat ketersediaan barang. Silakan coba lagi.'
  } finally {
    isAvailabilityLoading.value = false
  }
}

const handleFileChange = (event) => {
  const [file] = event.target.files || []
  form.attachment = file ?? null
}

const formatDate = (value) =>
  value
    ? new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
      })
    : '-'

const statusLabel = (status) => {
  const labels = {
    requested: 'Menunggu',
    waiting: 'Menunggu',
    approved: 'Disetujui',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
    returned: 'Dikembalikan',
  }

  return labels[status] ?? status
}

const submit = () => {
  form.post(route('item-borrowings.store'))
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Peminjaman Barang" />

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-0">
      <div class="card-surface space-y-6 p-5 sm:p-6">
        <div class="border-b border-slate-200 pb-5">
          <h1 class="text-2xl font-semibold text-slate-900">Request Peminjaman Barang</h1>
          <p class="mt-1 text-sm text-slate-500">
            Pilih barang, tentukan tanggal peminjaman, lalu lengkapi detail pengajuan.
          </p>
        </div>

        <div class="grid gap-3 rounded-2xl bg-slate-50 p-4 text-xs text-slate-600 sm:grid-cols-3">
          <div class="flex items-start gap-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">1</span>
            <div>
              <p class="font-semibold text-slate-800">Pilih Barang</p>
              <p>Pilih barang yang tersedia dan jumlah yang dibutuhkan.</p>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">2</span>
            <div>
              <p class="font-semibold text-slate-800">Atur Periode</p>
              <p>Tentukan tanggal pinjam dan tanggal kembali.</p>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">3</span>
            <div>
              <p class="font-semibold text-slate-800">Lengkapi Dokumen</p>
              <p>Isi keperluan peminjaman dan upload lampiran bila perlu.</p>
            </div>
          </div>
        </div>

        <form class="space-y-8" @submit.prevent="submit">
          <div class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2 md:col-span-2">
              <label class="block text-sm font-medium text-slate-700">Barang</label>
              <select
                v-model="form.item_id"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              >
                <option value="" disabled>Pilih barang</option>
                <option
                  v-for="item in props.items"
                  :key="item.id"
                  :value="item.id"
                  :disabled="!item.is_available || Number(item.quantity) <= 0"
                >
                  {{ item.name }} ({{ item.code }}) - Stok {{ item.quantity }}
                  {{ item.is_available && Number(item.quantity) > 0 ? '' : '(Tidak tersedia)' }}
                </option>
              </select>
              <div v-if="form.errors.item_id" class="text-sm text-red-500">{{ form.errors.item_id }}</div>
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-slate-700">Jumlah</label>
              <input
                v-model.number="form.quantity"
                type="number"
                min="1"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <p v-if="requestedQuantityExceeded" class="text-xs text-rose-500">
                Jumlah yang diminta melebihi stok tersedia pada rentang tanggal ini.
              </p>
              <div v-if="form.errors.quantity" class="text-sm text-red-500">{{ form.errors.quantity }}</div>
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-slate-700">Keperluan / Judul</label>
              <input
                v-model="form.title"
                type="text"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                placeholder="Contoh: Dokumentasi kegiatan seminar"
              />
              <div v-if="form.errors.title" class="text-sm text-red-500">{{ form.errors.title }}</div>
            </div>
          </div>

          <div
            v-if="selectedItem"
            class="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-600"
          >
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
              <div>
                <p class="text-sm font-semibold text-slate-700">Detail Barang</p>
                <p class="mt-1">Nama: <span class="font-medium text-slate-800">{{ selectedItem.name }}</span></p>
                <p>Kode: <span class="font-medium text-slate-800">{{ selectedItem.code }}</span></p>
                <p>Kategori: <span class="font-medium text-slate-800">{{ selectedItem.category }}</span></p>
                <p>Stok total: <span class="font-medium text-slate-800">{{ selectedItem.quantity }}</span></p>
              </div>
              <div>
                <span
                  class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium"
                  :class="selectedItem.is_available
                    ? 'border-emerald-200 bg-emerald-100 text-emerald-700'
                    : 'border-rose-200 bg-rose-100 text-rose-700'"
                >
                  {{ selectedItem.is_available ? 'Tersedia' : 'Tidak tersedia' }}
                </span>
              </div>
            </div>
          </div>

          <div class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <label class="block text-sm font-medium text-slate-700">Tanggal Pinjam</label>
              <input
                v-model="form.borrow_date"
                type="date"
                :min="minBorrowDate"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <p class="text-xs text-slate-500">Tanggal minimal peminjaman: {{ minBorrowDate }} (H+3)</p>
              <div v-if="form.errors.borrow_date" class="text-sm text-red-500">{{ form.errors.borrow_date }}</div>
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-medium text-slate-700">Tanggal Kembali</label>
              <input
                v-model="form.return_date"
                type="date"
                :min="minReturnDate"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <p class="text-xs text-slate-500">Tanggal kembali harus sama atau setelah tanggal pinjam.</p>
              <div v-if="form.errors.return_date" class="text-sm text-red-500">{{ form.errors.return_date }}</div>
            </div>
          </div>

          <div class="space-y-3 rounded-2xl border border-slate-100 bg-slate-50 p-4 text-sm">
            <div class="flex items-center justify-between">
              <p class="font-semibold text-slate-700">Ketersediaan Barang pada Rentang Tanggal</p>
              <span v-if="isAvailabilityLoading" class="text-xs text-blue-500">Memuat stok...</span>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
              <div class="rounded-xl bg-white p-3 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Stok Total</div>
                <div class="mt-1 text-2xl font-semibold text-slate-900">{{ availability.total_quantity ?? selectedItem?.quantity ?? 0 }}</div>
              </div>
              <div class="rounded-xl bg-white p-3 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Dipakai</div>
                <div class="mt-1 text-2xl font-semibold text-slate-900">{{ availability.reserved_quantity }}</div>
              </div>
              <div class="rounded-xl bg-white p-3 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tersisa</div>
                <div class="mt-1 text-2xl font-semibold text-slate-900">{{ availability.remaining_quantity ?? selectedItem?.quantity ?? 0 }}</div>
              </div>
            </div>

            <p v-if="availabilityMessage" class="text-sm text-slate-600">{{ availabilityMessage }}</p>
            <ul v-else-if="availability.borrowings.length" class="space-y-2 text-sm text-slate-600">
              <li
                v-for="borrowing in availability.borrowings"
                :key="borrowing.id"
                class="flex flex-col gap-2 rounded-xl bg-white px-4 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between"
              >
                <div>
                  <p class="font-medium text-slate-800">{{ borrowing.title }}</p>
                  <p class="text-xs text-slate-500">
                    {{ formatDate(borrowing.borrow_date) }} - {{ formatDate(borrowing.return_date) }}
                  </p>
                </div>
                <div class="flex items-center gap-3">
                  <span class="text-xs font-semibold text-slate-500">Qty {{ borrowing.quantity }}</span>
                  <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                    {{ statusLabel(borrowing.status) }}
                  </span>
                </div>
              </li>
            </ul>
            <p v-else class="text-sm text-slate-500">
              Pilih barang dan rentang tanggal untuk melihat pemakaian stok.
            </p>
          </div>

          <div class="space-y-2">
            <label class="block text-sm font-medium text-slate-700">Deskripsi</label>
            <textarea
              v-model="form.description"
              rows="4"
              class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              placeholder="Tuliskan detail kegiatan atau kebutuhan peminjaman barang."
            />
            <div v-if="form.errors.description" class="text-sm text-red-500">{{ form.errors.description }}</div>
          </div>

          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <label class="block text-sm font-medium text-slate-700">Lampiran Pendukung</label>
              <span class="text-xs text-slate-400">PDF, JPG, PNG maksimal 2 MB</span>
            </div>
            <label class="flex cursor-pointer items-center justify-between rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 transition hover:border-blue-400 hover:bg-blue-50/40">
              <div>
                <p class="text-sm font-medium text-slate-700">
                  {{ form.attachment ? form.attachment.name : 'Pilih file lampiran' }}
                </p>
                <p class="text-xs text-slate-500">Opsional, gunakan untuk surat tugas atau dokumen pendukung lain.</p>
              </div>
              <span class="rounded-xl bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm">Browse</span>
              <input class="hidden" type="file" accept=".pdf,.jpg,.jpeg,.png" @change="handleFileChange" />
            </label>
            <div v-if="form.errors.attachment" class="text-sm text-red-500">{{ form.errors.attachment }}</div>
          </div>

          <div class="flex flex-col gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
            <button
              type="submit"
              class="inline-flex items-center justify-center rounded-2xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="form.processing || requestedQuantityExceeded"
            >
              {{ form.processing ? 'Menyimpan...' : 'Ajukan Peminjaman Barang' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
