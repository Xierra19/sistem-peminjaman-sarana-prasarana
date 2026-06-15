<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  itemBorrowing: {
    type: Object,
    required: true,
  },
  items: {
    type: Array,
    default: () => [],
  },
  formItems: {
    type: Array,
    default: () => [],
  },
  minimumBorrowDate: {
    type: String,
    required: true,
  },
})

const form = useForm({
  title: props.itemBorrowing.title || '',
  description: props.itemBorrowing.description || '',
  attachment: null,
  items: props.formItems || [],
})

const itemRows = ref(props.formItems.length || 1)
const availabilities = ref({})
const availabilityMessages = ref({})
const isAvailabilityLoading = ref({})

const generateTimeSlots = (startHour = 7, endHour = 21, stepMinutes = 30) => {
  const slots = []

  for (let hour = startHour; hour <= endHour; hour++) {
    for (let minute = 0; minute < 60; minute += stepMinutes) {
      if (hour === endHour && minute > 0) break

      slots.push(`${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`)
    }
  }

  return slots
}

const timeSlots = generateTimeSlots()

const borrowTimeSlots = (row) => {
  if (!row.borrow_time || timeSlots.includes(row.borrow_time)) return timeSlots
  return [...timeSlots, row.borrow_time].sort()
}

const availableReturnTimeSlots = (row) => {
  const slots = !row.return_time || timeSlots.includes(row.return_time)
    ? timeSlots
    : [...timeSlots, row.return_time].sort()

  if (!row.borrow_date || !row.return_date || !row.borrow_time) return slots
  if (row.return_date > row.borrow_date) return slots

  return slots.filter((slot) => slot > row.borrow_time)
}

const onBorrowTimeChange = (index) => {
  const row = form.items[index]

  if (
    row.return_date === row.borrow_date &&
    row.return_time &&
    row.return_time <= row.borrow_time
  ) {
    row.return_time = ''
  }
}

const minBorrowDate = computed(() => {
  return props.minimumBorrowDate
})

const addItemRow = () => {
  form.items.push({
    item_id: '',
    quantity: 1,
    borrow_date: '',
    borrow_time: '',
    return_date: '',
    return_time: '',
  })
  itemRows.value += 1
}

const removeItemRow = (index) => {
  form.items.splice(index, 1)
  itemRows.value -= 1
  delete availabilities.value[index]
  delete availabilityMessages.value[index]
  delete isAvailabilityLoading.value[index]
}

const getSelectedItem = (index) => {
  return props.items.find(item => String(item.id) === String(form.items[index]?.item_id))
}

const requestedQuantityExceeded = (index) => {
  const row = form.items[index]
  if (!row) return false
  const requested = Number(row.quantity || 0)
  const remaining = Number(availabilities.value[index]?.remaining_quantity || 0)
  return requested > 0 && row.item_id && row.borrow_date && row.borrow_time && row.return_date && row.return_time && requested > remaining
}

const loadAvailability = async (index) => {
  const row = form.items[index]
  const item = getSelectedItem(index)
  
  if (!item || !row?.borrow_date || !row?.borrow_time || !row?.return_date || !row?.return_time) {
    resetAvailability(index)
    return
  }

  isAvailabilityLoading.value[index] = true
  availabilityMessages.value[index] = ''

  try {
    const response = await fetch(
      route('items.availability', {
        item: item.id,
        borrow_date: row.borrow_date,
        borrow_time: row.borrow_time,
        return_date: row.return_date,
        return_time: row.return_time,
        exclude_item_borrowing_item_id: row.id || undefined,
      }),
      { headers: { Accept: 'application/json' } }
    )

    if (!response.ok) throw new Error('Gagal load')

    const data = await response.json()
    availabilities.value[index] = {
      total_quantity: Number(data.total_quantity ?? item.quantity ?? 0),
      reserved_quantity: Number(data.reserved_quantity ?? 0),
      remaining_quantity: Number(data.remaining_quantity ?? item.quantity ?? 0),
      borrowings: Array.isArray(data.borrowings) ? data.borrowings : [],
    }
    
    availabilityMessages.value[index] = data.message || 
      (data.borrowings?.length ? '' : 'Belum ada pengajuan lain.')
  } catch (error) {
    availabilityMessages.value[index] = 'Gagal memuat ketersediaan.'
  } finally {
    isAvailabilityLoading.value[index] = false
  }
}

const resetAvailability = (index) => {
  const item = getSelectedItem(index)
  availabilities.value[index] = {
    total_quantity: item?.quantity ?? 0,
    reserved_quantity: 0,
    remaining_quantity: item?.quantity ?? 0,
    borrowings: [],
  }
  availabilityMessages.value[index] = ''
  isAvailabilityLoading.value[index] = false
}

const handleFileChange = (event) => {
  const [file] = event.target.files || []
  form.attachment = file ?? null
}

const formatDate = (value) => value ? new Date(value).toLocaleDateString('id-ID', {
  day: '2-digit', month: 'short', year: 'numeric'
}) : '-'

const submit = () => {
  form.put(route('item-borrowings.update', props.itemBorrowing.id))
}

// Watchers for each row
watch(() => form.items, (newItems) => {
  newItems.forEach((_, index) => {
    if (newItems[index]?.item_id && newItems[index]?.borrow_date && newItems[index]?.borrow_time && newItems[index]?.return_date && newItems[index]?.return_time) {
      loadAvailability(index)
    }
  })
}, { deep: true })
</script>

<template>
  <AuthenticatedLayout>
    <Head :title="`Edit Request: ${itemBorrowing.title}`" />

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-0">
      <div class="card-surface space-y-6 p-5 sm:p-6">
        <div class="border-b border-slate-200 pb-5">
          <h1 class="text-2xl font-semibold text-slate-900">Edit Request Peminjaman Barang</h1>
          <p class="mt-1 text-sm text-slate-500">
            Revisi berdasarkan feedback admin. Request ID: #{{ itemBorrowing.id }}
          </p>
        </div>

        <form @submit.prevent="submit" class="space-y-8">
          <div class="space-y-2">
            <label class="block text-sm font-medium text-slate-700">Keperluan / Judul <span class="text-rose-500">*</span></label>
            <input
              v-model="form.title"
              type="text"
              class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              placeholder="Contoh: Dokumentasi kegiatan seminar (revisi)"
            />
            <div v-if="form.errors.title" class="text-sm text-red-500">{{ form.errors.title }}</div>
          </div>

          <div class="space-y-2">
            <label class="block text-sm font-medium text-slate-700">Deskripsi</label>
            <textarea
              v-model="form.description"
              rows="3"
              class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              placeholder="Detail revisi atau keperluan..."
            />
            <div v-if="form.errors.description" class="text-sm text-red-500">{{ form.errors.description }}</div>
          </div>

          <!-- Dynamic Items Rows -->
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <label class="text-sm font-medium text-slate-700">
                Daftar Barang <span class="text-rose-500">*</span> ({{ form.items.length }} item)
              </label>
              <button
                type="button"
                @click="addItemRow"
                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"
              >
                + Tambah Barang
              </button>
            </div>

            <div v-for="(row, index) in form.items" :key="index" class="space-y-4 rounded-2xl border border-slate-200 p-6">
              <div class="flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700">
                  {{ index + 1 }}
                </span>
                <button
                  v-if="form.items.length > 1"
                  type="button"
                  @click="removeItemRow(index)"
                  class="ml-auto text-rose-500 hover:text-rose-700"
                >
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <div class="grid gap-6 md:grid-cols-2">
                <div class="space-y-2">
                  <label class="block text-sm font-medium text-slate-700">Barang <span class="text-rose-500">*</span></label>
                  <select
                    v-model="row.item_id"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  >
                    <option value="">Pilih barang</option>
                    <option
                      v-for="item in items"
                      :key="item.id"
                      :value="item.id"
                      :disabled="!item.is_available || item.quantity <= 0"
                    >
                      {{ item.name }} ({{ item.code }}) - Stok {{ item.quantity }}
                      {{ item.is_available && item.quantity > 0 ? '' : '(Tidak tersedia)' }}
                    </option>
                  </select>
                  <div v-if="form.errors[`items.${index}.item_id`]" class="text-sm text-red-500">
                    {{ form.errors[`items.${index}.item_id`] }}
                  </div>
                </div>

                <div class="space-y-2">
                  <label class="block text-sm font-medium text-slate-700">Jumlah <span class="text-rose-500">*</span></label>
                  <input
                    v-model.number="row.quantity"
                    type="number"
                    min="1"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  />
                  <div v-if="requestedQuantityExceeded(index)" class="text-xs text-rose-500">
                    {{ form.errors[`items.${index}.quantity`] || 'Melebihi stok tersedia' }}
                  </div>
                </div>
              </div>

              <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                <div class="space-y-2">
                  <label class="block text-sm font-medium text-slate-700">Tanggal Pinjam <span class="text-rose-500">*</span></label>
                  <input
                    v-model="row.borrow_date"
                    type="date"
                    :min="minBorrowDate"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  />
                  <p class="text-xs text-slate-500">Minimal H-7 berdasarkan tanggal kalender</p>
                  <div v-if="form.errors[`items.${index}.borrow_date`]" class="text-xs text-rose-500">{{ form.errors[`items.${index}.borrow_date`] }}</div>
                </div>
                <div class="space-y-2">
                  <label class="block text-sm font-medium text-slate-700">Jam Pinjam <span class="text-rose-500">*</span></label>
                  <select
                    v-model="row.borrow_time"
                    @change="onBorrowTimeChange(index)"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  >
                    <option value="">Pilih jam pinjam</option>
                    <option v-for="slot in borrowTimeSlots(row)" :key="`edit-borrow-${index}-${slot}`" :value="slot">
                      {{ slot }}
                    </option>
                  </select>
                  <div v-if="form.errors[`items.${index}.borrow_time`]" class="text-xs text-rose-500">{{ form.errors[`items.${index}.borrow_time`] }}</div>
                </div>
                <div class="space-y-2">
                  <label class="block text-sm font-medium text-slate-700">Tanggal Kembali <span class="text-rose-500">*</span></label>
                  <input
                    v-model="row.return_date"
                    type="date"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  />
                  <div v-if="form.errors[`items.${index}.return_date`]" class="text-xs text-rose-500">{{ form.errors[`items.${index}.return_date`] }}</div>
                </div>
                <div class="space-y-2">
                  <label class="block text-sm font-medium text-slate-700">Jam Kembali <span class="text-rose-500">*</span></label>
                  <select
                    v-model="row.return_time"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  >
                    <option value="">Pilih jam kembali</option>
                    <option
                      v-for="slot in availableReturnTimeSlots(row)"
                      :key="`edit-return-${index}-${slot}`"
                      :value="slot"
                    >
                      {{ slot }}
                    </option>
                  </select>
                  <div v-if="form.errors[`items.${index}.return_time`]" class="text-xs text-rose-500">{{ form.errors[`items.${index}.return_time`] }}</div>
                </div>
              </div>

              <!-- Per-item availability (same style) -->
              <div v-if="getSelectedItem(index)" class="space-y-3 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                <div class="flex items-center justify-between">
                  <p class="font-semibold text-slate-700">Detail & Ketersediaan {{ getSelectedItem(index).name }}</p>
                  <span v-if="isAvailabilityLoading[index]" class="text-xs text-blue-500">Memuat...</span>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                  <div class="rounded-xl bg-white p-3 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Stok Total</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900">{{ availabilities[index]?.total_quantity ?? getSelectedItem(index).quantity }}</div>
                  </div>
                  <div class="rounded-xl bg-white p-3 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Dipakai</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900">{{ availabilities[index]?.reserved_quantity ?? 0 }}</div>
                  </div>
                  <div class="rounded-xl bg-white p-3 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tersisa</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900">{{ availabilities[index]?.remaining_quantity ?? getSelectedItem(index).quantity }}</div>
                  </div>
                </div>

                <p v-if="availabilityMessages[index]" class="text-sm text-slate-600">
                  {{ availabilityMessages[index] }}
                </p>
              </div>
            </div>
          </div>

          <!-- Attachment -->
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <label class="block text-sm font-medium text-slate-700">Lampiran Pendukung <span class="text-rose-500">*</span></label>
              <span class="text-xs text-slate-400">PDF, JPG, PNG max 2MB (wajib)</span>
            </div>
            <label class="flex cursor-pointer items-center justify-between rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 transition hover:border-blue-400 hover:bg-blue-50/40">
              <div>
                <p class="text-sm font-medium text-slate-700">
                  {{ form.attachment ? form.attachment.name : 'Pilih file lampiran baru (wajib untuk revisi)' }}
                </p>
                <p v-if="itemBorrowing.attachment" class="text-xs text-slate-500">
                  Saat ini: {{ itemBorrowing.attachment.split('/').pop() }} (akan ditimpa jika upload baru)
                </p>
              </div>
              <span class="rounded-xl bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm">Browse</span>
              <input class="hidden" type="file" accept=".pdf,.jpg,.jpeg,.png" @change="handleFileChange" />
            </label>
            <div v-if="form.errors.attachment" class="text-sm text-red-500">{{ form.errors.attachment }}</div>
          </div>

          <div class="flex flex-col gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
            <Link
              :href="route('item-borrowings.show', itemBorrowing.id)"
              class="inline-flex items-center rounded-2xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
            >
              ← Batal Edit
            </Link>
            <button
              type="submit"
              class="inline-flex items-center rounded-2xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:opacity-60"
              :disabled="form.processing || form.items.some((_, i) => requestedQuantityExceeded(i))"
            >
              {{ form.processing ? 'Menyimpan Revisi...' : 'Update Request' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
