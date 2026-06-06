import { computed, reactive } from 'vue'

const emptyFilters = () => ({
  search: '',
  status: '',
  start_date: '',
  end_date: '',
})

export function useAppliedFilters(statusLabels = {}) {
  const filterForm = reactive(emptyFilters())
  const appliedFilters = reactive(emptyFilters())

  const hasActiveFilters = computed(() =>
    Object.values(appliedFilters).some(Boolean),
  )

  const activeFilterBadges = computed(() => {
    const badges = []

    if (appliedFilters.search) {
      badges.push(`Cari: ${appliedFilters.search}`)
    }

    if (appliedFilters.status) {
      badges.push(`Status: ${statusLabels[appliedFilters.status] ?? appliedFilters.status}`)
    }

    if (appliedFilters.start_date) {
      badges.push(`Dari: ${appliedFilters.start_date}`)
    }

    if (appliedFilters.end_date) {
      badges.push(`Sampai: ${appliedFilters.end_date}`)
    }

    return badges
  })

  const applyFilters = () => {
    Object.assign(appliedFilters, filterForm)
  }

  const resetFilters = () => {
    Object.assign(filterForm, emptyFilters())
    Object.assign(appliedFilters, emptyFilters())
  }

  return {
    filterForm,
    appliedFilters,
    hasActiveFilters,
    activeFilterBadges,
    applyFilters,
    resetFilters,
  }
}

export function isDateWithinRange(value, startDate, endDate) {
  if (!value) {
    return false
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return false
  }

  if (startDate) {
    const start = new Date(startDate)
    if (date < start) {
      return false
    }
  }

  if (endDate) {
    const end = new Date(endDate)
    end.setHours(23, 59, 59, 999)
    if (date > end) {
      return false
    }
  }

  return true
}
