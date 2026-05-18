
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import flatpickr from 'flatpickr'
import 'flatpickr/dist/flatpickr.css'
import { formatToDDMMYY } from '@/Composables/useDateFormatter'

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
const datePickers = ref({}) // Menyimpan instance flatpickr

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
    borrow_date: '', // Tidak mengisi otomatis agar user memilih sendiri
    return_date: '', // Tidak mengisi otomatis agar user memilih sendiri
  })
  const newIndex = form.items.length - 1
  initFlatpickr(newIndex)
}

const removeItem = (index) => {
  // Hapus instance flatpickr sebelum menghapus item
  if (datePickers.value[index]) {
    datePickers.value[index].borrow?.destroy()
    datePickers.value[index].return?.destroy()
    delete datePickers.value[index]
  }
  
  form.items.splice(index, 1)
  // Clear availability
  delete itemAvailabilities.value[index]
  delete isLoadingAvailability.value[index]
  
  // Re-index datePickers
  const newDatePickers = {}
  Object.keys(datePickers.value).forEach(key => {
    const idx = parseInt(key)
    if (idx > index) {
      newDatePickers[idx - 1] = datePickers.value[idx]
    } else if (idx < index) {
      newDatePickers[idx] = datePickers.value[idx]
    }
  })
  datePickers.value = newDatePickers
}

const getItemById = (itemId) => props.items.find(i => String(i.id) === String(itemId)) || null

// Debounce sederhana untuk mencegah terlalu banyak request
let availabilityTimeouts = {}
const updateAvailability = async (index) => {
  // Clear timeout sebelumnya
  if (availabilityTimeouts[index]) {
    clearTimeout(availabilityTimeouts[index])
  }
  
  availabilityTimeouts[index] = setTimeout(async () => {
    const itemRow = form.items[index]
    if (!itemRow.item_id || !itemRow.borrow_date || !itemRow.return_date) {
      // Reset availability jika tanggal belum lengkap
      delete itemAvailabilities.value[index]
      return
    }

    // Basic date format validation
    const borrowDate = new Date(itemRow.borrow_date)
    const returnDate = new Date(itemRow.return_date)
    // Mengizinkan tanggal kembali sama dengan tanggal pinjam (peminjaman hari yang sama)
    if (isNaN(borrowDate.getTime()) || isNaN(returnDate.getTime()) || returnDate < borrowDate) {
      // Reset availability jika tanggal tidak valid (tanggal kembali harus sama atau setelah tanggal pinjam)
      delete itemAvailabilities.value[index]
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
  }, 300) // Debounce 300ms
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

// Inisialisasi Flatpickr setelah DOM update
const initFlatpickr = (index) => {
  nextTick(() => {
    const borrowInput = document.querySelector(`#borrow_date_${index}`)
    const returnInput = document.querySelector(`#return_date_${index}`)
    
    if (!borrowInput || !returnInput) return
    
    // Destroy existing instances if any
    if (datePickers.value[index]?.borrow) {
      datePickers.value[index].borrow.destroy()
    }
    if (datePickers.value[index]?.return) {
      datePickers.value[index].return.destroy()
    }
    
    const minDate = getMinBorrowDate()
    
    // Inisialisasi Flatpickr untuk tanggal pinjam
    const borrowPicker = flatpickr(borrowInput, {
      dateFormat: 'Y-m-d',      // Nilai untuk backend (ISO)
      altInput: true,           // Tampilkan input alternatif ke pengguna
      altFormat: 'd-m-y',       // Format tampilan DD-MM-YY
      minDate: minDate,
      onChange: (selectedDates, dateStr) => {
        form.items[index].borrow_date = dateStr
        // Update minDate untuk return date
        if (datePickers.value[index]?.return) {
          datePickers.value[index].return.set('minDate', dateStr || minDate)
        }
        updateAvailability(index)
      }
    })
    
    // Inisialisasi Flatpickr untuk tanggal kembali
    const returnPicker = flatpickr(returnInput, {
      dateFormat: 'Y-m-d',      // Nilai untuk backend (ISO)
      altInput: true,           // Tampilkan input alternatif ke pengguna
      altFormat: 'd-m-y',      // Format tampilan DD-MM-YY
      minDate: minDate,
      onChange: (selectedDates, dateStr) => {
        form.items[index].return_date = dateStr
        updateAvailability(index)
      }
    })
    
    datePickers.value[index] = {
      borrow: borrowPicker,
      return: returnPicker
    }
  })
}

const handleFileChange = (e) => {
  const file = e.target.files[0]
  form.attachment = file || null
}

const formatDate = (date) => formatToDDMMYY(date)

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

onMounted(() => {
  addItem() // Start with 1 row
})

onBeforeUnmount(() => {
  // Cleanup semua instance flatpickr
  Object.values(datePickers.value).forEach(pickers => {
    pickers.borrow?.destroy()
    pickers.return?.destroy()
  })
})
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Peminjaman Barang" />

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-0">
      <div class="card-surface space-y-6 p-5 sm:p-6 dark:bg-slate-800 dark:border-slate-700">
        <div class="border-b border-slate-200 pb-5 dark:border-slate-700">
          <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">Permintaan Peminjaman Barang</h1>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Pilih multiple barang untuk satu permintaan. Upload surat <span class="text-rose-500 font-medium">*</span>
          </p>
        </div>

        <div class="grid gap-3 rounded-2xl bg-slate-50 p-4 text-xs text-slate-600 sm:grid-cols-3 dark:bg-slate-700/50 dark:text-slate-300">
          <div class="flex items-start gap-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">1</span>
            <div>
              <p class="font-semibold text-slate-800 dark:text-slate-200">Tambah Barang</p>
              <p>Pilih barang & tentukan jumlah + tanggal.</p>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">2</span>
            <div>
              <p class="font-semibold text-slate-800 dark:text-slate-200">Detail Permintaan</p>
              <p>Judul & deskripsi kegiatan.</p>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">3</span>
            <div>
              <p class="font-semibold text-slate-800 dark:text-slate-200">Upload Surat <span class="text-rose-500">*</span></p>
              <p>Surat /wajib lampiran.</p>
            </div>
          </div>
        </div>

        <form @submit.prevent="submit" class="space-y-8">
            <!-- Title -->
            <div class="space-y-2">
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Judul Kegiatan <span class="text-rose-500">*</span></label>
              <input v-model="form.title" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400" placeholder="Contoh: Rapat koordinasi proyek" />
              <div v-if="form.errors.title" class="text-sm text-red-500">{{ form.errors.title }}</div>
            </div>

          <!-- Multi Items Rows -->
          <div>
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Daftar Barang</h3>
              <button type="button" @click="addItem" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                + Tambah Barang
              </button>
            </div>

            <div v-if="form.items.length === 0" class="text-center py-12 text-slate-500 dark:text-slate-400">
              Belum ada barang. Klik "Tambah Barang" untuk mulai.
            </div>

            <div v-else class="space-y-4">
              <div v-for="(itemRow, index) in form.items" :key="index" class="border border-slate-200 rounded-2xl p-6 space-y-4 hover:border-blue-300 dark:border-slate-600 dark:bg-slate-800">
                <div class="flex items-center justify-between">
                  <h4 class="font-semibold text-slate-900 dark:text-white">Barang #{{ index + 1 }}</h4>
                  <button type="button" @click="removeItem(index)" class="text-rose-500 hover:text-rose-600 text-sm font-medium">Hapus</button>
                </div>

                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                  <!-- Item Select -->
                  <div class="space-y-2">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Barang</label>
                    <select v-model="itemRow.item_id" @change="updateAvailability(index)" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
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
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Jumlah</label>
                    <input 
                      v-model.number="itemRow.quantity" 
                      type="number" 
                      min="1" 
                      @input="updateAvailability(index)"
                      class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white" 
                    />
                  </div>

                  <!-- Borrow Date -->
                  <div class="space-y-2">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Tanggal Mulai</label>
                    <input 
                      :id="`borrow_date_${index}`"
                      type="text" 
                      placeholder="Pilih tanggal mulai"
                      class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 cursor-pointer dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400" 
                      readonly
                    />
                  </div>

                  <!-- Return Date -->
                  <div class="space-y-2">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Tanggal Selesai</label>
                    <input 
                      :id="`return_date_${index}`"
                      type="text" 
                      placeholder="Pilih tanggal selesai"
                      class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 cursor-pointer dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400" 
                      readonly
                    />
                  </div>
                </div>

                <!-- Availability Card (same style) -->
                <div v-if="itemRow.item_id" class="rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-700/50">

                  <div class="flex items-center justify-between mb-3">
                    <p class="font-semibold text-slate-700 dark:text-slate-200">Ketersediaan {{ getItemById(itemRow.item_id)?.name }}</p>
                    <span v-if="isLoadingAvailability[index]" class="text-xs text-blue-500">Memuat...</span>
                  </div>
                  <div v-if="itemAvailabilities[index]" class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl bg-white p-3 shadow-sm dark:bg-slate-600 dark:text-slate-200">
                      <div class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-400">Total</div>
                      <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{{ itemAvailabilities[index].total_quantity }}</div>
                    </div>
                    <div class="rounded-xl bg-white p-3 shadow-sm dark:bg-slate-600 dark:text-slate-200">
                      <div class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-400">Dipakai</div>
                      <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{{ itemAvailabilities[index].reserved_quantity }}</div>
                    </div>
                    <div class="rounded-xl bg-white p-3 shadow-sm dark:bg-slate-600 dark:text-slate-200">
                      <div class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-400">Tersisa</div>
                      <div class="mt-1 text-2xl font-semibold" :class="itemRow.quantity > itemAvailabilities[index].remaining_quantity ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'">
                        {{ itemAvailabilities[index].remaining_quantity }}
                      </div>
                    </div>
                  </div>
                  <p v-else class="text-sm text-slate-500 dark:text-slate-400">Pilih tanggal untuk melihat ketersediaan.</p>
                  <p v-if="itemRow.quantity > (itemAvailabilities[index]?.remaining_quantity || 0)" class="mt-2 text-xs text-rose-500 font-medium dark:text-rose-400">
                    ⚠️ Jumlah melebihi stok tersedia
                  </p>
                </div>
              </div>
            </div>
            <div v-if="formErrors.items" class="text-sm text-red-500 mt-2">{{ formErrors.items }}</div>
          </div>

            <!-- Description -->
            <div class="space-y-2">
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Deskripsi</label>
              <textarea v-model="form.description" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder:text-slate-400" placeholder="Tuliskan detail kegiatan, kebutuhan fasilitas, atau catatan lainnya"></textarea>
              <div v-if="form.errors.description" class="text-sm text-red-500">{{ form.errors.description }}</div>
            </div>

          <!-- Mandatory Attachment * -->
          <div class="space-y-3">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Lampiran Surat <span class="text-rose-500">*</span></label>
            <p class="text-xs text-slate-500 mb-2 dark:text-slate-400">PDF, JPG, PNG (max 2MB) - Wajib untuk verifikasi</p>
            <label class="flex cursor-pointer items-center justify-between rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-6 transition hover:border-blue-400 hover:bg-blue-50/20 dark:border-slate-600 dark:bg-slate-700/50 dark:hover:border-slate-500 dark:hover:bg-slate-600/50">
              <div class="text-left">
                <p class="font-medium text-slate-700 dark:text-slate-200">
                  {{ form.attachment ? form.attachment.name : 'Upload surat (wajib)' }}
                </p>
                <p class="text-xs text-slate-500 mt-1 dark:text-slate-400">Wajib lampiran surat / dokumen resmi</p>
              </div>
              <span class="rounded-xl bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm dark:bg-slate-600 dark:text-slate-200">Pilih File</span>
              <input class="hidden" type="file" accept=".pdf,.jpg,.jpeg,.png" @change="handleFileChange" required />
            </label>
            <div v-if="form.errors.attachment" class="text-sm text-red-500">{{ form.errors.attachment }}</div>
          </div>

          <div class="flex flex-col gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end dark:border-slate-700">
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


