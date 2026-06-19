<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import flatpickr from 'flatpickr'
import 'flatpickr/dist/flatpickr.css'
import { Indonesian } from 'flatpickr/dist/l10n/id'

const props = defineProps({
  items: { type: Array, default: () => [] },
  minimumBorrowDate: { type: String, required: true },
  formMode: { type: String, default: 'create' },
  itemBorrowingId: { type: Number, default: null },
  sourceItemBorrowingId: { type: Number, default: null },
  initialData: { type: Object, default: null },
  revisionNote: { type: String, default: null },
  existingAttachment: { type: String, default: null },
})

let nextCardKey = 1

const createCard = (data = {}) => ({
  _key: nextCardKey++,
  item_id: data.item_id ?? '',
  quantity: Number(data.quantity ?? 1),
  dates: [...(data.dates ?? [])],
  start_time: data.start_time ?? '',
  end_time: data.end_time ?? '',
})

const form = useForm({
  title: props.initialData?.title ?? '',
  description: props.initialData?.description ?? '',
  attachment: null,
  items: props.initialData?.items?.length
    ? props.initialData.items.map(createCard)
    : [createCard()],
  resubmitted_from_id: props.formMode === 'resubmission' ? props.sourceItemBorrowingId : null,
})

const availabilityByKey = ref({})
const pickerInstances = new Map()
const requestIds = new Map()

const isRevision = computed(() => props.formMode === 'revision')
const isResubmission = computed(() => props.formMode === 'resubmission')
const pageTitle = computed(() => {
  if (isRevision.value) return 'Perbaiki Peminjaman Barang'
  if (isResubmission.value) return 'Ajukan Ulang Peminjaman Barang'
  return 'Ajukan Peminjaman Barang'
})

const timeSlots = (() => {
  const slots = []

  for (let hour = 7; hour <= 21; hour++) {
    for (let minute = 0; minute < 60; minute += 30) {
      if (hour === 21 && minute > 0) break
      slots.push(`${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`)
    }
  }

  return slots
})()
const sameDayStartSlots = timeSlots.slice(0, -1)

const selectedItem = (card) =>
  props.items.find((item) => String(item.id) === String(card.item_id))

const sortedDates = (card) => [...card.dates].sort()

const isCardComplete = (card) => {
  if (!card.item_id || Number(card.quantity) < 1) return false

  return Boolean(card.dates.length && card.start_time && card.end_time)
}

const availabilityFor = (card) =>
  availabilityByKey.value[card._key] ?? {
    loading: false,
    loaded: false,
    message: '',
    daily: [],
  }

const cardsOverlapOnDate = (left, right, date) =>
  String(left.item_id) === String(right.item_id)
  && left.dates.includes(date)
  && right.dates.includes(date)
  && left.start_time < right.end_time
  && left.end_time > right.start_time

const siblingDemand = (card, date) =>
{
  const siblings = form.items
    .filter((other) =>
      other._key !== card._key
      && isCardComplete(other)
      && cardsOverlapOnDate(card, other, date),
    )
  const boundaries = [
    card.start_time,
    card.end_time,
    ...siblings.flatMap((other) => [other.start_time, other.end_time]),
  ].filter((value, index, values) =>
    value >= card.start_time
    && value <= card.end_time
    && values.indexOf(value) === index,
  ).sort()

  let maximumDemand = 0

  for (let index = 0; index < boundaries.length - 1; index++) {
    const segmentStart = boundaries[index]
    const segmentEnd = boundaries[index + 1]
    const demand = siblings
      .filter((other) => other.start_time < segmentEnd && other.end_time > segmentStart)
      .reduce((total, other) => total + Number(other.quantity || 0), 0)

    maximumDemand = Math.max(maximumDemand, demand)
  }

  return maximumDemand
}

const adjustedDailyAvailability = (card) =>
  availabilityFor(card).daily.map((day) => {
    const requestedElsewhere = siblingDemand(card, day.date)

    return {
      ...day,
      requested_elsewhere: requestedElsewhere,
      adjusted_remaining_quantity: Math.max(
        0,
        Number(day.remaining_quantity ?? 0) - requestedElsewhere,
      ),
    }
  })

const remainingQuantities = (card) =>
  adjustedDailyAvailability(card).map((day) => day.adjusted_remaining_quantity)

const minimumRemaining = (card) => {
  const quantities = remainingQuantities(card)
  return quantities.length ? Math.min(...quantities) : null
}

const hasEnoughStock = (card) =>
  isCardComplete(card)
  && availabilityFor(card).loaded
  && minimumRemaining(card) !== null
  && Number(card.quantity) <= minimumRemaining(card)

const shortageDates = (card) =>
  adjustedDailyAvailability(card).filter(
    (day) => Number(card.quantity) > day.adjusted_remaining_quantity,
  )

const duplicateCardIndexes = computed(() => {
  const seen = new Map()
  const duplicates = new Set()

  form.items.forEach((card, index) => {
    if (!isCardComplete(card)) return

    const signature = [
      card.item_id,
      Number(card.quantity),
      sortedDates(card).join(','),
      card.start_time,
      card.end_time,
    ].join('|')
    const previousIndex = seen.get(signature)

    if (previousIndex !== undefined) {
      duplicates.add(previousIndex)
      duplicates.add(index)
    } else {
      seen.set(signature, index)
    }
  })

  return duplicates
})
const hasDuplicateCards = computed(() => duplicateCardIndexes.value.size > 0)

const hasIncompleteCard = computed(() => form.items.some((card) => !isCardComplete(card)))
const hasLoadingAvailability = computed(() =>
  form.items.some((card) => availabilityFor(card).loading),
)
const hasUncheckedCard = computed(() =>
  form.items.some((card) =>
    isCardComplete(card)
    && !availabilityFor(card).loaded
    && !availabilityFor(card).message,
  ),
)
const hasStockShortage = computed(() =>
  form.items.some((card) =>
    isCardComplete(card)
    && availabilityFor(card).loaded
    && !hasEnoughStock(card),
  ),
)
const completedCardCount = computed(() =>
  form.items.filter((card, index) =>
    isCardComplete(card)
    && hasEnoughStock(card)
    && !duplicateCardIndexes.value.has(index),
  ).length,
)
const hasInvalidPrefilledDates = computed(() =>
  form.items.some((card) =>
    card.dates.filter(Boolean).some((date) => date < props.minimumBorrowDate),
  ),
)

const resetAvailability = (card) => {
  requestIds.set(card._key, (requestIds.get(card._key) ?? 0) + 1)
  availabilityByKey.value[card._key] = {
    loading: false,
    loaded: false,
    message: '',
    daily: [],
  }
}

const loadAvailability = async (card) => {
  resetAvailability(card)
  if (!isCardComplete(card)) return

  const requestId = (requestIds.get(card._key) ?? 0) + 1
  requestIds.set(card._key, requestId)
  availabilityByKey.value[card._key] = {
    loading: true,
    loaded: false,
    message: '',
    daily: [],
  }

  const query = {
    item: card.item_id,
    dates: sortedDates(card),
    start_time: card.start_time,
    end_time: card.end_time,
  }

  if (isRevision.value) query.exclude_item_borrowing_id = props.itemBorrowingId

  try {
    const response = await fetch(route('items.availability', query), {
      headers: { Accept: 'application/json' },
    })

    if (!response.ok) throw new Error('Availability request failed')
    const data = await response.json()
    if (requestIds.get(card._key) !== requestId) return

    availabilityByKey.value[card._key] = {
      loading: false,
      loaded: true,
      message: data.message ?? '',
      daily: data.daily_availability ?? [],
    }
  } catch {
    if (requestIds.get(card._key) !== requestId) return

    availabilityByKey.value[card._key] = {
      loading: false,
      loaded: false,
      message: 'Ketersediaan stok belum dapat diperiksa. Coba ubah jadwal atau muat ulang halaman.',
      daily: [],
    }
  }
}

const addCard = () => form.items.push(createCard())

const removeCard = (index) => {
  if (form.items.length === 1) return
  const [removed] = form.items.splice(index, 1)

  for (const [key, picker] of pickerInstances.entries()) {
    if (key.startsWith(`${removed._key}:`)) {
      picker.destroy()
      pickerInstances.delete(key)
    }
  }

  requestIds.delete(removed._key)
  delete availabilityByKey.value[removed._key]
}

const onStartTimeChange = (card) => {
  if (card.end_time && card.end_time <= card.start_time) card.end_time = ''

  loadAvailability(card)
}

const pickerRef = (element, card) => {
  const key = `${card._key}:dates`
  const existing = pickerInstances.get(key)

  if (!element) {
    existing?.destroy()
    pickerInstances.delete(key)
    return
  }

  if (existing) return

  const picker = flatpickr(element, {
    mode: 'multiple',
    dateFormat: 'Y-m-d',
    altInput: true,
    altFormat: 'j F Y',
    conjunction: ', ',
    locale: Indonesian,
    minDate: props.minimumBorrowDate,
    defaultDate: card.dates,
    disableMobile: true,
    onChange: (_dates, dateString) => {
      card.dates = dateString ? dateString.split(', ').sort() : []
      loadAvailability(card)
    },
  })

  picker.altInput?.classList.add(
    'min-h-12',
    'w-full',
    'rounded-xl',
    'border',
    'border-slate-200',
    'bg-slate-50',
    'px-3',
    'text-sm',
    'text-slate-900',
    'transition',
    'focus:border-blue-500',
    'focus:bg-white',
    'focus:ring-4',
    'focus:ring-blue-500/10',
    'dark:border-slate-600',
    'dark:bg-slate-900/40',
    'dark:text-white',
    'dark:focus:bg-slate-900',
  )

  pickerInstances.set(key, picker)
}

const errorFor = (index, field) => form.errors[`items.${index}.${field}`]
const datesError = (index) =>
  errorFor(index, 'dates')
  ?? Object.entries(form.errors).find(([key]) => key.startsWith(`items.${index}.dates.`))?.[1]

const formatDate = (value) => {
  if (!value) return 'Tanggal belum dipilih'

  return new Intl.DateTimeFormat('id-ID', {
    weekday: 'short',
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(`${value}T00:00:00`))
}

const scheduleSummary = (card) => {
  if (!card.dates.length) return 'Tanggal belum dipilih'
  return card.dates.length === 1
    ? formatDate(card.dates[0])
    : `${card.dates.length} tanggal terpilih`
}

const timeSummary = (card) => {
  return card.start_time && card.end_time
    ? `${card.start_time}–${card.end_time} WIB`
    : 'Jam belum lengkap'
}

const cardStatus = (card, index) => {
  if (!isCardComplete(card)) return 'Belum lengkap'
  if (duplicateCardIndexes.value.has(index)) return 'Duplikat'
  if (availabilityFor(card).loading) return 'Memeriksa stok'
  if (availabilityFor(card).message) return 'Perlu diperiksa'
  if (hasEnoughStock(card)) return 'Tersedia'
  return 'Stok tidak cukup'
}

const cardStatusClasses = (card, index) => {
  if (duplicateCardIndexes.value.has(index)) {
    return 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'
  }
  if (availabilityFor(card).loaded && !hasEnoughStock(card)) {
    return 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'
  }
  if (hasEnoughStock(card)) {
    return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
  }
  return 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
}

const handleFileChange = (event) => {
  form.attachment = event.target.files?.[0] ?? null
}

const submit = () => {
  if (
    hasIncompleteCard.value
    || hasLoadingAvailability.value
    || hasUncheckedCard.value
    || hasStockShortage.value
    || hasDuplicateCards.value
    || hasInvalidPrefilledDates.value
  ) return

  if (isRevision.value) {
    form
      .transform((data) => ({ ...data, _method: 'put' }))
      .post(route('item-borrowings.update', props.itemBorrowingId), {
        forceFormData: true,
        preserveScroll: true,
      })
    return
  }

  form.post(route('item-borrowings.store'), {
    forceFormData: true,
    preserveScroll: true,
  })
}

onMounted(() => {
  for (const card of form.items) {
    if (isCardComplete(card)) loadAvailability(card)
  }
})

onBeforeUnmount(() => {
  pickerInstances.forEach((picker) => picker.destroy())
  pickerInstances.clear()
})
</script>

<template>
  <AuthenticatedLayout>
    <Head :title="pageTitle" />

    <div class="item-borrowing-form mx-auto max-w-6xl space-y-6 pb-24">
      <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:p-6">
        <div class="border-b border-slate-200 pb-5 dark:border-slate-700">
          <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">{{ pageTitle }}</h1>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Setiap tanggal memakai jam mulai dan selesai yang sama. Peminjaman menginap atau rentang terus-menerus tidak tersedia.
          </p>
        </div>

        <div
          v-if="revisionNote"
          class="rounded-2xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-800 dark:border-violet-900 dark:bg-violet-950/40 dark:text-violet-200"
        >
          <p class="font-semibold">Catatan admin</p>
          <p class="mt-1">{{ revisionNote }}</p>
        </div>

        <div
          v-if="hasInvalidPrefilledDates"
          class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300"
        >
          Sebagian jadwal lama tidak lagi memenuhi batas minimum {{ minimumBorrowDate }}. Ganti tanggal sebelum mengirim.
        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
          <header class="flex items-center gap-4 border-b border-slate-100 px-5 py-5 dark:border-slate-700 sm:px-7">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-600 font-bold text-white">1</span>
            <div>
              <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Informasi Kegiatan</h2>
              <p class="text-sm text-slate-500 dark:text-slate-400">Berlaku untuk seluruh barang dalam pengajuan.</p>
            </div>
          </header>
          <div class="grid gap-6 p-5 sm:p-7">
            <label class="space-y-2">
              <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Judul Kegiatan <span class="text-rose-500">*</span></span>
              <input
                v-model="form.title"
                type="text"
                maxlength="255"
                class="min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-900 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white"
                placeholder="Contoh: Dokumentasi kegiatan seminar"
              />
              <span v-if="form.errors.title" class="text-sm font-medium text-rose-600">{{ form.errors.title }}</span>
            </label>
            <label class="space-y-2">
              <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Deskripsi</span>
              <textarea
                v-model="form.description"
                rows="3"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white"
                placeholder="Tuliskan keperluan dan catatan tambahan."
              />
              <span v-if="form.errors.description" class="text-sm font-medium text-rose-600">{{ form.errors.description }}</span>
            </label>
          </div>
        </section>

        <section class="space-y-4">
          <div class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div class="flex items-center gap-4">
              <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-600 font-bold text-white">2</span>
              <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Barang dan Jadwal</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Satu kartu dapat memakai barang dan jam yang sama pada beberapa tanggal.</p>
              </div>
            </div>
            <button
              type="button"
              class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-5 text-sm font-semibold text-white hover:bg-blue-700"
              @click="addCard"
            >
              + Tambah Barang
            </button>
          </div>

          <p v-if="form.errors.items" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-300">
            {{ form.errors.items }}
          </p>

          <article
            v-for="(card, index) in form.items"
            :key="card._key"
            class="overflow-hidden rounded-3xl border bg-white shadow-sm dark:bg-slate-800"
            :class="hasEnoughStock(card)
              ? 'border-emerald-200 dark:border-emerald-900/70'
              : availabilityFor(card).loaded && !hasEnoughStock(card)
                ? 'border-rose-300 dark:border-rose-900/70'
                : 'border-slate-200 dark:border-slate-700'"
          >
            <header class="flex flex-col gap-4 border-b border-slate-100 bg-slate-50/70 px-5 py-5 dark:border-slate-700 dark:bg-slate-900/30 sm:flex-row sm:items-center sm:justify-between sm:px-7">
              <div class="flex items-center gap-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-100 text-sm font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">{{ index + 1 }}</span>
                <div>
                  <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-semibold text-slate-900 dark:text-white">Barang {{ index + 1 }}</h3>
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="cardStatusClasses(card, index)">
                      {{ cardStatus(card, index) }}
                    </span>
                  </div>
                  <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    {{ selectedItem(card)?.name ?? 'Barang belum dipilih' }}
                    <template v-if="card.item_id"> · {{ card.quantity || 0 }} unit</template>
                  </p>
                </div>
              </div>
              <button
                v-if="form.items.length > 1"
                type="button"
                class="text-sm font-semibold text-rose-600 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300"
                @click="removeCard(index)"
              >
                Hapus
              </button>
            </header>

            <div class="grid gap-6 p-5 sm:grid-cols-2 sm:p-7 lg:grid-cols-3">
              <label class="space-y-2">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Barang</span>
                <select
                  v-model="card.item_id"
                  class="min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-900 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 dark:border-slate-600 dark:bg-slate-900/60 dark:text-white dark:focus:bg-slate-900"
                  @change="loadAvailability(card)"
                >
                  <option value="">Pilih barang</option>
                  <option
                    v-for="item in items"
                    :key="item.id"
                    :value="item.id"
                    :disabled="!item.is_available"
                  >
                    {{ item.name }} · Stok {{ item.quantity }}{{ item.is_available ? '' : ' (tidak tersedia)' }}
                  </option>
                </select>
                <span v-if="errorFor(index, 'item_id')" class="text-sm font-medium text-rose-600">{{ errorFor(index, 'item_id') }}</span>
              </label>

              <label class="space-y-2">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Jumlah</span>
                <input
                  v-model.number="card.quantity"
                  type="number"
                  min="1"
                  :max="selectedItem(card)?.quantity"
                  class="min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-900 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 dark:border-slate-600 dark:bg-slate-900/60 dark:text-white dark:focus:bg-slate-900"
                  @input="loadAvailability(card)"
                />
                <span v-if="errorFor(index, 'quantity')" class="text-sm font-medium text-rose-600">{{ errorFor(index, 'quantity') }}</span>
              </label>

              <label class="space-y-2">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Tanggal Peminjaman</span>
                <input
                  :ref="(element) => pickerRef(element, card)"
                  type="text"
                  placeholder="Pilih satu atau beberapa tanggal"
                />
                <span v-if="datesError(index)" class="text-sm font-medium text-rose-600">{{ datesError(index) }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">Minimal H-7: {{ minimumBorrowDate }}</span>
              </label>

              <label class="space-y-2">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Jam Mulai</span>
                <select
                  v-model="card.start_time"
                  :disabled="!card.item_id || !card.dates.length"
                  class="min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-900 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 disabled:cursor-not-allowed disabled:opacity-45 dark:border-slate-600 dark:bg-slate-900/60 dark:text-white dark:focus:bg-slate-900"
                  @change="onStartTimeChange(card)"
                >
                  <option value="">Pilih jam mulai</option>
                  <option v-for="slot in sameDayStartSlots" :key="slot" :value="slot">{{ slot }}</option>
                </select>
                <span v-if="errorFor(index, 'start_time')" class="text-sm font-medium text-rose-600">{{ errorFor(index, 'start_time') }}</span>
              </label>

              <label class="space-y-2">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Jam Selesai</span>
                <select
                  v-model="card.end_time"
                  :disabled="!card.start_time"
                  class="min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-900 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 disabled:cursor-not-allowed disabled:opacity-45 dark:border-slate-600 dark:bg-slate-900/60 dark:text-white dark:focus:bg-slate-900"
                  @change="loadAvailability(card)"
                >
                  <option value="">Pilih jam selesai</option>
                  <option v-for="slot in timeSlots.filter((value) => value > card.start_time)" :key="slot" :value="slot">{{ slot }}</option>
                </select>
                <span v-if="errorFor(index, 'end_time')" class="text-sm font-medium text-rose-600">{{ errorFor(index, 'end_time') }}</span>
              </label>
            </div>

            <p
              v-if="duplicateCardIndexes.has(index)"
              class="mx-5 mb-5 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 dark:border-rose-900 dark:bg-rose-950/60 dark:text-rose-300 sm:mx-7"
            >
              Jadwal ini identik dengan kartu lain. Ubah barang, jumlah, tanggal, atau jam.
            </p>

            <footer class="border-t border-slate-100 bg-slate-50/60 px-5 py-4 dark:border-slate-700 dark:bg-slate-900/20 sm:px-7">
              <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="grid gap-1 text-sm">
                  <p class="font-semibold text-slate-800 dark:text-slate-200">{{ scheduleSummary(card) }}</p>
                  <p class="text-slate-500 dark:text-slate-400">{{ timeSummary(card) }} · {{ card.quantity || 0 }} unit</p>
                </div>

                <div v-if="availabilityFor(card).loading" class="text-sm font-medium text-blue-600 dark:text-blue-300">Memeriksa ketersediaan stok...</div>
                <div v-else-if="availabilityFor(card).message" class="rounded-xl border border-amber-200 bg-amber-100 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-300">
                  {{ availabilityFor(card).message }}
                </div>
                <div v-else-if="!isCardComplete(card)" class="text-xs text-slate-400 dark:text-slate-500">Lengkapi barang dan jadwal untuk memeriksa stok.</div>
                <div v-else-if="hasEnoughStock(card)" class="rounded-xl bg-emerald-100 px-3 py-2 text-sm font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                  ✓ Stok tersedia · minimum tersisa {{ minimumRemaining(card) }}
                </div>
                <div v-else class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/60 dark:text-rose-300">
                  <p class="font-semibold">Stok tidak mencukupi.</p>
                  <p v-for="day in shortageDates(card)" :key="day.date">
                    {{ formatDate(day.date) }}: tersedia {{ day.adjusted_remaining_quantity }} dari kebutuhan {{ card.quantity }}
                    <template v-if="day.requested_elsewhere"> setelah {{ day.requested_elsewhere }} unit pada kartu lain.</template>
                  </p>
                </div>
              </div>
            </footer>
          </article>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
          <header class="flex items-center gap-4 border-b border-slate-100 px-5 py-5 dark:border-slate-700 sm:px-7">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-600 font-bold text-white">3</span>
            <div>
              <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Lampiran Surat</h2>
              <p class="text-sm text-slate-500 dark:text-slate-400">PDF/JPG/PNG maksimal 2 MB.</p>
            </div>
          </header>
          <div class="p-5 sm:p-7">
            <label class="flex cursor-pointer items-center justify-between rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 px-5 py-6 dark:border-slate-600 dark:bg-slate-900/30">
              <div>
                <p class="font-semibold text-slate-800 dark:text-slate-200">
                  {{ form.attachment?.name ?? (existingAttachment ? 'Gunakan lampiran lama' : 'Pilih lampiran wajib') }}
                </p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Klik untuk memilih dokumen pendukung.</p>
              </div>
              <span class="rounded-xl bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm dark:bg-slate-700 dark:text-slate-200">Pilih File</span>
              <input type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="handleFileChange" />
            </label>
            <p v-if="form.errors.attachment" class="mt-3 text-sm font-medium text-rose-600">{{ form.errors.attachment }}</p>
          </div>
        </section>

        <div class="sticky bottom-4 z-20 rounded-2xl border border-slate-200 bg-white/90 p-3 shadow-2xl backdrop-blur-xl dark:border-slate-700 dark:bg-slate-800/90 sm:p-4">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm">
              <p class="font-semibold text-slate-900 dark:text-white">{{ completedCardCount }} dari {{ form.items.length }} barang siap diajukan</p>
              <p v-if="hasDuplicateCards" class="text-xs font-medium text-rose-600 dark:text-rose-400">Hapus atau ubah kartu dengan jadwal identik.</p>
              <p v-else-if="hasStockShortage" class="text-xs font-medium text-rose-600 dark:text-rose-400">Kurangi jumlah atau ganti jadwal yang stoknya tidak mencukupi.</p>
              <p v-else-if="hasIncompleteCard" class="text-xs font-medium text-amber-600 dark:text-amber-400">Lengkapi seluruh kartu barang dan jadwal.</p>
              <p v-else class="text-xs text-slate-500 dark:text-slate-400">Pastikan seluruh informasi sudah benar.</p>
            </div>
            <div class="flex flex-col-reverse gap-2 sm:flex-row">
              <Link
                :href="isRevision ? route('item-borrowings.show', itemBorrowingId) : route('item-borrowings.index')"
                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200"
              >
                Batal
              </Link>
              <button
                type="submit"
                :disabled="
                  form.processing
                    || hasIncompleteCard
                    || hasLoadingAvailability
                    || hasUncheckedCard
                    || hasStockShortage
                    || hasDuplicateCards
                    || hasInvalidPrefilledDates
                    || (!form.attachment && !existingAttachment)
                "
                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-6 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
              >
                {{ form.processing
                  ? 'Mengirim...'
                  : hasIncompleteCard
                    ? 'Lengkapi Jadwal'
                    : hasLoadingAvailability || hasUncheckedCard
                      ? 'Memeriksa Stok...'
                      : isRevision
                        ? 'Kirim Revisi'
                        : isResubmission
                          ? 'Ajukan Ulang'
                          : 'Ajukan Peminjaman' }}
              </button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>
