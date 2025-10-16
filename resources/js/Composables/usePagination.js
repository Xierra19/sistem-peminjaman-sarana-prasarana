import { computed, ref, watch } from 'vue'

export function usePagination(itemsRef, options = {}) {
  const rowsPerPage = ref(options.perPage ?? 10)
  const currentPage = ref(1)

  const normalizedItems = computed(() => {
    const value = itemsRef?.value ?? itemsRef
    return Array.isArray(value) ? value : []
  })

  const totalItems = computed(() => normalizedItems.value.length)

  const totalPages = computed(() => {
    if (!totalItems.value) {
      return 1
    }

    return Math.max(1, Math.ceil(totalItems.value / rowsPerPage.value))
  })

  const paginatedItems = computed(() => {
    const start = (currentPage.value - 1) * rowsPerPage.value
    const end = start + rowsPerPage.value
    return normalizedItems.value.slice(start, end)
  })

  const pageMeta = computed(() => {
    if (!totalItems.value) {
      return { from: 0, to: 0, of: 0 }
    }

    const from = (currentPage.value - 1) * rowsPerPage.value + 1
    const to = Math.min(from + rowsPerPage.value - 1, totalItems.value)

    return { from, to, of: totalItems.value }
  })

  const pages = computed(() => {
    return Array.from({ length: totalPages.value }, (_, index) => index + 1)
  })

  watch(rowsPerPage, () => {
    currentPage.value = 1
  })

  watch([currentPage, totalPages], ([page, total]) => {
    if (total === 0) {
      currentPage.value = 1
      return
    }

    if (page > total) {
      currentPage.value = total
    }

    if (page < 1) {
      currentPage.value = 1
    }
  })

  watch(normalizedItems, () => {
    if (currentPage.value > totalPages.value) {
      currentPage.value = totalPages.value
    }
  })

  const changePage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
      currentPage.value = page
    }
  }

  return {
    rowsPerPage,
    currentPage,
    totalItems,
    totalPages,
    paginatedItems,
    pageMeta,
    pages,
    changePage,
  }
}