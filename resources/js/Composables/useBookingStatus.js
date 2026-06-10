export const bookingStatusLabels = Object.freeze({
  waiting: 'Menunggu Persetujuan',
  approved: 'Disetujui',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan',
  expired: 'Kedaluwarsa',
})

export const bookingStatusClasses = Object.freeze({
  waiting: 'border bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
  approved: 'border bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800',
  rejected: 'border bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:border-rose-800',
  cancelled: 'border bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600',
  expired: 'border bg-orange-100 text-orange-700 border-orange-200 dark:bg-orange-900/30 dark:text-orange-300 dark:border-orange-800',
})

export const defaultBookingStatusClasses =
  'border bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600'

export function normalizeBookingStatus(status) {
  if (!status) {
    return ''
  }

  return status === 'pending' || status === 'requested' ? 'waiting' : status
}

export function getBookingStatusLabel(status) {
  const normalizedStatus = normalizeBookingStatus(status)
  return bookingStatusLabels[normalizedStatus] ?? normalizedStatus
}

export function getBookingStatusClasses(status) {
  return bookingStatusClasses[normalizeBookingStatus(status)] ?? defaultBookingStatusClasses
}
