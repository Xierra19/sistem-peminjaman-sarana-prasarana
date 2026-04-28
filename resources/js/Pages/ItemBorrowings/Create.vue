
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref, watch, nextTick } from 'vue'

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
})

const form = useForm({
  title: '',
  description: '',
  attachment: null,
  items: [],
})

const itemAvailabilities = ref({})
const isLoadingAvailability = ref({})
const formErrors = ref({})

const formatDateForInput = (date) => {
  if (!date) return ''
  const d = new Date(date)
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const getMinBorrowDate = () => {
  const date = new Date()
  date.setHours(0, 0, 0, 0)
  date.setDate(date.getDate() + 3)
  return formatDateForInput(date)
}

const addItem = () => {
  form.items.push({
    item_id: '',
    quantity: 1,
    borrow_date: getMinBorrowDate(),
    return_date: getMinBorrowDate(),
  })
  nextTick(() => {
    updateAvailability(form.items.length - 1)
  })
}

const removeItem = (index) => {
  form.items.splice(index, 1)
  // Clear availability
  delete itemAvailabilities.value[index]
}

const getItemById = (itemId) => props.items.find(i => String(i.id) === String(itemId)) || null

const updateAvailability = async (index) => {
  const itemRow = form.items[index]
  if (!itemRow.item_id || !itemRow.borrow_date || !itemRow.return_date) return

  // Basic date format validation
  const borrowDate = new Date(itemRow.borrow_date)
  const returnDate = new Date(itemRow.return_date)
  if (isNaN(borrowDate.getTime()) || isNaN(returnDate.getTime()) || returnDate <= borrowDate) {
    return
  }

  const item = getItemById(itemRow.item_id)
  if (!item) return

  isLoadingAvailability.value[index] = true

  try {
    const response = await fetch(route('items.availability', {
      item: itemRow.item_id,
      borrow_date: itemRow.borrow_date,
      return_date: itemRow.return_date,
    }), {
      headers: { Accept: 'application/json' },
    })

    if (!response.ok) {
      const errorText = await response.text()
      throw new Error(`HTTP ${response.status}: ${errorText}`)
    }

    const data = await response.json()
    itemAvailabilities.value[index] = data
  } catch (error) {
    console.error(`Availability fetch failed for item ${itemRow.item_id} (${itemRow.borrow_date} to ${itemRow.return_date}):`, error)
    itemAvailabilities.value[index] = { 
      remaining_quantity: 0,
      total_quantity: item?.quantity || 0,
      reserved_quantity: 'Error'
    }
  } finally {
    isLoadingAvailability.value[index] = false
  }
}

const isRowValid = (index) => {
  const row = form.items[index]
  const avail = itemAvailabilities.value[index] || {}
  return row.item_id && row.quantity > 0 && row.borrow_date && row.return_date && row.quantity <= (avail.remaining_quantity || 0)
}

const hasAnyRowError = computed(() => form.items.some((_, index) => !isRowValid(index)))

watch(() => form.items, () => {
  formErrors.value = {}
}, { deep: true })

watch(() => form.items, (newItems) => {
  newItems.forEach((row, index) => {
    // Only update if all required fields are set and valid
    if (row.item_id && row.borrow_date && row.return_date) {
      updateAvailability(index)
    }
  })
}, { deep: true })

const handleFileChange = (e) => {
  const file = e.target.files[0]
  form.attachment = file || null
}

const formatDate = (date) => date ? new Date(date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-'

const submit = () => {
  if (form.items.length === 0) {
    formErrors.value.items = 'Minimal 1 barang harus dipilih.'
    return
  }
  if (hasAnyRowError.value) {
    formErrors.value.submit = 'Periksa ketersediaan semua barang.'
    return
  }
  form.clearErrors()
  form.post(route('item-borrowings.store'))
}

addItem() // Start with 1 row
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Peminjaman Barang" />

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-0">
      <div class="card-surface space-y-6 p-5 sm:p-6">
        <div class="border-b border-slate-200 pb-5">
          <h1 class="text-2xl font-semibold text-slate-900">Request Peminjaman Barang</h1>
          <p class="mt-1 text-sm text-slate-500">
            Pilih multiple barang untuk satu request. Upload surat tugas <span class="text-rose-500 font-medium">*</span>
          </p>
        </div>

        <div class="grid gap-3 rounded-2xl bg-slate-50 p-4 text-xs text-slate-600 sm:grid-cols-3">
          <div class="flex items-start gap-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">1</span>
            <div>
              <p class="font-semibold text-slate-800">Tambah Barang</p>
              <p>Pilih barang & tentukan jumlah + tanggal.</p>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">2</span>
            <div>
              <p class="font-semibold text-slate-800">Detail Request</p>
              <p>Judul & deskripsi kegiatan.</p>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">3</span>
            <div>
              <p class="font-semibold text-slate-800">Upload Surat <span class="text-rose-500">*</span></p>
              <p>Surat tugas/wajib lampiran.</p>
            </div>
          </div>
        </div>

        <form @submit.prevent="submit" class="space-y-8">
          <!-- Title -->
          <div class="space-y-2">
            <label class="block text-sm font-medium text-slate-700">Keperluan / Judul <span class="text-rose-500">*</span></label>
            <input v-model="form.title" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="Contoh: Seminar Teknologi 2026" />
            <div v-if="form.errors.title" class="text-sm text-red-500">{{ form.errors.title }}</div>
          </div>

          <!-- Multi Items Rows -->
          <div>
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-semibold text-slate-900">Daftar Barang</h3>
              <button type="button" @click="addItem" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                + Tambah Barang
              </button>
            </div>

            <div v-if="form.items.length === 0" class="text-center py-12 text-slate-500">
              Belum ada barang. Klik "Tambah Barang" untuk mulai.
            </div>

            <div v-else class="space-y-4">
              <div v-for="(itemRow, index) in form.items" :key="index" class="border border-slate-200 rounded-2xl p-6 space-y-4 hover:border-blue-300">
                <div class="flex items-center justify-between">
                  <h4 class="font-semibold text-slate-900">Barang #{{ index + 1 }}</h4>
                  <button type="button" @click="removeItem(index)" class="text-rose-500 hover:text-rose-600 text-sm font-medium">Hapus</button>
                </div>

                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                  <!-- Item Select -->
                  <div class="space-y-2">
                    <label class="block text-sm font-medium text-slate-700">Barang</label>
                    <select v-model="itemRow.item_id" @change="updateAvailability(index)" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                      <option value="">Pilih barang</option>
                      <option
                        v-for="item in props.items"
                        :key="item.id"
                        :value="item.id"
                        :disabled="!item.is_available || form.items.some((row, i) => i !== index && String(row.item_id) === String(item.id))"
                      >
                        {{ item.name }} ({{ item.code }}) - Stok {{ item.quantity }}
                      </option>
                    </select>
                  </div>

                  <!-- Quantity -->
                  <div class="space-y-2">
                    <label class="block text-sm font-medium text-slate-700">Jumlah</label>
                    <input v-model.number="itemRow.quantity" type="number" min="1" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
                  </div>

                  <!-- Borrow Date -->
                  <div class="space-y-2">
                    <label class="block text-sm font-medium text-slate-700">Tanggal Pinjam</label>
                    <input v-model="itemRow.borrow_date" type="date" :min="getMinBorrowDate()" @change="updateAvailability(index)" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
                  </div>

                  <!-- Return Date -->
                  <div class="space-y-2">
                    <label class="block text-sm font-medium text-slate-700">Tanggal Kembali</label>
                    <input v-model="itemRow.return_date" type="date" :min="itemRow.borrow_date || getMinBorrowDate()" @change="updateAvailability(index)" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
                  </div>
                </div>

                <!-- Availability Card (same style) -->
                <div v-if="itemRow.item_id" class="rounded-2xl border border-slate-100 bg-slate-50 p-4">

                  <div class="flex items-center justify-between mb-3">
                    <p class="font-semibold text-slate-700">Ketersediaan {{ getItemById(itemRow.item_id)?.name }}</p>
                    <span v-if="isLoadingAvailability[index]" class="text-xs text-blue-500">Memuat...</span>
                  </div>
                  <div v-if="itemAvailabilities[index]" class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl bg-white p-3 shadow-sm">
                      <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total</div>
                      <div class="mt-1 text-2xl font-semibold text-slate-900">{{ itemAvailabilities[index].total_quantity }}</div>
                    </div>
                    <div class="rounded-xl bg-white p-3 shadow-sm">
                      <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Dipakai</div>
                      <div class="mt-1 text-2xl font-semibold text-slate-900">{{ itemAvailabilities[index].reserved_quantity }}</div>
                    </div>
                    <div class="rounded-xl bg-white p-3 shadow-sm">
                      <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tersisa</div>
                      <div class="mt-1 text-2xl font-semibold text-slate-900" :class="itemRow.quantity > itemAvailabilities[index].remaining_quantity ? 'text-rose-600' : 'text-emerald-600'">
                        {{ itemAvailabilities[index].remaining_quantity }}
                      </div>
                    </div>
                  </div>
                  <p v-else class="text-sm text-slate-500">Pilih tanggal untuk melihat ketersediaan.</p>
                  <p v-if="itemRow.quantity > (itemAvailabilities[index]?.remaining_quantity || 0)" class="mt-2 text-xs text-rose-500 font-medium">
                    ⚠️ Jumlah melebihi stok tersedia
                  </p>
                </div>
              </div>
            </div>
            <div v-if="formErrors.items" class="text-sm text-red-500 mt-2">{{ formErrors.items }}</div>
          </div>

          <!-- Description -->
          <div class="space-y-2">
            <label class="block text-sm font-medium text-slate-700">Deskripsi</label>
            <textarea v-model="form.description" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="Detail kegiatan atau keperluan..."></textarea>
            <div v-if="form.errors.description" class="text-sm text-red-500">{{ form.errors.description }}</div>
          </div>

          <!-- Mandatory Attachment * -->
          <div class="space-y-3">
            <label class="block text-sm font-medium text-slate-700">Lampiran Surat Tugas <span class="text-rose-500">*</span></label>
            <p class="text-xs text-slate-500 mb-2">PDF, JPG, PNG (max 2MB) - Wajib untuk verifikasi</p>
            <label class="flex cursor-pointer items-center justify-between rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-6 transition hover:border-blue-400 hover:bg-blue-50/20">
              <div class="text-left">
                <p class="font-medium text-slate-700">
                  {{ form.attachment ? form.attachment.name : 'Upload surat tugas (wajib)' }}
                </p>
                <p class="text-xs text-slate-500 mt-1">Wajib lampiran surat tugas / dokumen resmi</p>
              </div>
              <span class="rounded-xl bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm">Pilih File</span>
              <input class="hidden" type="file" accept=".pdf,.jpg,.jpeg,.png" @change="handleFileChange" required />
            </label>
            <div v-if="form.errors.attachment" class="text-sm text-red-500">{{ form.errors.attachment }}</div>
          </div>

          <div class="flex flex-col gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
            <button type="submit" :disabled="form.processing || hasAnyRowError || form.items.length === 0 || !form.attachment" class="inline-flex items-center justify-center rounded-2xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed w-full sm:w-auto">
              {{ form.processing ? 'Menyimpan...' : 'Ajukan Peminjaman' }}
            </button>
          </div>
          <p v-if="formErrors.submit" class="text-sm text-red-500 text-center">{{ formErrors.submit }}</p>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
  </template>


