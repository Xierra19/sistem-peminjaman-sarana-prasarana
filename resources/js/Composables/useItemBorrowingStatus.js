export const itemBorrowingStatusLabels = Object.freeze({
  waiting: 'Menunggu Persetujuan',
  needs_revision: 'Perlu Direvisi',
  approved: 'Disetujui',
  completed: 'Selesai',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan',
  returned: 'Dikembalikan',
})

export const itemBorrowingStatusClasses = Object.freeze({
  waiting: 'border bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
  needs_revision: 'border bg-violet-100 text-violet-700 border-violet-200 dark:bg-violet-900/30 dark:text-violet-300 dark:border-violet-800',
  approved: 'border bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800',
  completed: 'border bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800',
  rejected: 'border bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:border-rose-800',
  cancelled: 'border bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600',
  returned: 'border bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800',
})

export const itemBorrowingActionLabels = Object.freeze({
  requested: 'Diajukan',
  revised: 'Revisi Dikirim',
  waiting: 'Menunggu Persetujuan',
  needs_revision: 'Diminta Revisi',
  approved: 'Disetujui',
  completed: 'Selesai',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan',
  returned: 'Dikembalikan',
})

const defaultStatusClasses =
  'border bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600'

export function normalizeItemBorrowingStatus(status) {
  if (!status) {
    return ''
  }

  if (status === 'requested') return 'waiting'
  if (status === 'returned') return 'completed'
  return status
}

export function getItemBorrowingStatusLabel(status) {
  const normalizedStatus = normalizeItemBorrowingStatus(status)
  return itemBorrowingStatusLabels[normalizedStatus] ?? normalizedStatus
}

export function getItemBorrowingStatusClasses(status) {
  return itemBorrowingStatusClasses[normalizeItemBorrowingStatus(status)] ?? defaultStatusClasses
}

export function getItemBorrowingActionLabel(action) {
  return itemBorrowingActionLabels[action] ?? getItemBorrowingStatusLabel(action)
}
