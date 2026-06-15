<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Dropdown from '@/Components/Dropdown.vue'
import RowsPerPageSelect from '@/Components/RowsPerPageSelect.vue'
import SortableTh from '@/Components/SortableTh.vue'
import TablePagination from '@/Components/TablePagination.vue'
import {
  getBookingStatusClasses,
  getBookingStatusLabel,
} from '@/Composables/useBookingStatus'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { formatDateToYMD } from '@/Composables/useDateFormatter'
import { Head, router } from '@inertiajs/vue3'
import { computed, reactive, watch, ref, onMounted, onBeforeUnmount } from 'vue'
import { Bar, Pie } from 'vue-chartjs'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
  ArcElement,
} from 'chart.js'
import flatpickr from 'flatpickr'
import 'flatpickr/dist/flatpickr.css'
import { Indonesian } from 'flatpickr/dist/l10n/id'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement)

const props = defineProps({
  bookings: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  statusSummary: {
    type: Object,
    default: () => ({}),
  },
  statusOptions: {
    type: Array,
    default: () => [],
  },
})

const filterForm = reactive({
  search: props.filters?.search ?? '',
  status: props.filters?.status ?? '',
  start_date: props.filters?.start_date ?? '',
  end_date: props.filters?.end_date ?? '',
  booking_start_date: props.filters?.booking_start_date ?? '',
  booking_end_date: props.filters?.booking_end_date ?? '',
})

const dateRangePicker = ref(null)
const bookingDateRangePicker = ref(null)
const flatpickrInstance = ref(null)
const bookingFlatpickrInstance = ref(null)
const isMobileViewport = ref(false)

const syncMobileViewport = () => {
  if (typeof window === 'undefined') return
  isMobileViewport.value = window.innerWidth < 640
}

const syncDateRange = (selectedDates, startKey, endKey) => {
  if (selectedDates.length === 1) {
    filterForm[startKey] = formatDateToYMD(selectedDates[0])
    filterForm[endKey] = ''
  } else if (selectedDates.length === 2) {
    filterForm[startKey] = formatDateToYMD(selectedDates[0])
    filterForm[endKey] = formatDateToYMD(selectedDates[1])
  } else {
    filterForm[startKey] = ''
    filterForm[endKey] = ''
  }
}

const createDateRangePicker = (element, startKey, endKey) => {
  if (!element) return null

  return flatpickr(element, {
    mode: 'range',
    dateFormat: 'Y-m-d',
    locale: Indonesian,
    allowInput: true,
    showMonths: 1,
    defaultDate:
      filterForm[startKey] && filterForm[endKey]
        ? [filterForm[startKey], filterForm[endKey]]
        : filterForm[startKey] || filterForm[endKey] || null,
    onChange: (selectedDates) => syncDateRange(selectedDates, startKey, endKey),
    onClose: (selectedDates) => {
      if (selectedDates.length === 0) {
        syncDateRange([], startKey, endKey)
      }
    },
  })
}

onMounted(() => {
  syncMobileViewport()
  window.addEventListener('resize', syncMobileViewport)

  flatpickrInstance.value = createDateRangePicker(dateRangePicker.value, 'start_date', 'end_date')
  bookingFlatpickrInstance.value = createDateRangePicker(
    bookingDateRangePicker.value,
    'booking_start_date',
    'booking_end_date',
  )
})

onBeforeUnmount(() => {
  flatpickrInstance.value?.destroy()
  bookingFlatpickrInstance.value?.destroy()

  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', syncMobileViewport)
  }
})

watch(
  () => props.filters,
  (filters) => {
    filterForm.search = filters?.search ?? ''
    filterForm.status = filters?.status ?? ''
    filterForm.start_date = filters?.start_date ?? ''
    filterForm.end_date = filters?.end_date ?? ''
    filterForm.booking_start_date = filters?.booking_start_date ?? ''
    filterForm.booking_end_date = filters?.booking_end_date ?? ''

    flatpickrInstance.value?.setDate(
      [filterForm.start_date, filterForm.end_date].filter(Boolean),
      false,
    )
    bookingFlatpickrInstance.value?.setDate(
      [filterForm.booking_start_date, filterForm.booking_end_date].filter(Boolean),
      false,
    )
  },
  { deep: true },
)

const bookings = computed(() => props.bookings ?? [])
const selectedChartDate = ref(null)
const selectedChartStatus = ref(null)
const chartDateBasis = ref('application')
const chartStatusKeys = ['approved', 'waiting', 'rejected', 'cancelled', 'expired']

const normalizeChartStatus = (status) => (
  ['pending', 'requested', 'waiting', 'needs_revision'].includes(status) ? 'waiting' : (status || '')
)

const bookingScheduleDateKeys = (booking) => {
  const keys = new Set()
  const schedules = booking.room_schedules?.length
    ? booking.room_schedules
    : [{ start_time: booking.start_time, end_time: booking.end_time }]

  schedules.forEach((schedule) => {
    const start = parseDateKey(schedule.start_time)
    const end = parseDateKey(schedule.end_time)
    if (!start || !end) return

    for (const date = new Date(start); date <= end; date.setDate(date.getDate() + 1)) {
      keys.add(toDateKey(date))
    }
  })

  return [...keys]
}

const bookingMatchesDate = (booking, dateKey, basis = chartDateBasis.value) => {
  if (!dateKey) return true

  const selectedDate = parseDateKey(dateKey)
  if (!selectedDate) return false

  if (basis === 'booking') {
    return bookingScheduleDateKeys(booking).includes(toDateKey(selectedDate))
  }

  const createdAt = parseDateKey(booking.created_at)
  return Boolean(createdAt && toDateKey(createdAt) === dateKey)
}

const tableBookings = computed(() => {
  return bookings.value.filter((booking) => {
    const matchesDate = bookingMatchesDate(booking, selectedChartDate.value)
    const matchesStatus = !selectedChartStatus.value
      || normalizeChartStatus(booking.status) === selectedChartStatus.value

    return matchesDate && matchesStatus
  })
})

const {
  sortedItems: sortedBookings,
  toggleSort: toggleReportSort,
  sortDirection: reportSortDirection,
  ariaSortValue: reportAriaSortValue,
} = useTableSort(tableBookings, {
  accessors: {
    id: (booking) => booking.id ?? 0,
    created_at: (booking) => (booking.created_at ? new Date(booking.created_at) : null),
    applicant: (booking) => booking.user?.name ?? '',
    status: (booking) => booking.status ?? '',
    letter_number: (booking) => booking.letter_number ?? '',
    schedule: (booking) => (booking.start_time ? new Date(booking.start_time) : null),
    location: (booking) => booking.room_summary ?? '',
    details: (booking) => booking.title ?? '',
  },
})

const {
  paginatedItems: paginatedBookings,
  rowsPerPage,
  currentPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(sortedBookings, { perPage: 10 })

const perPageOptions = [10, 25, 50, 100]

const summary = computed(() => ({
  total: props.statusSummary?.total ?? bookings.value.length ?? 0,
  approved: props.statusSummary?.approved ?? 0,
  waiting: props.statusSummary?.waiting ?? 0,
  rejected: props.statusSummary?.rejected ?? 0,
  cancelled: props.statusSummary?.cancelled ?? 0,
  expired: props.statusSummary?.expired ?? 0,
}))

const formatDateTime = (value) => {
  if (!value) return '-'
  
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) {
    return value
  }
  
  return parsed.toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const buildQuery = () => {
  const payload = {}
  Object.entries(filterForm).forEach(([key, value]) => {
    if (value) {
      payload[key] = value
    }
  })
  return payload
}

const applyFilters = () => {
  selectedChartDate.value = null
  selectedChartStatus.value = null
  changePage(1)
  router.get(route('admin.reports.index'), buildQuery(), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const resetFilters = () => {
  filterForm.search = ''
  filterForm.status = ''
  filterForm.start_date = ''
  filterForm.end_date = ''
  filterForm.booking_start_date = ''
  filterForm.booking_end_date = ''
  flatpickrInstance.value?.clear()
  bookingFlatpickrInstance.value?.clear()
  applyFilters()
}

const exportExcel = () => {
  const url = route('admin.reports.export', buildQuery())
  window.open(url, '_blank')
}

const exportPdf = () => {
  const url = route('admin.reports.export.pdf', buildQuery())
  window.open(url, '_blank')
}

const openBookingApproval = (booking) => {
  if (!booking?.id) return

  router.visit(route('admin.bookings.show', booking.id))
}

const toDateKey = (date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

const parseDateKey = (value) => {
  if (!value) return null

  const datePart = String(value).split('T')[0].split(' ')[0]
  const [year, month, day] = datePart.split('-').map(Number)
  if (!year || !month || !day) return null

  const parsed = new Date(year, month - 1, day)
  parsed.setHours(0, 0, 0, 0)

  return Number.isNaN(parsed.getTime()) ? null : parsed
}

const hasApplicationDateFilter = computed(
  () => Boolean(props.filters?.start_date || props.filters?.end_date),
)
const hasBookingDateFilter = computed(
  () => Boolean(props.filters?.booking_start_date || props.filters?.booking_end_date),
)

watch(chartDateBasis, () => {
  selectedChartDate.value = null
  changePage(1)
})

const chartRange = computed(() => {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const defaultStart = new Date(today)
  defaultStart.setDate(today.getDate() - 6)

  let start = new Date(defaultStart)
  let end = new Date(today)

  if (chartDateBasis.value === 'booking' && hasBookingDateFilter.value) {
    const scheduleStarts = bookings.value
      .flatMap((booking) => (booking.room_schedules ?? []).map((schedule) => parseDateKey(schedule.start_time)))
      .filter(Boolean)
    const scheduleEnds = bookings.value
      .flatMap((booking) => (booking.room_schedules ?? []).map((schedule) => parseDateKey(schedule.end_time)))
      .filter(Boolean)

    start = parseDateKey(props.filters?.booking_start_date)
      ?? (scheduleStarts.length ? new Date(Math.min(...scheduleStarts)) : defaultStart)
    end = parseDateKey(props.filters?.booking_end_date)
      ?? (scheduleEnds.length ? new Date(Math.max(...scheduleEnds)) : start)
  } else if (chartDateBasis.value === 'application' && hasApplicationDateFilter.value) {
    const applicationDates = bookings.value
      .map((booking) => parseDateKey(booking.created_at))
      .filter(Boolean)

    start = parseDateKey(props.filters?.start_date)
      ?? (applicationDates.length ? new Date(Math.min(...applicationDates)) : defaultStart)
    end = parseDateKey(props.filters?.end_date)
      ?? (applicationDates.length ? new Date(Math.max(...applicationDates)) : start)
  }

  if (start > end) {
    const previousStart = start
    start = end
    end = previousStart
  }

  return { start, end }
})

const chartBookings = computed(() => {
  const { start, end } = chartRange.value

  return bookings.value.filter((booking) => {
    if (chartDateBasis.value === 'booking') {
      return bookingScheduleDateKeys(booking).some((dateKey) => {
        const date = parseDateKey(dateKey)
        return date && date >= start && date <= end
      })
    }

    const createdAt = parseDateKey(booking.created_at)
    return Boolean(createdAt && createdAt >= start && createdAt <= end)
  })
})

const chartPeriodDescription = computed(() => {
  if (chartDateBasis.value === 'booking' && hasBookingDateFilter.value) {
    return 'Sesuai rentang tanggal peminjaman'
  }

  if (chartDateBasis.value === 'application' && hasApplicationDateFilter.value) {
    return 'Sesuai rentang tanggal pengajuan'
  }

  return chartDateBasis.value === 'booking'
    ? '7 hari terakhir berdasarkan jadwal peminjaman'
    : '7 hari terakhir berdasarkan pengajuan'
})

// =========================================
// CHART DATA: STATUS DISTRIBUTION (PIE)
// =========================================
const statusChartData = computed(() => {
  const counts = {
    approved: 0,
    waiting: 0,
    rejected: 0,
    cancelled: 0,
    expired: 0,
  }
   
  chartBookings.value.forEach(b => {
    const s = normalizeChartStatus(b.status)
    if (counts[s] !== undefined) {
      counts[s]++
    }
  })

  const total = Object.values(counts).reduce((sum, count) => sum + count, 0)
  const colors = ['#10b981', '#f59e0b', '#f43f5e', '#8b5cf6', '#f97316']
  const toPercentageLabel = (label, count) => {
    const percentage = total > 0 ? ((count / total) * 100).toFixed(1) : '0.0'
    return `${label} (${percentage}%)`
  }

  return {
    labels: [
      toPercentageLabel('Disetujui', counts.approved),
      toPercentageLabel('Menunggu', counts.waiting),
      toPercentageLabel('Ditolak', counts.rejected),
      toPercentageLabel('Dibatalkan', counts.cancelled),
      toPercentageLabel('Kedaluwarsa', counts.expired),
    ],
    datasets: [{
      data: [counts.approved, counts.waiting, counts.rejected, counts.cancelled, counts.expired],
      backgroundColor: colors.map((color, index) => (
        !selectedChartStatus.value || chartStatusKeys[index] === selectedChartStatus.value
          ? color
          : `${color}55`
      )),
      borderColor: chartStatusKeys.map((status) => (
        status === selectedChartStatus.value ? '#0f172a' : '#ffffff'
      )),
      borderWidth: chartStatusKeys.map((status) => (
        status === selectedChartStatus.value ? 3 : 1
      )),
      offset: chartStatusKeys.map((status) => (
        status === selectedChartStatus.value ? 10 : 0
      )),
    }],
  }
})

// =========================================
// CHART DATA: WEEKLY BOOKINGS (BAR)
// =========================================
const weeklyChartData = computed(() => {
  const dayCounts = {}
  const dates = []
  const { start: rangeStart, end: rangeEnd } = chartRange.value

  for (
    const date = new Date(rangeStart);
    date <= rangeEnd;
    date.setDate(date.getDate() + 1)
  ) {
    const key = toDateKey(date)
    dates.push(key)
    dayCounts[key] = 0
  }

  chartBookings.value.forEach((booking) => {
    if (chartDateBasis.value === 'booking') {
      bookingScheduleDateKeys(booking).forEach((key) => {
        if (dayCounts[key] !== undefined) {
          dayCounts[key] += 1
        }
      })

      return
    }

    const createdAt = parseDateKey(booking.created_at)
    if (!createdAt) return

    const key = toDateKey(createdAt)
    if (dayCounts[key] !== undefined) {
      dayCounts[key] += 1
    }
  })

  const labels = dates.map((dateString) => {
    const date = parseDateKey(dateString)
    return date.toLocaleDateString('id-ID', {
      day: '2-digit',
      month: 'short',
    })
  })

  const data = dates.map((dateString) => dayCounts[dateString])
  const selectedDateIndex = dates.indexOf(selectedChartDate.value)

  return {
    labels,
    datasets: [{
      label: chartDateBasis.value === 'booking' ? 'Jadwal Aktif' : 'Jumlah Pengajuan',
      data,
      backgroundColor: dates.map((_, index) => (
        index === selectedDateIndex ? '#1d4ed8' : '#60a5fa'
      )),
      borderColor: dates.map((_, index) => (
        index === selectedDateIndex ? '#1e3a8a' : '#2563eb'
      )),
      borderWidth: dates.map((_, index) => (index === selectedDateIndex ? 2 : 1)),
    }],
  }
})

const chartDateKeys = computed(() => {
  const dates = []
  const { start, end } = chartRange.value

  for (const date = new Date(start); date <= end; date.setDate(date.getDate() + 1)) {
    dates.push(toDateKey(date))
  }

  return dates
})

const selectedDateBookings = computed(() => {
  if (!selectedChartDate.value) return []

  return chartBookings.value.filter((booking) => (
    bookingMatchesDate(booking, selectedChartDate.value)
  ))
})

const selectedDateLabel = computed(() => {
  const date = parseDateKey(selectedChartDate.value)
  if (!date) return ''

  return date.toLocaleDateString('id-ID', {
    weekday: 'long',
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  })
})

const extractClock = (value, fallback = '-') => {
  if (!value) return fallback

  const match = String(value).match(/(?:T|\s)(\d{2}:\d{2})/)
  return match?.[1] ?? String(value).slice(0, 5)
}

const formatTimeForSelectedDate = (booking) => {
  if (chartDateBasis.value === 'application') {
    return booking.schedule_summary ?? '-'
  }

  const selectedDate = selectedChartDate.value
  const matchingSchedules = (booking.room_schedules ?? []).filter((schedule) => (
    toDateKey(parseDateKey(schedule.start_time)) === selectedDate
  ))

  if (!matchingSchedules.length) return '-'

  return matchingSchedules
    .map((schedule) => `${schedule.room?.name ?? 'Ruangan'} ${extractClock(schedule.start_time)} - ${extractClock(schedule.end_time)}`)
    .join(', ')
}

const clearSelectedChartDate = () => {
  selectedChartDate.value = null
  changePage(1)
}

const selectChartDate = (dateKey) => {
  if (!dateKey) return

  selectedChartDate.value = selectedChartDate.value === dateKey ? null : dateKey
  changePage(1)
}

const selectedChartStatusLabel = computed(() => (
  selectedChartStatus.value
    ? getBookingStatusLabel(selectedChartStatus.value)
    : ''
))

const clearSelectedChartStatus = () => {
  selectedChartStatus.value = null
  changePage(1)
}

const selectChartStatus = (status) => {
  if (!status) return

  selectedChartStatus.value = selectedChartStatus.value === status ? null : status
  changePage(1)
}

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        usePointStyle: true,
        boxWidth: isMobileViewport.value ? 10 : 14,
        padding: isMobileViewport.value ? 10 : 16,
        font: {
          size: isMobileViewport.value ? 10 : 12,
        },
      },
    },
  },
}))

const statusChartOptions = computed(() => ({
  ...chartOptions.value,
  plugins: {
    ...chartOptions.value.plugins,
    legend: {
      ...chartOptions.value.plugins.legend,
      onClick: (_event, legendItem) => {
        selectChartStatus(chartStatusKeys[legendItem.index])
      },
    },
  },
  onClick: (_event, elements) => {
    const selectedElement = elements?.[0]
    if (!selectedElement) return

    selectChartStatus(chartStatusKeys[selectedElement.index])
  },
  onHover: (event, elements) => {
    if (event?.native?.target) {
      event.native.target.style.cursor = elements?.length ? 'pointer' : 'default'
    }
  },
}))

const trendChartOptions = computed(() => ({
  ...chartOptions.value,
  interaction: {
    mode: 'index',
    intersect: false,
  },
  onClick: (_event, elements) => {
    const selectedElement = elements?.[0]
    if (!selectedElement) return

    selectChartDate(chartDateKeys.value[selectedElement.index])
  },
  onHover: (event, elements) => {
    if (event?.native?.target) {
      event.native.target.style.cursor = elements?.length ? 'pointer' : 'default'
    }
  },
}))

</script>

<template>
  <AuthenticatedLayout>
    <Head title="Report Peminjaman Ruangan" />

    <div class="space-y-4 sm:space-y-6">
      <div class="flex flex-col gap-3 sm:gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200 sm:text-3xl">Report Peminjaman Ruangan</h1>
          <p class="mt-1 max-w-2xl text-xs leading-relaxed text-gray-500 dark:text-gray-400 sm:text-sm">
            Rekap lengkap pengajuan ruangan beserta status terbaru dan data pemohon.
          </p>
        </div>
        <Dropdown align="right" width="48">
          <template #trigger>
            <button
              type="button"
              class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-medium text-blue-700 transition hover:bg-blue-100 dark:border-blue-800 dark:bg-slate-700 dark:text-blue-300 sm:min-h-0 sm:w-auto sm:rounded-2xl"
            >
              <span>Export</span>
              <svg class="h-4 w-4" fill="none" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                <path
                  d="M5.25 7.5 10 12.5l4.75-5"
                  stroke="currentColor"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="1.5"
                />
              </svg>
            </button>
          </template>
          <template #content>
            <div class="flex flex-col gap-1 p-2 text-sm text-slate-700 dark:text-slate-300">
              <button
                type="button"
                class="flex items-center gap-2 rounded-md px-3 py-2 hover:bg-blue-50 dark:hover:bg-slate-700"
                @click="exportExcel"
              >
                <span>Export Excel</span>
              </button>
              <button
                type="button"
                class="flex items-center gap-2 rounded-md px-3 py-2 hover:bg-blue-50 dark:hover:bg-slate-700"
                @click="exportPdf"
              >
                <span>Export PDF</span>
              </button>
            </div>
          </template>
        </Dropdown>
      </div>

      <div class="grid grid-cols-2 gap-2.5 sm:gap-3 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:rounded-2xl sm:p-4">
          <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 sm:text-[11px] sm:tracking-[0.2em]">Total Data</p>
          <div class="mt-2 flex items-end justify-between gap-2 sm:mt-3 sm:gap-3">
            <p class="text-2xl font-semibold text-slate-900 dark:text-slate-100 sm:text-3xl">{{ summary.total }}</p>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-500 dark:bg-slate-700 dark:text-slate-300">Aktif</span>
          </div>
          <p class="mt-2 hidden text-xs leading-relaxed text-slate-500 dark:text-slate-400 sm:block">Seluruh hasil sesuai filter aktif</p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-white p-3 shadow-sm dark:border-amber-800 dark:bg-slate-800 sm:rounded-2xl sm:p-4">
          <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-amber-500 dark:text-amber-400 sm:text-[11px] sm:tracking-[0.2em]">Menunggu</p>
          <div class="mt-2 flex items-end justify-between gap-2 sm:mt-3 sm:gap-3">
            <p class="text-2xl font-semibold text-amber-600 dark:text-amber-400 sm:text-3xl">{{ summary.waiting }}</p>
            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-medium text-amber-600 dark:bg-amber-900/20 dark:text-amber-300">Review</span>
          </div>
          <p class="mt-2 hidden text-xs leading-relaxed text-amber-500 dark:text-amber-400 sm:block">Booking belum diputuskan</p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-white p-3 shadow-sm dark:border-emerald-800 dark:bg-slate-800 sm:rounded-2xl sm:p-4">
          <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-emerald-500 dark:text-emerald-400 sm:text-[11px] sm:tracking-[0.2em]">Disetujui</p>
          <div class="mt-2 flex items-end justify-between gap-2 sm:mt-3 sm:gap-3">
            <p class="text-2xl font-semibold text-emerald-600 dark:text-emerald-400 sm:text-3xl">{{ summary.approved }}</p>
            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-medium text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-300">Aktif</span>
          </div>
          <p class="mt-2 hidden text-xs leading-relaxed text-emerald-500 dark:text-emerald-400 sm:block">Booking aktif</p>
        </div>
        <div class="rounded-xl border border-rose-200 bg-white p-3 shadow-sm dark:border-rose-800 dark:bg-slate-800 sm:rounded-2xl sm:p-4">
          <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-rose-500 dark:text-rose-400 sm:text-[11px] sm:tracking-[0.2em]">Status Final Lain</p>
          <div class="mt-2 flex items-end justify-between gap-2 sm:mt-3 sm:gap-3">
            <p class="text-2xl font-semibold text-rose-600 dark:text-rose-400 sm:text-3xl">{{ summary.rejected + summary.cancelled + summary.expired }}</p>
            <span class="rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-medium text-rose-600 dark:bg-rose-900/20 dark:text-rose-300">Selesai</span>
          </div>
          <p class="mt-2 hidden text-xs leading-relaxed text-rose-500 dark:text-rose-400 sm:block">Ditolak, dibatalkan, atau kedaluwarsa</p>
        </div>
      </div>

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Periode Chart</h2>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Pilih dasar tanggal untuk distribusi status dan tren peminjaman.
          </p>
        </div>
        <div class="inline-flex w-full rounded-xl border border-slate-200 bg-slate-100 p-1 dark:border-slate-700 dark:bg-slate-800 sm:w-auto">
          <button
            type="button"
            class="flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition sm:flex-none"
            :class="chartDateBasis === 'application'
              ? 'bg-white text-blue-700 shadow-sm dark:bg-slate-700 dark:text-blue-300'
              : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'"
            :aria-pressed="chartDateBasis === 'application'"
            @click="chartDateBasis = 'application'"
          >
            Tanggal Pengajuan
          </button>
          <button
            type="button"
            class="flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition sm:flex-none"
            :class="chartDateBasis === 'booking'
              ? 'bg-white text-blue-700 shadow-sm dark:bg-slate-700 dark:text-blue-300'
              : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'"
            :aria-pressed="chartDateBasis === 'booking'"
            @click="chartDateBasis = 'booking'"
          >
            Tanggal Peminjaman
          </button>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:p-5">
          <div class="mb-4 flex items-start justify-between gap-3">
            <div>
              <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 sm:text-lg">Distribusi Status</h3>
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ chartPeriodDescription }}</p>
            </div>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-500 dark:bg-slate-700 dark:text-slate-300">Pie</span>
          </div>
          <div class="h-48 sm:h-56">
            <Pie :data="statusChartData" :options="statusChartOptions" />
          </div>
          <p class="mt-3 text-center text-xs text-slate-500 dark:text-slate-400">
            Klik bagian pie atau label status untuk menyaring tabel.
          </p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:p-5">
          <div class="mb-4 flex items-start justify-between gap-3">
            <div>
              <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 sm:text-lg">Tren Peminjaman</h3>
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ chartPeriodDescription }}</p>
            </div>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-500 dark:bg-slate-700 dark:text-slate-300">Bar</span>
          </div>
          <div class="h-48 sm:h-56">
            <Bar :data="weeklyChartData" :options="trendChartOptions" />
          </div>
          <p class="mt-3 text-center text-xs text-slate-500 dark:text-slate-400">
            Klik batang pada tanggal untuk melihat detail dan menyaring tabel.
          </p>
        </div>
      </div>

      <div
        v-if="selectedChartDate"
        class="overflow-hidden rounded-2xl border border-blue-200 bg-blue-50/60 shadow-sm dark:border-blue-900 dark:bg-blue-950/20"
      >
        <div class="border-b border-blue-100 p-4 sm:flex sm:items-center sm:justify-between sm:gap-4 sm:px-5 dark:border-blue-900">
          <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-blue-600 dark:text-blue-400 sm:text-xs sm:tracking-[0.18em]">
              {{ chartDateBasis === 'booking' ? 'Jadwal pada tanggal terpilih' : 'Pengajuan pada tanggal terpilih' }}
            </p>
            <h3 class="mt-1 text-lg font-bold capitalize leading-tight text-slate-900 dark:text-slate-100">
              {{ selectedDateLabel }}
            </h3>
            <p class="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
              <span class="font-semibold text-slate-700 dark:text-slate-300">{{ selectedDateBookings.length }} data</span>
              ditemukan. Tabel hasil report otomatis disaring.
            </p>
          </div>
          <button
            type="button"
            class="mt-3 inline-flex min-h-10 w-full shrink-0 items-center justify-center rounded-lg border border-blue-300 bg-white px-4 py-2 text-xs font-semibold text-blue-700 transition hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-blue-700 dark:bg-slate-800 dark:text-blue-300 dark:hover:bg-slate-700 dark:focus:ring-offset-slate-900 sm:mt-0 sm:min-h-0 sm:w-auto"
            @click="clearSelectedChartDate"
          >
            Tampilkan Semua
          </button>
        </div>

        <div v-if="selectedDateBookings.length" class="grid gap-2.5 p-3 sm:gap-3 sm:p-5 lg:grid-cols-2">
          <article
            v-for="booking in selectedDateBookings"
            :key="`preview-${booking.id}`"
            class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:p-4"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="truncate font-semibold text-slate-900 dark:text-slate-100">{{ booking.title ?? '-' }}</p>
                <p class="mt-1 text-xs capitalize leading-relaxed text-slate-500 dark:text-slate-400">{{ selectedDateLabel }}</p>
              </div>
              <span
                class="inline-flex shrink-0 self-start rounded-full px-2.5 py-1 text-[10px] font-semibold sm:text-[11px]"
                :class="getBookingStatusClasses(booking.status)"
              >
                {{ getBookingStatusLabel(booking.status) }}
              </span>
            </div>
            <dl class="mt-3 grid grid-cols-2 divide-x divide-slate-200 rounded-lg bg-slate-50 px-3 py-2.5 text-sm dark:divide-slate-700 dark:bg-slate-900/40">
              <div class="min-w-0 pr-3">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Waktu</dt>
                <dd class="mt-1 truncate font-medium text-slate-700 dark:text-slate-200">
                  {{ formatTimeForSelectedDate(booking) }}
                </dd>
              </div>
              <div class="min-w-0 pl-3">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Ruangan</dt>
                <dd class="mt-1 truncate font-medium text-slate-700 dark:text-slate-200">
                  {{ booking.room_summary ?? '-' }}
                </dd>
                <dd class="mt-0.5 truncate text-[11px] text-slate-500 dark:text-slate-400">
                  {{ booking.room_schedules?.length ?? 0 }} jadwal ruangan
                </dd>
              </div>
            </dl>
          </article>
        </div>
        <div v-else class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
          Tidak ada data pada tanggal ini.
        </div>
      </div>

      <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:p-5">
        <div class="mb-4">
          <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Filter Report</h3>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Saring data sebelum membaca detail tabel.</p>
        </div>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <div class="flex flex-col gap-2">
            <label for="report-search" class="text-sm font-medium text-gray-700 dark:text-gray-300">Pencarian bebas</label>
            <input
              id="report-search"
              v-model="filterForm.search"
              type="text"
              placeholder="Nama pemohon, email, ruangan…"
              class="min-h-11 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
            />
          </div>
          <div class="flex flex-col gap-2">
            <label for="report-status" class="text-sm font-medium text-gray-700 dark:text-gray-300">Status peminjaman</label>
            <select
              id="report-status"
              v-model="filterForm.status"
              class="min-h-11 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
            >
              <option value="">Semua status</option>
              <option v-for="status in statusOptions" :key="`status-${status}`" :value="status">
                {{ getBookingStatusLabel(status) }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-2">
            <label for="report-date-range" class="text-sm font-medium text-gray-700 dark:text-gray-300">Rentang Tanggal Pengajuan</label>
            <input
              id="report-date-range"
              ref="dateRangePicker"
              type="text"
              placeholder="Pilih rentang tanggal..."
              class="min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
            />
            <p class="text-xs text-gray-500 mt-1 dark:text-gray-400">
              Dari: {{ filterForm.start_date || '-' }} | Sampai: {{ filterForm.end_date || '-' }}
            </p>
          </div>
          <div class="flex flex-col gap-2">
            <label for="report-booking-date-range" class="text-sm font-medium text-gray-700 dark:text-gray-300">Rentang Tanggal Peminjaman</label>
            <input
              id="report-booking-date-range"
              ref="bookingDateRangePicker"
              type="text"
              placeholder="Pilih rentang tanggal..."
              class="min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200"
            />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Dari: {{ filterForm.booking_start_date || '-' }} | Sampai: {{ filterForm.booking_end_date || '-' }}
            </p>
          </div>
        </div>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
          <button
            type="button"
            class="min-h-11 w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-600 transition hover:border-gray-300 hover:text-gray-800 dark:border-slate-600 dark:text-slate-400 dark:hover:border-slate-500 dark:hover:text-slate-300 sm:min-h-0 sm:w-auto sm:rounded-md sm:py-2"
            @click="resetFilters"
          >
            Reset
          </button>
          <button
            type="button"
            class="min-h-11 w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 sm:min-h-0 sm:w-auto sm:rounded-md sm:py-2"
            @click="applyFilters"
          >
            Terapkan Filter
          </button>
        </div>
      </div>

      <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex flex-col gap-3 border-b border-gray-100 px-4 py-4 sm:px-5 md:flex-row md:items-center md:justify-between dark:border-slate-700">
          <div>
            <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">Hasil Report</div>
            <p v-if="selectedChartDate" class="mt-1 text-xs capitalize text-blue-600 dark:text-blue-400">
              Difilter berdasarkan {{ selectedDateLabel }}
            </p>
            <div v-if="selectedChartStatus" class="mt-2 flex flex-wrap items-center gap-2">
              <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                Status: {{ selectedChartStatusLabel }}
              </span>
              <button
                type="button"
                class="text-xs font-semibold text-slate-500 underline-offset-2 hover:text-blue-700 hover:underline dark:text-slate-400 dark:hover:text-blue-300"
                @click="clearSelectedChartStatus"
              >
                Hapus filter status
              </button>
            </div>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
              Klik dua kali pada baris atau gunakan tombol Buka Persetujuan.
            </p>
          </div>
          <RowsPerPageSelect
            v-model="rowsPerPage"
            input-id="report-rows"
            label="Baris per halaman"
            :options="perPageOptions"
            wide
          />
        </div>

        <div class="overflow-x-auto">
          <table class="report-mobile-table mobile-friendly-table min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-slate-700 dark:text-slate-400">
              <tr>
                <SortableTh class="px-5 py-3 text-left" column="id" :direction="reportSortDirection('id')" :aria-sort="reportAriaSortValue('id')" @toggle="toggleReportSort">
                  ID
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="created_at" :direction="reportSortDirection('created_at')" :aria-sort="reportAriaSortValue('created_at')" @toggle="toggleReportSort">
                  Tanggal Pengajuan
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="applicant" :direction="reportSortDirection('applicant')" :aria-sort="reportAriaSortValue('applicant')" @toggle="toggleReportSort">
                  Pemohon
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="status" :direction="reportSortDirection('status')" :aria-sort="reportAriaSortValue('status')" @toggle="toggleReportSort">
                  Status
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="letter_number" :direction="reportSortDirection('letter_number')" :aria-sort="reportAriaSortValue('letter_number')" @toggle="toggleReportSort">
                  No. Surat
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="schedule" :direction="reportSortDirection('schedule')" :aria-sort="reportAriaSortValue('schedule')" @toggle="toggleReportSort">
                  Jadwal
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="location" :direction="reportSortDirection('location')" :aria-sort="reportAriaSortValue('location')" @toggle="toggleReportSort">
                  Lokasi
                </SortableTh>
                <SortableTh class="px-5 py-3 text-left" column="details" :direction="reportSortDirection('details')" :aria-sort="reportAriaSortValue('details')" @toggle="toggleReportSort">
                  Detail Peminjaman
                </SortableTh>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-slate-700 dark:text-slate-300">
              <tr
                v-for="booking in paginatedBookings"
                :key="booking.id"
                class="cursor-pointer transition hover:bg-blue-50/70 dark:hover:bg-slate-700/70"
                title="Klik dua kali untuk membuka halaman persetujuan"
                @dblclick="openBookingApproval(booking)"
              >
                <td class="mobile-report-muted px-5 py-4 font-semibold text-gray-900 dark:text-slate-100" data-title="ID">#{{ booking.id }}</td>
                <td class="mobile-report-muted px-5 py-4" data-title="Tanggal Pengajuan">{{ formatDateTime(booking.created_at) }}</td>
                <td class="mobile-report-applicant mobile-report-span-2 px-5 py-4" data-title="Pemohon">
                  <div class="font-medium text-gray-900 dark:text-slate-100">{{ booking.user?.name ?? '-' }}</div>
                  <div class="text-xs text-gray-500 dark:text-slate-400">{{ booking.user?.email ?? '-' }}</div>
                  <div class="text-xs text-gray-500 dark:text-slate-400">Telp: {{ booking.user?.phone ?? '-' }}</div>
                </td>
                <td class="mobile-report-status mobile-report-span-2 px-5 py-4" data-title="Status">
                  <span
                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                    :class="getBookingStatusClasses(booking.status)"
                  >
                    {{ getBookingStatusLabel(booking.status) }}
                  </span>
                </td>
                <td class="mobile-report-muted px-5 py-4" data-title="No. Surat">
                  <div class="font-medium text-gray-900 dark:text-slate-100">
                    {{ booking.letter_number ?? '-' }}
                  </div>
                  <div class="text-xs text-gray-500 dark:text-slate-400" v-if="booking.letter_generated_at">
                    Terbit {{ formatDateTime(booking.letter_generated_at) }}
                  </div>
                </td>
                <td class="mobile-report-span-2 px-5 py-4" data-title="Jadwal">
                  <div class="font-medium text-gray-900 dark:text-slate-100">Jadwal penggunaan</div>
                  <div class="text-xs text-gray-500 dark:text-slate-400">{{ booking.schedule_summary }}</div>
                </td>
                <td class="mobile-report-span-2 px-5 py-4" data-title="Lokasi">
                  <div class="font-medium text-gray-900 dark:text-slate-100">{{ booking.room_summary ?? '-' }}</div>
                  <div class="text-xs text-gray-500 dark:text-slate-400">
                    {{ booking.room_schedules?.length ?? 0 }} jadwal ruangan
                  </div>
                </td>
                <td class="mobile-report-primary mobile-report-span-2 px-5 py-4" data-title="Detail Peminjaman">
                  <div class="font-semibold text-gray-900 dark:text-slate-100">{{ booking.title ?? '-' }}</div>
                  <div class="text-xs text-gray-500 dark:text-slate-400">
                    {{ booking.description ?? 'Tidak ada keterangan tambahan' }}
                  </div>
                  <button
                    type="button"
                    class="mt-3 inline-flex min-h-10 w-full items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 transition hover:border-blue-300 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50 dark:focus:ring-offset-slate-800 md:min-h-0 md:w-auto md:py-1.5"
                    @click.stop="openBookingApproval(booking)"
                  >
                    Buka Persetujuan
                  </button>
                </td>
              </tr>
              <tr v-if="!paginatedBookings.length">
                <td colspan="8" class="px-5 py-10 text-center text-sm text-gray-400 dark:text-slate-500">
                  Belum ada data sesuai filter yang dipilih.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <TablePagination
          :page-meta="pageMeta"
          :pages="pages"
          :current-page="currentPage"
          @change="changePage"
        />
      </div>
    </div>
  </AuthenticatedLayout>
</template>
